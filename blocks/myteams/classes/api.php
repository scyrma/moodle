<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

namespace block_myteams;

use block_myteams\external\global_report_link_exporter;
use block_myteams\external\userinfo_section_exporter;
use completion_info;
use core_collator;
use course_enrolment_manager;
use stdClass;

/**
 * Class api
 *
 * @package    block_myteams
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class api {

    /**
     * Returns a list of direct enrolled courses for the given user (excluding courses enrolled with only the enrol program plugin).
     *
     * @param int $userid
     * @return stdClass[]
     */
    public static function get_user_enrolled_courses(int $userid): array {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/enrol/locallib.php');

        // Get courses where given user is enrolled to.
        $courses = enrol_get_users_courses($userid, true, '*');

        $finalcourses = [];

        foreach ($courses as $course) {
            // Instance course_enrolment_manager class.
            $manager = new course_enrolment_manager($PAGE, $course);

            // Get user enrolments for given user.
            $userenrolments = $manager->get_user_enrolments($userid);

            // Get enrolment instances for user in current course.
            $enrolmentinstances = array_column($userenrolments, 'enrolmentinstance');
            $enrolnames = array_column($enrolmentinstances, 'enrol');

            // Filter enrol method names, to retrieve all different to program.
            $coursesenrolments = array_filter($enrolnames, static function(string $enrol): bool {
                // Only return enrolment method names different to program.
                return $enrol != 'program';
            });

            // If filtered course enrolment method names isn't empty, means other valid method exist, then add course to list.
            if (!empty($coursesenrolments)) {
                $finalcourses[] = $course;
            }
        }

        return $finalcourses;
    }

    /**
     * Get courses completions for a given user
     *
     * @param stdClass[] $courses
     * @param int $userid
     * @return array
     */
    public static function get_user_courses_completion(array $courses, int $userid): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $courseids = array_column($courses, 'id');
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid', true, 0);

        $sql = "SELECT course, timecompleted
                FROM {course_completions}
                WHERE course $insql AND userid = :userid";
        $params = $inparams + ['userid' => $userid];
        $completions = $DB->get_records_sql($sql, $params);
        $coursecompletions = [];
        foreach ($courses as $course) {
            $completion = new completion_info($course);
            $coursecompletions[$course->id] = (object) [
                'timecompleted' => $completions[$course->id]->timecompleted ?? null,
                'completionenabled' => $completion->is_enabled(),
            ];
        }
        return $coursecompletions;
    }

    /**
     * Look for plugins adding a section to user information and return them
     *
     * @param int $userid
     * @return array
     */
    public static function get_all_user_sections(int $userid): array {
        global $PAGE;

        $sections = [];
        $isoverdue = false;
        $output = $PAGE->get_renderer('block_myteams');

        $pluginswithcallback = get_plugins_with_function('block_myteams_user_section');
        foreach ($pluginswithcallback as $plugintype => $plugincallbacks) {
            foreach ($plugincallbacks as $callback) {
                $sectiondata = $callback($userid);
                if (!is_array($sectiondata)) {
                    debugging('Method '.$callback.' must return an array of \blocks_myteams\external\userinfo_section_exporter',
                        DEBUG_DEVELOPER);
                    continue;
                }
                // Add menu items returned by the plugin callback.
                $sections = array_merge($sections, $sectiondata);
            }
        }

        // Order sections by order property.
        core_collator::asort_objects_by_method($sections, 'get_order', core_collator::SORT_NUMERIC);

        $exportedsections = [];
        foreach ($sections as $section) {
            $exporter = new userinfo_section_exporter(null, ['section' => $section]);
            // Don't add a section if it does not have items.
            if (!empty($section->get_items())) {
                $exportedsections[] = $exporter->export($output);
            }
            // If any section has overdue items then user has overdue icon.
            if ($section->get_overdue()) {
                $isoverdue = true;
            }
        }

        return [$exportedsections, $isoverdue];
    }

    /**
     * Look for plugins adding global report links and return them
     *
     * @return array
     */
    public static function get_all_global_report_links() {
        global $PAGE;

        $reportlinks = [];
        $output = $PAGE->get_renderer('block_myteams');

        $pluginswithcallback = get_plugins_with_function('block_myteams_global_report_link');
        foreach ($pluginswithcallback as $plugintype => $plugincallbacks) {
            foreach ($plugincallbacks as $callback) {
                $reportlink = $callback();
                if (!($reportlink instanceof global_report_link)) {
                    debugging('Method '.$callback.' must return a \blocks_myteams\global_report_link',
                        DEBUG_DEVELOPER);
                    continue;
                }
                // Add report links returned by the plugin callback.
                $exporter = new global_report_link_exporter(null, ['reportlink' => $reportlink]);
                $reportlinks[] = $exporter->export($output);
            }
        }
        return $reportlinks;
    }
}

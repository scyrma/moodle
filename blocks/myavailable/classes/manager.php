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

declare(strict_types=1);

namespace block_myavailable;

use tool_program\api;

/**
 * Manager class for block_myavailable
 *
 * @package    block_myavailable
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Odei Alba <odei.alba@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {

    /**
     * Returns available courses for current user.
     *
     * @param int|null $userid
     * @return array
     */
    public static function get_available_courses(int $userid = null): array {
        global $DB, $USER;

        $userid = !empty($userid) ? $userid : (int) $USER->id;

        // Get courses to exclude. Hidden courses and already accessed courses.
        $hiddencourses = get_hidden_courses_on_timeline();
        $accessedcourses = $DB->get_fieldset_select('user_lastaccess', 'courseid', 'userid = :user', ['user' => $userid]);
        $excludecourses = array_unique(array_merge($hiddencourses, $accessedcourses));

        // Get all accessible courses within a program.
        $programcourseids = [[]];
        $programs = api::get_user_accessible_programs($userid);
        foreach ($programs as $program) {
            $programcourseids[] = api::get_unlocked_courses_ids($program, $userid);
        }
        $programcourseids = array_unique(array_merge(...$programcourseids));

        $programcourses = [];
        if ($programcourseids) {
            list ($coursesql, $courseparams) = $DB->get_in_or_equal($programcourseids);
            $coursesql = 'id ' . $coursesql;
            $programcourses = $DB->get_records_select('course', $coursesql, $courseparams, '', '*');
        }

        // Get all courses where user is enrolled.
        $enrolledcourses = enrol_get_all_users_courses($userid, true, '*', 'fullname');

        // Merge all courses and make sure they are not repeated.
        $allcourses = $enrolledcourses;
        foreach ($programcourses as $course) {
            if (!in_array($course->id, array_keys($allcourses))) {
                $allcourses[$course->id] = $course;
            }
        }

        // Remove courses that are not accessible.
        $availablecourses = array_filter($allcourses, static function ($element) use ($excludecourses) {
            return !in_array($element->id, $excludecourses);
        });

        // Sort courses by name.
        usort($availablecourses, static function ($a, $b) {
            return strcasecmp($a->fullname, $b->fullname);
        });

        return $availablecourses;
    }
}

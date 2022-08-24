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

namespace tool_program\output;

use context_system;
use core_course\external\course_summary_exporter;
use core_user;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use tool_certification\api as certificationapi;
use tool_certification\certification_completion;
use tool_certification\certification_user;
use tool_program\api;
use tool_program\external\program_overview_view_exporter;
use tool_program\persistent\program_user;
use tool_program\program_tree_progress;

/**
 * Class programs_overview_view
 *
 * @package tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programs_overview_view implements templatable, renderable {
    /**
     * @var int|string
     */
    protected $userid;

    /**
     * edit_program_view constructor.
     *
     * @param int|string $userid
     */
    public function __construct($userid) {
        $this->userid = $userid;
    }

    /**
     * Implementation of exporter from templatable interface
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        // TODO this exporter is never called for userid other than current user.
        $certifications = api::get_certifications_by_userid($this->userid);
        $certallocations = certification_user::get_records(['userid' => $this->userid]);
        $programs = api::get_user_accessible_programs($this->userid);
        $programstreeprogress = $this->get_programs_tree_progress($programs);

        // Get plugin config.
        $config = get_config('block_mylearning') ?: new stdClass();
        $config->show = !empty($config->show) ? $config->show : 'all';
        $config->display = !empty($config->display) ? $config->display : 'viewcards';
        $config->sort = !empty($config->sort) ? $config->sort : 'lastaccess';

        // Get user preference values.
        $userpreferencesstatus = get_user_preferences('tool_program_program_status_filter', $config->show);
        $userpreferencesview = get_user_preferences('tool_program_program_view_filter', $config->display);
        $userpreferencessort = get_user_preferences('tool_program_program_sort_filter', $config->sort);

        $programsenrolledcourses = [];
        foreach ($programstreeprogress as $programtreeprogress) {
            $programsenrolledcourses[$programtreeprogress->get_program()->get('id')] = $programtreeprogress->get_user_enrolments();
        }

        // Add program courses info to coursesinfo.
        $coursesinfo = [];
        foreach ($programstreeprogress as $programtree) {
            $programcourses = $programtree->get_courses();
            foreach ($programcourses as $programcourse) {
                $coursesinfo[$programcourse['courseid']] = $this->get_course_info([
                    'course' => $programcourse['course'],
                    'completionenabled' => $programcourse['completionenabled'],
                    'progress' => $programcourse['progress'],
                    'isenrolled' => $programcourse['isenrolled']
                ]);
            }
        }

        // Get user enrolled courses excluding courses enrolled only with the enrol program plugin.
        $courses = api::get_user_accessible_courses($this->userid);
        $coursescompletion = api::get_user_courses_completion($courses, $this->userid);
        $coursesprogress = api::get_user_courses_progress($this->userid, $courses, $coursescompletion);
        // Add non program courses info to coursesinfo.
        foreach ($courses as $course) {
            $coursesinfo[$course->id] = $this->get_course_info([
                'course' => $course,
                'completionenabled' => $coursescompletion[$course->id]->completionenabled,
                'progress' => $coursesprogress[$course->id],
                'isenrolled' => true,
            ]);
        }

        $allcourseids = array_keys($coursesinfo);
        $coursesinfo = array_values($coursesinfo);

        $relateddata = [
            'context' => context_system::instance(),
            'user' => core_user::get_user($this->userid, '*', MUST_EXIST),
            'programs' => $programs,
            'courses' => $courses,
            'coursesprogress' => $coursesprogress,
            'coursescompletion' => $coursescompletion,
            'lastcourseaccess' => api::get_last_course_access($this->userid, $allcourseids),
            'programsallocations' => program_user::get_records(['userid' => $this->userid]),
            'programstreeprogress' => $programstreeprogress,
            'certifications' => $certifications,
            'certificationscompletion' => $this->get_certifications_completion($certifications),
            'certificationsallocations' => $certallocations,
            'certificationsallocationsstatus' => $this->get_certification_allocations_status($certallocations),
            'programstatusfilterstr' => get_string($userpreferencesstatus, 'tool_program'),
            'programstatusfilterval' => $userpreferencesstatus,
            'showfilters' => true,
            'programsenrolledcourses' => $programsenrolledcourses,
            'view' => $userpreferencesview,
            'sort' => $userpreferencessort,
            'coursesmodals' => $coursesinfo,
            'coursestartdates' => api::get_all_user_course_startdates($this->userid, $allcourseids) ?? [],
            'isdashboard' => true,
        ];
        $exporter = new program_overview_view_exporter(null, $relateddata);
        return $exporter->export($output);
    }

    /**
     * Get certification allocation status.
     *
     * @param array $allocations
     * @return array
     */
    private function get_certification_allocations_status(array $allocations): array {
        $status = [];
        foreach ($allocations as $allocation) {
            $allocationid = $allocation->get('id');
            $certificationid = $allocation->get('certificationid');
            $statuses = certificationapi::get_user_allocation_status($certificationid, $this->userid);
            $status[$allocationid] = $statuses[0]['statusint'] ?? -1;
        }
        return $status;
    }

    /**
     * Get certifications completion.
     *
     * @param array $certifications
     * @return array
     */
    private function get_certifications_completion(array $certifications): array {
        $completions = [];
        foreach ($certifications as $certification) {
            $certid = $certification->get('id');
            $params = ['userid' => $this->userid, 'certificationid' => $certid, 'timerevoked' => 0, 'islast' => 1];
            $completion = certification_completion::get_record($params);
            if ($completion) {
                $completions[$certid] = $completion;
            }
        }
        return $completions;
    }

    /**
     * Get all programs tree progress.
     *
     * @param array $programs
     * @return array
     */
    private function get_programs_tree_progress(array $programs): array {
        $programstreeprogress = [];
        if ($programs) {
            foreach ($programs as $program) {
                $programstreeprogress[$program->get('id')] = new program_tree_progress($program, $this->userid);
            }
        }
        return $programstreeprogress;
    }

    /**
     * Returns course information for the program course information modal
     *
     * @param array $programcourse
     * @return stdClass
     */
    private function get_course_info(array $programcourse): stdClass {
        global $PAGE, $OUTPUT;

        /** @var \core_course_renderer $courserenderer */
        $courserenderer = $PAGE->get_renderer('course');

        $course = $programcourse['course'];
        $category = \core_course_category::get($course->category, IGNORE_MISSING);
        $data = (object)[];
        $data->courseinfo = $courserenderer->course_info_box($course);
        $data->courseinfoimage = course_summary_exporter::get_course_image($course);
        if (!$data->courseinfoimage) {
            $data->courseinfoimage = $OUTPUT->get_generated_image_for_id($course->id);
        }
        $data->name = $course->fullname;
        $data->category = isset($category) ? $category->get_formatted_name() : '';
        $data->courseid = $course->id;
        $data->completionenabled = $programcourse['completionenabled'];
        $data->progress = floor($programcourse['progress']);
        $data->isenrolled = $programcourse['isenrolled'];
        $data->url = ($data->isenrolled) ? new \moodle_url('/course/view.php', ['id' => $course->id]) : '';
        return $data;
    }
}

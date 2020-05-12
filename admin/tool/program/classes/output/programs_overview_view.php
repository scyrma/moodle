<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Class program overview.
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\output;

defined('MOODLE_INTERNAL') || die();

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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $userpreferencesstatus = get_user_preferences('tool_program_program_status_filter') ?? 'all';
        $userpreferencesview = get_user_preferences('tool_program_program_view_filter', 'viewcards');
        $userpreferencessort = get_user_preferences('tool_program_program_sort_filter', 'duedate');

        $programsenrolledcourses = [];
        foreach ($programstreeprogress as $programtreeprogress) {
            $programsenrolledcourses[$programtreeprogress->get_program()->get('id')] = $programtreeprogress->get_user_enrolments();
        }

        $coursesinfo = [];
        foreach ($programstreeprogress as $programtree) {
            $programcourses = $programtree->get_courses();
            foreach ($programcourses as $programcourse) {
                $coursesinfo[$programcourse['courseid']] = $this->get_course_info([
                    'course' => $programcourse['course'],
                    'progress' => $programcourse['progress'],
                    'isenrolled' => $programcourse['isenrolled']
                ]);
            }
        }
        $coursesinfo = array_values($coursesinfo);

        $relateddata = [
            'context' => context_system::instance(),
            'user' => core_user::get_user($this->userid, '*', MUST_EXIST),
            'programs' => $programs,
            'programsallocations' => program_user::get_records(['userid' => $this->userid]),
            'programstreeprogress' => $programstreeprogress,
            'certifications' => $certifications,
            'certificationscompletion' => $this->get_certifications_completion($certifications),
            'certificationsallocations' => $certallocations,
            'certificationsallocationsstatus' => $this->get_certification_allocations_status($certallocations),
            'programstatusfilterstr' => get_string($userpreferencesstatus, 'tool_program'),
            'showfilters' => true,
            'programsenrolledcourses' => $programsenrolledcourses,
            'view' => $userpreferencesview,
            'sort' => $userpreferencessort,
            'coursesmodals' => $coursesinfo
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
        $data = (object)[];
        $data->courseinfo = $courserenderer->course_info_box($course);
        $data->courseinfoimage = course_summary_exporter::get_course_image($course);
        if (!$data->courseinfoimage) {
            $data->courseinfoimage = $OUTPUT->get_generated_image_for_id($course->id);
        }
        $data->name = $course->fullname;
        $data->courseid = $course->id;
        $data->progress = $programcourse['progress'];
        $data->isenrolled = $programcourse['isenrolled'];
        $data->url = ($data->isenrolled) ? new \moodle_url('/course/view.php', ['id' => $course->id]) : '';
        return $data;
    }
}

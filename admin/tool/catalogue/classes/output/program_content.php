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

namespace tool_catalogue\output;

use context_course;
use core_completion\progress;
use core_course\external\course_summary_exporter;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use tool_catalogue\external\program_content_exporter;
use tool_program\persistent\program;
use tool_program\program_tree_progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/course/lib.php");

/**
 * Class to prepare the program content (structure of sets and courses) for display.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Bas Brands <bas@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_content implements templatable, renderable {

    /** @var program The program instance */
    private $program;

    /**
     * Constructor
     *
     * @param program $program
     */
    public function __construct(program $program) {
        $this->program = $program;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return object Export data as an object.
     */
    public function export_for_template(renderer_base $output) {
        global $USER;

        $data = (object)[];
        $userid = (int) $USER->id;
        $context = $this->program->get_context();

        // Get all course objects at once to avoid retrieving individual courses inside the exporters.
        $courses = $this->program->get_courses();

        // Fetch the program structure with all related data for the current user.
        $treeprogress = new program_tree_progress($this->program, $userid);
        $exporter = new program_content_exporter(null, [
            'context' => $context,
            'treeprogress' => $treeprogress,
            'courses' => $courses,
        ]);
        $programstructure = $exporter->export($output);

        // Fetch all recent accessed courses.
        $data->recentlyaccessedcourses = $this->get_recent_accessed_courses($courses);
        $data->hasrecentlyaccessedcourses = !empty($data->recentlyaccessedcourses);
        $data->listitems = $programstructure->baseset->items;
        $data->programid = $this->program->get('id');
        $data->completionicon = $programstructure->baseset->setcriteriaicon;
        $data->completioncriteria = $programstructure->baseset->setcriteriastr;
        $data->completionprogress = get_string('progresscompleted', 'tool_catalogue', [
            'completed' => $programstructure->baseset->completeditems,
            'total' => $programstructure->baseset->totalitems,
        ]);
        $data->fullname = $this->program->get_formatted_name();

        return $data;
    }

    /**
     * Get all recent accessed program courses
     *
     * @param stdClass[] $courses array of course objects keyed by courseid
     * @return array
     */
    private function get_recent_accessed_courses(array $courses): array {
        global $DB, $USER, $OUTPUT;

        if (empty($courses)) {
            return [];
        }

        $recentlyaccessedcourses = [];

        // The $courses array that contains the course objects is keyed by courseid.
        [$inorequal, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
        $params += ['userid' => (int) $USER->id];

        $courseids = $DB->get_records_select('user_lastaccess', "userid = :userid AND courseid $inorequal",
            $params, 'timeaccess DESC', 'courseid', 0, 3);

        // The array $courseids returned by get_records_select() is keyed by the courseid.
        foreach (array_keys($courseids) as $courseid) {
            $course = $courses[$courseid];
            $context = context_course::instance($course->id);

            // Do not add course to the list if the course is hidden for this user.
            if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
                continue;
            }

            $image = course_summary_exporter::get_course_image($course);
            if (!$image) {
                $image = $OUTPUT->get_generated_image_for_id($course->id);
            }
            $recentlyaccessedcourses[] = [
                'name' => format_string($course->fullname, true, ['context' => $context]),
                'courseimage' => $image,
                'progress' => progress::get_course_progress_percentage($course) ?? 0,
                'viewurl' => course_get_url($course),
            ];
        }

        return $recentlyaccessedcourses;
    }
}

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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * File for class program_overview_course_exporter.
 *
 * @package    tool_program
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Mikel Martín <mikel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\external;

use context;
use core\external\exporter;
use core_course\external\course_summary_exporter;
use renderer_base;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for exporting course data to the programs overview view.
 *
 * @package    tool_program
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Mikel Martín <mikel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_overview_course_exporter extends exporter {
    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'course' => 'stdClass',
            'courseprogress' => 'float',
            'coursecompletion' => 'bool',
            'lastcourseaccess' => 'stdClass[]?',
            'startdate' => 'int',
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'id' => [
                'type' => PARAM_INT,
            ],
            'fullname' => [
                'type' => PARAM_TEXT,
            ],
            'category' => [
                'type' => PARAM_TEXT,
            ],
            'image' => [
                'type' => PARAM_RAW,
            ],
            'progress' => [
                'type' => PARAM_TEXT,
            ],
            'iscompleted' => [
                'type' => PARAM_BOOL,
            ],
            'startdatestr' => [
                'type' => PARAM_TEXT,
            ],
            'enddatestr' => [
                'type' => PARAM_TEXT,
            ],
            'isprogram' => [
                'type' => PARAM_BOOL,
            ],
            'lastaccess' => [
                'type' => PARAM_INT
            ],
        ];
    }

    /**
     * Other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        global $OUTPUT;

        /** @var context $context */
        $context = $this->related['context'];
        /** @var stdClass $course */
        $course = $this->related['course'];
        /** @var int $coursecompletion */
        $coursecompletion = $this->related['coursecompletion'];
        /** @var int $courseprogress */
        $courseprogress = $this->related['courseprogress'];
        /** @var int $userstartdate */
        $userstartdate = $this->related['startdate'];

        $exporteddata = new stdClass();
        $exporteddata->id = $course->id;
        $exporteddata->fullname = format_string($course->fullname);
        $category = \core_course_category::get($course->category, IGNORE_MISSING);
        $exporteddata->category = isset($category) ? $category->get_formatted_name() : '';
        $exporteddata->image = course_summary_exporter::get_course_image($course);
        if (!$exporteddata->image) {
            $exporteddata->image = $OUTPUT->get_generated_image_for_id($course->id);
        }

        $exporteddata->iscompleted = $coursecompletion;
        $exporteddata->progress = (int)$courseprogress;
        $exporteddata->isprogram = false;

        $exporteddata->lastaccess = $this->related['lastcourseaccess'][$course->id]->timeaccess ?? null;

        // Get user enrolment start date if is after course start date.
        $userstartdate = ($userstartdate > 0) ? $userstartdate : $course->startdate ?? 0;

        if ($userstartdate != 0) {
            $startdate = userdate($userstartdate, get_string('strftimedatefullshort', 'langconfig'));
        } else {
            $startdate = get_string('datetypenone', 'tool_program');
        }
        if ($course->enddate != 0) {
            $enddate = userdate($course->enddate, get_string('strftimedatefullshort', 'langconfig'));
        } else {
            $enddate = get_string('datetypenone', 'tool_program');
        }
        $exporteddata->startdatestr = get_string('coursestartdate', 'tool_program', $startdate);
        $exporteddata->enddatestr = get_string('courseenddate', 'tool_program', $enddate);

        return (array) $exporteddata;
    }
}

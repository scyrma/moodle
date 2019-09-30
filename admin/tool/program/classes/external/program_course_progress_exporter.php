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
 * Class for exporting program course data.
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\external;

defined('MOODLE_INTERNAL') || die();

use context;
use core\external\exporter;
use renderer_base;
use stdClass;
use tool_program\persistent\program_course;
use tool_program\program_item;

/**
 * Class for exporting field data.
 *
 * @property   program_course persistent
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_course_progress_exporter extends exporter {
    /**
     * Related objects definition.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'programitemcourse' => program_item::class,
        ];
    }

    /**
     * Other properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return program_course_exporter::read_properties_definition() + [
                'level' => [
                    'type' => PARAM_INT,
                ],
                'iscompleted' => [
                    'type' => PARAM_BOOL,
                ],
                'totalitems' => [
                    'type' => PARAM_INT,
                ],
                'weight' => [
                    'type' => PARAM_INT,
                ],
                'completion' => [
                    'type' => PARAM_INT,
                ],
                'totalactivities' => [
                    'type' => PARAM_INT,
                ],
                'completedactivities' => [
                    'type' => PARAM_INT,
                ],
                'isenrolled' => [
                    'type' => PARAM_BOOL,
                ],
                'progress' => [
                    'type' => PARAM_FLOAT,
                ],
                'unlocked' => [
                    'type' => PARAM_BOOL,
                ],
                'showprogress' => [
                    'type' => PARAM_BOOL,
                ],
                'statusmessage' => [
                    'type' => PARAM_TEXT,
                ],
                'statusmessagestr' => [
                    'type' => PARAM_TEXT,
                ],
            ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output): array {
        /** @var context $context */
        $context = $this->related['context'];
        /** @var program_item $programitemcourse */
        $programitemcourse = $this->related['programitemcourse'];

        $exporter = new program_course_exporter($programitemcourse->get_persistent(), [
            'context' => $context,
            'course' => $programitemcourse->get_course(),
            'programid' => $programitemcourse->get_programid(),
        ]);
        $exporteddata = $exporter->export($output);

        $this->export_program_course_progress_details($exporteddata, $programitemcourse);
        $this->export_program_course_status($exporteddata, $programitemcourse);

        return (array) $exporteddata;
    }

    /**
     * Exports main progress details of the program course.
     *
     * @param stdClass $exporteddata
     * @param program_item $programitemcourse
     */
    private function export_program_course_progress_details(stdClass $exporteddata, program_item $programitemcourse): void {
        $exporteddata->level = $programitemcourse->level;
        $exporteddata->iscompleted = $programitemcourse->iscompleted;
        $exporteddata->totalitems = $programitemcourse->totalitems;
        $exporteddata->weight = $programitemcourse->weight;
        $exporteddata->completion = $programitemcourse->completion;
        $exporteddata->totalactivities = $programitemcourse->totalitems;
        $exporteddata->completedactivities = $programitemcourse->completeditems;
        $exporteddata->isenrolled = $programitemcourse->isenrolled;
        $exporteddata->progress = $programitemcourse->progresspercentage;
        $exporteddata->unlocked = $programitemcourse->isunlocked;
    }

    /**
     * Exports program course status information (locked, completed, available...).
     *
     * @param stdClass $exporteddata
     * @param program_item $programitemcourse
     */
    private function export_program_course_status(stdClass $exporteddata, program_item $programitemcourse): void {
        if (!$programitemcourse->isunlocked) {
            if ($programitemcourse->iscompleted) {
                $exporteddata->showprogress = false;
                $exporteddata->statusmessage = 'pending';
                $exporteddata->statusmessagestr = get_string('pending', 'tool_program');
            } else {
                $exporteddata->showprogress = false;
                $exporteddata->statusmessage = 'locked';
                $exporteddata->statusmessagestr = get_string('locked', 'tool_program');
            }
        } else if ($programitemcourse->iscompleted) {
            $exporteddata->showprogress = false;
            $exporteddata->statusmessage = 'completed';
            $exporteddata->statusmessagestr = get_string('completed', 'tool_program');
        } else if (!$programitemcourse->isenrolled) {
            $exporteddata->showprogress = false;
            $exporteddata->statusmessage = 'available';
            $exporteddata->statusmessagestr = get_string('available', 'tool_program');
        } else if ($programitemcourse->completeditems > 0) {
            $exporteddata->showprogress = true;
            $exporteddata->statusmessage = 'inprogress';
            $exporteddata->statusmessagestr = get_string('inprogress', 'tool_program');
        } else {
            $exporteddata->showprogress = true;
            $exporteddata->statusmessage = 'enrolled';
            $exporteddata->statusmessagestr = get_string('enrolled', 'tool_program');
        }
    }
}

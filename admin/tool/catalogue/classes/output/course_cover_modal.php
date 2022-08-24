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
use core_course\external\course_summary_exporter;
use html_writer;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Class to prepare the course cover modal to be displayed in the course view page the first time user accesses.
 *
 * @package     tool_catalogue
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_cover_modal implements templatable, renderable {

    /** @var stdClass The course instance */
    private $course;

    /**
     * Constructor.
     *
     * @param stdClass $course
     */
    public function __construct(stdClass $course) {
        $this->course = $course;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return object Export data as an object.
     */
    public function export_for_template(renderer_base $output) {
        $courseview = new course_cover($this->course, true);
        $data = $courseview->export_for_template($output);

        $image = course_summary_exporter::get_course_image($this->course);
        if (!$image) {
            $image = $output->get_generated_image_for_id($this->course->id);
        }
        $data->courseimage = html_writer::tag('div', '', ['class' => 'rounded item-heading-image',
            'style' => 'background-image: url(' . $image . ')']);
        $context = context_course::instance($this->course->id);
        $data->coursename = format_string($this->course->fullname, true, ['context' => $context]);

        return $data;
    }
}

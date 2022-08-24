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

namespace block_myinprogress\output;

use renderable;
use renderer_base;
use stdClass;
use templatable;
use tool_catalogue\output\course_carousel;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to prepare the inprogress block for display.
 *
 * @package    block_myinprogress
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Bas Brands <bas@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class myinprogress implements renderable, templatable {

    /** @var stdClass[] Array of courses */
    private $courses;

    /**
     * Get my courses
     *
     * @param stdClass[] $courses
     */
    public function __construct(array $courses) {
        $this->courses = $courses;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return object Export data as an object.
     */
    public function export_for_template(renderer_base $output) {
        global $PAGE;

        $coursecarousel = new course_carousel($this->courses);
        $cataloguerenderer = $PAGE->get_renderer('tool_catalogue');
        $coursescount = count($this->courses);

        return (object) [
            'blocktitle' => $coursescount
                ? get_string('blocktitle', 'block_myinprogress', $coursescount)
                : get_string('pluginname', 'block_myinprogress'),
            'coursecarousel' => $cataloguerenderer->render($coursecarousel),
        ];
    }
};

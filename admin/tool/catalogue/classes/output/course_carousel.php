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

use tool_catalogue\external\coursecarousel_exporter;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Class to prepare the course carousel for display.
 *
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_carousel implements renderable, templatable {

    /** @var stdClass[] Array of courses */
    private $courses;
    /** @var array Array of actions for every card */
    private $cardactions;
    /** @var string String to set the carousel filter */
    private $filterby;
    /** @var array Array of extra classes for each card */
    private $extraclasses;
    /** @var array Array of extra attributes for each card */
    private $extraattributes;
    /** @var array Array of extra HTML for each card name */
    private $extracoursenames;
    /** @var string Custom empty state HTML */
    private $customemptystate;

    /**
     * Get my courses
     *
     * @param stdClass[] $courses
     * @param array $cardactions
     * @param string $filterby
     * @param array $extraclasses
     * @param array $extraattributes
     * @param array $extracoursenames
     * @param string|null $customemptystate
     */
    public function __construct(array $courses, array $cardactions = [], string $filterby = '', array $extraclasses = [],
                                array $extraattributes = [], array $extracoursenames = [], ?string $customemptystate = null) {
        $this->courses = $courses;
        $this->cardactions = $cardactions;
        $this->filterby = $filterby;
        $this->extraclasses = $extraclasses;
        $this->extraattributes = $extraattributes;
        $this->extracoursenames = $extracoursenames;
        $this->customemptystate = $customemptystate;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return object Export data as an object.
     */
    public function export_for_template(renderer_base $output) {
        global $USER;

        $exporter = new coursecarousel_exporter(null, ['courses' => $this->courses]);
        $export = $exporter->export($output);

        $courses = [];
        foreach ((array) $export->courses as $exportedcourse) {
            $course = (array) $exportedcourse;
            $course['extraclasses'] = $this->extraclasses[$course['id']] ?? '';
            $course['extraattributes'] = $this->extraattributes[$course['id']] ?? [];
            $course['extracoursename'] = $this->extracoursenames[$course['id']] ?? '';
            $courses[] = $course;
        }

        return (object) [
            'carouselid' => uniqid(),
            'userid' => $USER->id,
            'courses' => array_values($courses),
            'hascourses' => !empty($courses),
            'cardactions' => $this->cardactions,
            'hascardactions' => !empty($this->cardactions),
            'filterby' => $this->filterby,
            'customemptystate' => $this->customemptystate,
        ];
    }
}

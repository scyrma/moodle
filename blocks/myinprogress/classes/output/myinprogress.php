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

use moodle_url;
use block_myinprogress\constants;
use html_writer;
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

        $coursescount = count($this->courses);
        $hascourses = $coursescount > 0;
        $showhiddencards = get_user_preferences(constants::PREFERENCE_SHOW_HIDDEN_CARDS, false);

        if ($hascourses) {
            $hiddenbadgeclasses = 'badge badge-secondary';
            $hiddencourses = json_decode(get_user_preferences(constants::PREFERENCE_HIDDEN_COURSES, '[]'));
            $extraclasses = [];
            $extraattributes = [];
            $extracoursenames = [];

            foreach ($this->courses as $courseid => $course) {
                $ishidden = in_array($courseid, $hiddencourses);
                // Populate $extraclasses and $extraattributes.
                if ($ishidden) {
                    $extraclasses[$courseid] = !$showhiddencards ? 'd-none' : '';
                } else {
                    $extraattributes[$courseid] = ['name' => 'data-' . constants::FILTER_VISIBLE];
                }
                // Add 'hidden' badge span to the courses fullnames.
                $extracoursenames[$courseid] = html_writer::span(
                    get_string('hidden', 'block_myinprogress'),
                    $ishidden ? $hiddenbadgeclasses : $hiddenbadgeclasses . ' d-none',
                    ['data-region' => 'hidden-card']
                );
            }

            $cardactions = [
                ['name' => get_string('hidefromview', 'block_myinprogress'), 'dataaction' => 'carousel-hide-card'],
                ['name' => get_string('donthide', 'block_myinprogress'), 'dataaction' => 'carousel-show-card'],
            ];

            $cataloguerenderer = $PAGE->get_renderer('tool_catalogue');
            $customemptystate = $cataloguerenderer->render_from_template('block_myinprogress/emptystate',
                ['emptyimgurl' => new moodle_url('/blocks/myinprogress/pix/no-courses.png')]);
            $filterby = !$showhiddencards ? constants::FILTER_VISIBLE : '';
            $coursecarousel = new course_carousel(
                $this->courses,
                $cardactions,
                $filterby,
                $extraclasses,
                $extraattributes,
                $extracoursenames,
                $customemptystate
            );
            $coursecarouseloutput = $cataloguerenderer->render($coursecarousel);
        }

        return (object) [
            'blocktitle' => $hascourses
                ? get_string('blocktitle', 'block_myinprogress', $coursescount)
                : get_string('pluginname', 'block_myinprogress'),
            'hascourses' => $hascourses,
            'coursecarousel' => $coursecarouseloutput ?? '',
            'showhiddencards' => $showhiddencards,
            'hiddencoursescount' => isset($hiddencourses) ? count($hiddencourses) : 0,
            'emptyimgurl' => new moodle_url('/blocks/myinprogress/pix/no-courses.png')
        ];
    }
};

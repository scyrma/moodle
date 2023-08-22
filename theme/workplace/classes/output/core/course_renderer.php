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

namespace theme_workplace\output\core;

use core_course_renderer;
use stdClass;

/**
 * Theme course renderer
 *
 * @package    theme_workplace
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_renderer extends core_course_renderer {

    /**
     * Overrides course info box to show course cover from tool_catalogue.
     *
     * @param stdClass $course
     * @return string
     */
    public function course_info_box(stdClass $course) {
        if (class_exists(\tool_catalogue\output\course_cover::class)) {
            $isenrolpage = $this->page->bodyid == 'page-enrol-index';
            $output = $this->page->get_renderer('tool_catalogue');
            $courseview = new \tool_catalogue\output\course_cover($course, $isenrolpage);
            $content = $output->render($courseview);
            if ($isenrolpage) {
                $content .= $this->render_from_template('tool_catalogue/enrolments_heading', []);
            }
            return $content;
        }
        return parent::course_info_box($course);
    }
}

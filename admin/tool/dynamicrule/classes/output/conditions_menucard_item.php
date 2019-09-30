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
 * conditions_menucard_item renderable.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;

/**
 * conditions_menucard_item renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conditions_menucard_item implements \renderable, \templatable {

    /** @var \tool_dynamicrule\condition_base */
    protected $condition;

    /**
     * Constructor.
     *
     * @param condition_base $condition
     */
    public function __construct(\tool_dynamicrule\condition_base $condition) {
        $this->condition = $condition;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $explodedclass = explode('\\', get_class($this->condition));

        // Put together context for conditions_menucard_item template.
        $params = [
            'title' => $this->condition->get_title(),
            'configclass' => $explodedclass[0] . ':' . $explodedclass[3],
            'addlabel' => get_string('addcondition', 'tool_dynamicrule'),
        ];

        return $params;
    }
}

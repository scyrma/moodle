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
 * rules_list_item_outcomes_description renderable.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;

/**
 * rules_list_item_outcomes_description renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class rules_list_item_outcomes_description implements \renderable, \templatable {

    /** @var int */
    protected $ruleid;

    /**
     * Constructor.
     *
     * @param int $ruleid
     */
    public function __construct($ruleid) {
        $this->ruleid = $ruleid;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $outcomes = [];
        foreach (\tool_dynamicrule\api::get_rule_outcomes($this->ruleid) as $outcome) {
            if ($outcome->is_broken()) {
                $description = $outcome->get_title();
            } else {
                $description = $outcome->get_description();
            }
            $outcomes[] = [
                'description' => $description,
                'isbroken' => $outcome->is_broken(),
            ];
        }

        if (!count($outcomes)) {
            // No outcomes.
            $outcomes[] = ['description' => get_string('noruleoutcomes', 'tool_dynamicrule')];
        }

        return [
            'instances' => $outcomes,
            'brokenlabel' => get_string('outcomeisbroken', 'tool_dynamicrule')
        ];
    }
}

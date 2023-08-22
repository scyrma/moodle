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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_dynamicrule\output;

use renderer_base;
use tool_dynamicrule\rule;

/**
 * duplicate_rule_validation renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class duplicate_rule_validation implements \renderable, \templatable {

    /** @var array */
    protected $conditions;
    /** @var array */
    protected $outcomes;
    /** @var rule */
    protected $rule;
    /**
     * Constructor.
     *
     * @param rule $rule
     * @param array $conditions
     * @param array $outcomes
     */
    public function __construct(rule $rule, array $conditions, array $outcomes) {
        $this->rule = $rule;
        $this->conditions = $conditions;
        $this->outcomes = $outcomes;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $hasconditions = !empty($this->conditions);
        $hasoutcomes = !empty($this->outcomes);
        return [
            'hasconflicts' => $hasconditions || $hasoutcomes,
            'hasconditions' => $hasconditions,
            'conditions' => $this->conditions,
            'hasoutcomes' => $hasoutcomes,
            'outcomes' => $this->outcomes,
            'rulename' => $this->rule->get_formatted_name(),
        ];
    }
}

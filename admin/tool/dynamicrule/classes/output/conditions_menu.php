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

/**
 * conditions_menu renderable.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

use renderer_base;
use tool_dynamicrule\condition_base;
use tool_dynamicrule\rule;

/**
 * conditions_menu renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class conditions_menu implements \renderable, \templatable {

    /** @var condition_base[] */
    protected $conditions;
    /** @var rule */
    protected $rule;

    /**
     * Constructor.
     *
     * @param condition_base[] $conditions
     * @param rule $rule
     */
    public function __construct(array $conditions, rule $rule) {
        $this->conditions = $conditions;
        $this->rule = $rule;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        // Group conditions by categories.
        $categorisedconditions = [];
        $categorynamemap = [];
        foreach ($this->conditions as $condition) {
            // Build menu only for conditions user is allowed to add.
            if (\tool_dynamicrule\permission::can_add_condition($condition, $this->rule)) {
                // Derive key from category name and store name mapping.
                $categoryname = $condition->get_category();
                $categorykey = strtolower(clean_param($categoryname, PARAM_ALPHA));
                if (!array_key_exists($categorykey, $categorynamemap)) {
                    $categorynamemap[$categorykey] = $categoryname;
                }
                // Add condition to the list of conditions in this category.
                $categorisedconditions[$categorykey][] = $condition;
            }
        }

        $menucards = [];
        foreach ($categorisedconditions as $categorykey => $categoryconditions) {
            $menucard = new conditions_menucard($categorykey, $categorynamemap[$categorykey], $categoryconditions);
            if ($categorykey === 'general') {
                // General category goes first.
                array_unshift($menucards, $menucard->export_for_template($output));
            } else {
                // All other categories are appended to menu and listed in alphabetical order.
                $menucards[] = $menucard->export_for_template($output);
            }
        }

        return $menucards;
    }
}

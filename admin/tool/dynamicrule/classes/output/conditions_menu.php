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
 * conditions_menu renderable.
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
 * conditions_menu renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conditions_menu implements \renderable, \templatable {

    /** @var \tool_dynamicrule\condition_base[] */
    protected $conditions;

    /**
     * Constructor.
     *
     * @param array $conditions
     */
    public function __construct($conditions) {
        $this->conditions = $conditions;
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
            // Derive key from category name and store name mapping.
            $categoryname = $condition->get_category();
            $categorykey = strtolower(clean_param($categoryname, PARAM_ALPHA));
            if (!array_key_exists($categorykey, $categorynamemap)) {
                $categorynamemap[$categorykey] = $categoryname;
            }
            // Add condition to the list of conditions in this category.
            $categorisedconditions[$categorykey][] = $condition;
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

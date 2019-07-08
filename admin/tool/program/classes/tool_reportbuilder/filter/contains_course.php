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
 * Class containing the logic for the filter contains_course.
 *
 * @package   tool_program
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\tool_reportbuilder\filter;

use tool_reportbuilder\local\filter\text;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Class contains_course
 *
 * @package   tool_program
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contains_course extends text {

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        global $DB;

        if (!$values) {
            return ['', []];
        }

        $operator = array_key_exists("{$this->name}_op", $values) ? $values["{$this->name}_op"] : null;
        $value = array_key_exists($this->name, $values) ? $values[$this->name] : 0;

        $name = db::generate_param_name();
        $coursealias = db::generate_alias();
        $field = "$coursealias.fullname";
        $params = [];

        if (is_null($operator) || ('' . $value === '' and ($operator != 5 && $operator != 6))) {
            // Filter configuration is invalid. Ignore the filter.
            return ['', []];
        }

        switch($operator) {
            case 0: // Contains.
                $res = $DB->sql_like($field, ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value%";
                break;
            case 1: // Does not contain.
                $res = $DB->sql_like($field, ":$name", false, false, true);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value%";
                break;
            case 2: // Equal to.
                $res = $DB->sql_equal($field, ":$name", false, false);
                $params[$name] = "$value";
                break;
            case 3: // Starts with.
                $res = $DB->sql_like($field, ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "$value%";
                break;
            case 4: // Ends with.
                $res = $DB->sql_like($field, ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value";
                break;
            case 5: // Empty.
                $res = "$field = :$name";
                $params[$name] = '';
                break;
            case 6: // Empty.
                $res = "$field != :$name";
                $params[$name] = '';
                break;
            default:
                // Filter configuration is invalid. Ignore the filter.
                return ['', []];
        }

        $tpc = db::generate_alias();
        $tps = db::generate_alias();
        $tp = db::generate_alias();

        $sql = "EXISTS (SELECT 1 FROM {course} $coursealias WHERE $res AND $coursealias.id IN
        (SELECT $tpc.courseid FROM {tool_program_courses} $tpc
        INNER JOIN {tool_program_sets} $tps ON $tpc.setid = $tps.id
        INNER JOIN {tool_program} $tp ON $tp.id = $tps.programid
        WHERE $tp.id = tp.id))";

        return [$sql, $params];
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label(array $data) : string {
        return 'contains_course';
    }

    /**
     * Check if the current filter is active in order to show the reset button.
     *
     * Must set the variable "isactive" to true or false.
     *
     * Each filter type must have the own logic to determinate if the filter is active or not.
     *
     * @param array $values
     * @return mixed
     */
    public function is_active(?array $values) : void {
        $this->isactive = !empty($values);
    }

    /**
     * Define the type of the filter.
     *
     * @return string
     */
    protected function filter_type() : string {
        return 'filter-contains_course';
    }

    /**
     * Returns sample values that can be used in the tests
     *
     * If $extended is not set, return 1-2 sets of values, they will be massively used in tests for all filters in all datasources
     * If $extended is set, return as many sets of values as possible, for extended test of this specific filter
     *
     * @param bool $extended
     * @return array
     */
    public function get_test_values(bool $extended = false) {
        return [
        ];
    }
}
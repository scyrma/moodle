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
 * Class containing the logic for the filter associated_certification.
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\tool_reportbuilder\filter;

use core_reportbuilder\local\helpers\database;
use tool_reportbuilder\local\filter\text;

/**
 * Class associated_certification
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class associated_certification extends text {

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

        $name = database::generate_param_name();
        $certalias = database::generate_alias();
        $field = "$certalias.fullname";
        $params = [];

        if (is_null($operator) || ('' . $value === '' && ($operator != 5 && $operator != 6))) {
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
                $res = $DB->sql_isempty('', $field, false, false);
                break;
            case 6: // Not empty.
                $res = $DB->sql_isnotempty('', $field, false, false);
                break;
            default:
                // Filter configuration is invalid. Ignore the filter.
                return ['', []];
        }

        $sql = "EXISTS (SELECT 1 FROM {tool_certification} $certalias WHERE $res AND tp.id = $certalias.program)";

        return [$sql, $params];
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label(array $data) : string {
        return 'associated_certification';
    }

    /**
     * Define the type of the filter.
     *
     * @return string
     */
    protected function filter_type() : string {
        return 'filter-associated_certification';
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

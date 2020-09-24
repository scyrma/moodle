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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class tenant_filter_with_empty
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\tool_reportbuilder\filter;

use tool_reportbuilder\local\filter\select;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tenant_filter_with_empty
 *
 * Same as tenant filter but also allows empty value
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenant_filter_with_empty extends tenant_filter {

    /**
     * Options for the actual select element
     *
     * @return array
     */
    protected function get_options_for_select_element(): array {
        $results = parent::get_options_for_select_element();
        if (\tool_tenant\permission::can_switch_tenant()) {
            $results = [0 => ''] + $results;
        }
        return $results;
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        $name = db::generate_param_name();

        $field = $this->reportfilter->get_field_sql();
        $params = $this->reportfilter->get_field_params();

        $operator = array_key_exists("{$this->name}_op", $values) ? $values["{$this->name}_op"] : null;
        $value = array_key_exists($this->name, $values) ? $values[$this->name] : 0;

        switch($operator) {
            case 1: // Equal to.
                $res = $value ? "$field=:$name" : "$field IS NULL OR $field = :$name";
                $params[$name] = (int)$value;
                break;
            case 2: // Not equal to.
                $res = $value ? "($field<>:$name OR $field IS NULL)" : "$field IS NOT NULL";
                $params[$name] = (int)$value;
                break;
            default:
                // Filter configuration is invalid. Ignore the filter.
                return array('', array());
        }
        return array(
            $res, $params);
    }

}

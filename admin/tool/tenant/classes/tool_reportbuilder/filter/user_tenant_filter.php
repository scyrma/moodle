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
 * Class user_tenant_filter
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\tool_reportbuilder\filter;

use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Class user_tenant_filter
 *
 * Almost the same as tenant_filter but treats value "null" as the default tenant
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_tenant_filter extends tenant_filter {

    /**
     * Options for the actual select element
     *
     * @return array
     */
    protected function get_options_for_select_element(): array {
        $results = parent::get_options_for_select_element();
        if ($sharedid = sharedspace::get_shared_space_id()) {
            unset($results[$sharedid]);
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
        // When value is equal to the default tenant id, treat "null" as default tenant.
        $isdefault = $value == tenancy::get_default_tenant_id();

        switch($operator) {
            case 1: // Equal to.
                $res = $isdefault ? "($field=:$name OR $field IS NULL)" : "$field=:$name";
                $params[$name] = $value;
                break;
            case 2: // Not equal to.
                $res = $isdefault ? "($field<>:$name AND $field IS NOT NULL)" : "($field<>:$name OR $field IS NULL)";
                $params[$name] = $value;
                break;
            default:
                // Filter configuration is invalid. Ignore the filter.
                return array('', array());
        }
        return array($res, $params);
    }
}

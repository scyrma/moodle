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

declare(strict_types=1);

namespace tool_tenant\reportbuilder\local\filters;

use core_reportbuilder\local\helpers\database;

/**
 * Tenant report filter similar to {@see \tool_tenant\reportbuilder\local\filters\tenant} but also allows empty value (no tenant)
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenant_with_empty extends tenant {

    /**
     * Return the options for the filter
     *
     * @return array
     */
    protected function get_select_options(): array {
        $results = parent::get_select_options();
        if (\tool_tenant\permission::can_switch_tenant()) {
            $results = [0 => ''] + $results;
        }
        return $results;
    }

    /**
     * Return filter SQL, accounting for the "empty" tenant option
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values) : array {
        $operator = $values["{$this->name}_operator"] ?? self::ANY_VALUE;
        $value = (int) ($values["{$this->name}_value"] ?? 0);

        $fieldsql = $this->filter->get_field_sql();
        $params = $this->filter->get_field_params();

        $paramvalue = database::generate_param_name();

        // Both zero/null value are treated as "empty", e.g. not belonging to any tenant.
        switch($operator) {
            case self::EQUAL_TO:
                $fieldsql = "COALESCE({$fieldsql}, 0) = :{$paramvalue}";
                $params[$paramvalue] = $value;
            break;
            case self::NOT_EQUAL_TO:
                $fieldsql = "COALESCE({$fieldsql}, 0) <> :{$paramvalue}";
                $params[$paramvalue] = $value;
            break;
            default:
                return ['', []];
        }

        return [$fieldsql, $params];
    }
}

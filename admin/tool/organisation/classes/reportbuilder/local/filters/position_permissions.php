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

namespace tool_organisation\reportbuilder\local\filters;

use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\helpers\database;

/**
 * Filter for jobs
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class position_permissions extends boolean_select {

    /**
     * Returns the condition to be used with the SQL where clause
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values): array {
        global $DB;

        $operator = (int) ($values["{$this->name}_operator"] ?? self::ANY_VALUE);

        if ($operator === self::ANY_VALUE) {
            return ['', []];
        }

        $pos = $this->filter->get_field_sql();
        $params = $this->filter->get_field_params();
        // Permission type set on the options when calling the filter.
        $withpermission = $this->filter->get_options()['type'];

        $globperm = $DB->sql_bitand("{$pos}.globalpermissions", $withpermission);
        $deptperm = $DB->sql_bitand("{$pos}.departmentpermissions", $withpermission);
        $withperm1 = database::generate_param_name();
        $withperm2 = database::generate_param_name();

        if ($operator === self::CHECKED) {
            $where = "(({$pos}.departmentmanager = 1 AND {$deptperm} = :{$withperm1})
            OR ({$pos}.globalmanager = 1 AND {$globperm} = :{$withperm2}))";
        } else {
            $where = "NOT (({$pos}.departmentmanager = 1 AND {$deptperm} = :{$withperm1})
            OR ({$pos}.globalmanager = 1 AND {$globperm} = :{$withperm2}))";
        }

        $params[$withperm1] = $withpermission;
        $params[$withperm2] = $withpermission;

        return [$where, $params];
    }
}

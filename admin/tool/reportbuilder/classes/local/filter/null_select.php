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
 * Class containing the logic for the null select filter
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\filter;

use tool_wp\db;

/**
 * Class null_select
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class null_select extends select {

    /**
     * Build the SQL filter SQL
     *
     * @param array|null $values
     * @return array
     */
    public function get_sql_filter(?array $values) : array {
        $field = $this->reportfilter->get_field_sql();
        $params = $this->reportfilter->get_field_params();

        $sql = "CASE WHEN {$field} IS NULL THEN 1 ELSE 0 END";

        $valueparam = db::generate_param_name();
        $params[$valueparam] = $values[$this->name] ?? null;

        $operator = $values[$this->name . '_op'] ?? null;
        switch($operator) {
            case 1: // Equal to.
                $result = " = :{$valueparam}";
                break;
            case 2: // Not equal to.
                $result = " <> :{$valueparam}";
                break;
            default:
                return ['', []];
        }

        return [$sql . $result, $params];
    }
}

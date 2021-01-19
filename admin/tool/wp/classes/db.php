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
 * Class db
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper functions for DB manipulations
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class db {
    /**
     * Generates unique table/column alias that must be used in conditions SQL
     *
     * @return string
     */
    public static function generate_alias() : string {
        static $cnt = 0;
        return 'wpdba' . ($cnt++);
    }
    /**
     * Generates unique parameter name that must be used in conditions SQL
     *
     * @return string
     */
    public static function generate_param_name() : string {
        static $cnt = 0;
        return 'wpdbp' . ($cnt++);
    }

    /**
     * Validates that all parameters in SQL query were generated
     *
     * Shows debugging message if there are parameters that were not generated using generate_param_name().
     *
     * @param array $params
     * @param string $custommessage custom debugging message
     */
    public static function validate_params(array $params, string $custommessage = null) {
        global $CFG;
        if (!$CFG->debugdeveloper) {
            return;
        }

        // Make sure the condition uses proper api::generate_param_name() functions for generating parameters.
        if ($wrongparams = array_filter($params, function ($key) {
            return !preg_match('/^wpdbp[\d]+/', $key);
        }, ARRAY_FILTER_USE_KEY)) {
            if ($custommessage === null) {
                $custommessage = 'SQL uses parameters that were not generated with tool_wp\db::generate_param_name(): ' .
                    join(', ', array_keys($wrongparams));
            }
            debugging($custommessage, DEBUG_DEVELOPER);
        }
    }

    /**
     * Validates that all table/column aliases in an SQL query were generated
     *
     * Shows debugging message if there are aliases that were not generated using generate_alias().
     *
     * @param string $sql
     * @param string|null $custommessage
     */
    public static function validate_sql(string $sql, string $custommessage = null) {
        global $CFG;
        if (!$CFG->debugdeveloper) {
            return;
        }

        // TODO think about how to check table/column aliases.
    }
}

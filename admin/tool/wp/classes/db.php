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

namespace tool_wp;

use coding_exception;
use core_reportbuilder\local\helpers\database;

/**
 * Helper functions for DB manipulations
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class db {

    /**
     * Generates unique table/column alias that must be used in conditions SQL
     *
     * @deprecated since Moodle Workplace 4.1
     *
     * @return string
     */
    public static function generate_alias(): string {
        debugging('Function \tool_wp\db::generate_alias() is deprecated. '.
            'Please use \core_reportbuilder\local\helpers\database::generate_alias()', DEBUG_DEVELOPER);
        return database::generate_alias();
    }
    /**
     * Generates unique parameter name that must be used in conditions SQL
     *
     * @deprecated since Moodle Workplace 4.1
     *
     * @return string
     */
    public static function generate_param_name(): string {
        debugging('Function \tool_wp\db::generate_param_name() is deprecated. '.
            'Please use \core_reportbuilder\local\helpers\database::generate_param_name()', DEBUG_DEVELOPER);
        return database::generate_param_name();
    }

    /**
     * Validates that all parameters in SQL query were generated
     *
     * Shows debugging message if there are parameters that were not generated using generate_param_name().
     *
     * @deprecated since Moodle Workplace 4.1
     *
     * @param array $params
     * @param string $custommessage custom debugging message
     */
    public static function validate_params(array $params, string $custommessage = null): void {
        global $CFG;

        if (!$CFG->debugdeveloper) {
            return;
        }

        debugging('Function \tool_wp\db::validate_params() is deprecated. '.
            'Please use \core_reportbuilder\local\helpers\database::validate_params()', DEBUG_DEVELOPER);

        // Originally this method did not throw exception but printed debugging message, preserve this behavior.
        try {
            database::validate_params($params);
        } catch (coding_exception $exception) {
            if ($custommessage === null) {
                $custommessage = $exception->getMessage();
            }
            debugging($custommessage, DEBUG_DEVELOPER);
        }
    }

    /**
     * Validates that all table/column aliases in an SQL query were generated
     *
     * Shows debugging message if there are aliases that were not generated using generate_alias().
     *
     * @deprecated since Moodle Workplace 4.1
     *
     * @param string $sql
     * @param string|null $custommessage
     */
    public static function validate_sql(string $sql, string $custommessage = null) {
        debugging('Function \tool_wp\db::validate_sql() is deprecated '.
            'without replacement, it was not doing anything', DEBUG_DEVELOPER);
    }
}

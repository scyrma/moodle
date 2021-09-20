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
 * Class containing the min aggregation
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\aggregate;

use tool_reportbuilder\aggregation_base;
use tool_reportbuilder\constants;

defined('MOODLE_INTERNAL') || die();

/**
 * Class containing the minimum aggregation
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class min extends aggregation_base {
    /**
     * Get the SQL
     *
     * @param string $field
     * @param int|null $dbtype
     * @return string
     */
    public static function get_field(string $field, ?int $dbtype = null) : string {
        return "MIN($field)";
    }

    /**
     * Check if this aggregation can be use with the type of field
     *
     * @param int|null $dbtype
     * @return bool
     */
    public static function is_compatible(?int $dbtype) : bool {
        return in_array($dbtype, [
            constants::DB_TYPE_NUMBER,
            constants::DB_TYPE_TIMESTAMP,
            constants::DB_TYPE_DATETIME,
            constants::DB_TYPE_BOOLEAN
        ]);
        // TODO why not support it for short text (char) type?
    }

    /**
     * Get visible name
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_displayname(): string {
        return get_string('aggregation_min', 'tool_reportbuilder');
    }

    /**
     * Get aggregation shortname (use for key value in the select)
     *
     * @return string
     */
    public static function get_shortname(): string {
        return 'min';
    }
}

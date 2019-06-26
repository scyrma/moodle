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
 * Class groupconcatdistinct
 *
 * @package   tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\aggregate;

use tool_reportbuilder\aggregation_base;
use tool_reportbuilder\constants;

defined('MOODLE_INTERNAL') || die();

/**
 * Class containing the group concat with distinct results aggregation
 *
 * @package   tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupconcatdistinct extends aggregation_base {
    /**
     * Get the SQL
     *
     * @param string $field
     * @param int|null $dbtype
     * @return string
     * @throws \coding_exception
     */
    public static function get_field(string $field, ?int $dbtype = null) : string {
        // TODO: add order by and order direction.
        global $DB;
        $dbfamily = $DB->get_dbfamily();
        $separator = get_string('listsep', 'langconfig');
        switch ($dbfamily) {
            case 'mssql':
                return " STRING_AGG($field, '{$separator}') ";
                break;
            case 'postgres':
                return " STRING_AGG(CAST($field AS VARCHAR), '{$separator}')";
                break;
            case 'mysql':
                return "GROUP_CONCAT(DISTINCT $field SEPARATOR '$separator')";
                break;
            case 'oracle':
                return "LISTAGG($field, '{$separator}') WITHIN GROUP (ORDER BY 1)";
                break;
        }
    }

    /**
     * Check if this aggregation can be use with the type of field
     *
     * @param int|null $dbtype
     * @return bool
     */
    public static function is_compatible(?int $dbtype) : bool {
        return !in_array($dbtype, [
            constants::DB_TYPE_DATETIME,
            constants::DB_TYPE_TIMESTAMP
        ]);
    }

    /**
     * Get visible name
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_displayname(): string {
        return get_string('aggregation_groupconcatdistinct', 'tool_reportbuilder');
    }

    /**
     * Get aggregation shortname (use for key value in the select)
     *
     * @return string
     */
    public static function get_shortname(): string {
        return 'groupconcatdistinct';
    }
}

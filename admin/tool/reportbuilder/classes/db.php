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
 * Class db
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

use html_writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper functions for DB manipulations
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class db {

    /**
     * Helper method to create unique placeholders replacements
     *
     * @param array $placeholders
     * @return array
     */
    protected static function get_placeholders_replacements(array $placeholders) : array {
        $len = strlen(count($placeholders) - 1);
        $r = [];
        foreach (array_values($placeholders) as $idx => $ph) {
            $r[$ph] = sprintf("|||<<<%0{$len}d>>>|||", $idx);
        }
        return $r;
    }

    /**
     * Takes a string with placeholders and converts it to the SQL expression
     *
     * @param string $str actual string
     * @param array $sqlfields SQL field names (or expressions)
     * @param bool $ascsv return as CSV string with only necessary fields, no other characters, no concatenation
     *        (to be used in group by)
     * @return array|string
     */
    protected static function string_to_sql_int(string $str, array $sqlfields, bool $ascsv = false) {
        global $DB;
        $parts = preg_split('/\|\|\|/', $str);
        $params = [];
        $elements = [];
        $sqlfields = array_values($sqlfields);
        foreach ($parts as $part) {
            if (!strlen($part)) {
                continue;
            }
            if (preg_match('/^<<<(\d+)>>>$/', $part, $matches)) {
                // This is a field.
                $elements[] = $sqlfields[(int)$matches[1]];
            } else if (!$ascsv) {
                if (preg_match('/^[ \,\.\-\(\)]*$/', $part)) {
                    // The separator is a simple string containing spaces, commas, braces, we don't need parameter.
                    $elements[] = "'" . $part . "'";
                } else {
                    // Use parameter for any complex separator.
                    $paramname = \tool_wp\db::generate_param_name();
                    $params[$paramname] = $part;
                    $elements[] = ':' . $paramname;
                }
            }
        }
        if ($ascsv) {
            return join(', ', $elements);
        }
        $sql = call_user_func_array([$DB, 'sql_concat'], $elements);
        return [$sql, $params];
    }

    /**
     * Takes a string with placeholders and converts it to the SQL expression
     *
     * Examples:
     *     list($sql1, $params1) = \tool_reportbuilder\db::sql_get_string('and', 'moodle',
     *         ['one' => 't.field1', 'two' => 't.field2']);
     *     list($sql2, $params2) = \tool_reportbuilder\db::sql_get_string('backto', 'moodle',
     *         't.url');
     *
     *     $sql1:    "'' || t.field1 || :wpdbp6 || t.field2"
     *     $params1: ['wpdbp6' => ' and ']
     *     $sql2:    "'' :wpdbp7 || t.url"
     *     $params2: ['wpdbp7' => 'Back to ']
     *
     * @param string $identifier string identifier
     * @param string $component string component
     * @param array|object|string $phmap placeholders mapping - all placeholders that can be in strings mapped to the SQL
     *        field names (or expressions). If the string takes simple placeholder ({$a}), this is the string with the
     *        SQL field (expression).
     * @param bool $ascsv return as CSV string with only necessary fields, no other characters, no concatenation
     *        (to be used in group by)
     * @return array|string
     */
    public static function sql_get_string(string $identifier, string $component, $phmap, bool $ascsv = false) {
        if (is_array($phmap) || is_object($phmap)) {
            // This is a string with multiple placeholders, i.e.: $string['and'] = '{$a->one} and {$a->two}'; .
            $phmap = (array)$phmap;
            $placeholders = array_keys($phmap);
            $sqlfields = array_values($phmap);
            $a = (object)self::get_placeholders_replacements($placeholders);
        } else {
            // This is a string with a simple placeholder, i.e.: $string['backto'] = 'Back to {$a}'; .
            $a = self::get_placeholders_replacements([1])[1];
            $sqlfields = [$phmap];
        }
        $str = get_string($identifier, $component, $a);
        return self::string_to_sql_int($str, $sqlfields, $ascsv);
    }

    /**
     * Correct implementation of sql_fullname
     *
     * @param string $usertablealias
     * @param bool $override
     * @param bool $ascsv return as CSV string with only necessary fields, no other characters, no concatenation
     *        (to be used in group by)
     * @return array|string
     */
    public static function sql_fullname($usertablealias = 'u', bool $override = false, bool $ascsv = false) {
        $usernames = get_all_user_name_fields();
        $user = (object)self::get_placeholders_replacements($usernames);
        $fullname = fullname($user, $override);
        $sqlfields = [];
        $prefix = strlen($usertablealias) ? $usertablealias . '.' : '';
        foreach ($usernames as $name) {
            $sqlfields[] = $prefix . $name;
        }
        return self::string_to_sql_int($fullname, $sqlfields, $ascsv);
    }

    /**
     * Generate SQL query for a concatenated tag field
     *
     * @param string $tablealias
     * @param string $component
     * @return array
     */
    public static function sql_tag_field(string $tablealias, string $component) : array {
        $placeholder = html_writer::span('{{name}}', '', ['data-rawname' => '{{rawname}}']);
        list($placeholdersql, $params) = self::sql_string_with_placeholders($placeholder, [
            '{{name}}' => 'tg.name',
            '{{rawname}}' => 'tg.rawname',
        ]);

        $taggroupconcat = self::sql_group_concat($placeholdersql, '');

        $sql = "(SELECT {$taggroupconcat}
                   FROM {tag_instance} ti
                   JOIN {tag} tg ON tg.id = ti.tagid
                  WHERE ti.itemid = {$tablealias}.id
                    AND ti.itemtype = '{$component}'
                    AND ti.component = '{$component}')";

        return [$sql, $params];
    }

    /**
     * Remove Oracle hack
     *
     * Oracle does not allow to do $DB->sql_concat('x', $DB->sql_concat('a', 'b'))
     *
     * Each sql_concat wraps the expression in MOODLELIB.UNDO_MEGA_HACK()
     * If we want to use one concat inside another, we need to remove the mega hack from the inner one.
     *
     * @param string $sql
     * @return string
     */
    public static function remove_oracle_hack(string $sql) {
        global $DB;
        if ($DB->get_dbfamily() === 'oracle' &&
                preg_match('/^\s*MOODLELIB.UNDO_MEGA_HACK\((.*)\)\s*$/', $sql, $matches)) {
            return ' ' . $matches[1] . ' ';
        }
        return $sql;
    }

    /**
     * Converts a string with placeholders to an SQL expression
     *
     * Examples:
     *     list($sql1, $params) = db::sql_string_with_placeholders('{p2} and {p1}',
     *         ['{p1}' => 't.field1', '{p2}' => 't.field2'],
     *         false);
     *     $sql2 = db::sql_string_with_placeholders('{p1} and {p2}',
     *         ['{p1}' => 't.field1', '{p2}' => 't.field2'],
     *         true);
     *
     *     $sql1:   "'' || t.field2 || :wpdbp5 || t.field1" (different syntax for different DB types)
     *     $params: ['wpdbp5' => ' and ']
     *     $sql2:   "t.field2, t.field1"
     *
     * @param string $str
     * @param array $phmap placeholders mapping - all placeholders that can be in strings mapped to the SQL
     *        field names (or expressions)
     * @param bool $ascsv
     * @return array|string
     */
    public static function sql_string_with_placeholders(string $str, array $phmap, bool $ascsv = false) {
        $placeholders = array_keys($phmap);
        $sqlfields = array_values($phmap);
        $a = self::get_placeholders_replacements($placeholders);
        foreach ($placeholders as $ph) {
            $str = str_replace($ph, $a[$ph], $str);
        }
        return self::string_to_sql_int($str, $sqlfields, $ascsv);
    }

    /**
     * SQL expression for "group concatenation" aggregation
     *
     * @param string $field database field or SQL expression that needs to be concatenated. Note that MSSQL
     *        can not do aggregation functions on sub-selects
     * @param string $separator
     * @return string
     * @throws \coding_exception
     */
    public static function sql_group_concat(string $field, string $separator = null) : string {
        // TODO: add order by and order direction.
        global $DB;

        // If $separator is not specified, default to the helper method to specify.
        $separator = $separator ?? helper::get_list_separator();

        $dbfamily = $DB->get_dbfamily();
        switch ($dbfamily) {
            case 'mssql':
                return "STRING_AGG($field, '{$separator}')";
                break;
            case 'postgres':
                return "STRING_AGG(CAST($field AS VARCHAR), '{$separator}')";
                break;
            case 'mysql':
                return "GROUP_CONCAT($field SEPARATOR '{$separator}')";
                break;
            case 'oracle':
                return "LISTAGG($field, '{$separator}') WITHIN GROUP (ORDER BY 1)";
                break;
        }
    }

    /**
     * SQL expression for "group concatenation distinct" aggregation
     *
     * Note! Only Mysql supports "distinct" in group concatenation, all other databases do non-distinct concatenation
     *
     * @param string $field database field or SQL expression that needs to be concatenated. Note that MSSQL
     *        can not do aggregation functions on sub-selects
     * @param string $separator
     * @return string
     * @throws \coding_exception
     */
    public static function sql_group_concat_distinct(string $field, string $separator = null) : string {
        // TODO: add order by and order direction.
        global $DB;

        // If $separator is not specified, default to the helper method to specify.
        $separator = $separator ?? helper::get_list_separator();

        $dbfamily = $DB->get_dbfamily();
        switch ($dbfamily) {
            case 'mssql':
                return "STRING_AGG($field, '{$separator}') ";
                break;
            case 'postgres':
                return "STRING_AGG(CAST($field AS VARCHAR), '{$separator}')";
                break;
            case 'mysql':
                return "GROUP_CONCAT(DISTINCT $field SEPARATOR '$separator')";
                break;
            case 'oracle':
                return "LISTAGG($field, '{$separator}') WITHIN GROUP (ORDER BY 1)";
                break;
        }
    }
}

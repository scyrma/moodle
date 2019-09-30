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
 * Upgrade script for tool_wp
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_wp_upgrade(int $oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019090002) {

        // Define table tool_wp_course_reset to be created.
        $table = new xmldb_table('tool_wp_course_reset');

        // Adding fields to table tool_wp_course_reset.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('certificationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reason', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timerequested', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userrequested', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('wascompleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('resetstatus', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('resetinfo', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tool_wp_course_reset.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for tool_wp_course_reset.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2019090002, 'tool', 'wp');
    }

    if ($oldversion < 2019090003) {

        // Changing precision of field grade on table tool_wp_course_reset to (10, 5).
        $table = new xmldb_table('tool_wp_course_reset');
        $field = new xmldb_field('grade', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0', 'wascompleted');

        // Launch change of precision for field grade.
        $dbman->change_field_precision($table, $field);

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2019090003, 'tool', 'wp');
    }

    return true;
}

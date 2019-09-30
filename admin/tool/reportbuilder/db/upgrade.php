<?php
// This file is part of Moodle - https://moodle.org/
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
 * Plugin upgrade steps are defined here.
 *
 * @package     tool_reportbuilder
 * @category    upgrade
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 *
 * @return bool
 * @throws ddl_exception
 * @throws ddl_table_missing_exception
 * @throws downgrade_exception
 * @throws upgrade_exception
 */
function xmldb_tool_reportbuilder_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019050601) {

        // Define field conditions to be added to tool_reportbuilder.
        $table = new xmldb_table('tool_reportbuilder');
        $field = new xmldb_field('conditions', XMLDB_TYPE_TEXT, null, null, null, null, null, 'type');

        // Conditionally launch add field conditions.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2019050601, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2019052801) {
        // Create default role "Report builder manager".
        \tool_tenant\manager::create_workplace_role('tool_reportbuilder_manager',
            get_string('rolemanager', 'tool_reportbuilder'),
            get_string('rolemanagerdescription', 'tool_reportbuilder'),
            ['tool/reportbuilder:edit']);

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019052801, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2019062311) {

        // Define table tool_reportbuilder_scheduled to be dropped.
        $table = new xmldb_table('tool_reportbuilder_scheduled');

        // Conditionally launch drop table for tool_reportbuilder_scheduled.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Define table tool_reportbuilder_scheduled to be created.
        $table = new xmldb_table('tool_reportbuilder_scheduled');

        // Adding fields to table tool_reportbuilder_scheduled.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scheduled', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('recurrence', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastsenton', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextsend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('format', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('subject', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('audience', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tool_reportbuilder_scheduled.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('repobuilcond_rep_fk', XMLDB_KEY_FOREIGN, ['reportid'], 'report_builder', ['id']);

        // Conditionally launch create table for tool_reportbuilder_scheduled.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2019062311, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2019062501) {
        // Update formats in the database from int code to plugin name.
        $formats = [
            1 => 'excel',
            2 => 'csv',
            3 => 'pdf',
            4 => 'json',
            5 => 'html',
            6 => 'ods'
        ];
        foreach ($formats as $oldformat => $newformat) {
            $DB->execute('UPDATE {tool_reportbuilder_scheduled} SET format = ? WHERE format = ?',
                [$newformat, $oldformat]);
        }
        list($sql, $params) = $DB->get_in_or_equal($formats, SQL_PARAMS_NAMED, 'param', false);
        $DB->execute('UPDATE {tool_reportbuilder_scheduled} SET format = :excel WHERE format ' . $sql,
            ['excel' => 'excel'] + $params);

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2019062501, 'tool', 'reportbuilder');
    }

    return true;
}


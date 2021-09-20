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
 * Plugin upgrade steps are defined here.
 *
 * @package     tool_reportbuilder
 * @category    upgrade
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
    global $DB, $CFG;

    require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/reportbuilder/db/upgradelib.php');

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

    if ($oldversion < 2019111301) {
        // Define table tool_reportbuilder_audience to be created.
        $table = new xmldb_table('tool_reportbuilder_audience');

        // Adding fields to table tool_reportbuilder_audience.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('departmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('positionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('subdepartments', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('subpositions', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tool_reportbuilder_audience.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'tool_reportbuilder', ['id']);

        // Adding indexes to table tool_reportbuilder_audience.
        $table->add_index('department', XMLDB_INDEX_NOTUNIQUE, ['departmentid']);
        $table->add_index('position', XMLDB_INDEX_NOTUNIQUE, ['positionid']);

        // Conditionally launch create table for tool_reportbuilder_audience.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2019111301, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2019112601) {
        $table = new xmldb_table('tool_reportbuilder_column');

        // Define key repobuilcolu_rep_fk (foreign) to be dropped from tool_reportbuilder_column.
        $key = new xmldb_key('repobuilcolu_rep_fk', XMLDB_KEY_FOREIGN, ['reportid'], 'report_builder', ['id']);
        $dbman->drop_key($table, $key);

        // Define key reportid (foreign) to be added to tool_reportbuilder_column.
        $key = new xmldb_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'tool_reportbuilder', ['id']);
        $dbman->add_key($table, $key);

        $table = new xmldb_table('tool_reportbuilder_filter');

        // Define key repobuilfilt_rep_fk (foreign) to be dropped from tool_reportbuilder_filter.
        $key = new xmldb_key('repobuilfilt_rep_fk', XMLDB_KEY_FOREIGN, ['reportid'], 'report_builder', ['id']);
        $dbman->drop_key($table, $key);

        // Define key reportid (foreign) to be added to tool_reportbuilder_filter.
        $key = new xmldb_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'tool_reportbuilder', ['id']);
        $dbman->add_key($table, $key);

        $table = new xmldb_table('tool_reportbuilder_cond');

        // Define key repobuilcond_rep_fk (foreign) to be dropped from tool_reportbuilder_cond.
        $key = new xmldb_key('repobuilcond_rep_fk', XMLDB_KEY_FOREIGN, ['reportid'], 'report_builder', ['id']);
        $dbman->drop_key($table, $key);

        // Define key reportid (foreign) to be added to tool_reportbuilder_cond.
        $key = new xmldb_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'tool_reportbuilder', ['id']);
        $dbman->add_key($table, $key);

        $table = new xmldb_table('tool_reportbuilder_scheduled');

        // Define key repobuilcond_rep_fk (foreign) to be dropped form tool_reportbuilder_scheduled.
        $key = new xmldb_key('repobuilcond_rep_fk', XMLDB_KEY_FOREIGN, ['reportid'], 'report_builder', ['id']);
        $dbman->drop_key($table, $key);

        // Define key reportid (foreign) to be added to tool_reportbuilder_scheduled.
        $key = new xmldb_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'tool_reportbuilder', ['id']);
        $dbman->add_key($table, $key);

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2019112601, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2019120400) {
        // For any databases that don't support group concat distinct, we need to remove that aggregation type from all columns.
        if ($DB->get_dbfamily() === 'oracle' || $DB->get_dbfamily() === 'mssql') {
            $DB->set_field('tool_reportbuilder_column', 'aggregate', 'groupconcat', ['aggregate' => 'groupconcatdistinct']);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2019120400, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020010200) {
        $table = new xmldb_table('tool_reportbuilder');

        // Define key tenantid (foreign) to be added to tool_reportbuilder.
        $key = new xmldb_key('tenantid', XMLDB_KEY_FOREIGN, ['tenantid'], 'tool_tenant', ['id']);
        $dbman->add_key($table, $key);

        // Define index source (not unique) to be added to tool_reportbuilder.
        $index = new xmldb_index('source', XMLDB_INDEX_NOTUNIQUE, ['source']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index type (not unique) to be added to tool_reportbuilder.
        $index = new xmldb_index('type', XMLDB_INDEX_NOTUNIQUE, ['type']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020010200, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020012800) {
        // Clean up orphaned audience records.
        $DB->delete_records_select('tool_reportbuilder_audience', 'reportid NOT IN (SELECT id FROM {tool_reportbuilder})');

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020012800, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020013000) {
        // Clean up orphaned report records, allow them to clean up after themselves.
        $reports = \tool_reportbuilder\reportbuilder::get_records_select('tenantid NOT IN (SELECT id FROM {tool_tenant})');
        foreach ($reports as $report) {
            $report->delete();
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020013000, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020040700) {
        // Calculate next send time for existing report schedules.
        $schedules = $DB->get_records('tool_reportbuilder_scheduled');
        foreach ($schedules as $schedule) {
            $nextsend = tool_reportbuilder_upgrade_calculate_next_send_time($schedule->recurrence, $schedule->scheduled);

            $DB->set_field('tool_reportbuilder_scheduled', 'nextsend', $nextsend, ['id' => $schedule->id]);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020040700, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020042200) {
        // Define field enabled to be added to tool_reportbuilder_scheduled.
        $table = new xmldb_table('tool_reportbuilder_scheduled');
        $field = new xmldb_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'name');

        // Conditionally launch add field enabled.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020042200, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020051800) {
        // Define field departmentid and positionid to be added to tool_reportbuilder_scheduled.
        $table = new xmldb_table('tool_reportbuilder_scheduled');

        // Conditionally launch add field departmentid.
        $field = new xmldb_field('departmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'message');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Conditionally launch add field positionid.
        $field = new xmldb_field('positionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'departmentid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Migrate data from the "audience/recipients" JSON into the new fields.
        $schedules = $DB->get_records('tool_reportbuilder_scheduled', null, '', 'id, audience');
        foreach ($schedules as $schedule) {
            $audiencejson = json_decode($schedule->audience);

            $schedule->departmentid = $audiencejson->departmentid ?: 0;
            $schedule->positionid = $audiencejson->positionid ?: 0;
            unset($audiencejson->departmentid, $audiencejson->positionid);

            $schedule->audience = json_encode($audiencejson);

            $DB->update_record('tool_reportbuilder_scheduled', $schedule);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020051800, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020051801) {
        // Rename field audience on table tool_reportbuilder_scheduled to recipients.
        $table = new xmldb_table('tool_reportbuilder_scheduled');
        $field = new xmldb_field('audience', XMLDB_TYPE_TEXT, null, null, null, null, null, 'positionid');

        // Launch rename field audience.
        $dbman->rename_field($table, $field, 'recipients');

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020051801, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020082500) {
        // Remove tool_certificate system reports. They are not used anymore.
        $sql = "SELECT rb.id FROM {tool_reportbuilder} rb
                WHERE rb.type = :type
                AND (rb.source = :source1 OR rb.source = :source2)";
        $params = [
            'type' => \tool_reportbuilder\constants::TYPE_SYSTEM,
            'source1' => 'tool_certificate\certificates_list',
            'source2' => 'tool_certificate\issues_list',
        ];
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            // Remove system report columns.
            $DB->delete_records('tool_reportbuilder_column', ['reportid' => $record->id]);
        }
        // Remove system reports.
        $DB->delete_records_list('tool_reportbuilder', 'id', array_keys($records));

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020082500, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020090400) {
        // Update tool_certificate datasources to tool_reportbuilder.
        $replacements = [
            'tool_certificate\tool_reportbuilder\datasources\issues' =>
                'tool_reportbuilder\tool_reportbuilder\datasources\report_tool_certificate_issues',
            'tool_certificate\tool_reportbuilder\datasources\certificates' =>
                'tool_reportbuilder\tool_reportbuilder\datasources\report_tool_certificate_templates'
        ];
        foreach ($replacements as $oldclass => $newclass) {
            $DB->set_field('tool_reportbuilder', 'source', $newclass, ['source' => $oldclass]);
        }

        upgrade_plugin_savepoint(true, 2020090400, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020111201) {
        // Define field shortname to be dropped from tool_reportbuilder.
        $table = new xmldb_table('tool_reportbuilder');
        $field = new xmldb_field('shortname');

        // Conditionally launch drop field shortname.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020111201, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020111202) {
        // Define index idnumber (unique) to be added to tool_reportbuilder.
        $table = new xmldb_table('tool_reportbuilder');
        $index = new xmldb_index('idnumber', XMLDB_INDEX_UNIQUE, ['idnumber']);

        // Conditionally launch add index idnumber.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020111202, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020111203) {
        // Define field hidden to be dropped from tool_reportbuilder_column.
        $table = new xmldb_table('tool_reportbuilder_column');
        $field = new xmldb_field('hidden');

        // Conditionally launch drop field hidden.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020111203, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2020111204) {
        // Define field shared to be added to tool_reportbuilder.
        $table = new xmldb_table('tool_reportbuilder');
        $field = new xmldb_field('shared', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'conditions');

        // Conditionally launch add field shared.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2020111204, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2021051301) {

        // Define table tool_reportbuilder_audiences to be created.
        $table = new xmldb_table('tool_reportbuilder_audiences');

        // Adding fields to table tool_reportbuilder_audiences.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('classname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('configdata', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tool_reportbuilder_audiences.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'tool_reportbuilder', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for tool_reportbuilder_audiences.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2021051301, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2021052400) {
        // Convert all previous audience records to new audience job type.
        $audiencetype = \tool_organisation\tool_reportbuilder\audiences\job::class;

        $audiences = $DB->get_records('tool_reportbuilder_audience');
        foreach ($audiences as $audience) {
            $config = [
                'department' => [
                    'id' => $audience->departmentid,
                    'withsubdepartments' => $audience->subdepartments,
                ],
                'position' => [
                    'id' => $audience->positionid,
                    'withsubpositions' => $audience->subpositions,
                ],
            ];

            tool_reportbuilder_upgrade_create_audience_type($audience->reportid, $audiencetype, $config, $audience->usermodified);
        }

        // Define table tool_reportbuilder_audience to be dropped.
        $table = new xmldb_table('tool_reportbuilder_audience');

        // Conditionally launch drop table for tool_reportbuilder_audience.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2021052400, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2021052500) {

        // Define field usercreated to be added to tool_reportbuilder_audiences.
        $table = new xmldb_table('tool_reportbuilder_audiences');
        $field = new xmldb_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'configdata');

        // Conditionally launch add field usercreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key usercreated (foreign) to be added to tool_reportbuilder_audiences.
        $key = new xmldb_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);

        // Launch add key usercreated.
        $dbman->add_key($table, $key);

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2021052500, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2021052600) {

        // Define table tool_reportbuilder_schedule to be created.
        $table = new xmldb_table('tool_reportbuilder_schedule');

        // Adding fields to table tool_reportbuilder_schedule.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('scheduled', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('recurrence', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastsenton', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextsend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('format', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('subject', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('audiences', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tool_reportbuilder_schedule.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('reportid', XMLDB_KEY_FOREIGN, ['reportid'], 'tool_reportbuilder', ['id']);
        $table->add_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for tool_reportbuilder_schedule.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Convert all previous schedule records to new schedule type.
        $schedules = $DB->get_records('tool_reportbuilder_scheduled');
        foreach ($schedules as $schedule) {
            tool_reportbuilder_upgrade_create_schedule_with_audiences($schedule);
        }

        $DB->execute('UPDATE {tool_reportbuilder_audiences} SET usercreated = usermodified');

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2021052600, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2021052601) {

        // Define table tool_reportbuilder_scheduled to be dropped.
        $table = new xmldb_table('tool_reportbuilder_scheduled');

        // Conditionally launch drop table for tool_reportbuilder_scheduled.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2021052601, 'tool', 'reportbuilder');
    }

    if ($oldversion < 2021090801) {

        // Define field cardviewsettings to be added to tool_reportbuilder.
        $table = new xmldb_table('tool_reportbuilder');
        $field = new xmldb_field('cardviewsettings', XMLDB_TYPE_TEXT, null, null, null, null, null, 'shared');

        // Conditionally launch add field cardviewsettings.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->execute('UPDATE {tool_reportbuilder} SET cardviewsettings = :settings WHERE cardviewsettings IS NULL',
            ['settings' => json_encode(['showtitle' => 0, 'visibility' => 1])]);

        // Reportbuilder savepoint reached.
        upgrade_plugin_savepoint(true, 2021090801, 'tool', 'reportbuilder');
    }

    return true;
}

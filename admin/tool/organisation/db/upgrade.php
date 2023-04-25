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
 * @package     tool_organisation
 * @category    upgrade
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Execute tool_organisation upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_organisation_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019041000) {

        // Rename field level on table tool_organisation_position to pathlevel.
        $table = new xmldb_table('tool_organisation_position');
        $field = new xmldb_field('level', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'parentid');

        // Launch rename field level.
        $dbman->rename_field($table, $field, 'pathlevel');

        // Rename field level on table tool_organisation_department to pathlevel.
        $table = new xmldb_table('tool_organisation_department');
        $field = new xmldb_field('level', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'parentid');

        // Launch rename field level.
        $dbman->rename_field($table, $field, 'pathlevel');

        // Organisation savepoint reached.
        upgrade_plugin_savepoint(true, 2019041000, 'tool', 'organisation');
    }

    if ($oldversion < 2019052801) {
        // Create default role "Certification manager".
        \tool_tenant\manager::create_workplace_role('tool_organisation_manager',
            get_string('rolemanager', 'tool_organisation'),
            get_string('rolemanagerdescription', 'tool_organisation'),
            ['tool/organisation:managedepartments', 'tool/organisation:managepositions', 'tool/organisation:assignjobs']);

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019052801, 'tool', 'organisation');
    }

    if ($oldversion < 2020020500) {
        // Clean up orphaned jobs, departments and positions.
        $jobs = $DB->get_fieldset_sql("SELECT j.id from {tool_organisation_job} j
            LEFT JOIN {tool_tenant} t ON j.tenantid = t.id
            WHERE t.id IS NULL");
        if ($jobs) {
            list($sql, $params) = $DB->get_in_or_equal($jobs);
            $DB->delete_records_select('tool_organisation_job', 'id '.$sql, $params);
        }

        $ids = $DB->get_fieldset_sql("SELECT p.id from {tool_organisation_position} p
            LEFT JOIN {tool_tenant} t ON p.tenantid = t.id
            WHERE t.id IS NULL");
        if ($ids) {
            foreach ($ids as $id) {
                get_file_storage()->delete_area_files(context_system::instance()->id,
                    'tool_organisation', 'positiondescription', $id);
            }
            list($sql, $params) = $DB->get_in_or_equal($ids);
            $DB->delete_records_select('tool_organisation_position', 'id '.$sql, $params);
        }

        $ids = $DB->get_fieldset_sql("SELECT d.id from {tool_organisation_department} d
            LEFT JOIN {tool_tenant} t ON d.tenantid = t.id
            WHERE t.id IS NULL");
        if ($ids) {
            foreach ($ids as $id) {
                get_file_storage()->delete_area_files(context_system::instance()->id,
                    'tool_organisation', 'departmentdescription', $id);
            }
            list($sql, $params) = $DB->get_in_or_equal($ids);
            $DB->delete_records_select('tool_organisation_department', 'id '.$sql, $params);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2020020500, 'tool', 'organisation');
    }

    if ($oldversion < 2020063001) {
        // We are deleteing user preferences because we are changing the filters.
        $reports = $DB->get_records('tool_reportbuilder', ['source' => 'tool_organisation\managed_users_table']);
        foreach ($reports as $report) {
            $DB->delete_records('user_preferences', ['name' => 'filters_report_' . $report->id]);
        }

        // Organisation savepoint reached.
        upgrade_plugin_savepoint(true, 2020063001, 'tool', 'organisation');
    }

    if ($oldversion < 2021011902) {
        // Define field shared to be added to tool_organisation_department.
        $table = new xmldb_table('tool_organisation_department');
        $field = new xmldb_field('shared', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'archived');

        // Conditionally launch add field shared.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field shared to be added to tool_organisation_position.
        $table = new xmldb_table('tool_organisation_position');
        $field = new xmldb_field('shared', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'globalpermissions');

        // Conditionally launch add field shared.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Organisation savepoint reached.
        upgrade_plugin_savepoint(true, 2021011902, 'tool', 'organisation');
    }

    if ($oldversion < 2021032501) {
        // Schedule ad-hoc task to end orphaned jobs.
        $record = new \stdClass();
        $record->classname = '\tool_organisation\task\end_orphaned_jobs';
        $record->component = 'tool_organisation';
        // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
        $nextruntime = time() - 1;
        $record->nextruntime = $nextruntime;
        $DB->insert_record('task_adhoc', $record);

        upgrade_plugin_savepoint(true, 2021032501, 'tool', 'organisation');
    }

    return true;
}

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
 * @package     tool_datastore
 * @category    upgrade
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_datastore_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019120300) {
        // Define field actionid to be dropped from tool_datastore_idx_fields.
        $table = new xmldb_table('tool_datastore_idx_fields');
        $field = new xmldb_field('actionid');

        // Conditionally launch drop field actionid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Datastore savepoint reached.
        upgrade_plugin_savepoint(true, 2019120300, 'tool', 'datastore');
    }

    if ($oldversion < 2019121700) {
        // Remove configuration for default entity fields to index.
        unset_config('fieldscourse', 'tool_datastore');
        unset_config('fieldsuser', 'tool_datastore');

        // Datastore savepoint reached.
        upgrade_plugin_savepoint(true, 2019121700, 'tool', 'datastore');
    }

    if ($oldversion < 2019121800) {
        // Define field entityid to be added to tool_datastore_snapshot.
        $table = new xmldb_table('tool_datastore_snapshot');
        $field = new xmldb_field('entityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');

        // Conditionally launch add field entityid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key entityid (foreign) to be added to tool_datastore_snapshot.
        $key = new xmldb_key('entityid', XMLDB_KEY_FOREIGN, ['entityid'], 'tool_datastore_entity', ['id']);

        // Launch add key entityid.
        $dbman->add_key($table, $key);

        // Migrate data from entity.snapshotid to snapshot.entityid.
        $records = $DB->get_records_menu('tool_datastore_entity', null, '', 'id,snapshotid');
        foreach ($records as $entityid => $snapshotid) {
            $DB->set_field('tool_datastore_snapshot', 'entityid', $entityid, ['id' => $snapshotid]);
        }

        // Define field snapshotid to be dropped from tool_datastore_entity.
        $table = new xmldb_table('tool_datastore_entity');
        $field = new xmldb_field('snapshotid');

        // Conditionally launch drop field snapshotid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Datastore savepoint reached.
        upgrade_plugin_savepoint(true, 2019121800, 'tool', 'datastore');
    }

    return true;
}
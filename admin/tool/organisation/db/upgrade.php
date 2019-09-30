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
 * Plugin upgrade steps are defined here.
 *
 * @package     tool_organisation
 * @category    upgrade
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

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

    return true;
}

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
 * @package     tool_tenant
 * @category    upgrade
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute tool_tenant upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_tenant_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019040100) {
        // Remove system capability from tenant admin.
        $allroles = get_all_roles(\context_system::instance());
        foreach ($allroles as $role) {
            if ($role->shortname === 'tenantadmin') {
                unassign_capability('moodle/my:configsyspages', $role->id);
                break;
            }
        }

        upgrade_plugin_savepoint(true, 2019040100, 'tool', 'tenant');
    }

    if ($oldversion < 2019051401) {
        $DB->execute('UPDATE {role} SET shortname = ? WHERE shortname = ?', ['tool_tenant_admin', 'tenantadmin']);
        $DB->execute('UPDATE {role} SET shortname = ? WHERE shortname = ?', ['tool_tenant_manager', 'tenantmanager']);
        $DB->execute('UPDATE {role} SET shortname = ? WHERE shortname = ?', ['tool_tenant_user', 'tenantuser']);
        \tool_tenant\manager::change_core_roles();
        upgrade_plugin_savepoint(true, 2019051401, 'tool', 'tenant');
    }

    if ($oldversion < 2019052705) {
        // Remove associations that may be incorrect.
        $roles = [(int)get_config('', 'tool_tenant_adminrole'),
            (int)get_config('', 'tool_tenant_managerrole'),
            (int)get_config('', 'tool_tenant_userrole')];
        foreach ($roles as $roleid) {
            $DB->delete_records('role_allow_assign', ['roleid' => $roleid]);
            $DB->delete_records('role_allow_switch', ['roleid' => $roleid]);
            $DB->delete_records('role_allow_view', ['roleid' => $roleid]);
            $DB->delete_records('role_allow_override', ['roleid' => $roleid]);
        }

        // Create/update roles and add associations.
        \tool_tenant\manager::create_tenant_roles();
        upgrade_plugin_savepoint(true, 2019052705, 'tool', 'tenant');
    }

    if ($oldversion < 2019061200) {
        $roles = $DB->get_records('role', [], '', 'shortname, id');
        $syscontext = context_system::instance();
        foreach ($roles as $role) {
            if (preg_match('/^tool_.*_manager$/', $role->shortname) || $role->shortname === 'tool_tenant_admin') {
                assign_capability('moodle/site:doclinks', CAP_ALLOW, $role->id, $syscontext->id);
            }
        }

        upgrade_plugin_savepoint(true, 2019061200, 'tool', 'tenant');
    }

    if ($oldversion < 2019071100) {
        if ($roleid = $DB->get_field('role', 'id', ['shortname' => 'tool_tenant_admin'])) {
            $syscontext = context_system::instance();
            assign_capability('moodle/badges:awardbadge', CAP_ALLOW, $roleid, $syscontext->id);
            assign_capability('moodle/badges:viewawarded', CAP_ALLOW, $roleid, $syscontext->id);
        }
        upgrade_plugin_savepoint(true, 2019071100, 'tool', 'tenant');
    }

    if ($oldversion < 2019091703) {

        // Define table tool_tenant_group to be created.
        $table = new xmldb_table('tool_tenant_group');

        // Adding fields to table tool_tenant_group.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('tenantid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('area', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table tool_tenant_group.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('tenantid', XMLDB_KEY_FOREIGN, ['tenantid'], 'tool_tenant', ['id']);
        $table->add_key('groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'groups', ['id']);

        // Adding indexes to table tool_tenant_group.
        $table->add_index('courseidtenantid', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'tenantid']);
        $table->add_index('componentitem', XMLDB_INDEX_NOTUNIQUE, ['component', 'area', 'itemid', 'courseid']);

        // Conditionally launch create table for tool_tenant_group.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Tenant savepoint reached.
        upgrade_plugin_savepoint(true, 2019091703, 'tool', 'tenant');
    }

    if ($oldversion < 2019092500) {

        // Define field idnumber to be added to tool_tenant.
        $table = new xmldb_table('tool_tenant');
        $field = new xmldb_field('idnumber', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'sitename');

        // Conditionally launch add field idnumber.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Tenant savepoint reached.
        upgrade_plugin_savepoint(true, 2019092500, 'tool', 'tenant');
    }

    return true;
}

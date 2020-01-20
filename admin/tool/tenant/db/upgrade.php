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
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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

    if ($oldversion < 2019101803) {

        // Define field siteshortname to be added to tool_tenant.
        $table = new xmldb_table('tool_tenant');
        $field = new xmldb_field('siteshortname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'cssconfig');

        // Conditionally launch add field siteshortname.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute('UPDATE {tool_tenant} SET siteshortname=sitename');
        }

        // Define field useloginurlid to be added to tool_tenant.
        $table = new xmldb_table('tool_tenant');
        $field = new xmldb_field('useloginurlid', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'siteshortname');

        // Conditionally launch add field useloginurlid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field useloginurlidnumber to be added to tool_tenant.
        $table = new xmldb_table('tool_tenant');
        $field = new xmldb_field('useloginurlidnumber', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'useloginurlid');

        // Conditionally launch add field useloginurlidnumber.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Tenant savepoint reached.
        upgrade_plugin_savepoint(true, 2019101803, 'tool', 'tenant');
    }

    if ($oldversion < 2019102103) {

        // Check and fix if every tenant has a valid category associated.
        $tenants = $DB->get_records('tool_tenant');
        foreach ($tenants as $tenant) {
            if (!$DB->record_exists('course_categories', array('id' => $tenant->categoryid))) {
                $DB->update_record('tool_tenant', (object) ['id' => $tenant->id, 'categoryid' => 0]);
            }
        }

        // Tenant savepoint reached.
        upgrade_plugin_savepoint(true, 2019102103, 'tool', 'tenant');
    }

    if ($oldversion < 2019111502) {

        $syscontextid = context_system::instance()->id;
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'tool_tenant_manager']);
        $adminrole = $DB->get_field('role', 'id', ['shortname' => 'tool_tenant_admin']);

        // Remove capability 'tool/certificate:manage' from the Tenant administrator role.
        // If it was present, add it to the Tenant manager role.
        if ($adminrole && $managerrole && get_capability_info('tool/certificate:manage')) {
            $rolesmanage = get_roles_with_capability('tool/certificate:manage', CAP_ALLOW);
            if (array_key_exists($adminrole, $rolesmanage) && !array_key_exists($managerrole, $rolesmanage)) {
                assign_capability('tool/certificate:manage', CAP_ALLOW, $managerrole, $syscontextid);
            }
            unassign_capability('tool/certificate:manage', $adminrole);
        }

        // If capability 'tool/certificate:issue' was assigned to Tenant administrator role, assign it also
        // to the Tenant manager role.
        if ($managerrole && get_capability_info('tool/certificate:issue')) {
            $rolesissue = get_roles_with_capability('tool/certificate:issue', CAP_ALLOW);
            if (array_key_exists($adminrole, $rolesissue) && !array_key_exists($managerrole, $rolesissue)) {
                assign_capability('tool/certificate:issue', CAP_ALLOW, $managerrole, $syscontextid);
            }
        }

        // Make sure both roles can view certificates.
        if ($adminrole && $managerrole && get_capability_info('tool/certificate:viewallcertificates')) {
            assign_capability('tool/certificate:viewallcertificates', CAP_ALLOW, $adminrole, $syscontextid);
            assign_capability('tool/certificate:viewallcertificates', CAP_ALLOW, $managerrole, $syscontextid);
        }

        // Remove capability to verify certificates from tenant administrator.
        if ($adminrole && get_capability_info('tool/certificate:verify')) {
            unassign_capability('tool/certificate:verify', $adminrole);
        }

        // Tenant savepoint reached.
        upgrade_plugin_savepoint(true, 2019111502, 'tool', 'tenant');
    }

    if ($oldversion < 2020011400) {

        // Also tenant roles have default names in English, remove the names. Now the name will be automatically
        // taken from the current language if not specified (similar to core roles).
        $roles = ['admin' => 'Tenant administrator', 'manager' => 'Tenant manager', 'user' => 'Tenant user'];
        foreach ($roles as $key => $defaultname) {
            if ($role = $DB->get_record('role', ['shortname' => 'tool_tenant_' . $key])) {
                if ($role->name === $defaultname) {
                    $DB->update_record('role', ['id' => $role->id, 'name' => '']);
                }
                set_config('tool_tenant_' . $key . 'role', $role->id);
                // Tenant roles can not be assigned manually, even by admin.
                set_role_contextlevels($role->id, []);
                // Can not assign or view this role.
                $DB->delete_records('role_allow_assign', ['allowassign' => $role->id]);
                $DB->delete_records('role_allow_view', ['allowview' => $role->id]);
            }
        }

        // Tenant savepoint reached.
        upgrade_plugin_savepoint(true, 2020011400, 'tool', 'tenant');
    }

    return true;
}

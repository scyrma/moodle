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
 * @package     tool_certification
 * @category    upgrade
 * @author      2018 Workplace team
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute tool_certification upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_certification_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019040500) {

        // Define field allocationstarts to be dropped from tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('allocationstarts');

        // Conditionally launch drop field allocationstarts.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field allocationends to be dropped from tool_certification_users.
        $field = new xmldb_field('allocationends');

        // Conditionally launch drop field allocationends.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019040500, 'tool', 'certification');
    }

    if ($oldversion < 2019041000) {
        // Check if tool_dynamicrule is installed.
        if (class_exists('\\tool_dynamicrule\\rules_list')) {
            // We add dynamic rules to dynamic rules tab inside certifications.
            $certs = $DB->get_records('tool_certification', null, '', 'id, tenantid');
            if (!empty($certs)) {
                foreach ($certs as $cert) {
                    $params = [
                        'component' => 'tool_certification',
                        'componentarea' => 'certification',
                        'itemid' => $cert->id
                    ];
                    $rulesexist = $DB->record_exists('tool_dynamicrule', $params);
                    if (!$rulesexist) {
                        // Create default dynamic rules for dynamic rules tab.
                        \tool_certification\api::add_default_dynamicrule_conditions_to_certification($cert->id, $cert->tenantid);
                    }
                }
            }
        }
        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019041000, 'tool', 'certification');
    }

    if ($oldversion < 2019050600) {

        // Define field timesuspended to be added to tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('timesuspended', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');

        // Conditionally launch add field timesuspended.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019050600, 'tool', 'certification');
    }

    if ($oldversion < 2019050701) {
        // Define table tool_certification_badges to be dropped.
        $table = new xmldb_table('tool_certification_badges');
        // Conditionally launch drop table for tool_certification_badges.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        // Define table tool_certification_compets to be dropped.
        $table = new xmldb_table('tool_certification_compets');
        // Conditionally launch drop table for tool_certification_compets.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        // Define table tool_certification_certs to be dropped.
        $table = new xmldb_table('tool_certification_certs');
        // Conditionally launch drop table for tool_certification_certs.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019050701, 'tool', 'certification');
    }

    if ($oldversion < 2019051700) {

        // Define field timerevoked to be added to tool_certification_compltion.
        $table = new xmldb_table('tool_certification_compltion');
        $field = new xmldb_field('timerevoked', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'expirydate');

        // Conditionally launch add field timerevoked.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019051700, 'tool', 'certification');
    }

    if ($oldversion < 2019052103) {
        // Check if tool_dynamicrule is installed.
        if (class_exists('\\tool_dynamicrule\\rules_list')) {
            // We add dynamic rules to dynamic rules tab inside certifications.
            $certifications = $DB->get_records('tool_certification', null, '', 'id, tenantid');
            if (!empty($certifications)) {
                foreach ($certifications as $certification) {
                    $params = [
                        'component' => 'tool_certification',
                        'componentarea' => 'certification',
                        'itemid' => $certification->id
                    ];
                    $rules = $DB->get_records('tool_dynamicrule', $params);

                    // Delete any previous existing rule.
                    foreach ($rules as $rule) {
                        $rule = \tool_dynamicrule\api::get_rule($rule->id, true);
                        $rule->delete();
                    }

                    if (!$certification->tenantid) {
                        continue;
                    }

                    // Apply all default rules.
                    $tenantid = $certification->tenantid;
                    \tool_certification\api::add_default_dynamicrule_conditions_to_certification($certification->id, $tenantid);
                }
            }
        }
        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019052103, 'tool', 'certification');
    }

    if ($oldversion < 2019052702) {
        // Create default role "Certification manager".
        \tool_tenant\manager::create_workplace_role('tool_certification_manager',
                get_string('rolemanager', 'tool_certification'),
                get_string('rolemanagerdescription', 'tool_certification'),
                ['tool/certification:edit', 'tool/certification:allocateuser']);

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019052702, 'tool', 'certification');
    }

    if ($oldversion < 2019073102) {
        // Remove user allocations for deleted users.
        $sql = "SELECT cu.id
        FROM {tool_certification_users} cu
        INNER JOIN {user} u ON u.id = cu.userid
        WHERE u.deleted = 1";
        $ids = $DB->get_fieldset_sql($sql);

        $DB->delete_records_list('tool_certification_users', 'id', $ids);

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019073102, 'tool', 'certification');
    }

    if ($oldversion < 2019091200) {

        // Define index useridcertificationidtimerevoked (not unique) to be added to tool_certification_compltion.
        $table = new xmldb_table('tool_certification_compltion');
        $params = ['userid', 'certificationid', 'timerevoked'];
        $index = new xmldb_index('useridcertificationidtimerevoked', XMLDB_INDEX_NOTUNIQUE, $params);

        // Conditionally launch add index useridcertificationidtimerevoked.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index certificationiduserid (not unique) to be added to tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $index = new xmldb_index('certificationiduserid', XMLDB_INDEX_NOTUNIQUE, ['certificationid', 'userid']);

        // Conditionally launch add index certificationiduserid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index certificationidstatusexpirydate (not unique) to be added to tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $params = ['certificationid', 'status', 'expirydate'];
        $index = new xmldb_index('certificationidstatusexpirydate', XMLDB_INDEX_NOTUNIQUE, $params);

        // Conditionally launch add index certificationidstatusexpirydate.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index idnumbertenantid (not unique) to be added to tool_certification.
        $table = new xmldb_table('tool_certification');
        $index = new xmldb_index('idnumbertenantid', XMLDB_INDEX_NOTUNIQUE, ['idnumber', 'tenantid']);

        // Conditionally launch add index idnumbertenantid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define key fk_tenantid (foreign) to be added to tool_certification.
        $table = new xmldb_table('tool_certification');
        $key = new xmldb_key('fk_tenantid', XMLDB_KEY_FOREIGN, ['tenantid'], 'tool_tenant', ['id']);

        // Launch add key fk_tenantid.
        $dbman->add_key($table, $key);

        // Define key fk_program (foreign) to be added to tool_certification.
        $table = new xmldb_table('tool_certification');
        $key = new xmldb_key('fk_program', XMLDB_KEY_FOREIGN, ['program'], 'tool_program', ['id']);

        // Launch add key fk_program.
        $dbman->add_key($table, $key);

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019091200, 'tool', 'certification');
    }

    if ($oldversion < 2019091700) {

        // Define field autocreategroups to be added to tool_certification.
        $table = new xmldb_table('tool_certification');
        $field = new xmldb_field('autocreategroups', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '-1', 'allocationenddateabsolute');

        // Conditionally launch add field autocreategroups.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019091700, 'tool', 'certification');
    }

    return true;
}

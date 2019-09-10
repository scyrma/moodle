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
 * Upgrade script for tool_program
 *
 * @package   tool_program
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_program_upgrade(int $oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019041001) {
        // Check if tool_dynamicrule is installed.
        if (class_exists('\\tool_dynamicrule\\rules_list')) {
            // We add dynamic rules to dynamic rules tab inside programs.
            $programs = $DB->get_records('tool_program', null, '', 'id, tenantid');
            if (!empty($programs)) {
                foreach ($programs as $program) {
                    $params = [
                        'component' => 'tool_program',
                        'componentarea' => 'program',
                        'itemid' => $program->id
                    ];
                    $rulesexist = $DB->record_exists('tool_dynamicrule', $params);
                    if (!$rulesexist) {
                        // Create default dynamic rules for dynamic rules tab.
                        \tool_program\api::add_default_dynamicrule_conditions_to_program($program->id, $program->tenantid);
                    }
                }
            }
        }
        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019041001, 'tool', 'program');
    }

    if ($oldversion < 2019041500) {

        // Define table tool_program_certs to be dropped.
        $table = new xmldb_table('tool_program_badges');

        // Conditionally launch drop table for tool_program_certs.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Define field awardbadge to be dropped from tool_program.
        $table = new xmldb_table('tool_program');
        $field = new xmldb_field('awardbadge');

        // Conditionally launch drop field awardbadge.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019041500, 'tool', 'program');
    }

    if ($oldversion < 2019041501) {

        // Define table tool_program_compets to be dropped.
        $table = new xmldb_table('tool_program_compets');

        // Conditionally launch drop table for tool_program_certs.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019041501, 'tool', 'program');
    }

    if ($oldversion < 2019041700) {

        // Define table tool_program_compets to be dropped.
        $table = new xmldb_table('tool_program_certs');

        // Conditionally launch drop table for tool_program_certs.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019041700, 'tool', 'program');
    }

    if ($oldversion < 2019050700) {

        // Define field timesuspended to be added to tool_program_users.
        $table = new xmldb_table('tool_program_users');
        $field = new xmldb_field('timesuspended', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');

        // Conditionally launch add field timesuspended.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019050700, 'tool', 'program');
    }

    if ($oldversion < 2019051703) {
        // Check if tool_dynamicrule is installed.
        if (class_exists('\\tool_dynamicrule\\rules_list')) {
            // We add dynamic rules to dynamic rules tab inside programs.
            $programs = $DB->get_records('tool_program', null, '', 'id, tenantid');
            if (!empty($programs)) {
                foreach ($programs as $program) {
                    $params = [
                        'component' => 'tool_program',
                        'componentarea' => 'program',
                        'itemid' => $program->id
                    ];
                    $rules = $DB->get_records('tool_dynamicrule', $params);

                    // Delete any existing rule.
                    foreach ($rules as $rule) {
                        $rule = \tool_dynamicrule\api::get_rule($rule->id, true);
                        $rule->delete();
                    }

                    if (!$program->tenantid) {
                        continue;
                    }

                    // Apply all default rules.
                    \tool_program\api::add_default_dynamicrule_conditions_to_program($program->id, $program->tenantid);
                }
            }
        }
        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019051703, 'tool', 'program');
    }

    if ($oldversion < 2019052801) {
        \tool_tenant\manager::create_workplace_role('tool_program_manager',
                get_string('rolemanager', 'tool_program'),
                get_string('rolemanagerdescription', 'tool_program'),
                ['tool/program:edit', 'tool/program:allocateuser']);

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019052801, 'tool', 'program');
    }

    if ($oldversion < 2019073102) {
        // Remove user allocations for deleted users.
        $sql = "SELECT pu.id
        FROM {tool_program_users} pu
        INNER JOIN {user} u ON u.id = pu.userid
        WHERE u.deleted = 1";
        $ids = $DB->get_fieldset_sql($sql);

        $DB->delete_records_list('tool_program_users', 'id', $ids);

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019073102, 'tool', 'program');
    }

    return true;
}

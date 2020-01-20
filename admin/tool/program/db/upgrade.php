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
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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

    if ($oldversion < 2019091200) {

        // Define index idnumbertenantid (not unique) to be added to tool_program.
        $table = new xmldb_table('tool_program');
        $index = new xmldb_index('idnumbertenantid', XMLDB_INDEX_NOTUNIQUE, ['idnumber', 'tenantid']);

        // Conditionally launch add index idnumbertenantid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index programidparentsortorder (not unique) to be added to tool_program_sets.
        $table = new xmldb_table('tool_program_sets');
        $index = new xmldb_index('programidparentsortorder', XMLDB_INDEX_NOTUNIQUE, ['programid', 'parent', 'sortorder']);

        // Conditionally launch add index programidparentsortorder.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index useridsetid (not unique) to be added to tool_program_set_completion.
        $table = new xmldb_table('tool_program_set_completion');
        $index = new xmldb_index('useridsetid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'setid']);

        // Conditionally launch add index useridsetid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019091200, 'tool', 'program');
    }

    if ($oldversion < 2019091700) {

        // Define field autocreategroups to be added to tool_program.
        $table = new xmldb_table('tool_program');
        $field = new xmldb_field('autocreategroups', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '1', 'allowdirectallocation');

        // Conditionally launch add field autocreategroups.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019091700, 'tool', 'program');
    }

    if ($oldversion < 2019112601) {
        // Remove dynamic rules left from deleted programs.
        $sql = "
            SELECT tdr.id
            FROM {tool_dynamicrule} tdr
            WHERE component = 'tool_program' AND componentarea = 'program'
            AND itemid NOT IN (
                SELECT id FROM {tool_program}
            )
        ";
        $rules = $DB->get_records_sql($sql);
        foreach ($rules as $rule) {
            // TODO Change when WP-1293 is implemented.
            (new \tool_dynamicrule\rule($rule->id))->delete();
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019112601, 'tool', 'program');
    }

    if ($oldversion < 2019122000) {
        // Remove user alocations to non existent programs.
        $DB->delete_records_select('tool_program_users', 'programid NOT IN (SELECT id FROM {tool_program})');

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019122000, 'tool', 'program');
    }

    if ($oldversion < 2019122001) {
        // Check if tool_dynamicrule is installed.
        if (class_exists('\\tool_dynamicrule\\rules_list')) {
            // We add 2 missing dynamic rules to dynamic rules tab inside programs.
            $programs = $DB->get_records('tool_program', null, '', 'id, tenantid');
            if (!empty($programs)) {
                $component = 'tool_program';
                $componentarea = 'program';
                $usernotallocatedstr = get_string('conditionusernotallocated', 'tool_program');
                $programnotcompletedstr = get_string('conditionprogramnotcompleted', 'tool_program');

                foreach ($programs as $program) {
                    $params = [
                        'component' => $component,
                        'componentarea' => $componentarea,
                        'itemid' => $program->id
                    ];
                    // If program has only 4 dynamic rules we need to add the 2 missing rules.
                    if ($DB->count_records('tool_dynamicrule', $params) === 4) {
                        $configdata = ['programid' => $program->id];
                        // Create rule user_not_allocated.
                        $ruleid = \tool_dynamicrule\api::create_rule_for_component($component, $componentarea, $program->id,
                            $program->tenantid, $usernotallocatedstr);
                        $conditionclass = '\\tool_program\\tool_dynamicrule\\condition\\user_not_allocated';
                        // Create condition. No need to verify user tenancy,
                        // we are creating condition for rule that was just created.
                        \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata, true);

                        // Create rule program_not_completed.
                        $ruleid = \tool_dynamicrule\api::create_rule_for_component($component, $componentarea, $program->id,
                            $program->tenantid, $programnotcompletedstr);
                        $conditionclass = '\\tool_program\\tool_dynamicrule\\condition\\program_not_completed';
                        // Create condition. No need to verify user tenancy,
                        // we are creating condition for rule that was just created.
                        \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata, true);
                    }
                }
            }
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2019122001, 'tool', 'program');
    }

    if ($oldversion < 2020010700) {
        // Check if tool_dynamicrule is installed.
        if (class_exists('\\tool_dynamicrule\\rules_list')) {
            // Rename all existing program rules with the correct string.
            $conditionuserallocated = get_string('conditionuserallocated', 'tool_program');
            $conditionprogramcompleted = get_string('conditionprogramcompleted', 'tool_program');
            $conditionprogramoverdue = get_string('conditionprogramoverdue', 'tool_program');
            $conditionprogramsuspended = get_string('conditionprogramsuspended', 'tool_program');
            $programrules = get_string('programrules', 'tool_program');

            $sql = "SELECT drule.id, drcond.classname
                FROM {tool_dynamicrule} drule
                JOIN {tool_dynamicrule_condition} drcond
                ON drule.id = drcond.ruleid
                WHERE drule.name = :programrules AND drule.component = 'tool_program' AND drule.componentarea = 'program'
            ";
            $records = $DB->get_records_sql($sql, ['programrules' => $programrules]);
            foreach ($records as $record) {
                switch ($record->classname) {
                    case 'tool_program\tool_dynamicrule\condition\user_allocated':
                        $DB->set_field('tool_dynamicrule', 'name', $conditionuserallocated, ['id' => $record->id]);
                        break;
                    case 'tool_program\tool_dynamicrule\condition\program_completed':

                        $DB->set_field('tool_dynamicrule', 'name', $conditionprogramcompleted, ['id' => $record->id]);
                        break;
                    case 'tool_program\tool_dynamicrule\condition\program_overdue':
                        $DB->set_field('tool_dynamicrule', 'name', $conditionprogramoverdue, ['id' => $record->id]);
                        break;
                    case 'tool_program\tool_dynamicrule\condition\program_suspended':
                        $DB->set_field('tool_dynamicrule', 'name', $conditionprogramsuspended, ['id' => $record->id]);
                        break;
                    default:
                        break;
                }
            }
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2020010700, 'tool', 'program');
    }

    return true;
}

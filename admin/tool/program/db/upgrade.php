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
 * Upgrade script for tool_program
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Upgrade
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_program_upgrade(int $oldversion) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/program/db/upgradelib.php');

    $dbman = $DB->get_manager();

    if ($oldversion < 2019041001) {
        // Check if tool_dynamicrule is installed.
        if (class_exists(\tool_dynamicrule\api::class)) {
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
        if (class_exists(\tool_dynamicrule\api::class)) {
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
        if (class_exists(\tool_dynamicrule\api::class)) {
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
        if (class_exists(\tool_dynamicrule\api::class)) {
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

    if ($oldversion < 2020030500) {
        // Remove "orphaned" programs and releated data.
        tool_program_upgrade_remove_orphaned_programs();

        // Suspend all user course enrolments from current archived programs.
        tool_program_upgrade_suspend_enrolments_in_archived_programs();

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2020030500, 'tool', 'program');
    }

    if ($oldversion < 2020041500) {
        require_once($CFG->libdir.'/enrollib.php');

        if ($enrolplugin = enrol_get_plugin('program')) {
            // Enable enrol_program plugin globally.
            $enabled = enrol_get_plugins(true);

            if (!isset($enabled['program'])) {
                $enabled['program'] = true;
                $enabled = array_keys($enabled);
                set_config('enrol_plugins_enabled', implode(',', $enabled));
            }

            // Enable enrol instances for active programs.
            $sql = "
                SELECT e.*
                FROM {enrol} e
                JOIN {tool_program} tp
                ON tp.id = e.customint1
                WHERE e.enrol='program' AND e.status = 1 AND tp.archived = 0 AND tp.visible = 1
            ";
            $records = $DB->get_records_sql($sql);
            foreach ($records as $record) {
                $enrolplugin->update_status($record, ENROL_INSTANCE_ENABLED);
            }
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2020041500, 'tool', 'program');
    }

    if ($oldversion < 2020061501) {
        // Modify all program calendar events.
        $sql = "SELECT * FROM {event} WHERE eventtype = 'tool_program1' OR eventtype = 'tool_program2'";
        $events = $DB->get_records_sql($sql);
        foreach ($events as $event) {
            $event->type = CALENDAR_EVENT_TYPE_ACTION;
            $event->component = 'tool_program';
            $event->modulename = '';
            $event->courseid = 0;
            $event->categoryid = 0;
            $DB->update_record('event', $event);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2020061501, 'tool', 'program');
    }

    if ($oldversion < 2020071503) {

        tool_program_upgrade_remove_users_from_orphan_groups();

        // Remove enrolments in courses that belong to non-existing programs (and remove enrolment method too).
        tool_program_remove_enrolments_to_non_existing_programs();

        // Remove program component-specific tenant groups that belong to non-existing program.
        tool_program_remove_program_tenant_groups_to_nonexisting_program();
        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2020071503, 'tool', 'program');
    }

    if ($oldversion < 2020083102) {
        // Schedule ad-hoc task to deallocate users from previous tenant programs.
        $record = new \stdClass();
        $record->classname = '\tool_program\task\deallocate_from_previous_tenant_programs';
        $record->component = 'tool_program';

        // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
        $nextruntime = time() - 1;
        $record->nextruntime = $nextruntime;
        $DB->insert_record('task_adhoc', $record);

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2020083102, 'tool', 'program');
    }

    if ($oldversion < 2020091000) {

        // Define field shared to be added to tool_program.
        $table = new xmldb_table('tool_program');
        $field = new xmldb_field('shared', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'idnumber');

        // Conditionally launch add field shared.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2020091000, 'tool', 'program');
    }

    if ($oldversion < 2020111300) {
        $DB->delete_records('user_preferences', ['name' => 'tool_program_program_sort_filter']);

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2020111300, 'tool', 'program');
    }

    if ($oldversion < 2021011902) {
        // Modify pending program reset pending tasks customdata.
        $params = ['component' => 'tool_program', 'classname' => '\tool_program\task\reset_program'];
        $tasks = $DB->get_records('task_adhoc', $params);
        foreach ($tasks as $record) {
            $customdata = @json_decode($record->customdata, true);
            $programuser = $DB->get_record('tool_program_users', ['id' => $customdata['programuser']]);
            if ($programuser) {
                // Store programid and userid instead of programuser in customdata if programuser is still found.
                $customdata['programid'] = $programuser->programid;
                $customdata['userid'] = $programuser->userid;
                unset($customdata['programuser']);
                $record->customdata = json_encode($customdata);
                $DB->update_record('task_adhoc', $record);
            } else {
                // Delete record because we cannot find out programid and userid anymore.
                $DB->delete_records('task_adhoc', ['id' => $record->id]);
            }
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2021011902, 'tool', 'program');
    }

    if ($oldversion < 2021032300) {
        // Check if site ran the wrong upgrade step (MDL-71156).
        if ($DB->record_exists('upgrade_log', ['version' => '2020110901.09', 'plugin' => 'core'])) {
            // Schedule ad-hoc task to refresh all program calendar events.
            $record = new \stdClass();
            $record->classname = '\tool_program\task\refresh_program_calendar_events';
            $record->component = 'tool_program';

            // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
            $nextruntime = time() - 1;
            $record->nextruntime = $nextruntime;
            $DB->insert_record('task_adhoc', $record);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2021032300, 'tool', 'program');
    }

    return true;
}

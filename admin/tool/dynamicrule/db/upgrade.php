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
 * @package     tool_dynamicrule
 * @category    upgrade
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy <marina@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Execute tool_dynamicrule upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_dynamicrule_upgrade($oldversion) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/dynamicrule/db/upgradelib.php');

    $dbman = $DB->get_manager();

    if ($oldversion < 2019040900) {
        $table = new xmldb_table('tool_dynamicrule');
        $fields = [
            new xmldb_field('component', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'timemodified'),
            new xmldb_field('componentarea', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'component'),
            new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'componentarea')
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Dynamicrule savepoint reached.
        upgrade_plugin_savepoint(true, 2019040900, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2019052400) {

        // Define field negated to be dropped from tool_dynamicrule_condition.
        $table = new xmldb_table('tool_dynamicrule_condition');
        $field = new xmldb_field('negated');

        // Conditionally launch drop field negated.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Dynamicrule savepoint reached.
        upgrade_plugin_savepoint(true, 2019052400, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2019052801) {

        // Create default role "Dynamic rules manager".
        \tool_tenant\manager::create_workplace_role('tool_dynamicrule_manager',
                get_string('rolemanager', 'tool_dynamicrule'),
                get_string('rolemanagerdescription', 'tool_dynamicrule'),
                ['tool/dynamicrule:manage']);

        // Dynamicrule savepoint reached.
        upgrade_plugin_savepoint(true, 2019052801, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2019060100) {
        // Delete "orphaned" rules created before WP-690.
        $componentrules = $DB->get_records_sql('SELECT * FROM {tool_dynamicrule} WHERE component IS NOT NULL');
        foreach ($componentrules as $componentrule) {
            if ($componentrule->component === 'tool_program') {
                $class = '\\tool_program\\persistent\\program';
            } else if ($componentrule->component === 'tool_certification') {
                $class = '\\tool_certification\\certification';
            }
            try {
                $component = new $class($componentrule->itemid);
                if ($component->get('tenantid') !== $componentrule->tenantid) {
                    // Orphaned rule (tenant id is not matching). Delete it.
                    $rule = new \tool_dynamicrule\rule(0, $componentrule);
                    $rule->delete();
                }
            } catch (Exception $e) {
                // Orphaned rule (item id does not exist). Delete it.
                $rule = new \tool_dynamicrule\rule(0, $componentrule);
                $rule->delete();
                continue;
            }
        }
        upgrade_plugin_savepoint(true, 2019060100, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2019072900) {
        // Delete "orphaned" conditions following current_date removal in WP-636.
        $classname = 'tool_dynamicrule\tool_dynamicrule\condition\current_date';
        $DB->delete_records('tool_dynamicrule_condition', ['classname' => $classname]);

        upgrade_plugin_savepoint(true, 2019072900, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2020030600) {
        // Remove orphaned rules that belong to tenants which no longer exists.
        tool_dynamicrule_upgrade_remove_tenant_orphaned_rules();

        upgrade_plugin_savepoint(true, 2020030600, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2020040800) {
        // Define field broken to be dropped from tool_dynamicrule.
        $table = new xmldb_table('tool_dynamicrule');

        // Conditionally drop index.
        $index = new xmldb_index('enarbr', XMLDB_INDEX_NOTUNIQUE, array('enabled', 'archived', 'broken'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Conditionally drop field broken.
        $field = new xmldb_field('broken');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Re-adding index.
        $index = new xmldb_index('enabledarchived', XMLDB_INDEX_NOTUNIQUE, array('enabled', 'archived'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2020040800, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2020080601) {
        // Update tool_certificate:certificate outcomes to tool_dynamicrule:certificate.
        $results = $DB->get_records('tool_dynamicrule_outcome',
            ['classname' => 'tool_certificate\tool_dynamicrule\outcome\certificate']);
        foreach ($results as $result) {
            $result->classname = 'tool_dynamicrule\tool_dynamicrule\outcome\certificate';
            $configdata = @json_decode($result->configdata, true);
            $configdata['instanceclass'] = 'tool_dynamicrule:certificate';
            $result->configdata = json_encode($configdata);
            $DB->update_record('tool_dynamicrule_outcome', $result);
        }

        upgrade_plugin_savepoint(true, 2020080601, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2020102100) {
        // Define fields status and errordata to be added to tool_dynamicrule_match.
        $table = new xmldb_table('tool_dynamicrule_match');

        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'unmatchedtime');
        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('errordata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'status');
        // Conditionally launch add field errordata.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set status to "done" for all existing matching records.
        $DB->set_field('tool_dynamicrule_match', 'status', 1);

        upgrade_plugin_savepoint(true, 2020102100, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2020111300) {
        // Update tool_dynamicrule:notification outcomes with sendto['matching'] enabled.
        $results = $DB->get_records('tool_dynamicrule_outcome',
            ['classname' => 'tool_dynamicrule\tool_dynamicrule\outcome\notification']);
        foreach ($results as $result) {
            $configdata = @json_decode($result->configdata, true);
            $configdata['sendto'] = ['matching' => 1, 'dptlead' => 0, 'manager' => 0];
            $result->configdata = json_encode($configdata);
            $DB->update_record('tool_dynamicrule_outcome', $result);
        }

        upgrade_plugin_savepoint(true, 2020111300, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2021011100) {
        // We need to correct any course completed conditions without a time value to match "any time".
        $conditions = $DB->get_records('tool_dynamicrule_condition',
            ['classname' => 'tool_dynamicrule\tool_dynamicrule\condition\course_completed']);
        foreach ($conditions as $condition) {
            $configdata = @json_decode($condition->configdata, true);
            if (empty($configdata['timecompleted'])) {
                $configdata['operator'] = 'any';

                $DB->set_field('tool_dynamicrule_condition', 'configdata', json_encode($configdata), ['id' => $condition->id]);
            }
        }

        upgrade_plugin_savepoint(true, 2021011100, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2021110800) {

        // Define field shared to be added to tool_dynamicrule.
        $table = new xmldb_table('tool_dynamicrule');
        $field = new xmldb_field('shared', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'itemid');

        // Conditionally launch add field shared.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Update shared field for existing shared tenant rules (shared component rules).
        if ($sharedid = get_config('', 'tool_tenant_shared_tenant_id')) {
            $DB->set_field_select('tool_dynamicrule', 'shared', 1, 'tenantid = ?', [$sharedid]);
        }

        upgrade_plugin_savepoint(true, 2021110800, 'tool', 'dynamicrule');
    }

    if ($oldversion < 2022031502) {

        // Schedule ad-hoc task to update current custom user profile field conditions.
        $record = new \stdClass();
        $record->classname = '\tool_dynamicrule\task\update_user_profile_fields';
        $record->component = 'tool_dynamicrule';
        // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
        $nextruntime = time() - 1;
        $record->nextruntime = $nextruntime;
        $DB->insert_record('task_adhoc', $record);

        upgrade_plugin_savepoint(true, 2022031502, 'tool', 'dynamicrule');
    }

    return true;
}

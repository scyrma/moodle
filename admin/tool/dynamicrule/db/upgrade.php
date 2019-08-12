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
 * @package     tool_dynamicrule
 * @category    upgrade
 * @copyright   2018 Marina Glancy <marina@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute tool_dynamicrule upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_dynamicrule_upgrade($oldversion) {
    global $DB;

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

    return true;
}

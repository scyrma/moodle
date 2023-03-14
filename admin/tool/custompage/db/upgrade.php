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
 * Plugin upgrade steps
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Method to perform upgrade steps between versions
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_custompage_upgrade(int $oldversion): bool {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/custompage/db/upgradelib.php');

    $dbman = $DB->get_manager();

    if ($oldversion < 2022052300) {

        $table = new xmldb_table('tool_custompage');

        // Define field tenantscope to be dropped from tool_custompage.
        $field = new xmldb_field('tenantscope');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field global to be added to tool_custompage.
        $field = new xmldb_field('global', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'tenantid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Custompage savepoint reached.
        upgrade_plugin_savepoint(true, 2022052300, 'tool', 'custompage');
    }

    if ($oldversion < 2022061300) {

        // Define table tool_custompage_audience to be created.
        $table = new xmldb_table('tool_custompage_audience');

        // Adding fields to table tool_custompage_audience.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('pageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('classname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('configdata', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tool_custompage_audience.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('pageid', XMLDB_KEY_FOREIGN, ['pageid'], 'tool_custompage', ['id']);
        $table->add_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for tool_custompage_audience.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Custompage savepoint reached.
        upgrade_plugin_savepoint(true, 2022061300, 'tool', 'custompage');
    }

    if ($oldversion < 2022061302) {
        // We need to create the My teams page as a global page with audience "Manager"
        // available to both Managers and Department leads and with one myteams block.
        tool_custompage_create_myteams_page();

        // Custompage savepoint reached.
        upgrade_plugin_savepoint(true, 2022061302, 'tool', 'custompage');
    }

    return true;
}

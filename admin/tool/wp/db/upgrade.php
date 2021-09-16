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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Upgrade script for tool_wp
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
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
function xmldb_tool_wp_upgrade(int $oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019090002) {

        // Define table tool_wp_course_reset to be created.
        $table = new xmldb_table('tool_wp_course_reset');

        // Adding fields to table tool_wp_course_reset.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('certificationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reason', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timerequested', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userrequested', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('wascompleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('resetstatus', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('resetinfo', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tool_wp_course_reset.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for tool_wp_course_reset.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2019090002, 'tool', 'wp');
    }

    if ($oldversion < 2019090003) {

        // Changing precision of field grade on table tool_wp_course_reset to (10, 5).
        $table = new xmldb_table('tool_wp_course_reset');
        $field = new xmldb_field('grade', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0', 'wascompleted');

        // Launch change of precision for field grade.
        $dbman->change_field_precision($table, $field);

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2019090003, 'tool', 'wp');
    }

    if ($oldversion < 2020012702) {
        // Update mobile config to point to Workplace app.
        $settings = [
            ['plugin' => 'tool_mobile', 'name' => 'enablesmartappbanners', 'value' => 1, 'default' => 0],
            ['plugin' => 'tool_mobile', 'name' => 'iosappid', 'value' => '1470929705', 'default' => '633359593'],
            ['plugin' => 'tool_mobile', 'name' => 'androidappid', 'value' => 'com.moodle.workplace',
                'default' => 'com.moodle.moodlemobile'],
            ['plugin' => 'tool_mobile', 'name' => 'setuplink', 'value' => 'https://download.moodle.org/mobile',
                'default' => ''],
            ['name' => 'airnotifiermobileappname', 'value' => 'com.moodle.workplace', 'default' => 'com.moodle.moodlemobile'],
            ['name' => 'airnotifierappname', 'value' => 'commoodleworkplace', 'default' => 'commoodlemoodlemobile'],
        ];

        foreach ($settings as $setting) {
            $plugin = $setting['plugin'] ?? null;

            // Compare current value to default value, if they match then set our own.
            $currentvalue = get_config($plugin, $setting['name']);
            if (empty($currentvalue) || (strcasecmp($currentvalue, $setting['default']) === 0)) {
                set_config($setting['name'], $setting['value'], $plugin);
            }
        }

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2020012702, 'tool', 'wp');
    }

    if ($oldversion < 2020021706) {

        // In this version we added an event listener for the "langpack_imported" event that changes the
        // lang 'fr'->'fr_wp' when the later is installed.
        // We need to make an upgrade script that fixes existing languages in the database.
        // (Workplace modifies core to hide such parent languages from the language menu).
        $langs = get_string_manager()->get_list_of_translations();
        foreach ($langs as $langcode => $langname) {
            if (preg_match('/_wp$/', $langcode) && ($parentlang = get_parent_language($langcode))
                && !array_key_exists($parentlang, $langs)) {
                $DB->execute('UPDATE {course} SET lang=? WHERE lang=?', [$langcode, $parentlang]);
                $DB->execute('UPDATE {user} SET lang=? WHERE lang=?', [$langcode, $parentlang]);
                if ($CFG->lang === $parentlang) {
                    set_config('lang', $langcode);
                }
            }
        }

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2020021706, 'tool', 'wp');
    }

    if ($oldversion < 2020061900) {
        // Disable donation banner.
        set_config('showcampaigncontent', 0);
        // Disable "user feedback" banner.
        set_config('enableuserfeedback', 0);
        // Disable Moodle.net integration.
        set_config('enablemoodlenet', 0, 'tool_moodlenet');

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2020061900, 'tool', 'wp');
    }

    if ($oldversion < 2020061901) {
        // Require to agree to workplace license.
        set_config('wplicensepending', 1);

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2020061901, 'tool', 'wp');
    }

    if ($oldversion < 2020062200) {

        // Define table tool_wp_export to be created.
        $table = new xmldb_table('tool_wp_export');

        // Adding fields to table tool_wp_export.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('tenantid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('exporter', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('entrypoint', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('entrypointid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('configdata', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table tool_wp_export.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
        $table->add_key('tenantid', XMLDB_KEY_FOREIGN, ['tenantid'], 'tool_tenant', ['id']);

        // Conditionally launch create table for tool_wp_export.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table tool_wp_import to be created.
        $table = new xmldb_table('tool_wp_import');

        // Adding fields to table tool_wp_import.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('tenantid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('importer', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('entrypoint', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('entrypointid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('configdata', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table tool_wp_import.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
        $table->add_key('tenantid', XMLDB_KEY_FOREIGN, ['tenantid'], 'tool_tenant', ['id']);

        // Conditionally launch create table for tool_wp_import.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table tool_wp_import_details to be created.
        $table = new xmldb_table('tool_wp_import_details');

        // Adding fields to table tool_wp_import_details.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('importid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('importer', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('mapper', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('conflict', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table tool_wp_import_details.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('importid', XMLDB_KEY_FOREIGN, ['importid'], 'tool_wp_import', ['id']);

        // Conditionally launch create table for tool_wp_import_details.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define field reviewdata to be added to tool_wp_export.
        $table = new xmldb_table('tool_wp_export');
        $field = new xmldb_field('reviewdata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'configdata');

        // Conditionally launch add field reviewdata.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field reviewdata to be added to tool_wp_import.
        $table = new xmldb_table('tool_wp_import');
        $field = new xmldb_field('reviewdata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'configdata');

        // Conditionally launch add field reviewdata.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2020062200, 'tool', 'wp');
    }

    if ($oldversion < 2020070200) {
        // Update mobile URL scheme to point at Workplace-specific value.
        $currentvalue = get_config('tool_mobile', 'forcedurlscheme');
        if (empty($currentvalue) || (strcasecmp($currentvalue, 'moodlemobile') === 0)) {
            set_config('forcedurlscheme', 'mmworkplace', 'tool_mobile');
        }

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2020070200, 'tool', 'wp');
    }

    if ($oldversion < 2020091600) {
        // Hide block 'recentlyaccessedcourses'.
        $DB->execute('UPDATE {block} set visible=? WHERE name=?', [0, 'recentlyaccessedcourses']);
        // Initially we also hid the 'myoverview' block here. For users who run this upgrade step after it was fixed,
        // we set this variable so we don't need to revert it in the next step.
        $donotrestoremyoverviewblock = true;

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2020091600, 'tool', 'wp');
    }

    if ($oldversion < 2020092200) {
        // Restore visibility of 'myoverview' block but only if it was hidden in the previous upgrade script.
        // These two scripts were within the same build cycle so they only affect dev installations and demo sites.
        if (empty($donotrestoremyoverviewblock)) {
            $DB->execute('UPDATE {block} set visible=? WHERE name=?', [1, 'myoverview']);
        }

        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2020092200, 'tool', 'wp');
    }

    if ($oldversion < 2020101300) {
        $adminrole = $DB->get_field('role', 'id', ['shortname' => 'tool_tenant_admin']);
        $syscontext = context_system::instance();
        $rolesmanage = get_roles_with_capability('tool/wp:useexportimport', CAP_ALLOW);
        if (array_key_exists($adminrole, $rolesmanage)) {
            assign_capability('tool/wp:manageexportimport', CAP_ALLOW, $adminrole, $syscontext);
        }
        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2020101300, 'tool', 'wp');
    }
    if ($oldversion < 2021022201) {
        // Set the state to production for existing installations.
        set_config('workplaceproductionstate', 1);
        // Wp savepoint reached.
        upgrade_plugin_savepoint(true, 2021022201, 'tool', 'wp');
    }
    return true;
}

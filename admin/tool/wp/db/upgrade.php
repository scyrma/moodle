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

    return true;
}

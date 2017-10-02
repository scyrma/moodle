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

defined('MOODLE_INTERNAL') || die();

function xmldb_local_moodlecloud_upgrade($oldversion) {
    global $CFG, $DB;

    // Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2017051700) {

        // Force replace the instances of the old course_overview by the new dashboard block of the same name
        $DB->set_field('block_instances', 'blockname', 'myoverview', array('blockname' => 'course_overview'));

        upgrade_plugin_savepoint(true, 2017051700, 'local', 'moodlecloud');
    }

    if ($oldversion < 2017051701) {

        if (isset($CFG->moodlecloud_blocked_plugins)) {
            $CFG->mc_force_plugin_uninstall = true;
            $progress = new null_progress_trace();
            $manager = core_plugin_manager::instance();
            foreach ($CFG->moodlecloud_blocked_plugins as $k => $mcblocked) {
                if (!$mcblocked) {
                    continue;
                }

                $plugin = str_replace('/', '_', $k);
                $manager->uninstall_plugin($plugin, $progress);
                $manager->reset_caches();
                set_config('allversionshash', core_component::get_all_versions_hash());
            }
        }

        upgrade_plugin_savepoint(true, 2017051701, 'local', 'moodlecloud');
    }

    if ($oldversion < 2017092400) {
        $table = new xmldb_table('moodlecloud_touchpoints');

        //name, type, precision, unsigned, notnull, sequence, default, previous
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('created', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 'pending', null);
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if(!$DB->get_manager()->table_exists($table)) {
            $DB->get_manager()->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2017092400, 'local', 'moodlecloud');
    }

    return true;
}

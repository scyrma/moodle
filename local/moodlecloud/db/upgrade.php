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

                $plugin = str_replace_one('/', '_', $k);
                $manager->uninstall_plugin($plugin, $progress);
                $manager->reset_caches();
                set_config('allversionshash', core_component::get_all_versions_hash());
            }
        }

        upgrade_plugin_savepoint(true, 2017051701, 'local', 'moodlecloud');
    }

    return true;
}

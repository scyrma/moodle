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

    if ($oldversion < 2018030600) {
        $table = new xmldb_table('moodlecloud_notifications');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('created', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null, null, null);
        $table->add_field('level', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null);
        $table->add_field('source', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('category', XMLDB_TYPE_CHAR, '255', null, null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if(!$DB->get_manager()->table_exists($table)) {
            $DB->get_manager()->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2018030600, 'local', 'moodlecloud');
    }

    if ($oldversion < 2018030601) {
        array_map(function($touchpointname) {
            set_config(
                $touchpointname,
                get_config('moodlecloudnotifications', $touchpointname) == '0' ? 'sitenotifications' : 'emails,sitenotifications',
                'moodlecloudnotifications'

            );
        },
            [
                'touchpoints_send_user_limit_warning',
                'touchpoints_send_user_limit_reached',
                'touchpoints_send_file_storage_limit_warning',
                'touchpoints_send_file_storage_limit_reached'
            ]
        );

        upgrade_plugin_savepoint(true, 2018030601, 'local', 'moodlecloud');
    }

    if ($oldversion < 2019072200) {
        // MC-1333 - adding new fields for site registration
        $huburl = HUB_MOODLEORGHUBURL;
        $cleanhuburl = clean_param($huburl, PARAM_ALPHANUMEXT);
        $site = get_site();

        $newfields = ['contactphone', 'imageurl', 'street', 'regioncode', 'countrycode', 'geolocation'];

        foreach ($newfields as $field) {
            $fullfieldname = 'site_' . $field . '_' . $cleanhuburl;
            $fieldexists = get_config('core', $fullfieldname);

            if ($fieldexists === false) {
                set_config($fullfieldname, '');
            }
        }

        // MC-1345 - saving custom CSS
        $themes = ['moodlecloud', 'school'];

        foreach ($themes as $theme) {
            $themecomponent = 'theme_' . $theme;
            $customcss = get_config($themecomponent, 'customcss');
            set_config('customcss_old', $customcss, $themecomponent);
            set_config('customcss', '', $themecomponent);
        }

        upgrade_plugin_savepoint(true, 2019072200, 'local', 'moodlecloud');
    }

    if ($oldversion < 2019112600) {
        $attotoolbarconfig = get_config('editor_atto', 'toolbar');

        // Convert the config string in to a 2D array.
        // First index is the line number, second index is the string position in the CSVs (equals sign gets no special treatment).
        // e.g., for a config of:
        //     collapse = collapse
        //     style1 = title, bold, italic
        // $configasarray[1][0] == 'style1 = title'
        // $configasarray[1][1]  == 'bold'
        $configasarray = array_map(
            function(string $line) : array {
                return array_map('trim', explode(',', $line));
            },
            explode("\n", $attotoolbarconfig)
        );

        // Inserts the value "recordrtc" as the 3rd element of a CSV (which is the defualt position).
        // If "recordrtc" is already present in the list, it's left untouched.
        $addrecordrtc = function(array $buttons) : array {
            return array_merge(
                array_slice($buttons, 0, 2),
                in_array("recordrtc", $buttons) ? [] : ["recordrtc"],
                array_slice($buttons, 2)
            );
        };

        $removerecordrtc = function(array $buttons) : array {
            return array_filter(
                $buttons,
                function(string $button) : bool {
                    return $button != 'recordrtc';
                }
            );
        };

        // Adds "recordrtc" to the line beginning with "files", removes "recordrtc" from any other line
        // and converts the 2D array back in to a string.
        $fixedconfigasstring = implode(
            "\n",
            array_map(function(array $line) use ($addrecordrtc, $removerecordrtc) : string {
                return implode(", ", substr($line[0], 0, 5) == 'files' ? $addrecordrtc($line) : $removerecordrtc($line));
            }, $configasarray)
        );

        set_config('toolbar', $fixedconfigasstring, 'editor_atto');
    }

    if ($oldversion < 2020040600) {
        $attotoolbarconfig = get_config('editor_atto', 'toolbar');

        // Convert the config string in to a 2D array.
        // First index is the line number, second index is the string position in the CSVs (equals sign gets no special treatment).
        // e.g., for a config of:
        //     collapse = collapse
        //     style1 = title, bold, italic
        // $configasarray[1][0] == 'style1 = title'
        // $configasarray[1][1]  == 'bold'
        $configasarray = array_map(
            function(string $line) : array {
                return array_map('trim', explode(',', $line));
            },
            explode("\n", $attotoolbarconfig)
        );

        // Inserts the value "h5p" as the 5th element of a CSV (which is the defualt position).
        // If "h5p" is already present in the list, it's left untouched.
        $addh5p = function(array $buttons) : array {
            return array_merge(
                array_slice($buttons, 0, 4),
                in_array("h5p", $buttons) ? [] : ["h5p"],
                array_slice($buttons, 4)
            );
        };

        $removeh5p = function(array $buttons) : array {
            return array_filter(
                $buttons,
                function(string $button) : bool {
                    return $button != 'h5p';
                }
            );
        };

        // Adds "h5p" to the line beginning with "files", removes "h5p" from any other line
        // and converts the 2D array back in to a string.
        $fixedconfigasstring = implode(
            "\n",
            array_map(function(array $line) use ($addh5p, $removeh5p) : string {
                return implode(", ", substr($line[0], 0, 5) == 'files' ? $addh5p($line) : $removeh5p($line));
            }, $configasarray)
        );

        set_config('toolbar', $fixedconfigasstring, 'editor_atto');
    }

    return true;
}

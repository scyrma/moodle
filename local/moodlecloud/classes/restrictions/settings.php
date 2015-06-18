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
 * Utility functions for Moodle cloud core hacks.
 *
 * This could be in a local_plugin, but since its mostly used for hacking core code
 * and needs to be guaranteed to be installed (otherwise would need class_exists() everywhere)
 * thus it makes sense to have it hacked here in core.
 *
 * @copyright  2015 Dan Poltawski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_moodlecloud\restrictions;

defined('MOODLE_INTERNAL') || die();

class settings {
    /**
     * Nasty hack called by lib/setup.php to adjust $CFG values related to
     * cloud settings before handed onto Moodle scripts.
     * TODO: More testing if this twiddling is sufficient for all cases.
     *
     * @return void
     */
    public static function fiddle_config_settings() {
        global $CFG;

        if (during_initial_install()) {
            // Do not restrict during the initial install.
            return;
        }

        if (userquota::site_is_over_user_quota(false)) {
            // Prevent registration without hacking the code everywhere..
            $CFG->registerauth = '';
            $CFG->config_php_settings['registerauth'] = '';
        }

        // Always ensure that auth includes the correct auth.
        if (strpos($CFG->auth, 'moodlecloud') === false) {
            if (strlen($CFG->auth)) {
                $CFG->auth = 'moodlecloud,' . $CFG->auth;
            } else {
                $CFG->auth = 'moodlecloud,email';
            }
        }
    }
}

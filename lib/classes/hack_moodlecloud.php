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

defined('MOODLE_INTERNAL') || die();
define('QUOTA_MAX', 5);

class core_hack_moodlecloud {
    /**
     * Is the site over their user quota?
     *
     * @return bool true if site is over user quota.
     */
    public static function site_is_over_user_quota() {

        if (empty(self::number_of_user_slots_remaining())) {
            return true;
        }
        return false;
    }

    /**
     * Get the number of user slots remaining in the sites quota.
     *
     * @return int the number of user slots which are remaining. Returns 0
     *              in any over-quota case.
     */
    public static function number_of_user_slots_remaining() {
        global $DB;

        // TODO: The internal details of how to determine the sites quota remaining
        // would go here. It would be cached so that this is super cheap to compute.
        $usercount = $DB->count_records('user', array('deleted' => 0));

        // Don't return negative number if over quota.
        return max(QUOTA_MAX - $usercount, 0);
    }

    /**
     * Nasty hack called by lib/setup.php to adjust $CFG values related to
     * cloud settings before handed onto Moodle scripts.
     * TODO: More testing if this twiddling is sufficient for all cases.
     *
     * @return void
     */
    public static function fiddle_config_settings() {
        global $CFG;

        if (self::site_is_over_user_quota()) {
            // Prevent registration without hacking the code everywhere..
            $CFG->registerauth = '';
            $CFG->config_php_settings['registerauth'] = '';
        }
    }

    /**
     * Called by has_capability to determine if a capability should be prevented
     * due to going above quota based restrictions.
     * NOTE: THIS FUNCTION MUST BE SUPER SUPER CHEAP, its called by has_capability().
     *
     * @param string $capability the name of the capability to check. For example moodle/user:create
     * @return boolean true if the capability is restricted by quota limits.
     */
    public static function capability_is_restricted_by_quota($capability) {
        // AWOOOGA AWOOOGA AWOOOGA!!!!!!
        // IMPORTANT: this function must be CHEAP CHEAP CHEAP to call multiple times.
        // AWOOOGA AWOOOGA AWOOOGA!!!!!!
        static $restrictedcaps = null;
        if ($restrictedcaps === null) {
            // Only runs once per request, but still. KEEP THIS CHEAP.
            $restrictedcaps = array();

            if (self::site_is_over_user_quota()) {
                $restrictedcaps['moodle/user:create'] = true;
            }
        }

        // Now really, if you make this slow, Moodle will cry.
        return isset($restrictedcaps[$capability]);
    }
}

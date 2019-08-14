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

class userquota {

    /**
     * Determine whether a user quota is enforced.
     *
     * @return bool
     */
    public static function is_user_quota_enforced() {
        static $enforced = null;

        if (null === $enforced) {
            if (!defined('MOODLECLOUD_USER_QUOTA')) {
                $enforced = false;
            }
            else if (during_initial_install()) {
                // Do not restrict during the initial install.
                $enforced = false;
            }
            else {
                $enforced = true;
            }
        }

        return $enforced;
    }

    /**
     * Does this site have unlimited quota?
     *
     * @return bool true if site has unlimited quota.
     */
    public static function site_has_unlimited_quota() {
        return defined('MOODLECLOUD_USER_QUOTA') && MOODLECLOUD_USER_QUOTA == 0;
    }

    /**
     * Is the site over its user quota?
     *
     * @param bool $exception Whether to throw an exception on over quota.
     * @return bool true if site is over user quota.
     */
    public static function site_is_over_user_quota($exception = true) {
        if (!self::is_user_quota_enforced()) {
            // Site user quota is not enforced.
            return false;
        }

        if (empty(self::number_of_user_slots_remaining())) {
            // This site has now hit its user quota.
            if ($exception) {
                global $dynamicsite;
                $a = new \stdClass;
                $a->quota = MOODLECLOUD_USER_QUOTA;
                $a->sitename = $dynamicsite;
                throw new \moodle_exception('userquotahit', 'local_moodlecloud', '', $a);
            } else {
                return true;
            }
        }

        // This site has not yet hit its user quota.
        return false;
    }

    /**
     * Get the number of user slots remaining in the sites quota.
     *
     * @return mixed The number of user slots which are remaining.
     *               Returns 0 in any over-quota case.
     *               Returns null if quota constraints are not enabled.
     */
    public static function number_of_user_slots_remaining() {
        global $DB;

        if (!self::is_user_quota_enforced() || self::site_has_unlimited_quota()) {
            // Site user quota is not enforced.
            return null;
        }

        // Don't return negative number if over quota.
        return max(MOODLECLOUD_USER_QUOTA - self::get_user_count(), 0);
    }

    public static function get_user_count() {
        global $DB;
        return $DB->count_records_select('user', 'deleted = ? AND username <> ?', array(0, 'guest'));
    }
}

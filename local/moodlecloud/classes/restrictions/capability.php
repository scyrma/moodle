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

class capability {

    /**
     * Called by has_capability to determine if a capability should be prevented
     * due to going above quota based restrictions.
     *
     * NOTE: THIS FUNCTION MUST BE SUPER SUPER CHEAP, its called by has_capability().
     *
     * @param string $capability the name of the capability to check. For example moodle/user:create
     * @return boolean true if the capability is restricted by quota limits.
     */
    public static function capability_is_restricted_by_quota($capability) {
        // AWOOOGA AWOOOGA AWOOOGA!!!!!!
        // IMPORTANT: this function must be CHEAP CHEAP CHEAP to call multiple times.
        // AWOOOGA AWOOOGA AWOOOGA!!!!!!

        if ($capability === 'moodle/user:create') {
            if (!userquota::is_user_quota_enforced()) {
                // Site user quota is not enforced.
                return false;
            }

            static $userisrestricted = null;
            if (null === $userisrestricted) {
                $userisrestricted = userquota::site_is_over_user_quota(false);
            }

            return $userisrestricted;
        }


        // This capability is not restricted.
        return false;
    }
}

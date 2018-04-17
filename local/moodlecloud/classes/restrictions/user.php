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

class user {
    /**
     * Is the user restricted by the moodlecloud setup.
     *
     * Note this function does 1 db query on first use.
     * @param int $userid id of user record being checked
     * @return boolean true if the user is restricted.
     */
    public static function user_is_restricted($userid) {
        global $DB;

        static $restrictedusers = null;
        if ($restrictedusers === null) {
            // For flexibility its probably best that we work out the list of
            // users here, since they are going to be small and we can't guarantee the
            // calling code will ->auth.
            $restrictedusers = $DB->get_records('user', array('auth' => 'moodlecloud'), 'id', 'id, username');
        }

        return isset($restrictedusers[$userid]);
    }
}

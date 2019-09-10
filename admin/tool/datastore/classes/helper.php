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
 * Class containing helper methods
 *
 * @package     tool_datastore
 * @copyright   2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_datastore;

use completion_completion;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class
 *
 * @package     tool_datastore
 * @copyright   2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Create course completion instance for storage in datastore
     *
     * @param int $userid
     * @param int $courseid
     * @param int $timecomplete
     */
    public static function add_course_completion(int $userid, int $courseid, int $timecomplete) {
        (new completion_completion(['userid' => $userid, 'course' => $courseid]))->mark_complete($timecomplete);
    }
}

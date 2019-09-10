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
 * Class containing the course completed event action.
 *
 * @package   tool_datastore
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_datastore\action;

use tool_datastore\api;

defined('MOODLE_INTERNAL') || die;

/**
 * Class containing the course completed event action.
 *
 * @package   tool_datastore
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_completed extends base {

    /**
     * The entities related to the event which will be stored.
     *
     * @return array
     */
    protected function related_entities(): array {
        return array(
            'courseid' => 'course',
            'userid'   => 'user',
            'relateduserid' => 'user',
            'objectid' => 'course_completion',
        );
    }

    /**
     * The fields for each entity that will be stored in indexed fields table.
     *
     * @return array
     */
    public static function get_fields_to_index() : array {
        return array(
            'course' => api::get_entity_fields('course'),
            'user' => api::get_entity_fields('user'),
            'course_completion' => 'timeenrolled,timestarted,timecompleted,reaggregate',
        );
    }
}
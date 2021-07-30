<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class containing the badge awarded event action.
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_datastore\local\action;

use tool_datastore\api;

defined('MOODLE_INTERNAL') || die;

/**
 * Class containing the badge awarded event action.
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class badge_awarded extends base {

    /**
     * The entities related to the event which will be stored.
     *
     * @return array
     */
    protected function related_entities(): array {
        return array(
            'courseid' => 'course',
            'userid'   => 'user',
            'relateduserid'   => 'user',
            'objectid' => 'badge'
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
            'user'   => api::get_entity_fields('user'),
            'badge'  => ['name', 'description'], // TODO: WP-1073 store more fields.
        );
    }
}

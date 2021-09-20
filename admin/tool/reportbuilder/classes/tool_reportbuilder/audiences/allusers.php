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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * This file contains the backend class for All users audience type
 *
 * @package    tool_reportbuilder
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\audiences;

use context_system;
use MoodleQuickForm;
use tool_reportbuilder\audience_base;

/**
 * The backend class for All users audience type
 *
 * @package    tool_reportbuilder
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class allusers extends audience_base {

    /**
     * Adds audience's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $mform->addElement('static', 'allusersdesc', get_string('allusersdesc', 'tool_reportbuilder'));

    }

    /**
     * Helps to build SQL to retrieve users that matches the current report audience
     *
     * @param string $usertablealias
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(string $usertablealias): array {
        return ['', "$usertablealias.suspended = 0 AND $usertablealias.deleted = 0", []];
    }

    /**
     * Returns the title of the audience
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('allusers', 'tool_reportbuilder');
    }

    /**
     * Return the description for the audience.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('allusers', 'tool_reportbuilder');
    }

    /**
     * If the current user is able to add this audience.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return \tool_tenant\permission::can_browse_users() &&
            has_capability('moodle/user:viewalldetails', context_system::instance());
    }

    /**
     * If the current user is able to edit this audience.
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        return \tool_tenant\permission::can_browse_users() &&
            has_capability('moodle/user:viewalldetails', context_system::instance());
    }
}

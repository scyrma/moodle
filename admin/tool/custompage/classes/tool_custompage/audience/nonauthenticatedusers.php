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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_custompage\tool_custompage\audience;

use context_system;
use tool_custompage\local\audience\base;
use MoodleQuickForm;

/**
 * Non-authenticated user audience type
 *
 * @package     tool_custompage
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class nonauthenticatedusers extends base {
    /**
     * Adds audience's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $mform->addElement('static', 'nonauthenticatedusers', get_string('nonauthenticatedusersdesc', 'tool_custompage'));
    }

    /**
     * Helps to build SQL to retrieve users that matches the current report audience
     *
     * @param string $usertablealias
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(string $usertablealias): array {
        // Don't return any users to show on the reports.
        return ['', "1=0", []];
    }

    /**
     * Checking if the non-authenticated users or guests can access a given page.
     *
     * For non-authenticated users the {@see self::get_sql()} will not work because
     * the user record is not present. Instead this method is called.
     */
    public function allow_non_authenticated_users_access(): bool {
        return true;
    }

    /**
     * Return user friendly name of this audience type
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('nonauthenticatedusers', 'tool_custompage');
    }

    /**
     * Return the description for the audience.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('nonauthenticatedusersdesc', 'tool_custompage');
    }

    /**
     * If the current user is able to add this audience.
     *
     * @param bool $global True if current page is global, otherwise false
     * @return bool
     */
    public function user_can_add(bool $global = false): bool {
        return has_capability('moodle/user:viewalldetails', context_system::instance());
    }

    /**
     * If the current user is able to edit this audience.
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        return has_capability('moodle/user:viewalldetails', context_system::instance());
    }
}

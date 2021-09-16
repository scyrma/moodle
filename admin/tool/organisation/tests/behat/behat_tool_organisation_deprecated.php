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
 * Steps definitions for deprecated plugin Behat steps
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/tests/behat/behat_deprecated.php');

/**
 * Class containing deprecated plugin steps
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_organisation_deprecated extends behat_deprecated {

    /**
     * Create a simple org structure that makes one user a manager over others
     *
     * @Given user :manager has a global manager position over users :users with permissions :permission
     *
     * @param string $manager
     * @param string $users
     * @param int $permission
     *
     * @deprecated Please use {@see behat_tool_organisation::user_has_a_manager_position_over_users_with_permissions}
     */
    public function user_has_a_global_manager_position_over_users_with_permissions($manager, $users, $permission) {
        $this->deprecated_message(['behat_tool_organisation::user_has_a_manager_position_over_users_with_permissions']);

        $this->execute('behat_tool_organisation::user_has_a_manager_position_over_users_with_permissions',
            [$manager, $users, $permission]);
    }

    /**
     * Create a simple org structure that makes one user a department manager over others
     *
     * @Given user :manager has a department manager position over users :users with permissions :permission
     *
     * @param string $manager
     * @param string $users
     * @param int $permission
     *
     * @deprecated Please use {@see behat_tool_organisation::user_has_a_department_lead_position_over_users_with_permissions}
     */
    public function user_has_a_department_manager_position_over_users_with_permissions($manager, $users, $permission) {
        $this->deprecated_message(['behat_tool_organisation::user_has_a_department_lead_position_over_users_with_permissions']);

        $this->execute('behat_tool_organisation::user_has_a_department_lead_position_over_users_with_permissions',
            [$manager, $users, $permission]);
    }
}

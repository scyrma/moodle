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

namespace tool_organisation\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use moodle_exception;
use tool_organisation\local\helpers\user_manager;
use tool_organisation\permission;

/**
 * tool_organisation external class to un-assign/delete manually assigned manager
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class unassign_manager extends external_api {

    /**
     * Parameters for the 'tool_organisation_unassign_manager' WS
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'userid' => new external_value(PARAM_INT, 'Id of the user to delete', VALUE_REQUIRED),
                'managerid' => new external_value(PARAM_INT, 'Id of the manager to delete', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Delete manually assigned manager execution function
     *
     * @param int $userid
     * @param int $managerid
     * @throws moodle_exception
     */
    public static function execute(int $userid, int $managerid): void {
        permission::require_can_assign_manually_assigned_manager();
        $params = self::validate_parameters(self::execute_parameters(),
            ['userid' => $userid, 'managerid' => $managerid]);
        $userid = $params['userid'];
        $managerid = $params['managerid'];

        $context = context_system::instance();
        self::validate_context($context);
        $usermanager = new user_manager();
        $usermanager->delete_assigned_manager($userid, $managerid);
    }

    /**
     * Un-assigned manually manager return structure
     * @return null
     */
    public static function execute_returns() {
        return null;
    }
}

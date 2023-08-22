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

namespace tool_program\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_user;

/**
 * External function tool_program_allocate_users for tool_program.
 *
 * @package   tool_program
 * @copyright 2023 Moodle Pty Ltd <support@moodle.com>
 * @author    2023 Mohamed A. Shehata <mohamed.shehata@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class allocate_users extends external_api {
    /**
     * External function to get the users courses.
     *
     * @param int $programid
     * @param array $userids
     * @return array of courses
     */
    public static function execute(int $programid, array $userids = []): array {
        global $DB;
        // Parameter validation.
        [
            'programid' => $programid,
            'userids' => $userids,
        ] = self::validate_parameters(self::execute_parameters(), [
            'programid' => $programid,
            'userids' => array_unique($userids),
        ]);

        $warnings = [];
        $result = [];

        // Validate context.
        $context = \context_system::instance();
        self::validate_context($context);

        $program = new program($programid);
        foreach ($userids as $userid) {
            // Check if you can allocate user to program.
            if (!permission::can_allocate_user($program, $userid)) {
                $warnings[] = [
                    'item' => $userid,
                    'warningcode' => 'nopermissions',
                    'message' => get_string('errornopermissionallocateusers', 'tool_program')
                ];
                continue;
            }

            // Check if user already allocated.
            $programuser = program_user::record_exists_select(
                'allocationtype IN (?,?) AND programid = ? AND userid = ?',
                [constants::ALLOCATION_MANUAL, constants::ALLOCATION_DYNAMIC, $programid, $userid]
            );

            if ($programuser) {
                $warnings[] = [
                    'item' => $userid,
                    'warningcode' => 'savedfailed',
                    'message' => get_string('errorcannotallocate', 'tool_program')
                ];
                continue;
            }

            $programuserdata = (object)[
                'userid' => $userid,
                'allocationtype' => constants::ALLOCATION_MANUAL,
                'certificationid' => 0,
            ];

            api::allocate_user($program, $programuserdata);
            $result[] = $userid;
        }
        return [
            'result' => $result,
            'warnings' => $warnings,
            'summary' => [
                'success' => count($result),
                'failed' => count($warnings)
            ]
        ];
    }

    /**
     * Describes the parameters for allocate_users.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT),
            'userids' => new external_multiple_structure(new external_value(PARAM_INT)),
        ]);
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_multiple_structure(new external_value(PARAM_INT, 'user id')),
            'warnings' => new external_warnings(),
            'summary' => new external_single_structure([
                'success' => new external_value(PARAM_INT, 'success count'),
                'failed' => new external_value(PARAM_INT, 'failed count'),
            ])
        ]);
    }
}

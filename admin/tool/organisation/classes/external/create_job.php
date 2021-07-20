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
 * 'tool_organisation_create_job' WS
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Sumit Negi
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\external;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use tool_organisation\department;
use tool_organisation\job_manager;
use tool_organisation\position;
use tool_organisation\permission;
use tool_organisation\helper;
use tool_tenant\tenancy;
use core_user;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * tool_organisation external class create job for user
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Sumit Negi
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class create_job extends external_api {

    /**
     * Parameters for the 'tool_organisation_create_job' WS
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'userid' => new external_value(PARAM_INT, 'userid'),
                'jobdepartment' => new external_value(PARAM_RAW, 'Department idnumber'),
                'jobposition' => new external_value(PARAM_RAW, 'Position idnumber'),
                'startdate' => new external_value(PARAM_INT, 'Start date in Unix time stamp', VALUE_DEFAULT, 0),
                'enddate' => new external_value(PARAM_INT, 'End date in Unix time stamp', VALUE_DEFAULT, 0)
            ]
        );
    }

    /**
     * Create job execution function
     *
     * @param int $userid
     * @param string $jobdeparment
     * @param string $jobposition
     * @param int $startdate
     * @param int $enddate
     * @return array[] return true in case of job created and in case of failed return false status.
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function execute($userid, $jobdeparment, $jobposition, $startdate = null, $enddate = null) : array {
        $result = false;
        $params = self::validate_parameters(self::execute_parameters(),
            [
                'userid' => $userid,
                'jobdepartment' => $jobdeparment,
                'jobposition' => $jobposition,
                'startdate' => $startdate,
                'enddate' => $enddate
            ]);
        // Position must not be blank.
        if (trim($params['jobposition']) === '') {
            throw new moodle_exception('errorinvalidposition', 'tool_organisation');
        }
        // Department must not be blank.
        if (trim($params['jobdepartment']) === '') {
            throw new moodle_exception('errorinvaliddepartment', 'tool_organisation');
        }
        // Check Username is valid and exist or not.
        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user, true);
        permission::require_can_assign_job_to_user($user->id);

        // Validate position belongs to the same tenant or a parent tenant.
        $position = position::get_record(['idnumber' => $params['jobposition']]);
        if (!permission::can_access_entity($position, $params['userid'])) {
            throw new moodle_exception('errorinvalidposition', 'tool_organisation');
        }

        // Validate department belongs to the same tenant or a parent tenant.
        $department = department::get_record(['idnumber' => $params['jobdepartment']]);
        if (!permission::can_access_entity($department, $params['userid'])) {
            throw new moodle_exception('errorinvaliddepartment', 'tool_organisation');
        }

        $startdate = helper::round_time(time());
        if ($params['startdate']) {
            $startdate = helper::round_time($params['startdate']);
        }
        $enddate = 0;
        if ($params['enddate']) {
            $enddate = helper::round_time($params['enddate']);
        }

        $data = [
            'userid' => $user->id,
            'positionid' => $position->get('id'),
            'departmentid' => $department->get('id'),
            'tenantid' => tenancy::get_tenant_id($user->id),
            'startdate' => $startdate,
            'enddate' => $enddate
        ];
        $jobmanager = new job_manager();
        if ($job = $jobmanager->create_job((object) $data, false)) {
            // Job created successfully, return true status as result.
            $result = true;
        }
        return ['status' => $result, 'jobid' => $job ? $job->get('id') : 0];
    }

    /**
     * Create job return structure
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status'),
            'jobid' => new external_value(PARAM_INT, 'Internal Moodle ID of the created job or 0 if job was not created'),
        ]);
    }
}

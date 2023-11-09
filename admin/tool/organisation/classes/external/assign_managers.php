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

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_api;
use core_external\external_value;
use core_external\external_warnings;
use tool_organisation\local\helpers\user_manager;
use tool_organisation\permission;
use tool_organisation\local\persistent\user_manager as user_manager_model;
use tool_tenant\tenancy;
use tool_wp\external\external_helper_exception;
use tool_wp\external\external_helper;

/**
 * Implementation of web service tool_organisation_assign_managers
 *
 * @package    tool_organisation
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class assign_managers extends external_api {

    /**
     * Describes the parameters for tool_organisation_assign_managers
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'users' => external_helper::users_lookup_structure('Users to assign managers to'),
            'managers' => external_helper::users_lookup_structure('Managers to assign (usually only one)'),
            'permissions' => new external_single_structure([
                'allocateprograms' => new external_value(
                    PARAM_BOOL, 'Can allocate users on programs', VALUE_DEFAULT, false),
                'viewreports' => new external_value(PARAM_BOOL, 'Can view reports', VALUE_DEFAULT, false),
                'receivenotifications' => new external_value(
                    PARAM_BOOL, 'Should receive notifications', VALUE_DEFAULT, false),
            ], 'Manager permissions', VALUE_DEFAULT, []),
            'unassignexisting' => new external_value(PARAM_BOOL,
                'If these user(s) already have manager(s), they should be unassigned',
                VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Unassign all managers from the specified users and return the list of deleted records.
     *
     * @param int $userid
     * @return user_manager_model[]
     */
    protected static function unassign_all_managers(int $userid): array {
        $records = user_manager_model::get_records(['userid' => $userid]);
        foreach ($records as $record) {
            user_manager::delete_assigned_manager($record->get('userid'), $record->get('managerid'));
        }
        return $records;
    }

    /**
     * Implementation of web service tool_organisation_assign_managers
     *
     * @param array $users
     * @param array $managers
     * @param array $permissions
     * @param bool $unassignexisting
     *
     * @return array
     */
    public static function execute(array $users, array $managers, array $permissions = [], bool $unassignexisting = false): array {
        global $USER;
        [
            'users' => $users,
            'managers' => $managers,
            'permissions' => $permissions,
            'unassignexisting' => $unassignexisting,
        ] =
            self::validate_parameters(self::execute_parameters(),
        [
            'users' => $users,
            'managers' => $managers,
            'permissions' => $permissions,
            'unassignexisting' => $unassignexisting,
        ]);

        $helper = new external_helper();
        $warnings = [];
        $assignedmanagers = [];
        $unassignedmanagers = [];

        permission::require_can_assign_manually_assigned_manager();

        $permission = \tool_organisation_external::sum_permissions($permissions);

        foreach ($users as $idx => $userspec) {
            foreach ($managers as $managerspec) {
                // Lookup user and manager, in case user does not belong the same tenant as current $USER, return a warning.
                try {
                    $user = $helper->lookup_user($userspec);
                    $manager = $helper->lookup_user($managerspec);
                } catch (external_helper_exception $e) {
                    $warnings[] = $e->export_as_warning($idx);
                    continue;
                }

                // Let's check some conditions.
                $usertenantid = tenancy::get_actual_tenant_id($user->id);
                $managertenantid = tenancy::get_actual_tenant_id($manager->id);

                try {
                    // Check if user and manager are recently added.
                    if (in_array($user->id, array_column($assignedmanagers, 'userid'))
                        && in_array($manager->id, array_column($assignedmanagers, 'managerid'))) {
                        throw new \Exception('User and manager are already added');
                    }
                    // Check if user and manager are not the same person.
                    if ($user->id === $manager->id) {
                        throw new \Exception('User and manager cannot be the same person');
                    }
                    // Check if user and manager are in the same tenant.
                    if ($usertenantid !== $managertenantid) {
                        throw new \Exception('User and manager are not in the same tenant');
                    }
                } catch (\Exception $e) {
                    $warnings[$user->id] = [
                        'item' => $user->id,
                        'itemid' => $idx,
                        'warningcode' => 'invalidusermanager',
                        'message' => $e->getMessage(),
                    ];
                    continue;
                }

                // Unassign all existing managers if requested.
                if ($unassignexisting) {
                    foreach (self::unassign_all_managers($user->id) as $record) {
                        $unassignedmanagers[] = [
                            'itemid' => $idx,
                            'userid' => $record->get('userid'),
                            'managerid' => $record->get('managerid'),
                        ];
                    }
                }

                try {
                    $model = [
                        'id' => $manager->id,
                        'permissions' => $permission,
                    ];
                    user_manager::add_assigned_manager($user->id, (object)$model);
                    $assignedmanagers[] = [
                        'itemid' => $idx,
                        'userid' => $user->id,
                        'managerid' => $manager->id,
                    ];
                } catch (\moodle_exception $e) {
                    $warnings[$user->id] = [
                        'item' => $userspec['id'],
                        'itemid' => $idx,
                        'warningcode' => $e->errorcode,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'warnings' => $warnings,
            'assignedmanagers' => $assignedmanagers,
            'unassignedmanagers' => $unassignedmanagers,
        ];
    }

    /**
     * Describe the return structure for tool_organisation_assign_managers
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'warnings' => new external_warnings(
                'Parameter that caused the warning (user, manager, etc)',
                'Index in the input array or users'
            ),
            'assignedmanagers' => new external_multiple_structure(new external_single_structure([
                'itemid' => new external_value(PARAM_INT, 'Index in the input array of users'),
                'userid' => new external_value(PARAM_INT, 'Internal Moodle ID of the user (subordinate)'),
                'managerid' => new external_value(PARAM_INT, 'Internal Moodle ID of the manager'),
            ]),
                'Assigned managers'),
            'unassignedmanagers' => new external_multiple_structure(new external_single_structure([
                'itemid' => new external_value(PARAM_INT, 'Index in the input array of users'),
                'userid' => new external_value(PARAM_INT, 'Internal Moodle ID of the user (subordinate)'),
                'managerid' => new external_value(PARAM_INT, 'Internal Moodle ID of the manager'),
            ]),
                'Unassigned managers (if unassignexisting was specified)'),
        ]);
    }
}

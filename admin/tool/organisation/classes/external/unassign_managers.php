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
use tool_organisation\local\persistent\user_manager as user_manager_model;
use tool_organisation\permission;
use tool_tenant\tenancy;
use tool_wp\external\external_helper_exception;
use tool_wp\external\external_helper;

/**
 * Implementation of web service tool_organisation_unassign_managers
 *
 * @package    tool_organisation
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class unassign_managers extends external_api {

    /**
     * Describes the parameters for tool_organisation_unassign_managers
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'users' => external_helper::users_lookup_structure(
                'Users, whose managers we want to unassign', VALUE_DEFAULT, []),
            'managers' => external_helper::users_lookup_structure(
                'Users, whose subordinates we want to unassign', VALUE_DEFAULT, []),
            'unassignall' => new external_value(PARAM_BOOL,
                'Unassign all managers from specified users and all subordinates from specified managers',
                VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Add information about unassigned managers to the result array.
     *
     * @param user_manager_model[] $deletedrecords
     * @param int $idx
     * @param array $unassignedmanagers
     * @return void
     */
    protected static function add_unassigned_managers_to_result(array $deletedrecords, int $idx, array &$unassignedmanagers) {
        foreach ($deletedrecords as $d) {
            $unassignedmanagers[] = [
                'itemid' => $idx,
                'userid' => $d->get('userid'),
                'managerid' => $d->get('managerid'),
            ];
        }
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
     * Unassign all subordinates from the specified manager and return the list of deleted records.
     *
     * @param int $managerid
     * @return user_manager_model[]
     */
    protected static function unassign_all_subordinates(int $managerid): array {
        $records = user_manager_model::get_records(['managerid' => $managerid]);
        foreach ($records as $record) {
            user_manager::delete_assigned_manager($record->get('userid'), $record->get('managerid'));
        }
        return $records;
    }

    /**
     * Implementation of web service tool_organisation_unassign_managers
     *
     * @param array $userspecs
     * @param array $managerspecs
     * @param bool $unassignall
     * @return array
     */
    public static function execute(array $userspecs, array $managerspecs, bool $unassignall = false): array {
        // Validate parameters and access.
        [
            'users' => $userspecs,
            'managers' => $managerspecs,
            'unassignall' => $unassignall,
        ] =
            self::validate_parameters(self::execute_parameters(),
        [
            'users' => $userspecs,
            'managers' => $managerspecs,
            'unassignall' => $unassignall,
        ]);
        permission::require_can_assign_manually_assigned_manager();

        $helper = new external_helper();
        $warnings = [];
        $unassignedmanagers = [];

        // First retrieve all specified users and all specified managers and record warnings if somebody
        // can not be found, also in case user does not belong the same tenant as current $USER, return a warning as well.
        $users = [];
        $managers = [];
        foreach ($userspecs as $idx => $userspec) {
            try {
                $users[$idx] = $helper->lookup_user($userspec);
            } catch (external_helper_exception $e) {
                $warnings[] = $e->export_as_warning($idx);
            }
        }
        foreach ($managerspecs as $midx => $managerspec) {
            try {
                $managers[$midx] = $helper->lookup_user($managerspec, 'manager');
            } catch (external_helper_exception $e) {
                $warnings[] = $e->export_as_warning($midx);
            }
        }

        if ($unassignall) {
            // If 'unassignall' parameter is specified, unassign all managers from specified users
            // and all subordinates from specified managers.
            foreach ($users as $idx => $user) {
                $deleted = self::unassign_all_managers($user->id);
                self::add_unassigned_managers_to_result($deleted, $idx, $unassignedmanagers);
            }
            foreach ($managers as $midx => $manager) {
                $deleted = self::unassign_all_subordinates($manager->id);
                self::add_unassigned_managers_to_result($deleted, $midx, $unassignedmanagers);
            }
        } else {
            // Otherwise, unassign specified managers from specified users.
            foreach ($users as $idx => $user) {
                foreach ($managers as $midx => $manager) {
                    // Let's check some conditions.
                    $usertenantid = tenancy::get_actual_tenant_id($user->id);
                    $managertenantid = tenancy::get_actual_tenant_id($manager->id);
                    try {
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
                    $record = user_manager_model::get_record(['userid' => $user->id, 'managerid' => $manager->id]);
                    if ($record) {
                        user_manager::delete_assigned_manager($user->id, $manager->id);
                        self::add_unassigned_managers_to_result([$record], $idx, $unassignedmanagers);
                    } else {
                        $manageridentifier = reset($managerspecs[$midx]);
                        $useridentifier = reset($userspecs[$idx]);
                        $warnings[] = [
                            'itemid' => $idx,
                            'item' => 'user',
                            'warningcode' => 'notamanager',
                            'message' => "User '$manageridentifier' is not a manager of the user '$useridentifier'",
                        ];
                    }
                }
            }
        }

        return [
            'warnings' => $warnings,
            'unassignedmanagers' => $unassignedmanagers,
        ];
    }

    /**
     * Describe the return structure for tool_organisation_unassign_managers
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'warnings' => new external_warnings(
                'Parameter that caused the warning (user, manager, etc)',
                'Index in the input array or users'
            ),
            'unassignedmanagers' => new external_multiple_structure(new external_single_structure([
                'itemid' => new external_value(PARAM_INT, 'Index in the input array of users'),
                'userid' => new external_value(PARAM_INT, 'Internal Moodle ID of the user (subordinate)'),
                'managerid' => new external_value(PARAM_INT, 'Internal Moodle ID of the manager'),
            ]),
                'Unassigned managers'),
        ]);
    }
}

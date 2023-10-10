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

namespace tool_organisation\local\helpers;

use moodle_exception;
use stdClass;
use tool_organisation\event\user_manager_created;
use tool_organisation\event\user_manager_deleted;
use tool_organisation\event\user_manager_updated;
use tool_organisation\local\persistent\user_manager as user_manager_model;
use tool_tenant\tenancy;

/**
 * Class user_manager
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_manager {

    /**
     * Add assigned manager.
     *
     * @param int $employeeid
     * @param stdClass $manager
     * @param bool $triggerevent
     *
     * @return user_manager_model
     *
     */
    public static function add_assigned_manager(int $employeeid, stdClass $manager, bool $triggerevent = true): user_manager_model {
        // Check if there exists some circular dependency.
        $managertousers = user_manager_model::get_record([
            'userid' => (int)$manager->id,
            'managerid' => $employeeid,
        ]);

        // Check if there already exists the current relation.
        $existsrelation = user_manager_model::get_record([
            'userid' => $employeeid,
            'managerid' => (int)$manager->id,
        ]);

        // If current relation already exists or overlap with other one, then it cannot be added.
        if ($managertousers || ($existsrelation && $triggerevent)) {
            throw new moodle_exception('usermanagednotallowedrelation', 'tool_organisation');
        }

        // Create object for manually assigned managers.
        $userdata = new stdClass();
        $userdata->userid = $employeeid;
        $userdata->managerid = (int)$manager->id;
        $userdata->tenantid = tenancy::get_actual_tenant_id($userdata->managerid);
        $userdata->permissions = $manager->permissions ?? 0;
        $usermanager = new user_manager_model(0, $userdata);
        $usermanager->create();

        // Update new manager permissions.
        if (property_exists($manager, "permissions")) {
            $usermanager->set('permissions', $manager->permissions);
            $usermanager->update();
        }

        // Trigger created user manager event.
        if ($triggerevent) {
            user_manager_created::create_from_object($usermanager)->trigger();
        }

        // Schedule building reporting line ad-hoc task.
        reporting::schedule_reporting_line_reindex($userdata->tenantid);

        return $usermanager;
    }

    /**
     * Update assigned manager.
     *
     * @param int $employeeid
     * @param stdClass $newmanager // Change to persistent object
     * @param int $oldmanagerid
     *
     */
    public static function update_assigned_manager(int $employeeid, stdClass $newmanager, int $oldmanagerid): void {
        // Remove current relation.
        if ($oldmanager = user_manager_model::get_record(['userid' => $employeeid, 'managerid' => $oldmanagerid])) {
            $oldmanager->delete();
        }

        $usermanagertoupdate = self::add_assigned_manager($employeeid, $newmanager, false);

        // Trigger user manager event.
        user_manager_updated::create_from_object($usermanagertoupdate, $oldmanagerid)->trigger();

        // Schedule building reporting line ad-hoc task for the employee affected.
        reporting::schedule_reporting_line_reindex(tenancy::get_actual_tenant_id($employeeid));
    }

    /**
     * Delete assigned manager.
     *
     * @param int $employeeid
     * @param int $managerid
     *
     */
    public static function delete_assigned_manager(int $employeeid, int $managerid): void {
        // Get record from current employee to be deleted.
        $usermanagertodelete = user_manager_model::get_record(['userid' => $employeeid, 'managerid' => $managerid], MUST_EXIST);
        $usermanagertodelete->delete();

        // Trigger user manager event.
        user_manager_deleted::create_from_object($usermanagertodelete)->trigger();

        // Schedule building reporting line ad-hoc task for user recently deleted.
        reporting::schedule_reporting_line_reindex(tenancy::get_actual_tenant_id($employeeid));
    }

    /**
     * Delete user manager record.
     *
     * @param array $params
     */
    public static function delete_user_manager(array $params = []): void {
        $usermanagerstodelete = user_manager_model::get_records($params);
        foreach ($usermanagerstodelete as $usermanager) {
            $usermanager->delete();
        }
    }
}

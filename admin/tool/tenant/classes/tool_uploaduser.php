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

/**
 * Methods for tool_uploaduser
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use stdClass;
use uu_progress_tracker;

/**
 * Class tool_uploaduser
 *
 * This implements methods to be use on uploading users CSV files.
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_uploaduser {

    /**
     * Callback for tool_uploaduser that process updated user.
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     */
    public static function process_updated_user($user, $filecolumns, $upt) {

        if (empty($user->tenant)) {
            return;
        }

        if (!$tenant = \tool_tenant\tenant::get_record(['idnumber' => $user->tenant, 'archived' => 0])) {
            $upt->track('tool_wp', get_string('errorinvalidtenant', 'tool_tenant'), 'error');
            return;
        }

        if (!permission::can_move_users_between_tenants()) {
            $upt->track('tool_wp', get_string('errorcannotallocate', 'tool_tenant'), 'error');
            return;
        }

        // It seems that allocate_user() removes all tenant roles from the user when calling assign_tenant_user_role(), so we need
        // to check if the user was tenant admin, to assign the role again if 'istenantadmin' property is empty in the CSV file.
        $wastenantadmin = manager::is_tenant_admin($tenant->get('id'), $user->id);

        $manager = new \tool_tenant\manager();
        $manager->allocate_user($user->id, $tenant->get('id'), 'tool_tenant', 'uploaduser');

        // Check if the user needs to have assigned or unassigned the tenant admin role.
        if (property_exists($user, 'istenantadmin') && permission::can_assign_tenant_admin($tenant->get('id'))) {
            if ($user->istenantadmin || (strlen($user->istenantadmin) === 0 && $wastenantadmin)) {
                // If 'istenantadmin' property is set to true or if the user was tenant admin and the property is empty
                // (no value is set), we need to assign the tenant admin role.
                $manager->assign_tenant_admin_roles([$user->id], $tenant->get('id'));
            } else if (strlen($user->istenantadmin) > 0 && !$user->istenantadmin) {
                // If 'istenantadmin' property is set and is false, we need to unassign the tenant admin role.
                $manager->unassign_tenant_admin_roles([$user->id], $tenant->get('id'));
            }
        }
    }

    /**
     * Callback for tool_uploaduser that validates if can create user.
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     * @return bool
     */
    public static function can_create_user($user, $filecolumns, $upt) {
        global $DB;
        if (isset($user->tenant)) {
            $tenant = $user->tenant;
            if (!$tenantid = $DB->get_field('tool_tenant', 'id', ['idnumber' => $tenant, 'archived' => 0])) {
                $upt->track('tool_tenant', get_string('unknowntenant', 'error', s($tenant)), 'error');
                return false;
            }
        } else {
            $tenantid = tenancy::get_tenant_id();
        }
        if (!permission::can_create_users($tenantid)) {
            return false;
        }
        // Remember that this user should be allocated to this tenant. The actual allocation will happen in the observer
        // to user_created event. Observer in tool_tenant has very high priority and should be executed before any other observer.
        manager::preallocate_new_user($user, $tenantid, 'tool_tenant', 'uploaduser');
        return true;
    }

    /**
     * Callback for tool_uploaduser that validates if can create user.
     *
     * @param stdClass $user
     * @return bool
     */
    public static function can_update_user($user) {
        return permission::can_update_user($user);
    }
}

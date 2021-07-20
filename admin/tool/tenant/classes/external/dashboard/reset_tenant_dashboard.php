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
 * Reset dashboard for all users in a tenant
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\external\dashboard;

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use tool_tenant\dashboard_manager;
use tool_tenant\permission;
use tool_tenant\tenancy;


/**
 * reset_tenant_dashboard class
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reset_tenant_dashboard extends external_api {

    /**
     * Parameters for 'tool_tenant_reset_tenant_dashboard' WebService
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'tenantid' => new external_value(PARAM_INT, 'Tenant ID', VALUE_DEFAULT, 0)
            ]
        );
    }

    /**
     * Reset dashboard for all users in the tenant
     *
     * @param int $tenantid
     * @return bool
     */
    public static function execute(int $tenantid = 0): bool {
        global $DB;

        $context = context_system::instance();
        self::validate_context($context);

        ['tenantid' => $tenantid] = self::validate_parameters(self::execute_parameters(), ['tenantid' => $tenantid]);

        // Check permission.
        permission::require_can_see_tenant_dashboard_tab($tenantid);

        // Get list of users to reset the dashboard.
        $tenantcondition = \tool_tenant\tenancy::get_users_subquery(false, false, 'mp.userid', $tenantid);
        $sql = "SELECT DISTINCT(userid) FROM {my_pages} mp
                JOIN {user} u ON u.id = mp.userid AND u.deleted = 0
                    WHERE $tenantcondition
                    AND mp.userid IS NOT NULL
                    AND mp.private = :private";
        $users = $DB->get_fieldset_sql($sql, ['private' => 1]);

        // Reset the dashbaords.
        if (!empty($users)) {
            dashboard_manager::reset_dashboard_page_for_users($users);
        }
        return true;
    }

    /**
     * Return structure for 'tool_tenant_reset_tenant_dashboard' WebService
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Update result');
    }
}

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
 * Update dashboardlinked
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
 * update_dashboardlinked class
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class update_dashboardlinked extends external_api {

    /**
     * Parameters for 'tool_tenant_update_dashboardlinked' WebService
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'dashboardlinked' => new external_value(PARAM_BOOL, 'Whether the tenant\'s dashboard is linked', VALUE_REQUIRED),
                'tenantid' => new external_value(PARAM_INT, 'Tenant ID', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Update dashboardlinked
     *
     * @param bool $dashboardlinked
     * @param int $tenantid
     * @return bool
     */
    public static function execute(bool $dashboardlinked, int $tenantid = 0): bool {
        $context = context_system::instance();
        self::validate_context($context);

        [
            'dashboardlinked' => $dashboardlinked,
            'tenantid' => $tenantid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'dashboardlinked' => $dashboardlinked,
            'tenantid' => $tenantid,
        ]);

        // Check permission.
        permission::require_can_see_tenant_dashboard_tab($tenantid);

        if ($dashboardlinked) {
            dashboard_manager::link_tenant_dashboard($tenantid);
        } else {
            dashboard_manager::unlink_tenant_dashboard($tenantid);
        }
        return true;
    }

    /**
     * Return structure for 'tool_tenant_update_dashboardlinked' WebService
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Update result');
    }
}

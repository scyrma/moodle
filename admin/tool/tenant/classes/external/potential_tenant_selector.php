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
 * Potential Selector Web service
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\external;

use context_system;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use tool_tenant\permission;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_tenant\tenant;

/**
 * Class potential_tenant_selector
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class potential_tenant_selector extends external_api{

    /**
     * Describes the parameters for selecting potential tenants
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_NOTAGS, 'Search string', VALUE_REQUIRED)
        ]);
    }

    /**
     * Tenant selector.
     *
     * @param string $search
     * @return array $result
     */
    public static function execute(string $search) {
        global $DB;

        $result = [];

        [
            'search' => $search,
        ] = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        if (tenancy::is_site_multi_tenant() && permission::can_switch_tenant()) {
            // Exclude Shared space.
            $sharedid = sharedspace::get_shared_space_id();
            $exclude = [];
            if ($sharedid > 0) {
                $exclude[] = $sharedid;
            }
            [$select, $params] = tenancy::tenants_search_sql($search, null, true, [], $exclude);
            $tenants = $DB->get_records_select(tenant::TABLE, "archived = 0 AND {$select}", $params, 'name', 'id, name');
            foreach ($tenants as $tenant) {
                $result[] = [
                    'id' => $tenant->id,
                    'fullname' => tenancy::get_tenant_name_from_id($tenant->id),
                ];
            }
        }

        return $result;
    }

    /**
     * Describes the data returned from the external function
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT,
                'ID of the tenant'),
            'fullname' => new external_value(PARAM_NOTAGS,
                'The fullname of the tenant')
        ]));
    }
}

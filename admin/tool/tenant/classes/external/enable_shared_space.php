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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Enable Shared Space Web service
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\external;

use context_system;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use tool_tenant\sharedspace;
use tool_tenant\permission;
use tool_tenant\tenancy;

/**
 * Class enable_shared_space
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class enable_shared_space extends external_api {

    /**
     * Describes the parameters for enabling shared space.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters ([]);
    }

    /**
     * Enable Shared space.
     *
     * @return array
     */
    public static function execute() {
        if (\tool_tenant\tenancy::is_site_multi_tenant() && permission::can_switch_tenant()
                && sharedspace::get_shared_space_id() <= 0 && permission::can_create_tenant()) {

            sharedspace::enable_shared_space();
        }
        return [
            'url' => tenancy::get_tenantswitch_url(sharedspace::get_shared_space_id())->out_as_local_url(false)
        ];
    }

    /**
     * Describes the data returned from the external function
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'url' => new external_value(PARAM_URL, 'Switch URL for the tenant'),
        ]);
    }
}

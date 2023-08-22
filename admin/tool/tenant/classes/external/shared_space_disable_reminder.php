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
 * Disable Shared Space Reminder Web service
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_tenant\sharedspace;
use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_tenant\tenant;

/**
 * Class shared_space_disable_reminder
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class shared_space_disable_reminder extends external_api {

    /**
     * Describes the parameters for enabling shared space.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters ([]);
    }

    /**
     * Disable Shared Space reminder
     *
     * @return array
     */
    public static function execute() {
        $disabled = false;
        if (tenancy::is_site_multi_tenant() && permission::can_switch_tenant()
            && sharedspace::get_shared_space_id() === null) {
            $disabled = sharedspace::disable_shared_space_reminder();
        }
        return ['disabled' => $disabled];
    }

    /**
     * Describes the data returned from the external function
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'disabled' => new external_value(PARAM_BOOL, 'Reminder turned off to enable shared space'),
        ]);
    }
}

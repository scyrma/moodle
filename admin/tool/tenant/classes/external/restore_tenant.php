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

namespace tool_tenant\external;

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use tool_tenant\manager;
use tool_tenant\permission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/externallib.php");

/**
 * Class restore_tenant
 *
 * @package     tool_tenant
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class restore_tenant extends external_api {

    /**
     * Describes the parameters for restore tenant.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'tenantid' => new external_value(PARAM_INT, 'Tenant id to be restored', VALUE_REQUIRED),
        ]);
    }

    /**
     * Restore tenant
     *
     * @param int $tenantid
     */
    public static function execute(int $tenantid) {

        $context = context_system::instance();
        self::validate_context($context);

        // Validate parameters.
        $params = external_api::validate_parameters(self::execute_parameters(), ['tenantid' => $tenantid]);

        // Check if can restore tenant.
        permission::require_can_restore_tenant($params['tenantid']);

        $manager = new manager();
        $manager->restore_tenant($params['tenantid']);
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return null
     */
    public static function execute_returns() {
        return null;
    }
}

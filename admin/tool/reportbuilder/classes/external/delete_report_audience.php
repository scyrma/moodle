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
 * External function delete_report_audience for tool_reportbuilder.
 *
 * @package   tool_reportbuilder
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\external;

use external_api;
use external_function_parameters;
use external_value;
use context_system;
use tool_reportbuilder\audience_base;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * External function delete_report_audience for tool_reportbuilder.
 *
 * @package   tool_reportbuilder
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class delete_report_audience extends external_api {

    /**
     * Describes the parameters for get_users_courses.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'instanceid' => new external_value(PARAM_INT, 'audience instance id'),
            )
        );
    }

    /**
     * External function to delete a report audience instance.
     *
     * @param int $instanceid
     * @return array
     */
    public static function execute(int $instanceid): array {
        // Parameter validation.
        [
            'instanceid' => $instanceid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
        ]);

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);

        $baseinstance = audience_base::instance($instanceid);
        if ($baseinstance && $baseinstance->user_can_edit()) {

            $report = manager::get_report($baseinstance->get_reportid());
            permission::require_can_edit($report);

            $persistent = $baseinstance->get_persistent();
            $persistent->delete();
            return ['result' => true];
        }

        return ['result' => false];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'result' => new \external_value(PARAM_BOOL, '', VALUE_REQUIRED),
        ]);
    }
}

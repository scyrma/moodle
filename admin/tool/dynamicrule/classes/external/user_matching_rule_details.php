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

namespace tool_dynamicrule\external;

use tool_dynamicrule\permission;

/**
 * External function user_matching_rule_details.
 *
 * @package   tool_dynamicrule
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_matching_rule_details extends \external_api {

    /**
     * Returns the structure of parameters for user_matching_rule_details function.
     *
     * @return \external_function_parameters
     */
    protected static function execute_parameters() {
        $params = ['matchingid' => new \external_value(PARAM_INT, 'The ID of user matching record', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * External function to get matching users details.
     *
     * @param int $matchingid
     * @return string content of details modal
     */
    public static function execute(int $matchingid): string {
        global $PAGE, $DB;

        $context = \context_system::instance();
        self::validate_context($context);

        $params = self::validate_parameters(self::execute_parameters(), ['matchingid' => $matchingid]);
        $matchrecord = $DB->get_record('tool_dynamicrule_match', ['id' => $params['matchingid']]);
        $rule = \tool_dynamicrule\api::get_rule($matchrecord->ruleid);
        permission::require_can_view_matched_users_report($rule);

        $output = $PAGE->get_renderer('tool_dynamicrule');

        $details = new \tool_dynamicrule\output\user_matching_rule_details($params['matchingid']);
        return $output->render_from_template('tool_dynamicrule/user_matching_rule_details', $details->export_for_template($output));
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return \external_value
     */
    public static function execute_returns(): \external_value {
        return new \external_value(PARAM_RAW, 'Matching details in HTML.');
    }
}

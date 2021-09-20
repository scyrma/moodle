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
 * Confirm anybody
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
use tool_tenant\config;
use tool_tenant\permission;

/**
 * Class confirm
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class confirm_users extends external_api {

    /**
     * Describes the parameters to confirm user account.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            ['userids' => new external_multiple_structure(new external_value(\core_user::get_property_type('id'), 'user ID'))]
        );
    }

    /**
     * Confirm users
     *
     * @param array $userids
     * @return array
     */
    public static function execute($userids) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/user/lib.php");

        $context = context_system::instance();
        self::validate_context($context);

        $params = self::validate_parameters(self::execute_parameters(), ['userids' => $userids]);
        $fails = $alreadyconfirmed = 0;
        foreach ($params['userids'] as $userid) {
            try {
                $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
                if ($user->confirmed > 0) {
                    $alreadyconfirmed++;
                } else {
                    permission::require_can_confirm_user($user);

                    config::push_for_user($user->id);
                    $auth = get_auth_plugin($user->auth);
                    $auth->user_confirm($user->username, $user->secret);
                    config::pop();
                }
            } catch (\moodle_exception $exception) {
                $fails++;
            }
        }
        return ['successcount' => count($params['userids']) - $fails - $alreadyconfirmed,
            'failcount' => $fails, 'skippedcount' => $alreadyconfirmed];
    }

    /**
     * Describes the data returned from the external function
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new \external_single_structure([
            'successcount' => new external_value(PARAM_INT, 'The total number of confirmation emails sent'),
            'failcount' => new external_value(PARAM_INT, 'The total number of confirmation emails not sent'),
            'skippedcount' => new external_value(PARAM_INT, 'Total number of already confirmed', VALUE_OPTIONAL)
        ]);
    }
}

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

namespace tool_program\external;

use context_system;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use tool_program\api;
use tool_program\permission;
use tool_program\persistent\program_user;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * External function recalculate_program_user_completions for tool_program.
 *
 * @package   tool_program
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class recalculate_program_user_completions extends external_api {

    /**
     * Bulk recalculate program user completions parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programuserids' => new external_multiple_structure(new external_value(PARAM_INT)),
        ]);
    }

    /**
     * Bulk recalculate program user completions external function.
     *
     * @param array $programuserids
     * @return array
     */
    public static function execute(array $programuserids = []): array {
        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'programuserids' => $programuserids,
        ]);
        $programuserids = $params['programuserids'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        $successcount = 0;
        $skippedcount = 0;

        foreach ($programuserids as $programuserid) {
            $programuser = new program_user($programuserid);
            if (!permission::can_recalculate_user_completion($programuser)) {
                $skippedcount++;
                continue;
            }

            api::recalculate_program_user_completion($programuser);

            $successcount++;
        }

        return [
            'successcount' => $successcount,
            'skippedcount' => $skippedcount,
        ];
    }

    /**
     * Recalculate program user completions returns
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'successcount' => new external_value(PARAM_INT),
            'skippedcount' => new external_value(PARAM_INT),
        ]);
    }
}

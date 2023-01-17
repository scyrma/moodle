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

declare(strict_types=1);

namespace tool_custompage\external\audience;

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use tool_custompage\permission;
use tool_custompage\local\models\page;
use tool_custompage\local\audience\base;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/externallib.php");

/**
 * External method for deleting page audiences
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class delete extends external_api {

    /**
     * External method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Page ID'),
            'instanceid' => new external_value(PARAM_INT, 'Audience instance ID'),
        ]);
    }

    /**
     * External method execution
     *
     * @param int $pageid
     * @param int $instanceid
     * @return bool
     */
    public static function execute(int $pageid, int $instanceid): bool {
        [
            'pageid' => $pageid,
            'instanceid' => $instanceid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'pageid' => $pageid,
            'instanceid' => $instanceid,
        ]);

        self::validate_context(context_system::instance());

        $page = new page($pageid);
        permission::require_can_edit_page($page);

        $instance = base::instance($instanceid);
        if ($instance && $instance->user_can_edit()) {
            $persistent = $instance->get_persistent();
            return $persistent->delete();
        }

        return false;
    }

    /**
     * External method return value
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Success');
    }
}

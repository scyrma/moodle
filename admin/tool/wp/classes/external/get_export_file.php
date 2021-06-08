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
 * External class for getting an export file details.
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir.'/externallib.php');

use context_system;
use external_function_parameters;
use external_files;
use external_value;
use external_util;
use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\helper as exportimport_helper;

/**
 * External class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_export_file extends \external_api {

    /**
     * Parameters for getting export file details.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'exportid' => new external_value(PARAM_INT, 'Export ID'),
        ]);
    }

    /**
     * Getting export file details.
     *
     * @param int $exportid Export id
     * @return array External files structure.
     */
    public static function execute(int $exportid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'exportid' => $exportid,
        ]);
        $context = context_system::instance();
        self::validate_context($context);
        \tool_wp\permission::require_can_view_export($params['exportid']);

        // Check status.
        $exportmanager = new export_manager($params['exportid']);
        $status = $exportmanager->get_export_status();
        if ($status !== exportimport_helper::STATUS_DONE) {
            throw new \moodle_exception('exportnotready', 'tool_wp');
        }

        return external_util::get_area_files($context->id, 'tool_wp', 'export', $params['exportid']);
    }

    /**
     * Return for getting export file details.
     *
     * @return external_files
     */
    public static function execute_returns(): external_files {
        return new external_files('Export file.');
    }
}

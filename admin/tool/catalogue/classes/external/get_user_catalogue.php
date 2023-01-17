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

namespace tool_catalogue\external;

use context_system;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_warnings;
use tool_catalogue\output\renderer;
use tool_catalogue\permission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * External function get_user_catalogue for tool_catalogue.
 *
 * @package   tool_catalogue
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_user_catalogue extends external_api {

    /**
     * Describes the parameters for get_user_catalogue
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'The user id', VALUE_DEFAULT, 0),
            'filter' => new external_value(PARAM_TEXT, "Filter results by 'all', 'courses', 'programs', 'complete', 'incomplete'",
                VALUE_DEFAULT, ''),
            'sort' => new external_value(PARAM_TEXT, "Sort results by 'duedate', 'name', 'lastaccess'", VALUE_DEFAULT, ''),
            'search' => new external_value(PARAM_TEXT, "Search in item name", VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * External function to get user catalogue
     *
     * @param int $userid
     * @param string $filter Filter results by 'all', 'courses', 'programs', 'complete', 'incomplete'.
     * @param string $sort Sort results by 'duedate', 'name', 'lastaccess'.
     * @param string $search
     * @return array
     */
    public static function execute(int $userid = 0, string $filter = '', string $sort = '', string $search = ''): array {
        global $PAGE, $USER;

        // Parameter validation.
        [
            'userid' => $userid,
            'filter' => $filter,
            'sort' => $sort,
            'search' => $search,
        ] = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'filter' => $filter,
            'sort' => $sort,
            'search' => $search,
        ]);

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        // Default userid to current userid if is not set.
        $userid = !empty($userid) ? $userid : (int) $USER->id;

        // Check permissions.
        permission::require_can_get_user_catalogue($userid);

        /** @var renderer $output */
        $output = $PAGE->get_renderer('tool_catalogue');

        $exporter = new catalogue_exporter(null, ['userid' => $userid, 'filter' => $filter, 'sort' => $sort, 'search' => $search]);
        return [
            'catalogue' => (array)$exporter->export($output),
        ];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'catalogue' => catalogue_exporter::get_read_structure(),
            'warnings' => new external_warnings(),
        ]);
    }
}

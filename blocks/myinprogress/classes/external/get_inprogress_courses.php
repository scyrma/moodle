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

namespace block_myinprogress\external;

use block_myinprogress\manager;
use block_myinprogress\output\renderer;
use context_user;
use core_user;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_warnings;
use moodle_exception;
use tool_catalogue\external\coursecarousel_exporter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * External function get_inprogress_courses for block_myinprogress.
 *
 * @package   block_myinprogress
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Mikel Martín <mikel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_inprogress_courses extends external_api {

    /**
     * Describes the parameters for get_inprogress_courses
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'onlywithcompletion' => new external_value(PARAM_BOOL, 'Return only enrolled courses with completion enabled',
                VALUE_DEFAULT, true),
        ]);
    }

    /**
     * External function to get_inprogress_courses
     *
     * @param bool $onlywithcompletion
     * @return array
     */
    public static function execute(bool $onlywithcompletion = true): array {
        global $PAGE, $USER;

        // Parameter validation.
        [
            'onlywithcompletion' => $onlywithcompletion,
        ] = self::validate_parameters(self::execute_parameters(), [
            'onlywithcompletion' => $onlywithcompletion,
        ]);

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_user::instance($USER->id);
        self::validate_context($context);

        /** @var renderer $output */
        $output = $PAGE->get_renderer('block_myinprogress');

        $related = ['courses' => manager::get_inprogress_courses((int) $USER->id, $onlywithcompletion)];
        $exporter = new coursecarousel_exporter(null, $related);
        return [
            'data' => (array)$exporter->export($output),
            'warnings' => [],
        ];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'data' => coursecarousel_exporter::get_read_structure(),
            'warnings' => new external_warnings(),
        ]);
    }
}

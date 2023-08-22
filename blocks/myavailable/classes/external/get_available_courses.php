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

declare(strict_types=1);

namespace block_myavailable\external;

use block_myavailable\manager;
use block_myavailable\output\renderer;
use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_warnings;
use tool_catalogue\external\coursecarousel_exporter;

/**
 * External function get_available_courses for block_myavailable.
 *
 * @package   block_myavailable
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Odei Alba <odei.alba@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_available_courses extends external_api {

    /**
     * Describes the parameters for get_available_courses
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * External function to get_available_courses
     *
     * @return array
     */
    public static function execute(): array {
        global $PAGE, $USER;

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_user::instance($USER->id);
        self::validate_context($context);

        /** @var renderer $output */
        $output = $PAGE->get_renderer('block_myavailable');

        $exporter = new coursecarousel_exporter(null, ['courses' => manager::get_available_courses((int) $USER->id)]);
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

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
use core_user;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use tool_catalogue\external\program\course_exporter;
use tool_catalogue\external\program\set_exporter;
use tool_catalogue\manager;
use tool_catalogue\output\renderer;
use tool_catalogue\permission;
use tool_program\persistent\program;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * External function get_user_catalogue_program for tool_catalogue.
 *
 * @package   tool_catalogue
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_user_catalogue_program extends external_api {

    /**
     * Describes the parameters for get_user_catalogue_program
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT, 'The program id'),
            'userid' => new external_value(PARAM_INT, 'The user id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * External function to get user catalogue program
     *
     * @param int $programid
     * @param int $userid
     * @return array
     */
    public static function execute(int $programid, int $userid = 0): array {
        global $PAGE, $USER;

        // Parameter validation.
        [
            'programid' => $programid,
            'userid' => $userid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'programid' => $programid,
            'userid' => $userid,
        ]);

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        // Default userid to current userid if is not set.
        $userid = !empty($userid) ? $userid : (int) $USER->id;

        // Check permissions.
        $user = core_user::get_user($userid, '*', MUST_EXIST);
        core_user::require_active_user($user, true);
        $program = new program($programid);
        permission::require_can_view_program($userid, $program);

        /** @var renderer $output */
        $output = $PAGE->get_renderer('tool_catalogue');

        $related = [
            'userid' => $userid,
            'context' => $context,
            'program' => $program,
            'allocations' => manager::get_user_allocations($userid, $program->get('id')),
        ];
        $exporter = new program_exporter(null, $related);
        $program = (array)$exporter->export($output);

        // Convert the program structure to a flat structure and unset the tree structure. Program structure uses 'sets' and
        // 'courses' structures to return the content and they are different, and while is perfectly fine to use them in the
        // exporters, it does not seem possible to use them in web services due to some limitations.
        $baseset = $program['programstructure']->baseset;
        $flatstructure = \tool_catalogue\external\course_exporter::get_flat_structure($baseset->items);

        $sets = array_filter($flatstructure, static function($item) {
            return $item->isset;
        });
        $sets[] = $baseset;

        $courses = array_filter($flatstructure, static function($item) {
            return !$item->isset;
        });

        unset($program['programstructure']);

        return [
            'program' => $program,
            'programstructure' => [
                'sets' => $sets,
                'courses' => $courses,
            ],
        ];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'program' => program_exporter::get_read_structure(),
            'programstructure' => new external_single_structure([
                'sets' => new external_multiple_structure(set_exporter::get_read_structure()),
                'courses' => new external_multiple_structure(course_exporter::get_read_structure()),
            ]),
            'warnings' => new external_warnings(),
        ]);
    }
}

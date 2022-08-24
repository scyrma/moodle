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

use context_course;
use context_system;
use core_user;
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
 * External function get_user_catalogue_course for tool_catalogue.
 *
 * @package   tool_catalogue
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_user_catalogue_course extends external_api {

    /**
     * Describes the parameters for get_user_catalogue_course
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course id'),
            'userid' => new external_value(PARAM_INT, 'The user id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * External function to get user catalogue course
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function execute(int $courseid, int $userid = 0): array {
        global $PAGE, $USER;

        // Parameter validation.
        [
            'courseid' => $courseid,
            'userid' => $userid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_course::instance($courseid);
        self::validate_context($context);

        // Default userid to current userid if is not set.
        $userid = !empty($userid) ? $userid : (int) $USER->id;

        // Check permissions.
        $user = core_user::get_user($userid, '*', MUST_EXIST);
        core_user::require_active_user($user, true);
        $course = get_course($courseid);
        permission::require_can_view_course_cover($userid, $course);

        /** @var renderer $output */
        $output = $PAGE->get_renderer('tool_catalogue');

        $related = [
            'context' => context_course::instance($courseid),
            'course' => $course,
        ];
        $exporter = new course_exporter(null, $related);
        return [
            'course' => (array)$exporter->export($output),
        ];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'course' => course_exporter::get_read_structure(),
            'warnings' => new external_warnings(),
        ]);
    }
}

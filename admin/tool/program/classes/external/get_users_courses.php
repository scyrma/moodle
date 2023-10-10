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

/**
 * External function get_users_courses for tool_program.
 *
 * @package   tool_program
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use tool_program\api;
use core_enrol_external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/externallib.php');

/**
 * External function get_users_courses for tool_program.
 *
 * @package   tool_program
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_users_courses extends external_api {

    /**
     * Describes the parameters for get_users_courses.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return core_enrol_external::get_users_courses_parameters();
    }

    /**
     * External function to get the users courses.
     *
     * @param int $userid
     * @param bool $returnusercount
     * @return array of courses
     */
    public static function execute(int $userid, bool $returnusercount = true): array {
        // Parameter validation.
        [
            'userid' => $userid,
            'returnusercount' => $returnusercount,
        ] = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'returnusercount' => $returnusercount,
        ]);

        $enrolledincourses = core_enrol_external::get_users_courses($userid, $returnusercount);

        // This is necessary for consistency with other WS.
        $enrolledincourses = array_map(static function($course) {
            return (object) $course;
        }, $enrolledincourses);

        // If hideprogramcourses is set, remove courses with only enrol program from $enrolledincourses.
        return api::filter_by_hideprogramcourses($enrolledincourses);
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return core_enrol_external::get_users_courses_returns();
    }
}

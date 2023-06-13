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

namespace enrol_program\external;

use context_system;
use core_course_category;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use moodle_exception;

/**
 * External function get_instance_info for enrol_program.
 *
 * @package   enrol_program
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_instance_info extends external_api {

    /**
     * Describes the parameters for get_instance_info
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Instance id of program enrolment plugin')
        ]);
    }

    /**
     * External function to get_instance_info
     *
     * @param int $instanceid
     * @return array
     */
    public static function execute(int $instanceid): array {
        global $DB;

        // Parameter validation.
        [
            'instanceid' => $instanceid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
        ]);

        // Retrieve program enrolment plugin.
        /** @var \enrol_program_plugin $enrolplugin */
        $enrolplugin = enrol_get_plugin('program');
        if ($enrolplugin === null) {
            throw new moodle_exception('invaliddata', 'error');
        }

        self::validate_context(context_system::instance());

        $enrolinstance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $course = $DB->get_record('course', ['id' => $enrolinstance->courseid], '*', MUST_EXIST);
        if (!core_course_category::can_view_course_info($course) && !can_access_course($course)) {
            throw new moodle_exception('coursehidden');
        }

        $instanceinfo = $enrolplugin->get_enrol_info($enrolinstance);
        // Add programid to our information.
        $instanceinfo->programid = $enrolinstance->customint1;

        // Status returns string in case user cannot self enrol. Change to to boolean.
        $instanceinfo->canselfenrol = $instanceinfo->status === true;

        unset($instanceinfo->requiredparam);

        $result = [];
        $result['instanceinfo'] = $instanceinfo;
        $result['warnings'] = [];
        return $result;
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'instanceinfo' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Id of course enrolment instance'),
                'courseid' => new external_value(PARAM_INT, 'Id of course'),
                'programid' => new external_value(PARAM_INT, 'Id of program'),
                'type' => new external_value(PARAM_PLUGIN, 'Type of enrolment plugin'),
                'name' => new external_value(PARAM_RAW, 'Name of enrolment plugin'),
                'canselfenrol' => new external_value(PARAM_BOOL, 'Is the current user able to enrol by itself in the course?'),
            ]),
            'warnings' => new external_warnings()
        ]);
    }
}

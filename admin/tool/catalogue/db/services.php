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
 * Tool catalogue external functions and service definitions.
 *
 * @package    tool_catalogue
 * @category   external
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_catalogue\external\get_user_catalogue;
use tool_catalogue\external\get_user_catalogue_course;
use tool_catalogue\external\get_user_catalogue_program;
use tool_catalogue\external\get_user_catalogue_program_content;

defined('MOODLE_INTERNAL') || die;

$functions = [
    'tool_catalogue_get_user_catalogue' => [
        'classname' => get_user_catalogue::class,
        'description' => 'Get list of programs and courses user is enrolled in',
        'type' => 'read',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_catalogue_get_user_catalogue_program' => [
        'classname' => get_user_catalogue_program::class,
        'description' => 'Get the program allocation information from the user catalogue',
        'type' => 'read',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_catalogue_get_user_catalogue_program_content' => [
        'classname' => get_user_catalogue_program_content::class,
        'description' => 'Get the program content information from the user catalogue',
        'type' => 'read',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_catalogue_get_user_catalogue_course' => [
        'classname' => get_user_catalogue_course::class,
        'description' => 'Get the course allocation information from the user catalogue',
        'type' => 'read',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];

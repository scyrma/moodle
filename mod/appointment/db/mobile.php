<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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

/**
 * Appointment module mobile functions.
 *
 * @package    mod_appointment
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_appointment' => [
        'handlers' => [
            'courseappointment' => [
                'displaydata' => [
                    'title' => 'pluginname',
                    'icon' => $CFG->wwwroot . '/mod/appointment/pix/icon.svg',
                    'class' => '',
                ],

                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_sessions_view',
                'offlinefunctions' => [
                    'mobile_sessions_view' => [],
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'appointment'],
            ['capacity', 'appointment'],
            ['status', 'appointment'],
            ['details', 'appointment'],
            ['sessiondescription', 'appointment'],
            ['cancelbooking', 'appointment'],
            ['cancelreason', 'appointment'],
            ['confirmcancelbooking', 'appointment'],
            ['bookingcompleted', 'appointment'],
            ['bookingcancelled', 'appointment'],
        ],
    ],
];

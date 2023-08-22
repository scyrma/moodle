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
 * Events for tool_program
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use core\event\course_completed;
use core\event\course_deleted;
use core\event\user_deleted;

defined('MOODLE_INTERNAL') || die;

$observers = [
    [
        'eventname' => course_completed::class,
        'callback' => 'tool_program_observer::on_course_completed'
    ],
    [
        'eventname' => course_deleted::class,
        'callback' => 'tool_program_observer::course_deleted'
    ],
    [
        'eventname' => user_deleted::class,
        'callback' => 'tool_program_observer::user_deleted'
    ],
    [
        'eventname' => \tool_tenant\event\tenant_deleted::class,
        'callback' => 'tool_program_observer::tenant_deleted',
        'priority' => 100
    ],
    [
        'eventname' => \tool_tenant\event\tenant_updated::class,
        'callback' => 'tool_program_observer::tenant_updated'
    ],
    [
        'eventname' => \tool_tenant\event\tenant_user_updated::class,
        'callback' => 'tool_program_observer::tenant_user_updated'
    ],
];

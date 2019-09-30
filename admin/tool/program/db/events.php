<?php
// This file is part of Moodle - http://moodle.org/
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
 * Events for tool_program
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\event\course_completed;
use core\event\course_deleted;
use tool_program\event\program_completed;
use tool_program\event\user_allocation_created;
use tool_program\event\user_allocation_deleted;
use core\event\user_deleted;

defined('MOODLE_INTERNAL') || die;

$observers = [
    [
        'eventname' => course_completed::class,
        'callback' => 'tool_program_observer::on_course_completed'
    ],
    [
        'eventname' => program_completed::class,
        'callback' => 'tool_program_observer::on_program_completed'
    ],
    [
        'eventname' => user_allocation_created::class,
        'callback' => 'tool_program_observer::user_allocation_created'
    ],
    [
        'eventname' => user_allocation_deleted::class,
        'callback' => 'tool_program_observer::user_allocation_deleted'
    ],
    [
        'eventname' => course_deleted::class,
        'callback' => 'tool_program_observer::course_deleted'
    ],
    [
        'eventname' => user_deleted::class,
        'callback' => 'tool_program_observer::user_deleted'
    ],
];

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
 * Tool program external functions and service definitions.
 *
 * @package    tool_program
 * @category   external
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'tool_program_delete_set' => [
        'classname' => tool_program\external::class,
        'methodname' => 'delete_set',
        'description' => 'delete_set',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_program_delete_course' => [
        'classname' => tool_program\external::class,
        'methodname' => 'delete_course',
        'description' => 'delete course',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_program_deallocate_user' => [
        'classname' => tool_program\external::class,
        'methodname' => 'deallocate_user',
        'description' => 'deallocate user from a program',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_program_potential_courses_program_selector' => [
        'classname' => tool_program\external::class,
        'methodname' => 'potential_courses_program_selector',
        'description' => 'get list of courses to add to a set',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_program_move_program_item' => [
        'classname' => tool_program\external::class,
        'methodname' => 'move_program_item',
        'description' => 'move program item',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_program_submit_edit_program_set_completion_form' => [
        'classname' => tool_program\external::class,
        'methodname' => 'submit_edit_program_set_completion_form',
        'description' => 'submit edit program set completion form',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_program_update_program_visibility' => [
        'classname' => tool_program\external::class,
        'methodname' => 'update_program_visibility',
        'description' => 'Updates program visibility',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_program_delete_program' => [
        'classname' => tool_program\external::class,
        'methodname' => 'delete_program',
        'description' => 'Deletes a program',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_program_archive_program' => [
        'classname' => tool_program\external::class,
        'methodname' => 'archive_program',
        'description' => 'Archives a program',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_program_restore_program' => [
        'classname' => tool_program\external::class,
        'methodname' => 'restore_program',
        'description' => 'Restores a program',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_program_duplicate_program' => [
        'classname' => tool_program\external::class,
        'methodname' => 'duplicate_program',
        'description' => 'Duplicates a program',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_program_get_user_programs' => [
        'classname' => tool_program\external::class,
        'methodname' => 'get_user_programs',
        'description' => 'Return the list of programs user is allocated to,' .
            ' with the list of the sets and courses' .
            ' and the user progress in each program/course/set',
        'type' => 'read',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_program_enrol_user_to_course' => [
        'classname' => tool_program\external::class,
        'methodname' => 'enrol_user_to_course',
        'description' => 'Enrols user to a course using enrol program plugin',
        'type' => 'write',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_program_reset_program_progress' => [
        'classname' => tool_program\external::class,
        'methodname' => 'reset_program_progress',
        'description' => 'reset program progress',
        'type' => 'write',
        'ajax' => true,
    ],
];

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
 * Services for tool_certification.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'tool_certification_archive_certification' => [
        'classname' => tool_certification\external::class,
        'methodname' => 'archive_certification',
        'description' => 'Archives a certification',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_certification_restore_certification' => [
        'classname' => tool_certification\external::class,
        'methodname' => 'restore_certification',
        'description' => 'Restores a certification',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_certification_delete_certification' => [
        'classname' => tool_certification\external::class,
        'methodname' => 'delete_certification',
        'description' => 'Deletes a certification',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_certification_potential_program_selector' => [
        'classname' => tool_certification\external::class,
        'methodname' => 'potential_program_selector',
        'description' => 'Get list of programs',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_certification_potential_certification_selector' => [
        'classname' => tool_certification\external::class,
        'methodname' => 'potential_certification_selector',
        'description' => 'Get list of certifications',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_certification_deallocate_user' => [
        'classname' => tool_certification\external::class,
        'methodname' => 'deallocate_user',
        'description' => 'Deallocate user from a certification',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_certification_certify_user' => [
        'classname' => tool_certification\external::class,
        'methodname' => 'certify_user',
        'description' => 'Certifies a user in a certification',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_certification_revoke_certification' => [
        'classname' => tool_certification\external::class,
        'methodname' => 'revoke_certification',
        'description' => 'Revokes a certification from a user',
        'type' => 'write',
        'ajax' => true,
    ],
];

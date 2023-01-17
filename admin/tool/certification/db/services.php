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
 * Services for tool_certification.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
    'tool_certification_get_certification_user_log' => [
        'classname' => tool_certification\external::class,
        'methodname' => 'get_certification_user_log',
        'description' => 'Gets the event log from a user certification',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_certification_bulk_deallocate_user' => [
        'classname' => tool_certification\external::class,
        'methodname' => 'bulk_deallocate_user',
        'description' => 'Deallocates a list of users from a certification',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_certification_get_certifications' => [
        'classname' => tool_certification\external\get_certifications::class,
        'description' => 'Get certifications (list of certifications in the active tenant)',
        'type' => 'read',
    ],
    'tool_certification_get_certification_allocations' => [
        'classname' => tool_certification\external\get_certification_allocations::class,
        'description' => 'Get list of allocated users into a certification',
        'type' => 'read',
    ],
    'tool_certification_get_certification_user_allocation' => [
        'classname' => tool_certification\external\get_certification_user_allocation::class,
        'description' => 'Get user allocation details',
        'type' => 'read',
    ],
    'tool_certification_get_user_certification_allocations' => [
        'classname' => tool_certification\external\get_user_certification_allocations::class,
        'description' => 'Get all current user certification allocations',
        'type' => 'read',
    ],
];

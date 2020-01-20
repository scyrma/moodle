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
 * Tool organisation external functions and service definitions.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'tool_organisation_department_move' => [
        'classname' => tool_organisation_external::class,
        'methodname' => 'department_move',
        'description' => 'change sortorder of departments',
        'type' => 'write',
        'ajax' => true,
    ],

    'tool_organisation_position_move' => [
        'classname' => tool_organisation_external::class,
        'methodname' => 'position_move',
        'description' => 'change sortorder of positions',
        'type' => 'write',
        'ajax' => true,
    ],

    'tool_organisation_department_delete' => [
        'classname' => tool_organisation_external::class,
        'methodname' => 'department_delete',
        'description' => 'Delete department(s) if they don\'t have jobs in the hierarchy',
        'type' => 'write',
        'ajax' => true,
    ],

    'tool_organisation_position_delete' => [
        'classname' => tool_organisation_external::class,
        'methodname' => 'position_delete',
        'description' => 'Delete department(s) if they don\'t have jobs in the hierarchy',
        'type' => 'write',
        'ajax' => true,
    ],

    'tool_organisation_job_delete' => [
        'classname' => tool_organisation_external::class,
        'methodname' => 'job_delete',
        'description' => 'delete job',
        'type' => 'write',
        'ajax' => true,
    ],

    'tool_organisation_create_departments' => [
        'classname' => tool_organisation_external::class,
        'methodname' => 'create_departments',
        'description' => 'Create departments',
        'type' => 'write',
        'ajax' => true,
    ],

    'tool_organisation_create_positions' => [
        'classname' => tool_organisation_external::class,
        'methodname' => 'create_positions',
        'description' => 'Create positions',
        'type' => 'write',
        'ajax' => true,
    ],

    'tool_organisation_is_jobs_tab_available' => [
        'classname' => tool_organisation_external::class,
        'methodname' => 'is_jobs_tab_available',
        'description' => 'Check if jobs tab can be accessed',
        'type' => 'read',
        'ajax' => true,
    ],
];

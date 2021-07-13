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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

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
    'tool_organisation_create_job' => [
        'classname' => 'tool_organisation\external\create_job',
        'methodname' => 'execute',
        'description' => 'Create a new job',
        'type' => 'write',
    ],
    'tool_organisation_update_job' => [
        'classname' => 'tool_organisation\external\update_job',
        'methodname' => 'execute',
        'description' => 'Update job',
        'type' => 'write',
    ],
    'tool_organisation_get_managed_users' => [
        'classname' => tool_organisation_external::class,
        'methodname' => 'get_managed_users',
        'description' => 'Get the list of managed users',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ],
    'tool_organisation_get_teams_tab_filters' => [
        'classname' => tool_organisation_external::class,
        'methodname' => 'get_teams_tab_filters',
        'description' => 'Get the list of filters for the Teams tab',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ],
    'tool_organisation_get_potential_parent_departments' => [
        'classname' => tool_organisation\external\get_potential_parent_departments::class,
        'methodname' => 'execute',
        'description' => 'Get the list of potential parents for a department',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_organisation_get_potential_parent_positions' => [
        'classname' => tool_organisation\external\get_potential_parent_positions::class,
        'methodname' => 'execute',
        'description' => 'Get the list of potential parents for a position',
        'type' => 'read',
        'ajax' => true,
    ],
];

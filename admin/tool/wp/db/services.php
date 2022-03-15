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
 * Tool tenant external functions and service definitions.
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'tool_wp_perform_export' => [
        'classname' => \tool_wp\external\perform_export::class,
        'methodname' => 'execute',
        'description' => 'perform export',
        'type' => 'write',
    ],
    'tool_wp_get_export_file' => [
        'classname' => \tool_wp\external\get_export_file::class,
        'methodname' => 'execute',
        'description' => 'Get export file',
        'type' => 'read',
    ],
    'tool_wp_perform_import' => [
        'classname' => \tool_wp\external\perform_import::class,
        'methodname' => 'execute',
        'description' => 'Perform import',
        'type' => 'write',
    ],
    'tool_wp_potential_users_selector' => [
        'classname' => tool_wp_external::class,
        'methodname' => 'potential_users_selector',
        'description' => 'get list of users',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_wp_get_tab_content' => [
        'classname' => tool_wp_external::class,
        'methodname' => 'get_tab_content',
        'description' => 'get tab content',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_wp_modal_form' => [
        'classname' => tool_wp_external::class,
        'methodname' => 'modal_form',
        'description' => 'process submission of a modal form',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_wp_delete_export' => [
        'classname' => tool_wp_external::class,
        'methodname' => 'delete_export',
        'description' => 'deletes an export instance',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_wp_delete_import' => [
        'classname' => tool_wp_external::class,
        'methodname' => 'delete_import',
        'description' => 'deletes an import instance',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_wp_export' => [
        'classname' => tool_wp_external::class,
        'methodname' => 'export',
        'description' => 'displays configuration forms, schedules export and displays information about export',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_wp_import' => [
        'classname' => tool_wp_external::class,
        'methodname' => 'import',
        'description' => 'displays configuration forms, schedules import and displays information about import',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_wp_get_export_status' => [
        'classname' => tool_wp_external::class,
        'methodname' => 'get_export_status',
        'description' => 'gets export status and progress',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_wp_get_import_status' => [
        'classname' => tool_wp_external::class,
        'methodname' => 'get_import_status',
        'description' => 'gets import status and progress',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_wp_export_file_preview' => [
        'classname' => \tool_wp\external\export_file_preview::class,
        'methodname' => 'execute',
        'description' => 'Retrieve export file preview for given import ID',
        'type' => 'read',
        'ajax' => true,
    ]
];

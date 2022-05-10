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
 * Report builder external functions and service definitions.
 *
 * @package    tool_reportbuilder
 * @category   external
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'tool_reportbuilder_add_report_column' => [
        'classname' => tool_reportbuilder\external::class,
        'methodname' => 'add_report_column',
        'classpath' => '',
        'description' => 'Add a new column to the report',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_remove_report_column' => [
        'classname' => tool_reportbuilder\external::class,
        'methodname' => 'remove_report_column',
        'classpath' => '',
        'description' => 'Add a new column to the report',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_add_filter' => [
        'classname' => tool_reportbuilder\external\filters::class,
        'methodname' => 'add_filter',
        'classpath' => '',
        'description' => 'Add a new filter to the report',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_delete_report_filter' => [
        'classname' => tool_reportbuilder\external::class,
        'methodname' => 'delete_report_filter',
        'classpath' => '',
        'description' => 'Delete a report filter',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_reorder_report_filters' => [
        'classname' => tool_reportbuilder\external::class,
        'methodname' => 'reorder_report_filters',
        'classpath' => '',
        'description' => 'Change the order of a report filter',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_get_report_sortable_columns' => [
        'classname' => tool_reportbuilder\external::class,
        'methodname' => 'get_report_sortable_columns',
        'classpath' => '',
        'description' => 'Get the columns active in the report that can be sortable',
        'ajax' => true,
        'type' => 'read'
    ],
    'tool_reportbuilder_toggle_report_sorting_column' => [
        'classname' => tool_reportbuilder\external::class,
        'methodname' => 'toggle_report_sorting_column',
        'classpath' => '',
        'description' => 'Toggle the sortenabled status of a columns (enabled/disabled)',
        'ajax' => true,
        'type' => 'read'
    ],
    'tool_reportbuilder_toggle_column_sorting_direction' => [
        'classname' => tool_reportbuilder\external::class,
        'methodname' => 'toggle_column_sorting_direction',
        'classpath' => '',
        'description' => 'Toggle the sort direction of a column',
        'ajax' => true,
        'type' => 'read'
    ],
    'tool_reportbuilder_reorder_sortable_column' => [
        'classname' => tool_reportbuilder\external::class,
        'methodname' => 'reorder_sortable_column',
        'classpath' => '',
        'description' => 'Change the order of sortable columns',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_add_report_condition' => [
        'classname' => tool_reportbuilder\external\conditions::class,
        'methodname' => 'add_report_condition',
        'classpath' => '',
        'description' => 'Add a new condition to the report',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_delete_condition' => [
        'classname' => tool_reportbuilder\external\conditions::class,
        'methodname' => 'delete_condition',
        'classpath' => '',
        'description' => 'Remove a report condition',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_get_reportbuilder' => [
        'classname' => tool_reportbuilder\external\report::class,
        'methodname' => 'get_reportbuilder',
        'classpath' => '',
        'description' => 'Get the reportbuilder complete view.',
        'ajax' => true,
        'type' => 'read'
    ],
    'tool_reportbuilder_delete_schedule' => [
        'classname' => tool_reportbuilder\external\schedule::class,
        'methodname' => 'delete_schedule',
        'classpath' => '',
        'description' => 'Delete the given schedule',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_send_schedule' => [
        'classname' => tool_reportbuilder\external\schedule::class,
        'methodname' => 'send_schedule',
        'classpath' => '',
        'description' => 'Send the given schedule',
        'ajax' => true,
        'type' => 'read'
    ],
    'tool_reportbuilder_toggle_schedule' => [
        'classname' => tool_reportbuilder\external\schedule::class,
        'methodname' => 'toggle_schedule',
        'description' => 'Toggle the schedule enabled state',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_delete_report' => [
        'classname' => tool_reportbuilder\external\report::class,
        'methodname' => 'delete_report',
        'classpath' => '',
        'description' => 'Delete a report and all related data',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_reset_filter' => [
        'classname' => tool_reportbuilder\external\filters::class,
        'methodname' => 'reset_filter',
        'classpath' => '',
        'description' => 'Reset a filter',
        'ajax' => true,
        'type' => 'read'
    ],
    'tool_reportbuilder_reorder_columns_filter' => [
        'classname' => tool_reportbuilder\external\columns::class,
        'methodname' => 'reorder_columns',
        'classpath' => '',
        'description' => 'Reorder the columns',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_reset_all_conditions' => [
        'classname' => tool_reportbuilder\external\conditions::class,
        'methodname' => 'reset_all',
        'classpath' => '',
        'description' => 'Reset all conditions',
        'ajax' => true,
        'type' => 'read'
    ],
    'tool_reportbuilder_reset_condition' => [
        'classname' => tool_reportbuilder\external\conditions::class,
        'methodname' => 'reset_condition',
        'classpath' => '',
        'description' => 'Reset a condition',
        'ajax' => true,
        'type' => 'read'
    ],
    'tool_reportbuilder_sort_table_by_heading' => [
        'classname' => tool_reportbuilder\external\columns::class,
        'methodname' => 'sort_table_by_heading',
        'classpath' => '',
        'description' => 'Sort table by heading',
        'ajax' => true,
        'type' => 'write'
    ],
    'tool_reportbuilder_reset_table' => [
        'classname' => tool_reportbuilder\external\report::class,
        'methodname' => 'reset_table',
        'classpath' => '',
        'description' => 'Reset report filters and table heading sort',
        'ajax' => true,
        'type' => 'write'
    ],
];

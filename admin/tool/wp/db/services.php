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
 * Tool tenant external functions and service definitions.
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
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
];

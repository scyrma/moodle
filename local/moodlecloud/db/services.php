<?php declare(strict_types=1);
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
 * External functions and service definitions.
 *
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_moodlecloud_get_popup_notifications' => [
        'classname' => 'local_moodlecloud_external',
        'methodname' => 'get_popup_notifications',
        'description' => 'Retrieve a list of notifications for the admin',
        'type' => 'read',
        'ajax' => true,
    ],

    'local_moodlecloud_delete_notification' => [
        'classname' => 'local_moodlecloud_external',
        'methodname' => 'delete_notification',
        'description' => 'Delete a notification',
        'type' => 'read',
        'ajax' => true,
    ],

    'local_moodlecloud_delete_all_notifications' => [
        'classname' => 'local_moodlecloud_external',
        'methodname' => 'delete_all_notifications',
        'description' => 'Delete all notifications',
        'type' => 'write',
        'ajax' => true,
    ]
];

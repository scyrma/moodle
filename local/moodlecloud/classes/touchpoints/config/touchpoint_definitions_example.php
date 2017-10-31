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
 * Simple touchpoint definitions file.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

return [
    (object)[
        "name" => "Send user limit warning",
        "cooldown" => 604800,
        "criteria" => [
            (object)[
                "name" => "reached_user_quota_percentage",
                "arguments" => [0.8]
            ]
        ],
        "actions" => [
            (object)[
                "name" => "signup_touchpoint",
                "arguments" => [
                    "reached_user_quota_percentage",
                    [
                        "quotaUsed" => MOODLECLOUD_USER_QUOTA - \local_moodlecloud\restrictions\userquota::number_of_user_slots_remaining()
                    ]
                ]
            ]
        ]
    ],
    (object)[
        "name" => "Send user limit reached",
        "cooldown" => 604800,
        "criteria" => [
            (object)[
                "name" => "reached_user_quota",
                "arguments" => []
            ]
        ],
        "actions" => [
            (object)[
                "name" => "signup_touchpoint",
                "arguments" => [
                    "reached_user_quota",
                    []
                ]
            ]
        ]
    ],
    (object)[
        "name" => "Send file storage limit warning",
        "cooldown" => 604800,
        "criteria" => [
            (object)[
                "name" => "reached_file_quota_percentage",
                "arguments" => [0.8]
            ]
        ],
        "actions" => [
            (object)[
                "name" => "signup_touchpoint",
                "arguments" => [
                    "reached_file_quota_percentage",
                    [
                        "quotaUsed" => \local_filestorage\file_storage\file_system_s3::unique_storage_size_used()
                    ]
                ]
            ]
        ]
    ],
    (object)[
        "name" => "Send file storage limit reached",
        "cooldown" => 604800,
        "criteria" => [
            (object)[
                "name" => "reached_file_quota",
                "arguments" => []
            ]
        ],
        "actions" => [
            (object)[
                "name" => "signup_touchpoint",
                "arguments" => [
                    "reached_file_quota",
                    []
                ]
            ]
        ]
    ]
];

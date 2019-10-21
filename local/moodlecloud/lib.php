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
 * Local admin notifications.
 *
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\restrictions\userquota;
use local_filestorage\file_storage\file_system_s3;

function local_moodlecloud_is_super_admin(array $superadmins, int $userid) : bool {
    return in_array($userid, $superadmins);
}

function local_moodlecloud_render_navbar_output(renderer_base $renderer) {
    global $USER, $CFG, $DB, $PAGE;

    if (!is_siteadmin()) {
        return '';
    }

    require_once($CFG->dirroot . '/local/filestorage/lib.php');

    $userpercentage = userquota::site_has_unlimited_quota() ? 1 : min(1, 1 - userquota::number_of_user_slots_remaining() / MOODLECLOUD_USER_QUOTA);
    $storagepercentage = local_filestorage_site_has_unlimited_quota() ? 1 : min(1, file_system_s3::unique_storage_size_used() / FILESTORAGE_QUOTA);

    return
        (($DB->count_records('moodlecloud_notifications') === 0)
            ? ""
            : $renderer->render_from_template(
                  'local_moodlecloud/notification_popover',
                  [
                      'urls' =>
                      [
                          'preferences' => (new moodle_url(
                              '/admin/settings.php',
                              ['section' => 'moodlecloudnotifications'])
                          )->out(true)
                      ]
                  ]
            )
        )
        .
        $renderer->render_from_template(
            'local_moodlecloud/quota_popover',
            [
                'userpercentage' => $userpercentage,
                'userpercentagestatus' => userquota::site_has_unlimited_quota() ? 'ok' : ['ok', 'warn', 'danger'][min(2, floor($userpercentage * 3))],
                'userpercentagedashoffset' => 440 * (1 - $userpercentage),
                'storagepercentage' => $storagepercentage,
                'storagepercentagestatus' => local_filestorage_site_has_unlimited_quota() ? 'ok' : ['ok', 'warn', 'danger'][min(2, floor($storagepercentage * 3))],
                'storagepercentagedashoffset' => 440 * (1 - $storagepercentage),
                'users' => userquota::get_user_count(),
                'totalusers' => userquota::site_has_unlimited_quota() ? get_string('unlimited', 'local_moodlecloud') : MOODLECLOUD_USER_QUOTA ,
                'mb' => round(file_system_s3::unique_storage_size_used()/(1024**2)),
                'totalmb' => local_filestorage_site_has_unlimited_quota() ? get_string('unlimited', 'local_moodlecloud') : FILESTORAGE_QUOTA/(1024**2),
                'urls' => [
                    'seeall' => (new moodle_url('/admin/tool/fileslist'))->out()
                ],
                'theme' => $PAGE->theme->name
            ]
        );
}

function local_moodlecloud_get_fontawesome_icon_map() {
    return [
        'local_moodlecloud:i/notifications' => 'fa-info-circle'
    ];
}

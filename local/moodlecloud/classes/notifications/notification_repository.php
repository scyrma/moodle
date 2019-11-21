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
 * Notification repository.
 *
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\notifications;
defined('MOODLE_INTERNAL') || die();

use DateTimeImmutable;
use moodle_database;
use stdClass;

/**
 * Notification repository.
 *
 * @copyright 2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_repository {
    /** @var moodle_database $db The database. */
    private $db;

    /**
     * Constructor.
     *
     * @param moodle_database $db The database.
     */
    public function __construct(moodle_database $db) {
        $this->db = $db;
    }

    /**
     * Retrieve all the notifications from the DB.
     *
     * @return notification[] Array of notifications
     */
    public function get_notifications() : array {
        return $this->transform_from_db(
            $this->db->get_records('moodlecloud_notifications')
        );
    }

    /**
     * Get a notification by its ID.
     *
     * @param int $id The notification ID.
     * @return notification
     */
    public function get_notification_by_id(int $id) : notification {
        return ($this->transform_from_db(
            [$this->db->get_record('moodlecloud_notifications', ['id' => $id])]
        ))[0];
    }

    /**
     * Transform an array of DB rows to notifications.
     *
     * @param stdClass[] Array of rows from the database.
     * @return notification[] Array of notifications.
     */
    private function transform_from_db(array $rows) : array {
        return array_map(
            function(stdClass $row) : notification {
                return new notification(
                    (int)$row->id,
                    $row->name,
                    $row->body,
                    (new DateTimeImmutable())->setTimestamp((int)$row->created),
                    (int)$row->level,
                    $row->source
                );
            },
            $rows
        );
    }

    /**
     * Save a notification to the database.
     *
     * Only creates new notifications in the DB. Cannot update existing ones.
     *
     * @param notification $notification The notification to save.
     * @param ?string $category Optional category to associate with the notification.
     * @return notification
     */
    public function save(notification $notification, string $category = null) : notification {
        $this->db->insert_record(
            'moodlecloud_notifications',
            (object)([
                'name' => $notification->get_name(),
                'body' => $notification->get_body(),
                'created' => $notification->get_date()->getTimestamp(),
                'level' => $notification->get_level(),
                'source' => $notification->get_source()
            ] + ($category ? ['category' => $category] : []))
        );

        return $notification;
    }

    /**
     * Delete a notification from the database.
     *
     * @param notification $notification
     */
    public function delete(notification $notification) {
        $this->db->delete_records(
            'moodlecloud_notifications',
            [
                'created' => $notification->get_date()->getTimestamp(),
                'name' => $notification->get_name()
            ]
        );
    }

    public function delete_all() {
        $this->db->delete_records_select('moodlecloud_notifications', 'id > 0');
    }
}

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
 * Various external MoodleCloud APIs.
 *
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\notifications\external\notifications_exporter;
use local_moodlecloud\notifications\notification;
use local_moodlecloud\notifications\notification_repository;

require_once($CFG->libdir . '/externallib.php');

final class local_moodlecloud_external extends external_api {
    /**
     * Get popup notifications to display in the custom MoodleCloud popover.
     *
     * @return string
     */
    public static function get_popup_notifications() {
        if (!is_siteadmin()) {
            throw new moodle_exception('nopermissions', 'error', '', 'access MoodleCloud site notifications');
        }

        global $PAGE, $CFG, $DB;

        self::validate_context(context_system::instance());
        return (new notifications_exporter(
            (new notification_repository($DB))->get_notifications()
        ))->export($PAGE->get_renderer('core'));
    }

    /**
     * Delete a MoodleCloud notification.
     *
     * @param int $id ID of the notification to delete.
     */
    public static function delete_notification(int $id) {
        if (!is_siteadmin()) {
            throw new moodle_exception('nopermissions', 'error', '', 'access MoodleCloud site notifications');
        }

        global $DB;
        self::validate_context(context_system::instance());
        $repo = new notification_repository($DB);
        $repo->delete($repo->get_notification_by_id($id));
    }

    /**
     * Delete all MoodleCloud notifications.
     */
    public static function delete_all_notifications() {
        if (!is_siteadmin()) {
            throw new moodle_exception('nopermissions', 'error', '', 'access MoodleCloud site notifications');
        }

        global $DB;
        self::validate_context(context_system::instance());

        $repo = new notification_repository($DB);
        $repo->delete_all();
    }

    protected static function get_popup_notifications_returns() {
        return notifications_exporter::get_read_structure();
    }

    protected static function get_popup_notifications_parameters() {
        return new external_function_parameters([]);
    }

    protected static function delete_notification_parameters() {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_INT, 'Notification ID', VALUE_REQUIRED, '', NULL_NOT_ALLOWED)
            ]
        );
    }

    protected static function delete_notification_returns() {
        return null;
    }


    protected static function delete_all_notifications_parameters() {
        return new external_function_parameters([]);
    }

    protected static function delete_all_notifications_returns() {
        return null;
    }

}

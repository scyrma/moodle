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
 * This module retrieves notifications from the server.
 *
 * @module     local_moodlecloud/notification_popover_controller
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    return {
        /**
         * Retrieve a list of notifications.
         *
         * @method query
         * @return {promise} Resolved with an array of notification objects.
         */
        query: function() {
            return Ajax.call([
                {
                    methodname: 'local_moodlecloud_get_popup_notifications',
                    args: []
                }
            ])[0].fail(Notification.exception);
        },

        /**
         * Delete a notification.
         *
         * @method delete
         * @return {promise} Resolved when the notification is deleted.
         */
        delete: function(notificationid) {
            return Ajax.call([
                {
                    methodname: 'local_moodlecloud_delete_notification',
                    args: {
                        id: notificationid
                    }
                }
            ])[0].fail(Notification.exception);
        },

        /**
         * Delete all the notifications.
         *
         * @method deleteAll
         * @return {promise} Resolved when the notifications are deleted.
         */
        deleteAll: function() {
            return Ajax.call([
                {
                    methodname: 'local_moodlecloud_delete_all_notifications',
                    args: []
                }
            ])[0].fail(Notification.exception);
        }
    };
});

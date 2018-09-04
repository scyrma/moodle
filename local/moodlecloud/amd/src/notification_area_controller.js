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
 * This module retrieves renders the admin notifcations page.
 *
 * @module     local_moodlecloud/notification_area_controller
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    [
    'jquery',
    'core/templates',
    'core/custom_interaction_events',
    'local_moodlecloud/notification_repository'
    ],
    function(
        $,
        Templates,
        CustomEvents,
        NotificationRepo
    ) {
        var SELECTORS = {
            COUNT_CONTAINER: '#nav-moodlecloud-notification-popover-container .count-container',
            POPOVER_CONTAINER: '#nav-moodlecloud-notification-popover-container',
            PLACEHOLDER: '#moodlecloud-notifications-empty'
        };

        /**
         * Register the event listeners for the root element.
         *
         * @mathod registerEventListeners
         * @param {jQuery} root The root element
         */
        var registerEventListeners = function(root) {
            CustomEvents.define(root, [CustomEvents.events.activate]);
            root.on(CustomEvents.events.activate, '.close', function(e) {
                NotificationRepo.delete(
                    $(e.currentTarget).closest('[data-notification-id]').attr('data-notification-id')
                );

                $(SELECTORS.COUNT_CONTAINER).text(
                    parseInt($(SELECTORS.COUNT_CONTAINER).text()) - 1
                );

                if (parseInt($(SELECTORS.COUNT_CONTAINER).text()) === 0) {
                    $(SELECTORS.POPOVER_CONTAINER).hide();
                    $(SELECTORS.PLACEHOLDER).show();
                }
            });
        };

        return {
            /**
             * Initialise the root element.
             *
             * @method init
             * @return {promise} Resolved when notifications are added to the root element.
             */
            init : function(root) {
                // This shouldn't really happen, but I suppose someone could access the notifications
                // page when there are no notifications (even though there is no link to it in that case)
                if (!$(SELECTORS.POPOVER_CONTAINER).length) {
                    $(SELECTORS.PLACEHOLDER).show();
                }

                registerEventListeners(root);
                return NotificationRepo.query().then(function(result) {
                    return Templates.render(
                        'local_moodlecloud/notification_area_list',
                        {
                            notifications: result.notifications.map(function(e) {
                                return {
                                    message: e.date + ': ' + e.body,
                                    closebutton: 1,
                                    id: e.id,
                                    is_info: e.is_info,
                                    is_warning: e.is_warning,
                                    is_error: e.is_error
                                };
                            })
                        }
                    ).done(function(html, js) {
                        Templates.replaceNodeContents(root, html, js);
                    });
                });
            }
        };
    }
);

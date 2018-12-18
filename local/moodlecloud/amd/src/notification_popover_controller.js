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
 * This module retrieves notifications and render them in an element.
 *
 * JS based classical inheritance is lame, but that's the way the popovers were designed
 * so that's what we have to work with.
 *
 * @module     local_moodlecloud/notification_popover_controller
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    [
        'jquery',
        'core/ajax',
        'core/templates',
        'core/popover_region_controller',
        'core/custom_interaction_events',
        'local_moodlecloud/notification_repository'
    ],
    function($, Ajax, Templates, PopoverController, CustomEvents, NotificationRepo) {
        var SELECTORS = {
            COUNT_CONTAINER: '[data-region="count-container"]',
            MARK_ALL_READ: '[data-action="mark-all-read"]',
            CONTENT: '.popover-region-content',
            POPOVER_CONTAINER: '#nav-moodlecloud-notification-popover-container'
        };

        /**
         * Register the event listeners for the root element.
         *
         * @mathod registerEventListeners
         * @param {jQuery} root The root element
         */
        var registerEventListeners = function(root) {
            root.on(CustomEvents.events.activate, SELECTORS.MARK_ALL_READ, function(e) {
                NotificationRepo.deleteAll();
                $(SELECTORS.POPOVER_CONTAINER).hide();
                e.stopPropagation();
            });
        };

        /**
         * Constructor.
         * Extends PopoverController.
         *
         * @param {jQuery} root The root element of the popover.
         */
        var NotificationPopoverController = function(root) {
            registerEventListeners(root);
            PopoverController.call(this, root);
            this.root = root;
        };

        NotificationPopoverController.prototype = Object.create(PopoverController.prototype);
        NotificationPopoverController.prototype.constructor = NotificationPopoverController;

        /**
         * Renders notifications in the root element.
         *
         * @method renderNotifications
         * @return {promise} Resolved when the notifications are added to the root element.
         */
        NotificationPopoverController.prototype.renderNotifications = function() {
            return NotificationRepo.query().then(function(result) {
                this.renderUnreadCount(result.notifications.length);
                return Templates.render(
                    'local_moodlecloud/notification_popover_list',
                    result
                ).done(function(html, js) {
                    Templates.replaceNodeContents(this.root.find(SELECTORS.CONTENT), html, js);
                }.bind(this));
            }.bind(this));
        };

        /**
         * Renders the notification count.
         *
         * @method renderNotifications
         * @return {promise} Resolved when the notifications are added to the root element.
         */
        NotificationPopoverController.prototype.renderUnreadCount = function(count) {
            if (count > 0) {
                this.root.find(SELECTORS.COUNT_CONTAINER).text(count).removeClass('hidden');
            }
        };

        return NotificationPopoverController;
    }
);

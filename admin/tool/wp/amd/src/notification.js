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
 * A system for displaying notifications to users.
 *
 * @module     tool_wp/notification
 * @package    tool_wp
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/notification'
], function($, Notification) {

    var thisModule = {
        types: {
            'success': 'tool_wp/notification_success',
            'info': 'tool_wp/notification_info',
            'warning': 'tool_wp/notification_warning',
            'error': 'tool_wp/notification_error'
        },

        container: 'wp-notifications-container',

        addNotification: function(notification) {
            thisModule.setupTargetRegion();

            notification = $.extend({
                closebutton: true,
                announce: true,
                type: 'error',
                autohide: true,
                autohidedelay: 5000,
                showprogressbar: true,
                hideduration: 2000
            }, notification);

            var template = thisModule.types.error;
            if (notification.template) {
                template = notification.template;
                delete notification.template;
            } else if (notification.type) {
                if (typeof thisModule.types[notification.type] !== 'undefined') {
                    template = thisModule.types[notification.type];
                }
                delete notification.type;
            }

            return thisModule.renderNotification(template, notification);
        },

        animateProgressBar: function($progressBar, totaltime) {
            var currentTimeLeft = totaltime;
            var intervalTime = 10; // Milliseconds.
            return setInterval(function() {
                currentTimeLeft = currentTimeLeft - intervalTime;
                $progressBar.width(Math.round(10000 * currentTimeLeft / totaltime) / 100 + '%');
            }, intervalTime);
        },

        animateNotification: function($notification, variables) {
            if (variables.autohide) {
                var progressBarInterval = null;
                if (variables.showprogressbar) {
                    var $progressBar = $('<div class="wp-notification-progress-bar"></div>');
                    $notification.append($progressBar);
                    var progressTotalTime = variables.autohidedelay + variables.hideduration;
                    progressBarInterval = thisModule.animateProgressBar($progressBar, progressTotalTime);
                }

                var autoHideTimer = setTimeout(function() {
                    $notification.fadeOut(variables.hideduration, function() {
                        $notification.remove();
                        clearInterval(progressBarInterval);
                    });
                    clearTimeout(autoHideTimer);
                }, variables.autohidedelay);

                $notification.on('closed.bs.alert', function() {
                    clearTimeout(autoHideTimer);
                    clearInterval(progressBarInterval);
                });
            }
        },

        renderNotification: function(template, variables) {
            if (typeof variables.message === 'undefined' || !variables.message) {
                return;
            }
            require(['core/templates'], function(templates) {
                templates.render(template, variables)
                    .done(function(html, js) {
                        var $notification = $(html);
                        thisModule.animateNotification($notification, variables);
                        $('#' + thisModule.container).append($notification);
                        templates.runTemplateJS(js);
                    })
                    .fail(Notification.exception);
            });
        },

        setupTargetRegion: function() {
            var targetRegion = $('#' + thisModule.container);
            if (targetRegion.length) {
                return false;
            }

            var newRegion = $('<span>').attr('id', thisModule.container);
            targetRegion = $('body');

            return targetRegion.prepend(newRegion);
        }
    };

    return /** @alias module:tool_wp/notification */{
        /**
         * Add a notification to the page.
         *
         * Note: This does not cause the notification to be added to the session.
         *
         * @method addNotification
         * @param {Object}  notification                    The notification to add.
         * @param {string}  notification.message            The body of the notification
         * @param {string}  notification.type               The type of notification to add (error, warning, info, success).
         * @param {Boolean} notification.closebutton        Whether to show the close button.
         * @param {Boolean} notification.announce           Whether to announce to screen readers.
         * @param {Boolean} notification.autohide           Wether this floating notification hides automatically after some
         *                                                  time (defaults to true).
         * @param {Number}  notification.autohidedelay      The amount of time (in ms) after which the floating notification
         *                                                  will automatically hide (defaults to 5000).
         * @param {Boolean} notification.showprogressbar    Wether this floating notification will show "progress" bar
         *                                                  showing the time left until the notification hides (defaults to true).
         * @param {Number}  notification.hideduration       The duration (in ms) of the hiding/fade out animation
         *                                                  (defaults to 2000).
         */
        addNotification: thisModule.addNotification
    };
});

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
 * This module instantiates the functionality reports main view.
 *
 * @module     tool_reportbuilder/schedules_manager
 * @package    tool_reportbuilder
 * @copyright  2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
        'jquery',
        'core/custom_interaction_events',
        'core/url',
        'core/str',
        'core/ajax',
        'core/modal_factory',
        'core/modal_events',
        'tool_wp/notification',
        'tool_wp/tabs',
        'tool_reportbuilder/reportbuilder_helper',
        'tool_wp/modal_form',
        'core/config'
    ],
    function($,
              CustomEvents,
              URL,
              Str,
              Ajax,
              ModalFactory,
              ModalEvents,
              Notification,
              Tabs,
              Helper,
              ModalForm,
              Config
    ) {

        /** @type {Object} The list of selectors for the reports area. */
        var SELECTORS = {
            ADDSCHEDULE: "[data-tabs-element='addbutton']",
            DELETESCHEDULE: "[data-action='delete']",
            SCHEDULESLIST: "[data-region='schedules-list']",
            TOGGLESTATUS: "[data-action='togglestatus']",
            DUPLICATE: "[data-action='duplicate']",
            SEND: "[data-action='send']",
            EDIT: "[data-action='edit']"
        },
        SERVICES = {
            DELETESCHEDULE: 'tool_reportbuilder_delete_schedule',
            SENDCHEDULE: 'tool_reportbuilder_send_schedule'
        };

        /**
         * Messagearea class.
         *
         * @param {String} selector The selector for the page region containing the reports area.
         * @param {Number} reportid
         */
        function SchedulesManager(selector, reportid) {
            this.node = $(selector);
            this.reportid = reportid;
            this._init();
        }

        /** @type {jQuery} The jQuery node for the page region containing the reports area. */
        SchedulesManager.prototype.node = null;

        /** @type {int} The main ID of the report */
        SchedulesManager.prototype.reportid = -1;

        /**
         * Initialise the other objects we require.
         */
        SchedulesManager.prototype._init = function() {
            CustomEvents.define(this.node, [
                CustomEvents.events.activate
            ]);

            Helper.onDelegateEvent(this.node, CustomEvents.events.activate, SELECTORS.ADDSCHEDULE,
                this._showModal.bind(this)
            );

            Helper.onDelegateEvent(this.node, CustomEvents.events.activate, SELECTORS.EDIT,
                this._showModal.bind(this)
            );

            Helper.onDelegateEvent(this.node, CustomEvents.events.activate, SELECTORS.TOGGLESTATUS,
                this._toggleStatus.bind(this)
            );

            Helper.onDelegateEvent(this.node, CustomEvents.events.activate, SELECTORS.DUPLICATE,
                this._duplicateSchedule.bind(this)
            );

            Helper.onDelegateEvent(this.node, CustomEvents.events.activate, SELECTORS.SEND,
                this._sendSchedule.bind(this)
            );

            Helper.onDelegateEvent(this.node, CustomEvents.events.activate, SELECTORS.DELETESCHEDULE,
                this._deleteScheduleHandler.bind(this)
            );

        };

        /**
         * Show modal for create or edit a schedule.
         * @param {Event} data
         * @param {Event} e
         */
        SchedulesManager.prototype._showModal = function(data, e) {
            e.originalEvent.preventDefault();
            var currentTarget = $(data.currentTarget);
            var modal = new ModalForm({
                formClass: 'tool_reportbuilder\\form\\schedule',
                args: {'id': currentTarget.data('id'), 'reportid': this.reportid},
                modalConfig: {
                    title: Str.get_string('schedule', 'tool_reportbuilder'),
                    preShowCallback: function(triggerElement, modal) {
                        modal.setSaveButtonText(Str.get_string('save'));
                    }
                },
                contextId: Config.contextid,
                triggerElement: currentTarget
            });
            modal.onSubmitSuccess = function() {
                Tabs.loadTab(null, {});
            };
        };

        /**
         * Toggle status
         * @param {Event} e
         * @param {Event} data
         * @private
         */
        SchedulesManager.prototype._toggleStatus = function(e, data) {
            data.originalEvent.preventDefault();
        };

        /**
         *
         * @param {Event} e
         * @param {Event} data
         * @private
         */
        SchedulesManager.prototype._duplicateSchedule = function(e, data) {
            data.originalEvent.preventDefault();
        };

        /**
         * Send schedule
         * @param {Event} e
         * @param {Event} data
         * @private
         */
        SchedulesManager.prototype._sendSchedule = function(e, data) {
            data.originalEvent.preventDefault();
            var element = $(e.currentTarget);
            var id = element.data('id');
            var request = {
                methodname: SERVICES.SENDCHEDULE,
                args: {
                    scheduleid: id
                }
            };

            Ajax.call([request])[0].then(function() {
                return Str.get_string('scheduleaddedastask', 'tool_reportbuilder');
            }).then(function(message) {
                Notification.addNotification({
                    message: message,
                    type: 'success'
                });
                return null;
            }).fail(Notification.exception);
        };

        /**
         * Handles add a new report.
         *
         * @param {Event} e
         * @param {Event} data
         */
        SchedulesManager.prototype._deleteScheduleHandler = function(e, data) {
            data.originalEvent.preventDefault();
            var element = $(e.currentTarget);
            var id = element.data('id');
            var schedulename = element.data('schedulename');

            var stringkeys = [
            {
                key: 'confirm',
                component: 'tool_reportbuilder'
            },
            {
                key: 'confirmdeleteschedule',
                component: 'tool_reportbuilder',
                param: schedulename
            },
            {
                key: 'removechedulesuccess',
                component: 'tool_reportbuilder'
            },
            {
                key: 'delete'
            }
            ];

            Str.get_strings(stringkeys).then(function(langStrings) {
                var title = langStrings[0];
                var confirmMessage = langStrings[1];
                var buttonText = langStrings[3];
                return ModalFactory.create({
                    title: title,
                    body: confirmMessage,
                    type: ModalFactory.types.SAVE_CANCEL
                }).then(function(modal) {
                    modal.setSaveButtonText(buttonText);
                    // Handle save event.
                    modal.getRoot().on(ModalEvents.save, function() {
                        var request = {
                            methodname: SERVICES.DELETESCHEDULE,
                            args: {
                                scheduleid: id
                            }
                        };

                        Ajax.call([request])[0].done(function(data) {
                            if (data) {
                                Notification.addNotification({
                                    message: langStrings[2],
                                    type: 'success'
                                });
                                Tabs.loadTab(null, null);
                            }
                        }).fail(Notification.exception);
                    });

                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });

                    return modal;
                });
            }).done(function(modal) {
                modal.show();
            }).fail(Notification.exception);
        };

        return SchedulesManager;
    }
);

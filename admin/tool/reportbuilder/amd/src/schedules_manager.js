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
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019, Alberto Lara Hernández <albertolara@moodle.com>
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
        'core/notification',
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
              WpNotification,
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
            SENDSCHEDULE: 'tool_reportbuilder_send_schedule'
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
                this._addSchedule.bind(this)
            );

            Helper.onDelegateEvent(this.node, CustomEvents.events.activate, SELECTORS.EDIT,
                this._editSchedule.bind(this)
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
         * Add a new schedule
         *
         * @param {Event} data
         * @param {Event} e
         * @private
         */
        SchedulesManager.prototype._addSchedule = function(data, e) {
            this._showModal(data, e, true);
        };

        /**
         * Edit a schedule
         *
         * @param {Event} data
         * @param {Event} e
         * @private
         */
        SchedulesManager.prototype._editSchedule = function(data, e) {
            this._showModal(data, e, false);
        };

        /**
         * Show modal for create or edit a schedule.
         * @param {Event} data
         * @param {Event} e
         * @param {boolean} newschedule
         */
        SchedulesManager.prototype._showModal = function(data, e, newschedule) {
            e.originalEvent.preventDefault();
            var currentTarget = $(data.currentTarget);
            var modal = new ModalForm({
                formClass: 'tool_reportbuilder\\form\\schedule',
                args: {'id': currentTarget.data('id'), 'reportid': this.reportid},
                modalConfig: {
                    title: newschedule ? Str.get_string('newschedule', 'tool_reportbuilder') :
                        Str.get_string('editschedule', 'tool_reportbuilder'),
                    preShowCallback: function(triggerElement, modal) {
                        modal.setSaveButtonText(Str.get_string('save', 'tool_reportbuilder'));
                    }
                },
                contextId: Config.contextid,
                triggerElement: currentTarget,
                saveButtonText: Str.get_string('save')
            });
            modal.onSubmitSuccess = function() {
                Tabs.loadTab(null, {});
            };
        };

        /**
         *
         * @param {Event} e
         * @param {Event} data
         * @private
         */
        SchedulesManager.prototype._duplicateSchedule = function(e, data) {
            // TODO WP-902 not implemented.
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
            var schedulename = element.data('schedulename');
            var doSend = function() {
                var request = {
                    methodname: SERVICES.SENDSCHEDULE,
                    args: {
                        scheduleid: id
                    }
                };

                Ajax.call([request])[0].then(function() {
                    return Str.get_string('scheduleaddedastask', 'tool_reportbuilder');
                }).then(function(message) {
                    WpNotification.addNotification({
                        message: message,
                        type: 'success'
                    });
                    return null;
                }).fail(Notification.exception);
            };

            Str.get_strings([
                {key: 'confirm', component: 'tool_reportbuilder'},
                {key: 'confirmsendschedule', component: 'tool_reportbuilder', param: schedulename},
                {key: 'send', component: 'tool_reportbuilder'},
                {key: 'cancel', component: 'moodle'}
            ]).done(function(strings) {
                Notification.confirm(
                    strings[0], // Confirm.
                    strings[1], // Confirmation text.
                    strings[2], // Save button.
                    strings[3], // Cancel.
                    doSend
                );
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
            var doDelete = function() {
                var request = {
                    methodname: SERVICES.DELETESCHEDULE,
                    args: {
                        scheduleid: id
                    }
                };

                Ajax.call([request])[0].done(function(data) {
                    if (data) {
                        Str.get_string('removechedulesuccess', 'tool_reportbuilder').then(function(message) {
                            WpNotification.addNotification({
                                message: message,
                                type: 'success'
                            });
                            Tabs.loadTab(null, null);
                            return null;
                        }).fail(Notification.exception);
                    }
                    return null;
                }).fail(Notification.exception);
            };

            Str.get_strings([
                {key: 'confirm', component: 'tool_reportbuilder'},
                {key: 'confirmdeleteschedule', component: 'tool_reportbuilder', param: schedulename},
                {key: 'delete', component: 'moodle'},
                {key: 'cancel', component: 'moodle'}
            ]).done(function(strings) {
                Notification.confirm(
                    strings[0], // Confirm.
                    strings[1], // Confirmation text.
                    strings[2], // Save button.
                    strings[3], // Cancel.
                    doDelete
                );
            }).fail(Notification.exception);
        };

        return SchedulesManager;
    }
);

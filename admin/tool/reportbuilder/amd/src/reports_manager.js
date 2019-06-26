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
 * @module     tool_reportbuilder/reports_manager
 * @package    tool_reportbuilder
 * @copyright  2018, Alberto Lara Hernández <albertolara@moodle.com>
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
              Config) {

        /** @type {Object} The list of selectors for the reports area. */
        var
        SELECTORS = {
            EDITREPORTDETAILS: "[data-action='editdetails']",
            DELETEREPORT: "[data-action='delete']",
            DUPLICATEREPORT: "[data-action='duplicate']",
            REPORTSLIST: "[data-region='report-table']",
        },
        SERVICES = {
            DELETEREPORT: 'tool_reportbuilder_delete_report'
        };

        /**
         * Messagearea class.
         *
         * @param {String} selector The selector for the page region containing the reports area.
         */
        function ReportsManager(selector) {
            this.node = $(selector);
            this._init();
        }

        /** @type {jQuery} The jQuery node for the page region containing the reports area. */
        ReportsManager.prototype.node = null;

        /**
         * Initialise the other objects we require.
         */
        ReportsManager.prototype._init = function() {
            CustomEvents.define(this.node, [
                CustomEvents.events.activate
            ]);

            Tabs.addButtonOnClick(this._addReport.bind(this));
            this.node.on('click', SELECTORS.EDITREPORTDETAILS, this._editReportDetailsHandler.bind(this));

            Helper.onDelegateEvent(this.node, CustomEvents.events.activate, SELECTORS.DELETEREPORT,
                this._deleteReportHandler.bind(this)
            );

            Helper.onDelegateEvent(this.node, CustomEvents.events.activate, SELECTORS.DUPLICATEREPORT,
                this._duplicateReportHandler.bind(this)
            );
        };

        /**
         * Popup to edit report details
         *
         * @param {jQuery} triggerElement
         * @param {Number} id
         * @param {Promise} title
         * @return {ModalForm}
         * @private
         */
        var showDetailsModal = function(triggerElement, id, title) {
            var modal = new ModalForm({
                formClass: 'tool_reportbuilder\\form\\detail',
                args: {id: id},
                modalConfig: {title: title},
                contextId: Config.contextid,
                triggerElement: triggerElement
            });
            // Override onInit() function to change the text for the save button.
            var oldInit = modal.onInit;
            modal.onInit = function() {
                this.modal.setSaveButtonText(Str.get_string('save'));
                oldInit.bind(this)();
            };
            return modal;
        };

        /**
         * Handles add a new report.
         *
         * @param {Event} e Click event
         * @private
         */
        ReportsManager.prototype._addReport = function(e) {
            e.preventDefault();
            var modal = showDetailsModal($(e.currentTarget), 0, Str.get_string('addreport', 'tool_reportbuilder'));
            modal.onSubmitSuccess = function(response) {
                window.location.href = response;
            };
        };

        /**
         * Handles duplicate report event
         *
         * @param {Event} e The jquery event
         * @private
         */
        ReportsManager.prototype._editReportDetailsHandler = function(e) {
            e.preventDefault();
            var element = $(e.currentTarget);
            var id = element.data('id');
            var reportname = element.data('reportname');
            var modal = showDetailsModal($(e.currentTarget), id, Str.get_string('edittitle', 'tool_reportbuilder', reportname));
            modal.onSubmitSuccess = function() {
                Tabs.loadTab();
            };
        };

        /**
         * Handles duplicate report event
         *
         * @param {event} e The jquery event
         * @param {object} data Extra event data
         * @private
         */
        ReportsManager.prototype._duplicateReportHandler = function(e, data) {
            data.originalEvent.preventDefault();
            data.originalEvent.stopPropagation();
        };

        /**
         * Handles delete a report.
         *
         * @param {event} e The jquery event
         * @param {object} data Extra event data
         * @private
         */
        ReportsManager.prototype._deleteReportHandler = function(e, data) {
            data.originalEvent.preventDefault();
            data.originalEvent.stopPropagation();
            var element = $(e.currentTarget);
            var id = element.data('id');
            var reportname = element.data('reportname');

            var stringkeys = [
            {
                key: 'confirm',
                component: 'tool_reportbuilder'
            },
            {
                key: 'deletereportmsg',
                component: 'tool_reportbuilder',
                param: reportname
            },
            {
                key: 'removereportsuccess',
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
                            methodname: SERVICES.DELETEREPORT,
                            args: {
                                reportid: id
                            }
                        };

                        Ajax.call([request])[0].done(function(data) {
                            if (data) {
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

        return ReportsManager;
    }
);
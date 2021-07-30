// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * This module instantiates the functionality reports main view.
 *
 * @module     tool_reportbuilder/reports_manager
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define([
        'jquery',
        'core/custom_interaction_events',
        'core/str',
        'core/ajax',
        'core/notification',
        'tool_wp/tabs',
        'tool_reportbuilder/reportbuilder_helper',
        'tool_reportbuilder/reportbuilder_events',
        'tool_wp/modal_form',
        'core/config',
        'tool_wp/notification',
    ],
    function(
        $,
        CustomEvents,
        Str,
        Ajax,
        Notification,
        Tabs,
        Helper,
        ReportEvents,
        ModalForm,
        Config,
        WpNotification
    ) {

        /** @type {Object} The list of selectors for the reports area. */
        var
        SELECTORS = {
            EDITREPORTDETAILS: "[data-action='editdetails']",
            DELETEREPORT: "[data-action='delete']",
            DUPLICATEREPORT: "[data-action='duplicate']",
            REPORTCONTAINER: "[data-region='system-report'] [data-region='data-report']"
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

            Helper.onDelegateEvent(this.node, CustomEvents.events.activate, SELECTORS.EDITREPORTDETAILS,
                this._editReportDetailsHandler.bind(this)
            );

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
                triggerElement: triggerElement,
                saveButtonText: Str.get_string('save')
            });
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

            if ($(e.currentTarget).data('limitReached')) {
                var limitValue = parseInt($(e.currentTarget).data('limitValue'), 10);
                var requiredStrings = [
                    {component: 'tool_reportbuilder', key: 'reportlimitreachedtitle'},
                    {component: 'tool_reportbuilder', param: limitValue,
                        key: limitValue > 0 ? 'reportlimitreachedtenant' : 'reportlimitreachedsite'}
                ];

                Str.get_strings(requiredStrings).done(function(str) {
                    Notification.alert(str[0], str[1]);
                    return;
                });
            } else {
                var modal = showDetailsModal($(e.currentTarget), 0, Str.get_string('addreport', 'tool_reportbuilder'));
                modal.onSubmitSuccess = function(response) {
                    window.location.href = response;
                };
            }
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
            var modal = showDetailsModal(element, id, Str.get_string('edittitle', 'tool_reportbuilder', reportname));
            modal.onSubmitSuccess = function() {
                Helper.triggerEvent(element.closest(SELECTORS.REPORTCONTAINER), ReportEvents.RELOADTABLE);
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
            // TODO WP-259 not implemented.
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
            var doDelete = function() {
                var request = {
                    methodname: SERVICES.DELETEREPORT,
                    args: {
                        reportid: id
                    }
                };

                Ajax.call([request])[0].done(function(data) {
                    if (data) {
                        Str.get_string('deletereportsuccess', 'tool_reportbuilder').then(function(message) {
                            WpNotification.addNotification({
                                message: message,
                                type: 'success'
                            });
                            Helper.triggerEvent(element.closest(SELECTORS.REPORTCONTAINER), ReportEvents.RELOADTABLE);
                            return null;
                        }).fail(Notification.exception);
                    }
                    return null;
                }).fail(Notification.exception);
            };

            Str.get_strings([
                {key: 'confirm', component: 'tool_reportbuilder'},
                {key: 'deletereportmsg', component: 'tool_reportbuilder', param: reportname},
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
                return null;
            }).fail(Notification.exception);
        };

        return ReportsManager;
    }
);
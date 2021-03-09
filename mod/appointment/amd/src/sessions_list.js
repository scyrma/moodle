// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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
 * This module instantiates the functionality for actions on sessions listing.
 *
 * @module     mod_appointment/sessions_list
 * @package    mod_appointment
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/modal_events',
    'core/modal_factory',
    'core/notification',
    'core/str',
    'tool_wp/modal_form',
    'tool_reportbuilder/reportbuilder_events'],
function($, Ajax, ModalEvents, ModalFactory, Notification, Str, ModalForm, ReportEvents) {

    /**
     * List of selectors.
     */
    var SELECTORS = {
        SESSIONSLIST: '[data-region=appointment-sessions-list]',
        SIGNUP: "[data-action=signup]",
        JOINWAITLIST: "[data-action=joinwaitlist]",
        DETAILS: "[data-action=details]",
        CANCELSIGNUP: "[data-action=cancelsignup]",
        ADD: "[data-action=addsession]",
        ADDMULTIPLE: "[data-action=addmultiple]",
        EDIT: "[data-action=editsession]",
        DELETE: "[data-action=deletesession]",
        DUPLICATE: "[data-action=duplicatesession]",
        REPORTCONTAINER: "[data-region='system-report'] [data-region='data-report']"
    };

    /**
     * Reload report
     *
     * @param {$} node that triggered the action.
     */
    var reloadReport = function(node) {
        var report = node.closest(SELECTORS.REPORTCONTAINER);

        report.trigger(ReportEvents.RELOADTABLE);
    };

    /**
     * Modal to add/edit single appointment.
     *
     * @param {jQuery} triggerElement
     * @param {Number} cmid
     * @param {Number} sessionid
     * @return {ModalForm} modal
     */
    var showAppointmentModal = function(triggerElement, cmid, sessionid) {
        var title = Str.get_string('addingappointment', 'mod_appointment');
        if (sessionid > 0) {
            title = Str.get_string('editingappointment', 'mod_appointment');
        }
        var modal = new ModalForm({
            formClass: 'mod_appointment\\form\\session',
            args: {cmid: cmid, sessionid: sessionid},
            modalConfig: {title: title},
            saveButtonText: Str.get_string('save'),
            triggerElement: triggerElement,
        });
        $(document).on(ModalEvents.bodyRendered, function() {
            $('.modal-body input:not([type=submit])').on('keydown', function(ev) {
                if (ev.key == 'Enter') {
                    ev.preventDefault();
                    ev.stopPropagation();
                    $('.modal-footer button[data-action=save]').click();
                }
            });
        });
        return modal;
    };

    /**
     * Modal to duplicate single appointment.
     *
     * @param {jQuery} triggerElement
     * @param {Number} cmid
     * @param {Number} sessionid
     * @return {ModalForm} modal
     */
    var showAppointmentDuplicateModal = function(triggerElement, cmid, sessionid) {
        var modal = new ModalForm({
            formClass: 'mod_appointment\\form\\duplicate',
            args: {cmid: cmid, sessionid: sessionid},
            modalConfig: {title: Str.get_string('duplicateappointment', 'mod_appointment')},
            saveButtonText: Str.get_string('duplicate', 'mod_appointment'),
            triggerElement: triggerElement,
        });
        return modal;
    };

    /**
     * Modal to add multiple appointments.
     *
     * @param {jQuery} triggerElement
     * @param {Number} cmid
     * @return {ModalForm} modal
     */
    var showMultipleModal = function(triggerElement, cmid) {
        var modal = new ModalForm({
            formClass: 'mod_appointment\\form\\multiple',
            args: {cmid: cmid},
            modalConfig: {title: Str.get_string('addingappointments', 'mod_appointment')},
            saveButtonText: Str.get_string('save'),
            triggerElement: triggerElement,
        });
        return modal;
    };

    /**
     * Modal to sign up to an appointment.
     *
     * @param {jQuery} triggerElement
     * @param {Number} sessionid
     * @return {ModalForm} modal
     */
    var showSignupModal = function(triggerElement, sessionid) {
        var modal = new ModalForm({
            formClass: 'mod_appointment\\form\\signup',
            args: {sessionid: sessionid},
            modalConfig: {title: Str.get_string('details', 'mod_appointment')},
            saveButtonText: Str.get_string('book', 'mod_appointment'),
            triggerElement: triggerElement,
        });
        return modal;
    };

    /**
     * Modal to join waitlist for an appointment.
     * Essentially this is signup but with different action title.
     *
     * @param {jQuery} triggerElement
     * @param {Number} sessionid
     * @return {ModalForm} modal
     */
    var showJoinWaitlistModal = function(triggerElement, sessionid) {
        var modal = new ModalForm({
            formClass: 'mod_appointment\\form\\signup',
            args: {sessionid: sessionid},
            modalConfig: {title: Str.get_string('details', 'mod_appointment')},
            saveButtonText: Str.get_string('joinwaitlist', 'mod_appointment'),
            triggerElement: triggerElement,
        });
        return modal;
    };

    /**
     * Modal to cancel sign up to an appointment.
     *
     * @param {jQuery} triggerElement
     * @param {Number} sessionid
     * @return {ModalForm} modal
     */
    var showCancelSignupModal = function(triggerElement, sessionid) {
        var modal = new ModalForm({
            formClass: 'mod_appointment\\form\\cancelsignup',
            args: {sessionid: sessionid},
            modalConfig: {title: Str.get_string('cancelbooking', 'mod_appointment')},
            saveButtonText: Str.get_string('confirmcancelbooking', 'mod_appointment'),
            triggerElement: triggerElement,
        });
        modal.onInit = function() {
            var saveButton = this.modal.getFooter().find('button[data-action="save"]');
            saveButton.removeClass('btn-primary').addClass('btn-danger');
            this.modal.getRoot().on(ModalEvents.save, this.submitForm.bind(this));
        };
        return modal;
    };

    /**
     * Modal to show details of an appointment.
     *
     * @param {jQuery} triggerElement
     * @param {Number} sessionid
     * @return {ModalForm} modal
     */
    var showDetailsModal = function(triggerElement, sessionid) {

        var body = Ajax.call([{
            methodname: 'mod_appointment_get_session_details',
            args: {sessionid: sessionid}
        }])[0]
        .then(function(data) {
            return data;
        }).fail(Notification.exception);

        return ModalFactory.create({
            type: ModalFactory.types.CANCEL,
            title: Str.get_string('details', 'mod_appointment'),
            body: body,
            removeOnClose: true,
        }).done((modal) => {
            modal.setLarge();
            modal.getRoot().on(ModalEvents.hidden, () => triggerElement.focus());
            modal.show();
            return modal;
        }).fail(Notification.exception);
    };

    /**
     * Delete appointment.
     *
     * @param {Number} sessionid
     * @param {jQuery} triggerElement
     */
    var deleteAppointment = function(sessionid, triggerElement) {
        Str.get_strings([
            {key: 'confirm', component: 'moodle'},
            {key: 'deleteappointmentconfirm', component: 'mod_appointment'},
            {key: 'delete', component: 'moodle'},
            {key: 'cancel', component: 'moodle'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var requests = Ajax.call([
                    {methodname: 'mod_appointment_delete_session', args: {sessionid: sessionid}}
                ]);
                requests[0].then(function() {
                    reloadReport(triggerElement);
                    return null;
                }).fail(Notification.exception);
            });
            return null;
        }).fail(Notification.exception);
    };

    return /** @alias module:mod_appointment/sessions_list */ {

        /**
         * Initialise the page.
         */
        init: function() {
            M.util.js_pending('mod_appointment_sessions_list_init');
            $(SELECTORS.ADD).on('click', function(e) {
                e.preventDefault();
                var cmid = $(e.currentTarget).data('cmid');
                var modal = showAppointmentModal($(e.currentTarget), cmid, 0);
                modal.onSubmitSuccess = function() {
                    reloadReport($('body').find(SELECTORS.REPORTCONTAINER));
                };
            });
            $(SELECTORS.ADDMULTIPLE).on('click', function(e) {
                e.preventDefault();
                var cmid = $(e.currentTarget).data('cmid');
                var modal = showMultipleModal($(e.currentTarget), cmid);
                modal.onSubmitSuccess = function() {
                    reloadReport($('body').find(SELECTORS.REPORTCONTAINER));
                };
            });
            $(SELECTORS.SESSIONSLIST).on('click', SELECTORS.SIGNUP, function(e) {
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                var modal = showSignupModal($(e.currentTarget), id);
                modal.onSubmitSuccess = function() {
                    reloadReport($(e.currentTarget));
                };
            });
            $(SELECTORS.SESSIONSLIST).on('click', SELECTORS.JOINWAITLIST, function(e) {
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                var modal = showJoinWaitlistModal($(e.currentTarget), id);
                modal.onSubmitSuccess = function() {
                    reloadReport($(e.currentTarget));
                };
            });
            $(SELECTORS.SESSIONSLIST).on('click', SELECTORS.DETAILS, function(e) {
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                showDetailsModal($(e.currentTarget), id);
            });
            $(SELECTORS.SESSIONSLIST).on('click', SELECTORS.CANCELSIGNUP, function(e) {
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                var modal = showCancelSignupModal($(e.currentTarget), id);
                modal.onSubmitSuccess = function() {
                    reloadReport($(e.currentTarget));
                };
            });
            $(SELECTORS.SESSIONSLIST).on('click', SELECTORS.EDIT, function(e) {
                e.preventDefault();
                var cmid = $(SELECTORS.ADD).data('cmid');
                var id = $(e.currentTarget).data('id');
                var modal = showAppointmentModal($(e.currentTarget), cmid, id);
                modal.onSubmitSuccess = function() {
                    reloadReport($(e.currentTarget));
                };
            });
            $(SELECTORS.SESSIONSLIST).on('click', SELECTORS.DUPLICATE, function(e) {
                e.preventDefault();
                var cmid = $(SELECTORS.ADD).data('cmid');
                var id = $(e.currentTarget).data('id');
                var modal = showAppointmentDuplicateModal($(e.currentTarget), cmid, id);
                modal.onSubmitSuccess = function() {
                    reloadReport($(e.currentTarget));
                };
            });
            $(SELECTORS.SESSIONSLIST).on('click', SELECTORS.DELETE, function(e) {
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                deleteAppointment(id, $(e.currentTarget));
            });
            M.util.js_complete('mod_appointment_sessions_list_init');
        },
       /**
        * Open sign up modal on page load (used for accessing the page from the calendar).
        * If user already booked a session, show details instead.
        *
        * @param {Number} sessionid
        * @param {Boolean} cansignup Indicates if user can sign up
        */
        signup: function(sessionid, cansignup) {
            if (cansignup) {
                var modal = showSignupModal($(SELECTORS.REPORTCONTAINER), sessionid);
                modal.onSubmitSuccess = function() {
                    reloadReport($(SELECTORS.REPORTCONTAINER));
                };
            } else {
                showDetailsModal($(SELECTORS.REPORTCONTAINER), sessionid);
            }

            // Modify URL.
            var url = $(location).attr('href').replace(/&s=\d+$/g, '');
            window.history.replaceState(null, '', url);
        }
    };
});

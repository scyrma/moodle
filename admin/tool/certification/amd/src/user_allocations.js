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
 * Potential user selector module.
 *
 * @module     tool_certification/user_allocations
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define([
    'jquery',
    'core/ajax',
    'core/notification',
    'tool_wp/modal_form',
    'core/str',
    'tool_wp/tabs',
    'tool_wp/notification',
    'tool_reportbuilder/reportbuilder_events',
], function($, ajax, Notification, ModalForm, str, Tabs, WpNotification, ReportEvents) {
    "use strict";

    var SELECTOR = {
        USERLIST: '[data-region=edit-certification-user-list]',
        TABS: '.wptabs',
        REPORTCONTAINER: "[data-region='system-report'] [data-region='data-report']",
    };

    /**
     * Reload report
     */
    var reloadReport = function() {
        var report = $(SELECTOR.TABS).find(SELECTOR.REPORTCONTAINER);
        report.trigger(ReportEvents.RELOADTABLEWITHOUTPAGINATION);
    };

    /**
     * Confirmation dialogue to de-allocate a user
     *
     * @param {jQuery} triggerElement
     */
    var confirmDeallocateUser = function(triggerElement) {
        var certificationid = triggerElement.data('id');
        var userid = triggerElement.data('userid');
        var name = triggerElement.closest('tr').children('td:first').text();

        str.get_strings([
            {'key': 'deleteuserallocation', component: 'tool_certification'},
            {'key': 'confirmdeleteuserallocation', component: 'tool_certification', param: name},
            {'key': 'yes'},
            {'key': 'no'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var promises = ajax.call([{
                    methodname: 'tool_certification_deallocate_user',
                    args: {
                        certificationid: certificationid,
                        userid: userid
                    }
                }]);
                promises[0].done(function() {
                    reloadReport();
                    return null;
                });
            });
            return null;
        }).fail(Notification.exception);
    };

    /**
     * Confirmation dialogue to revoke a user.
     *
     * @param {jQuery} triggerElement
     */
    var confirmRevokeUser = function(triggerElement) {
        var certificationid = triggerElement.data('id');
        var userid = triggerElement.data('userid');
        var name = triggerElement.closest('tr').children('td:first').text();

        str.get_strings([
            {'key': 'revokecertification', component: 'tool_certification'},
            {'key': 'revokewarning', component: 'tool_certification', param: name},
            {'key': 'yes'},
            {'key': 'no'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var promises = ajax.call([{
                    methodname: 'tool_certification_revoke_certification',
                    args: {
                        certificationid: certificationid,
                        userid: userid
                    }
                }]);
                promises[0].done(function() {
                    // We show revoked notification.
                    str.get_string('revokednotification', 'tool_certification')
                    .then(function(string) {
                        WpNotification.addNotification({
                            message: string,
                            type: 'success'
                        });
                        reloadReport();
                        return null;
                    }).fail(Notification.exception);
                    return null;
                }).fail(Notification.exception);
            });
            return null;
        }).fail(Notification.exception);
    };

    /**
     * Modal for certifying users.
     *
     * @param {jQuery} triggerElement
     * @param {Number} contextid
     * @return {ModalForm} modal
     */
    var certifyUsersModal = function(triggerElement, contextid) {
        var certificationid = triggerElement.data('id');
        var certificationuserid = triggerElement.data('certificationuserid');
        var userid = triggerElement.data('userid');
        var certifystr = str.get_string('certify', 'tool_certification');

        var modal = new ModalForm({
            formClass: 'tool_certification\\edit_certification_users_certify_form_modal',
            args: {id: certificationid, userid: userid, certificationuserid: certificationuserid},
            modalConfig: {title: certifystr},
            contextId: contextid,
            saveButtonText: certifystr,
            triggerElement: triggerElement,
        });
        return modal;
    };

    /**
     * Modal for editing users.
     *
     * @param {jQuery} triggerElement
     * @param {Number} contextid
     * @return {ModalForm} modal
     */
    var editUsersModal = function(triggerElement, contextid) {
        var certificationid = triggerElement.data('id');
        var certificationuserid = triggerElement.data('certificationuserid');
        var name = triggerElement.closest('tr').children('td:first').text();

        var modal = new ModalForm({
            formClass: 'tool_certification\\edit_certification_users_edit_form_modal',
            args: {id: certificationid, certificationuserid: certificationuserid},
            modalConfig: {title: str.get_string('allocationfor', 'tool_certification', name)},
            contextId: contextid,
            triggerElement: triggerElement,
        });
        return modal;
    };

    /**
     * Modal for allocating users.
     *
     * @param {jQuery} triggerElement
     * @param {Number} contextid
     * @param {Number} certificationid
     * @return {ModalForm} modal
     */
    var allocateUsersModal = function(triggerElement, contextid, certificationid) {
        var modal = new ModalForm({
            formClass: 'tool_certification\\edit_certification_users_form_modal',
            args: {id: certificationid, allocateuser: str.get_string('allocateusers', 'tool_certification')},
            modalConfig: {title: str.get_string('allocateusers', 'tool_certification')},
            contextId: contextid,
            triggerElement: triggerElement,
        });
        return modal;
    };

    return /** @alias module:tool_certification/user_allocations */ {

        /**
         * Initialise the page.
         */
        init: function() {
            M.util.js_pending('tool_certification_user_allocations_init');
            var contextid = $(SELECTOR.TABS).data('contextid');
            // Allocate user.
            Tabs.addButtonOnClick(function(e) {
                e.preventDefault();
                var certificationid = $(SELECTOR.TABS).data('id');
                var modal = allocateUsersModal($(e.currentTarget), contextid, certificationid);
                modal.onSubmitSuccess = function() {
                    reloadReport();
                };
            });
            // Edit user allocation.
            $(SELECTOR.USERLIST).on('click', '.edit_user', function(e) {
                e.preventDefault();
                var modal = editUsersModal($(e.currentTarget), contextid);
                modal.onSubmitSuccess = function() {
                    reloadReport();
                };
            });
            // Deallocate user.
            $(SELECTOR.USERLIST).on('click', '.confirm_deallocate_user', function(e) {
                e.preventDefault();
                confirmDeallocateUser($(e.currentTarget));
            });
            // Revoke user.
            $(SELECTOR.USERLIST).on('click', '.confirm_revoke_user', function(e) {
                e.preventDefault();
                confirmRevokeUser($(e.currentTarget));
            });
            // Certify user.
            $(SELECTOR.USERLIST).on('click', '.confirm_certify_user', function(e) {
                e.preventDefault();
                var modal = certifyUsersModal($(e.currentTarget), contextid);
                modal.onSubmitSuccess = function() {
                    // We show certified notification.
                    str.get_string('certifiednotification', 'tool_certification')
                    .then(function(string) {
                        WpNotification.addNotification({
                            message: string,
                            type: 'success'
                        });
                        reloadReport();
                        return null;
                    }).fail(Notification.exception);
                };
            });
            M.util.js_complete('tool_certification_user_allocations_init');
        }
    };
});
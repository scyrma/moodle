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
    'core/templates',
], function($, ajax, Notification, ModalForm, str, Tabs, WpNotification, ReportEvents, Templates) {
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
        report.trigger(ReportEvents.RELOADTABLE);
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
            modalConfig: {title: str.get_string('allocateusers', 'tool_certification'), scrollable: false},
            contextId: contextid,
            triggerElement: triggerElement,
        });
        return modal;
    };

    /**
     * Allocation window closed alert.
     *
     * @param {jQuery} target
     */
    var allocationWindowClosedAlert = function(target) {
        let type = target.data('allocationwindowtype');
        let time = target.data('allocationwindowtime');

        str.get_strings([
            {key: 'usersallocationnotavailable', component: 'tool_certification'},
            {key: type, component: 'tool_certification', param: time},
            {key: 'ok'},
        ]).done(function(s) {
            Notification.alert(s[0], s[1], s[2]);
            return null;
        }).fail(Notification.exception);
    };

    var enableDisableBulkAction = function(formSelector) {
        var checkheader = $('[data-region="report-table"] th.sr-only-header[data-source="user:check"][data-togglegroup-name]');
        if (checkheader.length) {
            // Replace the header of "user:check" column with the "Select all" checkbox.
            M.util.js_pending('tool_certification_user_list_selectall');
            checkheader.removeClass('sr-only-header');
            checkheader.html('<input type="checkbox" disabled="disabled">');
            str.get_strings([
                {key: 'selectall'},
                {key: 'deselectall'}
            ]).then(function(str) {
                return Templates.render('core/checkbox-toggleall-master', {
                    togglegroup: checkheader.data('togglegroup-name'),
                    value: 1,
                    label: str[0],
                    labelclasses: 'sr-only',
                    id: 'checkall' + Math.floor(Math.random() * 26) + Date.now(),
                    selectall: str[0],
                    deselectall: str[1]
                });
            }).then(function(html, js) {
                Templates.replaceNodeContents(checkheader, html, js);
                M.util.js_complete('tool_certification_user_list_selectall');
                return null;
            }).fail();
        }
        if ($('[data-bulkuserid]:checked').length) {
            $(formSelector + ' select').prop('disabled', false);
        } else {
            $(formSelector + ' select').prop('disabled', 'disabled');
        }
    };

    var showBulkNotifications = function(data, successtext, infotext) {
        var strings = [];
        strings.push({key: successtext, component: 'tool_certification', param: data.successcount});
        if (data.skippedcount > 0) {
            strings.push({key: infotext, component: 'tool_certification', param: data.skippedcount});
        }
        str.get_strings(strings).done(function(s) {
            if (data.successcount > 0) {
                WpNotification.addNotification({
                    message: s[0],
                    type: 'success'
                });
            }
            if (data.skippedcount > 0) {
                WpNotification.addNotification({
                    message: s[1],
                    type: 'info'
                });
            }
            $(SELECTOR.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
            return null;
        });
        return null;
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
                let target = $(e.currentTarget);
                if (target.data('allocationwindow')) {
                    allocationWindowClosedAlert(target);
                } else {
                    var certificationid = $(SELECTOR.TABS).data('id');
                    var modal = allocateUsersModal($(e.currentTarget), contextid, certificationid);
                    modal.onSubmitSuccess = function() {
                        reloadReport();
                    };
                }
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
        },

        initBulkActions: function(formSelector) {
            enableDisableBulkAction(formSelector);
            $('#tool_certification-users').on('change', '[data-bulkuserid]', function() {
                enableDisableBulkAction(formSelector);
            });
            $(document).on(M.core.event.FILTER_CONTENT_UPDATED, function() {
                enableDisableBulkAction(formSelector);
            });
            $(formSelector + ' select').off();
            $(formSelector + ' select').on('change', function() {
                var func, certificationid, certificationuserids, confirmtext, buttontext, successtext, infotext, headertext;
                if (isNaN(this.value)) {
                    certificationuserids = $.map($('[data-bulkuserid]:checked'), function(e) {
                        return e.getAttribute('data-certificationuserid');
                    });
                    certificationid = $('#tool_certification-users').data('certificationid');
                }
                if (this.value === 'editstatusanddates') {
                    var contextid = $(SELECTOR.TABS).data('contextid');
                    var modal = new ModalForm({
                        formClass: 'tool_certification\\edit_certification_users_edit_form_modal_bulk',
                        args: {certificationid: certificationid, certificationuserids: certificationuserids},
                        modalConfig: {title: str.get_string('editstatusanddatesbulk', 'tool_certification')},
                        contextId: contextid,
                        triggerElement: $(formSelector.currentTarget),
                    });
                    // Override class method to reload user list after submit form.
                    modal.onSubmitSuccess = function(data) {
                        successtext = 'userseditedsuccess';
                        infotext = 'usersskipped';
                        showBulkNotifications(data, successtext, infotext);
                        $(SELECTOR.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
                        $(formSelector + ' select').val($(formSelector + ' select option:first').val());
                    };
                    // Reset dropdown.
                    $(formSelector + ' select').val($(formSelector + ' select option:first').val());
                } else {
                    switch (this.value) {
                        case 'deallocateusers':
                            func = 'tool_certification_bulk_deallocate_user';
                            headertext = 'confirmdeallocateusersheader';
                            confirmtext = 'confirmdeallocateusers';
                            buttontext = 'deallocateusers';
                            successtext = 'usersdeallocatedsuccess';
                            infotext = 'usersskipped';
                            break;
                    }
                    if (typeof func !== 'undefined') {
                        var confirmtextString = {key: confirmtext, component: 'tool_certification'};
                        if (confirmtext.hasOwnProperty('key')) {
                            confirmtextString = confirmtext;
                        }
                        str.get_strings([
                            {key: headertext, component: 'tool_certification'},
                            confirmtextString,
                            {key: buttontext, component: 'tool_certification'},
                            {key: 'cancel', component: 'moodle'},
                        ]).done(function(s) {
                            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                                var requests = ajax.call([{
                                    methodname: func,
                                    args: {certificationuserids: certificationuserids}
                                }]);
                                requests[0].then(function(data) {
                                    showBulkNotifications(data, successtext, infotext);
                                    $(formSelector + ' select').val($(formSelector + ' select option:first').val());
                                    return null;
                                }).fail(Notification.exception);
                            }, function() {
                                // Reset dropdown when pressing Cancel button.
                                $(formSelector + ' select').val($(formSelector + ' select option:first').val());
                            });
                            return null;
                        }).fail(Notification.exception);
                    }
                }
            });
        }
    };
});
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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Manages tenant's users
 *
 * @module     tool_tenant/users
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Adrian Greeve
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery',
        'core/ajax',
        'core/notification',
        'core/str',
        'core/templates',
        'core_form/modalform',
        'tool_wp/notification',
        'tool_wp/tabs',
        'tool_reportbuilder/reportbuilder_events'],
function($, Ajax, Notification, Str, Templates, ModalForm, WpNotification, Tabs, ReportEvents) {
    /**
     * Element selectors.
     */
    var SELECTORS = {
        REPORTCONTAINER: "[data-region='system-report'] [data-region='data-report']"
    };
    var canAddUser = function(e, tenantid, usercount) {
            e.preventDefault();
        var req = Ajax.call([
            {methodname: 'tool_tenant_check_user_limit', args: {tenantid: tenantid, numberofusers: usercount}}
        ]);
        return req[0];

    };
    var editUser = function(event, tenantid, userid, username) {
        var title;
        if (!userid) {
            userid = 0;
            title = Str.get_strings([{key: 'adduser', component: 'tool_tenant'}]);
        } else {
            title = Str.get_strings([{key: 'edituserwithname', component: 'tool_tenant', param: username}]);
        }

        var modal = new ModalForm({
            formClass: 'tool_tenant\\form\\add_user_form',
            args: {
                tenantid: tenantid,
                id: userid
            },
            modalConfig: {title: title},
            returnFocus: event.currentTarget,
            saveButtonText: Str.get_string('save')
        });
        modal.show();
        modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
            reloadReport($('body').find(SELECTORS.REPORTCONTAINER));
        });
    };
    /**
     * Reload report
     *
     * @param {$} node that triggered the action.
     */
    var reloadReport = function(node) {
        var report = node.closest(SELECTORS.REPORTCONTAINER);
        report.trigger(ReportEvents.RELOADTABLEWITHOUTPAGINATION);
    };
    var enableDisableBulkAction = function(formSelector) {
        var checkheader = $('[data-region="report-table"] th.sr-only-header[data-source="user:check"][data-togglegroup-name]');
        if (checkheader.length) {
            // Replace the header of "user:check" column with the "Select all" checkbox.
            M.util.js_pending('tool_tenant_user_list_selectall');
            checkheader.removeClass('sr-only-header');
            checkheader.html('<input type="checkbox" disabled="disabled">');
            Str.get_strings([
                {key: 'selectall', component: 'moodle'},
                {key: 'deselectall', component: 'moodle'}
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
                M.util.js_complete('tool_tenant_user_list_selectall');
                return null;
            }).fail();
        }
        if ($('[data-bulkuserid]:checked').length) {
            $(formSelector + ' select').prop('disabled', false);
        } else {
            $(formSelector + ' select').prop('disabled', 'disabled');
        }
    };

    var initActionsHandlers = function() {
        $('#tool_tenant-users').on('click', '[data-action=edit][data-id]', function(e) {
            e.preventDefault();
            var userid = $(e.currentTarget).data('id'),
                username = $(e.currentTarget).data('fullusername');
            editUser(e, 0, userid, username);
        });
        $('#tool_tenant-users').on('click', '[data-action=confirm]', function(e) {
            e.preventDefault();
            var menuNode = $(e.currentTarget);
            var username = $(e.currentTarget).data('fullusername');
            Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: 'confirmcheckfull', component: 'moodle', param: username},
                {key: 'confirmuser', component: 'tool_tenant'},
                {key: 'cancel', component: 'moodle'},
                {key: 'userconfirmedsuccess', component: 'tool_tenant', param: 1}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var requests = Ajax.call([
                        {methodname: 'tool_tenant_confirm_users', args: {userids: [menuNode.data('id')]}}
                    ]);
                    requests[0].then(function() {
                        WpNotification.addNotification({
                            message: s[4],
                            type: 'success'
                        });
                        reloadReport(menuNode);
                        return null;
                    }).fail(Notification.exception);
                });
                return null;
            }).fail(Notification.exception);
        });
        $('#tool_tenant-users').on('click', '[data-action=resendemail]', function(e) {
            e.preventDefault();
            var menuNode = $(e.currentTarget);
            Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: 'confirmresendemailuser', component: 'tool_tenant'},
                {key: 'emailconfirmationresend', component: 'moodle'},
                {key: 'cancel', component: 'moodle'},
                {key: 'resendemailsentsuccess', component: 'tool_tenant', param: 1}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var requests = Ajax.call([
                        {methodname: 'tool_tenant_resend_email_users', args: {userids: [menuNode.data('id')]}}
                    ]);
                    requests[0].then(function() {
                        WpNotification.addNotification({
                            message: s[4],
                            type: 'success'
                        });
                        reloadReport(menuNode);
                        return null;
                    }).fail(Notification.exception);
                });
                return null;
            }).fail(Notification.exception);
        });

        $('#tool_tenant-users').on('click', '[data-action=suspend]', function(e) {
            e.preventDefault();
            var menuNode = $(e.currentTarget);
            Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: 'confirmsuspenduser', component: 'tool_tenant'},
                {key: 'suspenduser', component: 'tool_tenant'},
                {key: 'cancel', component: 'moodle'},
                {key: 'usersuspendedsuccess', component: 'tool_tenant'}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var requests = Ajax.call([
                        {methodname: 'tool_tenant_suspend_users', args: {userids: [menuNode.data('id')]}}
                    ]);
                    requests[0].then(function() {
                        WpNotification.addNotification({
                            message: s[4],
                            type: 'success'
                        });
                        reloadReport(menuNode);
                        return null;
                    }).fail(Notification.exception);
                });
                return null;
            }).fail(Notification.exception);
        });
        $('#tool_tenant-users').on('click', '[data-action=unsuspend]', function(e) {
            e.preventDefault();
            var menuNode = $(e.currentTarget);
            Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: 'confirmunsuspenduser', component: 'tool_tenant'},
                {key: 'unsuspenduser', component: 'tool_tenant'},
                {key: 'cancel', component: 'moodle'},
                {key: 'userunsuspendedsuccess', component: 'tool_tenant'},
                {key: 'userslimitreached', component: 'tool_tenant'}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var requests = Ajax.call([
                        {methodname: 'tool_tenant_unsuspend_users', args: {userids: [menuNode.data('id')]}}
                    ]);
                    requests[0].then(function(data) {
                        if (data.skippedcount > 0) {
                            WpNotification.addNotification({
                                message: s[5],
                                type: 'info'
                            });
                        }
                        if (data.successcount > 0) {
                            WpNotification.addNotification({
                                message: s[4],
                                type: 'success'
                            });
                        }
                        reloadReport(menuNode);
                        return null;
                    }).fail(Notification.exception);
                });
                return null;
            }).fail(Notification.exception);
        });
        $('#tool_tenant-users').on('click', '[data-action=delete]', function(e) {
            e.preventDefault();
            var menuNode = $(e.currentTarget);
            Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: 'confirmdeleteuser', component: 'tool_tenant'},
                {key: 'deleteuser', component: 'tool_tenant'},
                {key: 'cancel', component: 'moodle'},
                {key: 'userdeletedsuccess', component: 'tool_tenant', param: 1}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var requests = Ajax.call([
                        {methodname: 'tool_tenant_delete_users', args: {userids: [menuNode.data('id')]}}
                    ]);
                    requests[0].then(function() {
                        WpNotification.addNotification({
                            message: s[4],
                            type: 'success'
                        });
                        reloadReport(menuNode);
                        return null;
                    }).fail(Notification.exception);
                });
                return null;
            }).fail(Notification.exception);
        });
    };

    /**
     * Displays a modal form to allocate users to programs or certifications
     *
     * @param {Event} e
     * @param {Object} userids
     * @param {String} component
     */
    var allocateUsersModal = function(e, userids, component) {
        const programclass = 'tool_program\\form\\programs_selector_form_modal';
        const certificationclass = 'tool_certification\\certifications_selector_form_modal';
        let args = {};
        // TODO MDL-71686 preserve arrays (remove when integrated).
        userids.forEach((u, k) => (args[`userids[${k}]`] = u));
        const modal = new ModalForm({
            formClass: (component === 'tool_program') ? programclass : certificationclass,
            args,
            modalConfig: {title: Str.get_string('allocateusers', component), scrollable: false},
            returnFocus: e.currentTarget
        });
        modal.show();
        // Override class method to reload user list after submit form.
        modal.addEventListener(modal.events.FORM_SUBMITTED, (ev) => {
            const data = ev.detail;
            let strings = [];
            strings.push({key: 'usersallocatedsuccess', component: 'tool_program', param: data.successcount});
            if (data.skippedcount > 0) {
                strings.push({key: 'usersskipped', component: 'tool_program', param: data.skippedcount});
            }
            Str.get_strings(strings).done(function(s) {
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
                reloadReport($('body').find(SELECTORS.REPORTCONTAINER));
                return null;
            });
            return null;
        });
    };

    return {
        init: function(tenantid, canadduser = false) {
            initActionsHandlers();
            if (canadduser) {
                // When the add user button is not in a tab.
                if (Tabs.getActiveTab()) {
                    Tabs.addButtonOnClick(function(e) {
                        // Check if its okay to add new user.
                        canAddUser(e, tenantid, 1)
                            .then(function(data) {
                                if (data.result) {
                                    editUser(e, tenantid);
                                } else {
                                    Str.get_strings([
                                        {key: 'userslimitreached', component: 'tool_tenant'},
                                        {key: 'userslimitreached', component: 'tool_tenant'},
                                        {key: 'ok', component: 'moodle'}]).done((s) => Notification.alert(s[0], s[1], s[2]));
                                }
                                return null;
                            })
                            .catch();
                    });
                } else {
                    $('#tool_tenant-users').on('click', '[data-tabs-element="addbutton"]', function(e) {
                        // Check if its okay to add new user.
                        canAddUser(e, tenantid, 1)
                            .then(function(data) {
                                if (data.result) {
                                    editUser(e, tenantid);
                                } else {
                                    Str.get_strings([
                                        {key: 'userslimitreached', component: 'tool_tenant'},
                                        {key: 'userslimitreached', component: 'tool_tenant'},
                                        {key: 'ok', component: 'moodle'}]).done((s) => Notification.alert(s[0], s[1], s[2]));
                                }
                                return null;
                            })
                            .catch();
                    });
                }
            }
        },

        initBulkActions: function(formSelector) {
            enableDisableBulkAction(formSelector);
            $('#tool_tenant-users').on('change', '[data-bulkuserid]', function() {
                enableDisableBulkAction(formSelector);
            });
            $(document).on(M.core.event.FILTER_CONTENT_UPDATED, function() {
                enableDisableBulkAction(formSelector);
            });
            $(formSelector + ' select').off();
            $(formSelector + ' select').on('change', function(e) {
                var func, args, confirmtext, buttontext, successtext, successtextparam, failtext, infotext, fullnames;
                if (this.value === '') {
                    return;
                }
                if (isNaN(this.value)) {
                    args = $.map($('[data-bulkuserid]:checked'), function(e) {
                        return e.value;
                    });
                    fullnames = $.map($('[data-fullname]:checked'), function(e) {
                        return $(e).attr('data-fullname');
                    });
                    switch (this.value) {
                        case 'suspendusers':
                            func = 'tool_tenant_suspend_users';
                            args = {userids: args};
                            confirmtext = 'confirmsuspendusers';
                            buttontext = 'suspendusers';
                            successtext = 'userssuspendedsuccess';
                            failtext = 'userssuspendedfail';
                            break;
                        case 'unsuspendusers':
                            func = 'tool_tenant_unsuspend_users';
                            args = {userids: args};
                            confirmtext = 'confirmunsuspendusers';
                            buttontext = 'unsuspendusers';
                            successtext = 'usersunsuspendedsuccess';
                            failtext = 'usersunsuspendedfail';
                            infotext = 'userslimitreached';
                            break;
                        case 'confirm':
                            func = 'tool_tenant_confirm_users';
                            args = {userids: args};
                            confirmtext = {key: 'confirmcheckfull', component: 'moodle', param: fullnames.join()};
                            buttontext = 'confirmusers';
                            successtext = 'userconfirmedsuccess';
                            failtext = 'userconfirmedfail';
                            infotext = 'useralreadyconfirmedinfo';
                            break;
                        case 'resend':
                            func = 'tool_tenant_resend_email_users';
                            args = {userids: args};
                            confirmtext = 'confirmresendemailusers';
                            buttontext = 'emailsconfirmationresend';
                            successtext = 'resendemailsentsuccess';
                            failtext = 'resendemailsentfail';
                            infotext = 'useralreadyconfirmedinfo';
                            break;
                        case 'deleteusers':
                            func = 'tool_tenant_delete_users';
                            args = {userids: args};
                            confirmtext = 'confirmdeleteusers';
                            buttontext = 'deleteusers';
                            successtext = 'userdeletedsuccess';
                            failtext = 'userdeletedfail';
                            break;
                        case 'assigntenantadmin':
                            func = 'tool_tenant_assign_tenant_admin_roles';
                            args = {userids: args};
                            confirmtext = 'confirmassigntenantadmins';
                            buttontext = 'assigntenantadmins';
                            successtext = 'usersassignedtenantadminsuccess';
                            failtext = 'usersassignedtenantadminfail';
                            infotext = 'tenantadminalreadyassigned';
                            break;
                        case 'unassigntenantadmin':
                            func = 'tool_tenant_unassign_tenant_admin_roles';
                            args = {userids: args};
                            confirmtext = 'confirmunassigntenantadmins';
                            buttontext = 'unassigntenantadmins';
                            successtext = 'usersunassignedtenantadminsuccess';
                            failtext = 'usersunassignedtenantadminfail';
                            infotext = 'tenantadminalreadyunassigned';
                            break;
                        case 'allocatetoprogram':
                            allocateUsersModal(e, args, 'tool_program');
                            break;
                        case 'allocatetocertification':
                            allocateUsersModal(e, args, 'tool_certification');
                            break;
                    }
                } else {
                    // We are moving between tenants.
                    var tenantid = this.value;
                    func = 'tool_tenant_allocate_users';
                    args = $.map($('.system-report input[type=checkbox][name^=users]:checked'), function(e) {
                        return {userid: e.value, tenantid: tenantid};
                    });
                    args = {allocations: args};
                    confirmtext = 'confirmallocateusers';
                    buttontext = 'allocateusers';
                    successtext = 'usermovetotenant';
                    failtext = 'usernotmovetotenant';
                    infotext = 'userslimitreached';
                    successtextparam = {tenant: $(this).find('option:selected').text()};
                }
                if (typeof func !== 'undefined') {
                    var confirmtextString = {key: confirmtext, component: 'tool_tenant'};
                    if (confirmtext.hasOwnProperty('key')) {
                        confirmtextString = confirmtext;
                    }
                        Str.get_strings([
                        {key: 'confirm', component: 'moodle'},
                            confirmtextString,
                        {key: buttontext, component: 'tool_tenant'},
                        {key: 'cancel', component: 'moodle'},
                    ]).done(function(s) {
                        Notification.confirm(s[0], s[1], s[2], s[3], function() {
                            var requests = Ajax.call([
                                {methodname: func, args: args}
                            ]);
                            requests[0].then(function(data) {
                                var strings = [];
                                    if (typeof successtextparam == 'undefined') {
                                        successtextparam = data.successcount;
                                    } else {
                                        successtextparam.count = data.successcount;
                                    }
                                    strings.push({key: successtext, component: 'tool_tenant', param: successtextparam});
                                    strings.push({key: failtext, component: 'tool_tenant', param: data.failcount});
                                if (data.skippedcount > 0) {
                                    strings.push({key: infotext, component: 'tool_tenant', param: data.skippedcount});
                                }
                                Str.get_strings(strings).done(function(s) {
                                    if (data.successcount > 0) {
                                        WpNotification.addNotification({
                                            message: s[0],
                                            type: 'success'
                                        });
                                    }
                                    if (data.failcount > 0) {
                                        WpNotification.addNotification({
                                            message: s[1],
                                            type: 'error'
                                        });
                                    }
                                    if (data.skippedcount > 0) {
                                        WpNotification.addNotification({
                                            message: s[2],
                                            type: 'info'
                                        });
                                    }
                                    reloadReport($('body').find(SELECTORS.REPORTCONTAINER));
                                    return null;
                                });
                                return null;
                            }).fail(Notification.exception);
                        });
                        return null;
                    }).fail(Notification.exception);
                }
            });
        }
    };
});

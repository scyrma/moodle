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
 * Manages tenant's users
 *
 * @module     tool_tenant/users
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery',
        'core/ajax',
        'core/notification',
        'core/str',
        'tool_wp/modal_form',
        'tool_wp/notification',
        'tool_wp/tabs'],
function($, Ajax, Notification, Str, ModalForm, WpNotification, Tabs) {

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
            triggerElement: $(event.currentTarget),
            saveButtonText: Str.get_string('save')
        });
        modal.onSubmitSuccess = function() {
            Tabs.loadTab();
        };
    };

    var enableDisableBulkAction = function(formSelector) {
        if ($('[data-bulkuserid]:checked').length) {
            $(formSelector + ' select').prop('disabled', false);
        } else {
            $(formSelector + ' select').prop('disabled', 'disabled');
        }
    };

    var initActionsHandlers = function() {
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
                        Tabs.loadTab();
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
                {key: 'userunsuspendedsuccess', component: 'tool_tenant'}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var requests = Ajax.call([
                        {methodname: 'tool_tenant_unsuspend_users', args: {userids: [menuNode.data('id')]}}
                    ]);
                    requests[0].then(function() {
                        WpNotification.addNotification({
                            message: s[4],
                            type: 'success'
                        });
                        Tabs.loadTab();
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
                {key: 'userdeletedsuccess', component: 'tool_tenant'}
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
                        Tabs.loadTab();
                        return null;
                    }).fail(Notification.exception);
                });
                return null;
            }).fail(Notification.exception);
        });
    };

    return {
        init: function(tenantid) {
            M.util.js_pending('tool_tenant_user_list_init');
            initActionsHandlers();
            $('#tool_tenant-users').on('click', '[data-action=edit]', function(e) {
                e.preventDefault();
                var userid = $(e.currentTarget).data('id'),
                    username = $(e.currentTarget).data('fullusername');
                editUser(e, tenantid, userid, username);
            });
            Tabs.addButtonOnClick(function(e) {
                editUser(e, tenantid, 0);
            });
            M.util.js_complete('tool_tenant_user_list_init');
        },

        initBulkActions: function(formSelector, currentTenantId) {
            enableDisableBulkAction(formSelector);
            $('#tool_tenant-users').on('change', '[data-bulkuserid]', function() {
                enableDisableBulkAction(formSelector);
            });
            $(document).on(M.core.event.FILTER_CONTENT_UPDATED, function() {
                enableDisableBulkAction(formSelector);
            });
            $(formSelector + ' select').off();
            $(formSelector + ' select').on('change', function() {
                var func, args, confirmtext, buttontext, successtext, successtextparam, failtext;
                if (isNaN(this.value)) {
                    args = $.map($('[data-bulkuserid]:checked'), function(e) {
                        return e.value;
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
                            args = {userids: args, tenantid: currentTenantId};
                            confirmtext = 'confirmassigntenantadmins';
                            buttontext = 'assigntenantadmins';
                            successtext = 'usersassignedtenantadminsuccess';
                            failtext = 'usersassignedtenantadminfail';
                            break;
                        case 'unassigntenantadmin':
                            func = 'tool_tenant_unassign_tenant_admin_roles';
                            args = {userids: args, tenantid: currentTenantId};
                            confirmtext = 'confirmunassigntenantadmins';
                            buttontext = 'unassigntenantadmins';
                            successtext = 'usersunassignedtenantadminsuccess';
                            failtext = 'usersunassignedtenantadminfail';
                            break;
                    }
                } else {
                    // We are moving between tenants.
                    var tenantid = this.value;
                    func = 'tool_tenant_allocate_users';
                    args = $.map($('.tab-content input[type=checkbox]:checked'), function(e) {
                        return {userid: e.value, tenantid: tenantid};
                    });
                    args = {allocations: args};
                    confirmtext = 'confirmallocateusers';
                    buttontext = 'allocateusers';
                    successtext = 'usermovetotenant';
                    successtextparam = {tenant: $(this).find('option:selected').text()};
                }
                if (typeof func !== 'undefined') {
                    Str.get_strings([
                        {key: 'confirm', component: 'moodle'},
                        {key: confirmtext, component: 'tool_tenant'},
                        {key: buttontext, component: 'tool_tenant'},
                        {key: 'cancel', component: 'moodle'},
                    ]).done(function(s) {
                        Notification.confirm(s[0], s[1], s[2], s[3], function() {
                            var requests = Ajax.call([
                                {methodname: func, args: args}
                            ]);
                            requests[0].then(function(data) {
                                var strings = [];
                                if (data.successcount > 0) {
                                    if (typeof successtextparam == 'undefined') {
                                        successtextparam = data.successcount;
                                    } else {
                                        successtextparam.count = data.successcount;
                                    }
                                    strings.push({key: successtext, component: 'tool_tenant', param: successtextparam});
                                }
                                if (data.failcount > 0) {
                                    strings.push({key: failtext, component: 'tool_tenant', param: data.failcount});
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
                                    Tabs.loadTab();
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

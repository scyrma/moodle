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
 * User allocation module
 *
 * @module     tool_program/user_allocations
 * @package    tool_program
 * @copyright  2018 David Matamoros
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/templates',
    'core/fragment',
    'core/notification',
    'tool_wp/modal_form',
    'core/str',
    'tool_wp/tabs'
], function($, ajax, Templates, Fragment, Notification, ModalForm, str, Tabs) {
    "use strict";

    var SELECTOR = {
        editProgramViewRegion: '.wptabs',
        editProgramUserList: '[data-region=edit-program-user-list]',
        modalButton: '.wptabs',
        openbutton: '#userallocationbutton'
    };

    /**
     * Confirmation dialogue to reset user program progress
     *
     * @param {Number} programid
     * @param {Number} programuserid
     * @param {Number} contextid
     * @param {String} name
     */
    var confirmResetProgramProgress = function(programid, programuserid, contextid, name) {
        str.get_strings([
            {'key': 'resetprogress', component: 'tool_program'},
            {'key': 'confirmresetprogress', component: 'tool_program', param: name},
            {'key': 'yes'},
            {'key': 'no'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var promises = ajax.call([{
                    methodname: 'tool_program_reset_program_progress',
                    args: {
                        programuserid: programuserid
                    }
                }]);
                promises[0].done(function() {
                    // We reload only users list.
                    var args = {id: programid};
                    return Fragment.loadFragment('tool_program', 'programs_manager_users_list', contextid, args)
                        .then(function(html, js) {
                            return Templates.replaceNodeContents("[data-region='edit-program-user-list']", html, js);
                        });

                });
            });
        }).fail(Notification.exception);
    };

    /**
     * Confirmation dialogue to de-allocate a user
     *
     * @param {Number} programid
     * @param {Number} id
     * @param {Number} contextid
     * @param {String} name
     */
    var confirmDeallocateUser = function(programid, id, contextid, name) {
        str.get_strings([
            {'key': 'deleteuserallocation', component: 'tool_program'},
            {'key': 'confirmdeleteuserallocation', component: 'tool_program', param: name},
            {'key': 'yes'},
            {'key': 'no'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var promises = ajax.call([{
                    methodname: 'tool_program_deallocate_user',
                    args: {
                        programid: programid,
                        userid: id
                    }
                }]);
                promises[0].done(function() {
                    // We reload only users list.
                    var args = {id: programid};
                    return Fragment.loadFragment('tool_program', 'programs_manager_users_list', contextid, args)
                        .then(function(html, js) {
                            return Templates.replaceNodeContents("[data-region='edit-program-user-list']", html, js);
                        });

                });
            });
        }).fail(Notification.exception);
    };

    /**
     * Displays a modal to override dates
     *
     * @param {Event} e
     * @param {$} editProgramViewRegion
     * @param {$} editProgramUserList
     */
    var overrideUserDatesModal = function(e, editProgramViewRegion, editProgramUserList) {
        var programuserid = $(e.currentTarget).data('programuserid');
        var programid = editProgramViewRegion.data('id');
        var contextid = editProgramViewRegion.data('contextid');
        var name = $(e.currentTarget).closest('tr').children('td:first').text();
        var modal = new ModalForm({
            formClass: 'tool_program\\form\\edit_program_users_edit_form_modal',
            args: {id: programid, programuserid: programuserid},
            modalConfig: {title: str.get_string('allocationfor', 'tool_program', name)},
            contextId: contextid,
            triggerElement: $(e.currentTarget)
        });
        // Override class method to reload user list after submit form.
        modal.onSubmitSuccess = function() {
            Fragment.loadFragment('tool_program', 'programs_manager_users_list', contextid, {
                id: programid
            }).then(function(html, js) {
                return Templates.replaceNodeContents(editProgramUserList, html, js);
            }).catch(Notification.exception);
        };
    };

    /**
     * Displays a modal form to allocate users
     *
     * @param {Event} e
     * @param {$} editProgramUserList
     */
    var allocateUsersModal = function(e, editProgramUserList) {
        var programid = $(SELECTOR.modalButton).data('id');
        var contextid = $(SELECTOR.modalButton).data('contextid');
        var modal = new ModalForm({
            formClass: 'tool_program\\form\\edit_program_users_form_modal',
            args: {id: programid, allocateuser: str.get_string('allocateusers', 'tool_program')},
            modalConfig: {title: str.get_string('allocateusers', 'tool_program')},
            contextId: contextid,
            triggerElement: $(e.currentTarget)
        });
        // Override class method to reload user list after submit form.
        modal.onSubmitSuccess = function() {
            Fragment.loadFragment('tool_program', 'programs_manager_users_list', contextid, {
                id: programid
            }).then(function(html, js) {
                return Templates.replaceNodeContents(editProgramUserList, html, js);
            }).catch(Notification.exception);
        };
    };

    return {
        /**
         * Initialise user allocation
         */
        init: function() {
            var $editProgramUserList = $(SELECTOR.editProgramUserList);
            var $editProgramViewRegion = $(SELECTOR.editProgramViewRegion);
            var contextid = $editProgramViewRegion.data('contextid');
            // Edit user modal.
            $editProgramUserList.on('click', '.edit_user', function(e) {
                e.preventDefault();
                overrideUserDatesModal(e, $editProgramViewRegion, $editProgramUserList);
            });
            // Reset program completion.
            $editProgramUserList.on('click', '.confirm_reset_program', function(e) {
                e.preventDefault();
                var name = $(e.currentTarget).closest('tr').children('td:first').text();
                var programuserid = $(e.currentTarget).data('programuserid');
                confirmResetProgramProgress($(e.currentTarget).data('id'), programuserid, contextid, name);
            });
            // Deallocate user.
            $editProgramUserList.on('click', '.confirm_deallocate_user', function(e) {
                e.preventDefault();
                var name = $(e.currentTarget).closest('tr').children('td:first').text();
                confirmDeallocateUser($(e.currentTarget).data('id'), $(e.currentTarget).data('userid'), contextid, name);
            });
            // User allocation modal.
            Tabs.addButtonOnClick(function(e) {
                e.preventDefault();
                allocateUsersModal(e, $editProgramUserList);
            });
        }
    };
});

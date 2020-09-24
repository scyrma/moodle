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
 * User allocation module
 *
 * @module     tool_program/user_allocations
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define([
    'jquery',
    'core/ajax',
    'core/templates',
    'core/fragment',
    'core/notification',
    'tool_wp/modal_form',
    'core/str',
    'tool_wp/tabs',
    'tool_reportbuilder/reportbuilder_events'
], function($, ajax, Templates, Fragment, Notification, ModalForm, str, Tabs, ReportEvents) {
    "use strict";

    var SELECTOR = {
        editProgramViewRegion: '.wptabs',
        editProgramUserList: '[data-region=edit-program-user-list]',
        modalButton: '.wptabs',
        openbutton: '#userallocationbutton',
        REPORTCONTAINER: "[data-region='system-report'] [data-region='data-report']"
    };

    /**
     * Confirmation dialogue to reset user program progress
     *
     * @param {Event} e
     */
    var confirmResetProgramProgress = function(e) {
        let element = $(e.currentTarget);
        let name = element.closest('tr').children('td:first').text();
        let programuserid = element.data('programuserid');

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
                    element.closest(SELECTOR.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
                });
            });
        }).fail(Notification.exception);
    };

    /**
     * Confirmation dialogue to de-allocate a user
     *
     * @param {Event} e
     */
    var confirmDeallocateUser = function(e) {
        let element = $(e.currentTarget);
        let name = element.closest('tr').children('td:first').text();

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
                        programid: element.data('id'),
                        userid: element.data('userid')
                    }
                }]);
                promises[0].done(function() {
                    element.closest(SELECTOR.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
                });
            });
        }).fail(Notification.exception);
    };

    /**
     * Displays a modal to override dates
     *
     * @param {Event} e
     * @param {jQuery} editProgramViewRegion
     */
    var overrideUserDatesModal = function(e, editProgramViewRegion) {
        let element = $(e.currentTarget);
        var programuserid = element.data('programuserid');
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
            element.closest(SELECTOR.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
        };
    };

    /**
     * Displays a modal form to allocate users
     *
     * @param {Event} e
     */
    var allocateUsersModal = function(e) {
        let element = $(SELECTOR.modalButton);
        let programid = element.data('id');
        let contextid = element.data('contextid');
        var modal = new ModalForm({
            formClass: 'tool_program\\form\\edit_program_users_form_modal',
            args: {id: programid, allocateuser: str.get_string('allocateusers', 'tool_program')},
            modalConfig: {title: str.get_string('allocateusers', 'tool_program')},
            contextId: contextid,
            triggerElement: $(e.currentTarget)
        });
        // Override class method to reload user list after submit form.
        modal.onSubmitSuccess = function() {
            $(SELECTOR.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
        };
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
            {key: 'usersallocationnotavailable', component: 'tool_program'},
            {key: type, component: 'tool_program', param: time},
            {key: 'ok'},
        ]).done(function(s) {
            Notification.alert(s[0], s[1], s[2]);
            return null;
        }).fail(Notification.exception);
    };

    return {
        /**
         * Initialise user allocation
         */
        init: function() {
            var $editProgramUserList = $(SELECTOR.editProgramUserList);
            var $editProgramViewRegion = $(SELECTOR.editProgramViewRegion);
            // Edit user modal.
            $editProgramUserList.on('click', '.edit_user', function(e) {
                e.preventDefault();
                overrideUserDatesModal(e, $editProgramViewRegion);
            });
            // Reset program completion.
            $editProgramUserList.on('click', '.confirm_reset_program', function(e) {
                e.preventDefault();
                confirmResetProgramProgress(e);
            });
            // Deallocate user.
            $editProgramUserList.on('click', '.confirm_deallocate_user', function(e) {
                e.preventDefault();
                confirmDeallocateUser(e);
            });
            // User allocation modal.
            Tabs.addButtonOnClick(function(e) {
                e.preventDefault();
                let target = $(e.currentTarget);
                if (target.data('allocationwindow')) {
                    allocationWindowClosedAlert(target);
                } else {
                    allocateUsersModal(e);
                }
            });
        }
    };
});

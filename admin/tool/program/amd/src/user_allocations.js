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

"use strict";

import $ from 'jquery';
import Ajax from 'core/ajax';
import ModalForm from 'tool_wp/modal_form';
import Notification from 'core/notification';
import ReportEvents from 'tool_reportbuilder/reportbuilder_events';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import Pending from 'core/pending';

/** @type {Object} The list of selectors for the program allocations area. */
const Selector = {
    editProgramViewRegion: '.wptabs',
    editProgramUserList: '[data-region=edit-program-user-list]',
    openbutton: '#userallocationbutton',
    reportContainer: "[data-region='system-report'] [data-region='data-report']",
    editUser: "[data-region='system-report'] [data-region='data-report'] a[data-action='user_edit_form']",
    deallocateUser: "[data-region='system-report'] [data-region='data-report'] a[data-action='deallocate_user']",
    resetProgram: "[data-region='system-report'] [data-region='data-report'] a[data-action='reset_program']",
    allocateUsersButton: ".wptabs .tab-pane.active [data-tabs-element='addbutton']",
    checkboxHeader: '[data-region="report-table"] th.sr-only-header[data-source="user:check"][data-togglegroup-name]'
};

/**
 * Confirmation dialogue to reset user program progress
 *
 * @param {Number} programuserid
 * @param {String} name
 */
const confirmResetProgramProgress = (programuserid, name) => {
    getStrings([
        {'key': 'resetprogress', component: 'tool_program'},
        {'key': 'confirmresetprogress', component: 'tool_program', param: name},
        {'key': 'yes'}
    ]).then(([resetprogress, confirmresetprogress, yes]) => {
        Notification.confirm(resetprogress, confirmresetprogress, yes, null, () => {
            const pendingPromise = new Pending('tool/program:confirmResetProgramProgress');
            const promises = Ajax.call([{
                methodname: 'tool_program_reset_program_progress',
                args: {programuserid: programuserid}
            }]);
            promises[0].then(() => {
                reloadReport();
                return pendingPromise.resolve();
            }).catch(Notification.exception);
        });
        return null;
    }).catch(Notification.exception);
};

/**
 * Confirmation dialogue to de-allocate a user
 *
 * @param {Number} programid
 * @param {Number} userid
 * @param {string} name
 */
const confirmDeallocateUser = (programid, userid, name) => {
    getStrings([
        {'key': 'deleteuserallocation', component: 'tool_program'},
        {'key': 'confirmdeleteuserallocation', component: 'tool_program', param: name},
        {'key': 'yes'}
    ]).then(([deleteuserallocation, confirmdeleteuserallocation, yes]) => {
        Notification.confirm(deleteuserallocation, confirmdeleteuserallocation, yes, null, () => {
            const pendingPromise = new Pending('tool/program:confirmDeallocateUser');
            const promises = Ajax.call([{
                methodname: 'tool_program_deallocate_user',
                args: {
                    programid: programid,
                    userid: userid
                }
            }]);
            promises[0].then(() => {
                reloadReport();
                return pendingPromise.resolve();
            }).catch(Notification.exception);
        });
        return null;
    }).catch(Notification.exception);
};

/**
 * Displays a modal to override dates
 *
 * @param {Event} event
 * @param {Number} programuserid
 * @param {String} name
 */
const overrideUserDatesModal = (event, programuserid, name) => {
    const programRegion = document.querySelector(Selector.editProgramViewRegion);
    const programid = programRegion.dataset.id;
    const contextid = programRegion.dataset.contextid;
    const modal = new ModalForm({
        formClass: 'tool_program\\form\\edit_program_users_edit_form_modal',
        args: {id: programid, programuserid: programuserid},
        modalConfig: {title: getString('allocationfor', 'tool_program', name)},
        contextId: contextid,
        triggerElement: event.target
    });
    // Override class method to reload user list after submit form.
    modal.onSubmitSuccess = reloadReport;
};

/**
 * Displays a modal form to allocate users
 *
 * @param {Event} event
 * @param {Number} programid
 * @param {Number} contextid
 */
const allocateUsersModal = (event, programid, contextid) => {
    const modal = new ModalForm({
        formClass: 'tool_program\\form\\edit_program_users_form_modal',
        args: {id: programid, allocateuser: getString('allocateusers', 'tool_program')},
        modalConfig: {title: getString('allocateusers', 'tool_program'), scrollable: false},
        contextId: contextid,
        triggerElement: event.target
    });
    // Override class method to reload user list after submit form.
    modal.onSubmitSuccess = reloadReport;
};

/**
 * Direct allocation to program disabled alert.
 */
const directAllocationDisabledAlert = () => {
    allocationWindowClosedAlert('directallocationdisabled', null);
};

/**
 * Allocation window closed alert.
 *
 * @param {String} type
 * @param {String|null} time
 */
const allocationWindowClosedAlert = (type, time) => {
    getStrings([
        {key: 'usersallocationnotavailable', component: 'tool_program'},
        {key: type, component: 'tool_program', param: time},
        {key: 'ok', component: 'moodle'},
    ]).then(([usersallocationnotavailable, typestr, ok]) => {
        Notification.alert(usersallocationnotavailable, typestr, ok);
        return null;
    }).catch(Notification.exception);
};

/**
 * Reloads the report
 *
 * Note: We are still using JQuery here because ReportEvents has not been updated to ES6 yet.
 */
const reloadReport = () => {
    $(Selector.reportContainer).trigger(ReportEvents.RELOADTABLE);
};


let initialized = false;

const init = () => {

    if (initialized) {
        // We already added the event listeners (can be called multiple times by mustache template).
        return;
    }

    document.addEventListener('click', (event) => {

        // Edit user.
        const editUser = event.target.closest(Selector.editUser);
        if (editUser) {
            event.preventDefault();
            const programuserid = editUser.dataset.programuserid;
            const name = editUser.dataset.userfullname;
            overrideUserDatesModal(event, programuserid, name);
        }

        // Deallocate user.
        const deallocateUser = event.target.closest(Selector.deallocateUser);
        if (deallocateUser) {
            event.preventDefault();
            const userid = deallocateUser.dataset.userid;
            const programid = deallocateUser.dataset.id;
            const name = deallocateUser.dataset.userfullname;
            confirmDeallocateUser(programid, userid, name);
        }

        // Reset program.
        const resetProgram = event.target.closest(Selector.resetProgram);
        if (resetProgram) {
            event.preventDefault();
            const programuserid = resetProgram.dataset.programuserid;
            const name = resetProgram.dataset.userfullname;
            confirmResetProgramProgress(programuserid, name);
        }

        // User allocation modal.
        const AllocateUser = event.target.closest(Selector.allocateUsersButton);
        if (AllocateUser) {
            event.preventDefault();
            const allocationwindow = AllocateUser.dataset.allocationwindow;
            const directallocationdisabled = AllocateUser.dataset.directallocationdisabled;
            if (directallocationdisabled) {
                directAllocationDisabledAlert();
            } else if (allocationwindow) {
                const type = AllocateUser.dataset.allocationwindowtype;
                const time = AllocateUser.dataset.allocationwindowtime;
                allocationWindowClosedAlert(type, time);
            } else {
                const programRegion = document.querySelector(Selector.editProgramViewRegion);
                const programid = programRegion.dataset.id;
                const contextid = programRegion.dataset.contextid;
                allocateUsersModal(event, programid, contextid);
            }
        }
    });

    initialized = true;
};

export default {
    init: init
};

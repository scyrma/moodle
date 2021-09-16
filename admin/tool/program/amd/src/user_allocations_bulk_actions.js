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
 * User allocation module for bulk actions
 *
 * @module     tool_program/user_allocations_bulk_actions
 * @package    tool_program
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
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
import Templates from 'core/templates';
import WpNotification from 'tool_wp/notification';
import Pending from 'core/pending';

/** @type {Object} The list of selectors for the program allocations area. */
const Selector = {
    editProgramViewRegion: '.wptabs',
    editProgramUserList: '[data-region=edit-program-user-list]',
    openbutton: '#userallocationbutton',
    reportContainer: "[data-region='system-report'] [data-region='data-report']",
    bulkCheckHeader: '[data-region="report-table"] th.sr-only-header[data-source="user:check"][data-togglegroup-name]',
    programUsersRegion: '#tool_program-users'
};

/**
 * Enable bulk actions on report
 */
const enableBulkActions = () => {
    const checkheader = document.querySelector(Selector.bulkCheckHeader);
    if (checkheader) {
        // Replace the header of "user:check" column with the "Select all" checkbox.
        const pendingPromise = new Pending('tool/program:enableDisableBulkAction');
        checkheader.classList.remove('sr-only-header');
        checkheader.innerHTML = '<input type="checkbox" disabled="disabled">';
        getStrings([
            {key: 'selectall'},
            {key: 'deselectall'}
        ]).then(([selectall, deselectall]) => {
            return Templates.render('core/checkbox-toggleall-master', {
                togglegroup: checkheader.getAttribute('data-togglegroup-name'),
                value: 1,
                label: selectall,
                labelclasses: 'sr-only',
                id: 'checkall',
                selectall: selectall,
                deselectall: deselectall
            });
        }).then((html, js) => {
            Templates.replaceNodeContents(checkheader, html, js);
            return pendingPromise.resolve();
        }).catch(Notification.exception);
    }
};

/**
 * Enable or disable bulk actions selector on report
 *
 * Enables the selector when at least one checkbox has been checked.
 *
 * @param {String} formSelector
 */
const enableDisableBulkActionSelector = (formSelector) => {
    // Enable or disable bulk action selector depending if any checkbox is checked.
    const bulkactionselector = document.querySelector(formSelector + ' select');
    if (bulkactionselector) {
        const countCheckboxesChecked = document.querySelectorAll('[data-bulkuserid]:checked').length;
        bulkactionselector.disabled = !countCheckboxesChecked;
    }
};

/**
 * Show bulk notifications
 *
 * @param {Object[]} data
 * @param {String} successtext
 * @param {String} infotext
 */
const showBulkNotifications = (data, successtext, infotext) => {
    let strings = [];
    strings.push({key: successtext, component: 'tool_program', param: data.successcount});
    if (data.skippedcount > 0) {
        strings.push({key: infotext, component: 'tool_program', param: data.skippedcount});
    }
    getStrings(strings).then((s) => {
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
        reloadReport();
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

/**
 * Perform selected action on the bulk selector
 *
 * @param {String} formSelector
 */
const bulkSelectorPerfomAction = (formSelector) => {
    const bulkactionselector = document.querySelector(formSelector + ' select');
    let programid, successtext, infotext;
    let programuserids = [];

    // Get program user ids from all checkboxes that are checked.
    const checkboxesChecked = document.querySelectorAll('[data-bulkuserid]:checked');
    programuserids = [...checkboxesChecked].map(checkbox => checkbox.dataset.programuserid);
    programid = document.querySelector(Selector.programUsersRegion).dataset.programid;

    // Check if we have selected the option to edit status and dates on the bulk selector.
    if (bulkactionselector.value === 'editstatusanddates') {
        const programRegion = document.querySelector(Selector.editProgramViewRegion);
        const contextid = programRegion.dataset.contextid;
        const modal = new ModalForm({
            formClass: 'tool_program\\form\\edit_program_users_edit_form_modal_bulk',
            args: {programid: programid, programuserids: programuserids},
            modalConfig: {title: getString('editstatusanddatesbulk', 'tool_program')},
            contextId: contextid,
            triggerElement: bulkactionselector,
        });
        // Override class method to reload user list after submit form.
        modal.onSubmitSuccess = (data) => {
            successtext = 'userseditedsuccess';
            infotext = 'usersskipped';
            showBulkNotifications(data, successtext, infotext);
            reloadReport();
            // Reset dropdown.
            bulkactionselector.value = '';
        };
        // Reset dropdown.
        bulkactionselector.value = '';
    } else {
        let func, headertext, confirmtext, buttontext, successtext, infotext;
        switch (bulkactionselector.value) {
            case 'resetusersprogram':
                func = 'tool_program_bulk_reset_program_progress';
                headertext = 'resetprogress';
                confirmtext = 'confirmresetusersprogramusers';
                buttontext = 'reset';
                successtext = 'usersresetprogramsuccess';
                infotext = 'usersskipped';
                break;
            case 'deallocateusers':
                func = 'tool_program_bulk_deallocate_user';
                headertext = 'confirmdeallocateusersheader';
                confirmtext = 'confirmdeallocateusers';
                buttontext = 'deallocateusers';
                successtext = 'usersdeallocatedsuccess';
                infotext = 'usersskipped';
                break;
        }

        if (typeof func === 'undefined') {
            return;
        }

        getStrings([
            {key: headertext, component: 'tool_program'},
            {key: confirmtext, component: 'tool_program'},
            {key: buttontext, component: 'tool_program'},
        ]).then(([headertext, confirmtext, buttontext]) => {
            Notification.confirm(headertext, confirmtext, buttontext, null, () => {
                const pendingPromise = new Pending('tool/program:bulkSelectorPerfomAction');
                const requests = Ajax.call([{
                    methodname: func,
                    args: {programuserids: programuserids}
                }]);
                requests[0].then((response) => {
                    showBulkNotifications(response, successtext, infotext);
                    // Reset dropdown.
                    bulkactionselector.value = '';
                    return pendingPromise.resolve();
                }).catch(Notification.exception);
            }, () => {
                // Reset dropdown when pressing Cancel button.
                bulkactionselector.value = '';
            }).catch(Notification.exception);
            return null;
        }).catch(Notification.exception);
    }
};

let initialized = false;

const init = (formSelector) => {

    enableBulkActions(formSelector);

    if (initialized) {
        // We already added the event listeners (can be called multiple times by mustache template).
        return;
    }

    document.addEventListener('change', (event) => {

        // Select/deselect bulk checkboxes.
        const toggleGroup = event.target.closest('[data-togglegroup="program-users"]');
        if (toggleGroup) {
            enableDisableBulkActionSelector(formSelector);
        }

        // Change on bulk action selector.
        const changeBulkActionSelector = event.target.closest(formSelector + ' select');
        if (changeBulkActionSelector) {
            bulkSelectorPerfomAction(formSelector);
        }
    });

    // This is needed to reset checkboxes/dropdown after performing an action.
    $(document).on(M.core.event.FILTER_CONTENT_UPDATED, function() {
        enableDisableBulkActionSelector(formSelector);
        enableBulkActions();
    });

    initialized = true;
};

export default {
    init: init
};

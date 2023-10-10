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
 * User allocation module for bulk actions
 *
 * @module     tool_program/user_allocations_bulk_actions
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import Ajax from 'core/ajax';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import Templates from 'core/templates';
import * as WpNotification from 'tool_wp/notification';
import Pending from 'core/pending';
import * as reportSelectors from 'core_reportbuilder/local/selectors';
import {dispatchEvent} from 'core/event_dispatcher';
import * as reportEvents from 'core_reportbuilder/local/events';
import * as tableEvents from 'core_table/local/dynamic/events';

/** @type {Object} The list of selectors for the program allocations area. */
const Selector = {
    editProgramViewRegion: '.wptabs',
    bulkCheckHeader: `${reportSelectors.regions.reportTable} th.c0`,
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

        getStrings([
            {key: 'selectall', component: 'moodle'},
            {key: 'deselectall', component: 'moodle'}
        ]).then(([selectall, deselectall]) => {
            return Templates.render('core/checkbox-toggleall-master', {
                togglegroup: 'program-users',
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
 */
const reloadReport = () => {
    const reportElement = document.querySelector(reportSelectors.regions.report);
    dispatchEvent(reportEvents.tableReload, {preservePagination: true}, reportElement);
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
        let args = {programid: programid};
        // TODO MDL-71686 preserve arrays (remove when integrated).
        programuserids.forEach((u, k) => (args[`programuserids[${k}]`] = u));
        const modal = new ModalForm({
            formClass: 'tool_program\\form\\edit_program_users_edit_form_modal_bulk',
            args,
            modalConfig: {title: getString('editstatusanddatesbulk', 'tool_program')},
            contextId: contextid,
            returnFocus: bulkactionselector,
        });
        modal.show();
        // Override class method to reload user list after submit form.
        modal.addEventListener(modal.events.FORM_SUBMITTED, (e) => {
            successtext = 'userseditedsuccess';
            infotext = 'usersskipped';
            showBulkNotifications(e.detail, successtext, infotext);
            reloadReport();
            // Reset dropdown.
            bulkactionselector.value = '';
        });
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
            case 'recalculateprogram':
                func = 'tool_program_recalculate_program_user_completions';
                headertext = 'recalculateprogramcompletion';
                confirmtext = 'confirmrecalculateprogress';
                buttontext = 'recalculateprogramcompletion';
                successtext = 'usersrecalculationcompletion';
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
    document.addEventListener(tableEvents.tableContentRefreshed, event => {
        const reportElement = event.target.closest(reportSelectors.regions.report);
        if (reportElement) {
            enableDisableBulkActionSelector(formSelector);
            enableBulkActions();
        }
    });

    initialized = true;
};

export default {
    init: init
};

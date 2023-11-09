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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

/**
 * People tab module for bulk actions
 *
 * @module     tool_organisation/people_bulk_actions
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Odei Alba <odei.alba@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import ModalForm from 'core_form/modalform';
import {prefetchStrings} from 'core/prefetch';
import {add as addToast} from 'core/toast';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import * as reportSelectors from 'core_reportbuilder/local/selectors';
import {dispatchEvent} from 'core/event_dispatcher';
import * as reportEvents from 'core_reportbuilder/local/events';
import * as tableEvents from 'core_table/local/dynamic/events';
import {deleteManuallyAssignedManagers} from 'tool_organisation/local/repository';
import Pending from 'core/pending';
import Notification from 'core/notification';

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
        const countCheckboxesChecked = document.querySelectorAll('[data-togglegroup="report-select-all"]:checked').length;
        bulkactionselector.disabled = !countCheckboxesChecked;
    }
};

/**
 * Reloads the report
 */
const reloadReport = () => {
    const reportElement = document.querySelector(reportSelectors.regions.report);
    dispatchEvent(reportEvents.tableReload, {preservePagination: true}, reportElement);
};

/**
 * Show modal form for different actions
 *
 * @param {String} formClass
 * @param {Object} args
 * @param {Object} modalConfig
 * @param {String} saveButtonText
 * @param {Element} triggerElement
 * @param {String} toastMessage
 */
const showModalForm = (formClass, args, modalConfig, saveButtonText, triggerElement, toastMessage) => {
    const modal = new ModalForm({
        formClass: formClass,
        args: args,
        modalConfig: modalConfig,
        saveButtonText: saveButtonText,
        contextId: systemContextId,
        returnFocus: triggerElement,
    });

    modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
        addToast(toastMessage, {type: 'success'});
        reloadReport();
    });

    modal.show();
};

/**
 * Perform selected action on the bulk selector
 *
 * @param {String} formSelector
 */
const bulkSelectorPerfomAction = (formSelector) => {
    const bulkactionselector = document.querySelector(formSelector + ' select');
    let userids = [];

    // Get user ids from all checkboxes that are checked.
    const checkboxesChecked = document.querySelectorAll('[data-togglegroup="report-select-all"]:checked');
    userids = [...checkboxesChecked].map(checkbox => checkbox.value);

    // Check if we have selected the option to edit status and dates on the bulk selector.
    if (bulkactionselector.value === 'addjobs') {
        showModalForm(
            'tool_organisation\\add_jobassign_form',
            {userids: userids.join(','), action: 'addjobs'},
            {title: getString('addjobselectedusers', 'tool_organisation'), scrollable: false},
            getString('save', 'core'),
            bulkactionselector,
            getString('jobscreated', 'tool_organisation')
        );
    } else if (bulkactionselector.value === 'setjobsfinished') {
        showModalForm(
            'tool_organisation\\add_jobassign_form',
            {userids: userids.join(','), action: 'setjobsfinished'},
            {title: getString('setjobsfinished', 'tool_organisation'), scrollable: false, type: 'SAVE_CANCEL'},
            getString('proceed', 'core'),
            bulkactionselector,
            getString('jobsupdated', 'tool_organisation')
        );
    } else if (bulkactionselector.value === 'assignmanagers') {
        showModalForm(
            'tool_organisation\\add_manually_assigned_manager_form',
            {userids: userids.join(','), title: 'assignmanagers'},
            {title: getString('assignmanager', 'tool_organisation'), scrollable: false},
            getString('save', 'core'),
            bulkactionselector,
            getString('manuallyassignedcreated', 'tool_organisation')
        );
    } else if (bulkactionselector.value === 'unassignmanager') {
        const requiredStrings = [
            {key: 'confirm', component: 'core'},
            {key: 'confirmunassignmanagers', component: 'tool_organisation'},
            {key: 'unassignmanagers', component: 'tool_organisation'},
        ];

        // TODO WP-4426 fix properly.
        /* eslint-disable promise/no-nesting */
        getStrings(requiredStrings).then(([confirm, confirmunassignmanagers, unassignmanagers]) => {
            return Notification.confirm(confirm, confirmunassignmanagers, unassignmanagers, null, () => {
                const pendingPromise = new Pending('tool/organisation:unassignmanagers');
                const request = deleteManuallyAssignedManagers(userids, [], true);
                request.then(response => {
                    if (response.warnings.length > 0) {
                        addToast(response.warnings[0].message, {type: 'error'});
                    } else {
                        addToast(getString('managersunassigned', 'tool_organisation'), {type: 'success'});
                    }
                    reloadReport();
                    return pendingPromise.resolve();
                }).catch(Notification.exception);
            }).catch(Notification.exception);
        }).catch(Notification.exception);
        /* eslint-enable promise/no-nesting */
    } else if (bulkactionselector.value === 'transferalltojob') {
        showModalForm(
            'tool_organisation\\add_jobassign_form',
            {userids: userids.join(','), action: 'transferalltojob'},
            {title: getString('transfertoanewjob', 'tool_organisation'), type: 'SAVE_CANCEL'},
            getString('proceed', 'core'),
            bulkactionselector,
            getString('jobstransfered', 'tool_organisation')
        );
    }

    // Reset dropdown.
    bulkactionselector.value = '';
};

let initialized = false;
let systemContextId = 0;

const init = (formSelector, contextId) => {
    enableDisableBulkActionSelector(formSelector);

    if (initialized) {
        // We already added the event listeners (can be called multiple times by mustache template).
        return;
    }

    systemContextId = contextId;
    prefetchStrings('tool_organisation', [
        'addjob',
        'addjobselectedusers',
        'assignmanager',
        'confirmunassignmanagers',
        'jobscreated',
        'jobstransfered',
        'jobsupdated',
        'managersunassigned',
        'manuallyassignedcreated',
        'setjobsfinished',
        'transfertoanewjob',
        'unassignmanagers',
    ]);

    prefetchStrings('core', [
        'confirm',
        'proceed',
        'save',
    ]);

    document.addEventListener('change', (event) => {
        // Select/deselect bulk checkboxes.
        const toggleGroup = event.target.closest('[data-togglegroup="report-select-all"]');
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
        }
    });

    initialized = true;
};

export default {
    init: init
};

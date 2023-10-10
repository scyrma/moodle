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
 * This module instantiates the functionality to manage manually assigned managers.
 *
 * @module     tool_organisation/manage_mam
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import Pending from 'core/pending';
import {prefetchStrings} from 'core/prefetch';
import Notification from 'core/notification';
import {add as addToast} from 'core/toast';
import {get_string as getString} from 'core/str';
import {dispatchEvent} from 'core/event_dispatcher';
import ModalForm from 'core_form/modalform';
import Selectors from 'tool_organisation/local/selectors';
import * as Repository from 'tool_organisation/local/repository';
import * as reportEvents from 'core_reportbuilder/local/events';
import * as reportSelectors from 'core_reportbuilder/local/selectors';

/**
 * Show manually assigned manager modal form
 *
 * @param {Object} args
 * @param {Object} modalConfig
 * @param {String} saveButtonText
 * @param {Element} triggerElement
 * @param {String} toastMessage
 */
const showMamModalForm = (args, modalConfig, saveButtonText, triggerElement, toastMessage) => {
    // In case that triggerElement has a data-reportselector attribute, we need to use it to find the report element,
    // otherwise we use the default one '.tab-pane.active'.
    const reportSelector = triggerElement.dataset.reportselector;

    // We need to check if the triggerElement is inside a dropdown, and if so, use the dropdown toggle as the return focus.
    const hasDropdownClass = triggerElement.classList.contains('dropdown');
    triggerElement = hasDropdownClass ? triggerElement.closest('.dropdown').querySelector('.dropdown-toggle') : triggerElement;

    const modal = new ModalForm({
        formClass: 'tool_organisation\\add_manually_assigned_manager_form',
        args: args,
        modalConfig: modalConfig,
        saveButtonText: saveButtonText,
        contextId: systemContextId,
        returnFocus: triggerElement
    });

    modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
        const report = triggerElement.closest(reportSelector).querySelector(reportSelectors.regions.report);
        dispatchEvent(reportEvents.tableReload, {preservePagination: true}, report);
        addToast(toastMessage, {type: 'success'});
    });

    modal.show();
};

/**
 * New manually assigned manager handler
 *
 * @param {Element} element
 * @param {string} titleidentifier
 * @param {string} toastidenfier
 * @param {object} params
 */
const newManuallyAssignedManager = (element, titleidentifier, toastidenfier, params = {}) => {
    const title = getString(titleidentifier, 'tool_organisation');
    showMamModalForm(
        params,
        {title: title, scrollable: false},
        getString('save', 'core'),
        element,
        getString(toastidenfier, 'tool_organisation'),
    );
};

/**
 * Delete manually assigned manager handler
 *
 * @param {Element} element
 */
const deleteManuallyAssignedManager = (element) => {
    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');

    Notification.saveCancelPromise(
        getString('confirm', 'core'),
        getString('manuallyassigneddeletedconfirm', 'tool_organisation'),
        getString('delete', 'core'),
        {triggerElement}
    )
        .then(() => {
            const pendingPromise = new Pending('tool/organisation:deleteMam');
            return Repository.deleteManuallyAssignedManager(element.dataset.userid, element.dataset.managerid)
                .then(() => addToast(getString('manuallyassigneddeleted', 'tool_organisation'), {type: 'success'}))
                .then(() => {
                    const report = triggerElement.closest(reportSelectors.regions.report);
                    dispatchEvent(reportEvents.tableReload, {preservePagination: true}, report);
                    return pendingPromise.resolve();
                })
                .catch(Notification.exception);
        })
        .catch(() => {
            return;
        });
};

let initialized = false;
let systemContextId = 0;

/**
 * Initialise module, ensuring we load our resources and event listeners only once
 *
 * @param {Integer} contextId
 */
export const init = (contextId) => {
    if (initialized) {
        return;
    }

    systemContextId = contextId;

    prefetchStrings('tool_organisation', [
        'addmanagerusers',
        'editmanuallyassignedmanager',
        'manuallyassignedcreated',
        'manuallyassigneddeleted',
        'manuallyassigneddeletedconfirm',
        'manuallyassignedupdated',
    ]);

    prefetchStrings('core', [
        'confirm',
        'delete',
        'save',
    ]);

    document.addEventListener('click', event => {
        // New manually assigned manager.
        const newManuallyAssignedManagerElement = event.target.closest(Selectors.actions.newManuallyAssignedManager);
        if (newManuallyAssignedManagerElement) {
            event.preventDefault();
            newManuallyAssignedManager(newManuallyAssignedManagerElement,
                newManuallyAssignedManagerElement.dataset.title,
                'manuallyassignedcreated',
                newManuallyAssignedManagerElement.dataset
            );
        }

        // Edit manually assigned manager permissions.
        const editManuallyAssignedManager = event.target.closest(Selectors.actions.editManuallyAssignedManager);
        if (editManuallyAssignedManager) {
            event.preventDefault();
            newManuallyAssignedManager(editManuallyAssignedManager,
                editManuallyAssignedManager.dataset.title,
                'manuallyassignedupdated',
                editManuallyAssignedManager.dataset
            );
        }

        // Delete manually assigned manager.
        const deleteAssignment = event.target.closest(Selectors.actions.deleteManuallyAssignedManager);
        if (deleteAssignment) {
            event.preventDefault();
            deleteManuallyAssignedManager(deleteAssignment);
        }
    });

    initialized = true;
};

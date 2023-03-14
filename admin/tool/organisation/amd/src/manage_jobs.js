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
 * This module instantiates the functionality to manage jobs.
 *
 * @module     tool_organisation/manage_jobs
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo
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
 * Show jobs modal form
 *
 * @param {Object} args
 * @param {Object} modalConfig
 * @param {String} saveButtonText
 * @param {Element} triggerElement
 * @param {String} toastMessage
 */
const showJobsModalForm = (args, modalConfig, saveButtonText, triggerElement, toastMessage) => {
    const modal = new ModalForm({
        formClass: 'tool_organisation\\add_jobassign_form',
        args: args,
        modalConfig: modalConfig,
        saveButtonText: saveButtonText,
        contextId: systemContextId,
        returnFocus: triggerElement
    });

    modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
        const report = triggerElement.closest('.tab-pane.active').querySelector(reportSelectors.regions.report);
        dispatchEvent(reportEvents.tableReload, {preservePagination: true}, report);
        addToast(toastMessage, {type: 'success'});
    });

    modal.show();
};

/**
 * New job handler
 *
 * @param {Element} element
 */
const newJob = (element) => {
    const title = getString('addjob', 'tool_organisation');

    if (1 === parseInt(element.dataset.cancreatejobs, 10)) {
        showJobsModalForm(
            {},
            {title: title, scrollable: false},
            getString('save', 'core'),
            element,
            getString('jobcreated', 'tool_organisation'),
        );
    } else {
        Notification.alert(
            getString('notification', 'tool_organisation'),
            getString('notificationcannotcreatejobs', 'tool_organisation'),
            getString('ok', 'core'),
        );
    }
};

/**
 * Delete job handler
 *
 * @param {Element} element
 */
const deleteJob = (element) => {
    const id = element.dataset.id;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');

    Notification.saveCancelPromise(
        getString('confirm', 'core'),
        getString('jobdeleteconfirm', 'tool_organisation'),
        getString('delete', 'core'),
        {triggerElement}
    )
    .then(() => {
        const pendingPromise = new Pending('tool/organisation:deleteJob');
        return Repository.deleteJob(id)
            .then(() => addToast(getString('jobdeleted', 'tool_organisation'), {type: 'success'}))
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

/**
 * Transfer to job handler
 *
 * @param {Element} element
 */
const transferToJob = (element) => {
    const title = getString('transfertojob', 'tool_organisation', element.dataset.fullusername);
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');

    showJobsModalForm(
        {id: element.dataset.id, action: 'transfertojob'},
        {title: title, type: 'SAVE_CANCEL'},
        getString('proceed', 'core'),
        triggerElement,
        getString('jobtransfered', 'tool_organisation'),
    );
};

/**
 * Set job as finished handler
 *
 * @param {Element} element
 */
const setJobFinished = (element) => {
    const title = getString('setjobfinished', 'tool_organisation', element.dataset.fullusername);
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');

    showJobsModalForm(
        {id: element.dataset.id, action: 'setjobfinished'},
        {title: title, type: 'SAVE_CANCEL'},
        getString('proceed', 'core'),
        triggerElement,
        getString('jobupdated', 'tool_organisation'),
    );
};

/**
 * Assign another job handler
 *
 * @param {Element} element
 */
const assignAnotherJob = (element) => {
    const title = getString('addjobforuser', 'tool_organisation', element.dataset.fullusername);
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');

    showJobsModalForm(
        {userid: element.dataset.userid},
        {title: title, type: 'SAVE_CANCEL'},
        getString('proceed', 'core'),
        triggerElement,
        getString('jobcreated', 'tool_organisation'),
    );
};

/**
 * Edit job dates handler
 *
 * @param {Element} element
 */
const editJobDates = (element) => {
    const title = getString('editjobdatesforuser', 'tool_organisation', element.dataset.fullusername);
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');

    showJobsModalForm(
        {id: element.dataset.id, action: 'editjobdates'},
        {title: title, type: 'SAVE_CANCEL'},
        getString('save', 'core'),
        triggerElement,
        getString('jobupdated', 'tool_organisation'),
    );
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
        'editjobdatesforuser',
        'transfertojob',
        'addjob',
        'setjobfinished',
        'jobdeleteconfirm',
        'jobdeleted',
        'jobcreated',
        'jobtransfered',
        'jobupdated',
        'notification',
        'notificationcannotcreatejobs',
    ]);

    prefetchStrings('core', [
        'confirm',
        'delete',
        'ok',
        'save',
        'proceed',
    ]);

    document.addEventListener('click', event => {

        // New job.
        const newJobElement = event.target.closest(Selectors.actions.newJob);
        if (newJobElement) {
            event.preventDefault();
            newJob(newJobElement);
        }

        // Delete job.
        const deleteJobElement = event.target.closest(Selectors.actions.deleteJob);
        if (deleteJobElement) {
            event.preventDefault();
            deleteJob(deleteJobElement);
        }

        // Transfer to job.
        const transferToJobElement = event.target.closest(Selectors.actions.transferToJob);
        if (transferToJobElement) {
            event.preventDefault();
            transferToJob(transferToJobElement);
        }

        // Set job as finished.
        const setJobFinishedElement = event.target.closest(Selectors.actions.setJobFinished);
        if (setJobFinishedElement) {
            event.preventDefault();
            setJobFinished(setJobFinishedElement);
        }

        // Assign another job.
        const assignAnotherJobElement = event.target.closest(Selectors.actions.assignAnotherJob);
        if (assignAnotherJobElement) {
            event.preventDefault();
            assignAnotherJob(assignAnotherJobElement);
        }

        // Edit job dates.
        const editJobDatesElement = event.target.closest(Selectors.actions.editJobDates);
        if (editJobDatesElement) {
            event.preventDefault();
            editJobDates(editJobDatesElement);
        }
    });

    initialized = true;
};

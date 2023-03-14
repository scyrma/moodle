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
 * This module instantiates the functionality to manage programs list.
 *
 * @module     tool_program/program_manager
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import {dispatchEvent} from 'core/event_dispatcher';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import * as reportEvents from 'core_reportbuilder/local/events';
import * as reportSelectors from 'core_reportbuilder/local/selectors';
import {prefetchStrings} from 'core/prefetch';
import {get_string as getString} from 'core/str';
import Pending from 'core/pending';
import * as Repository from 'tool_program/local/repository';

/** @type {Object} The list of selectors for the program area. */
const SELECTORS = {
        ARCHIVEPROGRAM: "[data-action='archive']",
        RESTOREPROGRAM: "[data-action='restore']",
        DUPLICATEPROGRAM: "[data-action='duplicate']",
        DELETEPROGRAM: "[data-action='delete']",
        UPDATEPROGRAMVISIBILITY: "[data-action='updatevisibility']",
        ADDPROGRAMBUTTON: ".wptabs .tab-pane.active [data-tabs-element='addbutton']",
};

/**
 * Handles archive a Program.
 *
 * @param {Element} element
 */
const archiveProgram = element => {
    const {programid, name} = element.dataset;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'core'),
        getString('archivedconfirmation', 'tool_program', name),
        getString('archive', 'tool_program'),
        {triggerElement}
    ).then(() => {
        const pendingPromise = new Pending('tool/program:archiveProgram');

        return Repository.archiveProgram(programid)
            .then(response => {
                if (response) {
                    reloadReport(element);
                }
                return pendingPromise.resolve();
            })
            .catch(Notification.exception);
    }).catch(() => {
        return;
    });
};

/**
 * Handles restore a Program.
 *
 * @param {Element} element
 */
const restoreProgram = element => {
    const pendingPromise = new Pending('tool/program:restoreProgram');
    const programid = element.dataset.programid;

    Repository.restoreProgram(programid)
        .then(response => {
            if (response) {
                reloadReport(element);
            }
            return pendingPromise.resolve();
        })
        .catch(Notification.exception);
};

/**
 * Updates a Program visibility.
 *
 * @param {Element} element
 */
const updateProgramVisibility = element => {
    const pendingPromise = new Pending('tool/program:updateProgramVisibility');
    const {programid, visibility} = element.dataset;

    Repository.updateProgramVisibility(programid, visibility)
        .then(response => {
            if (response) {
                reloadReport(element);
            }
            return pendingPromise.resolve();
        })
        .catch(Notification.exception);
};

/**
 * Handles delete a Program.
 *
 * @param {Element} element
 */
const deleteProgram = element => {
    const {programid, name} = element.dataset;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'core'),
        getString('confirmdeleteprogram', 'tool_program', name),
        getString('delete', 'core'),
        {triggerElement}
    ).then(() => {
        const pendingPromise = new Pending('tool/program:deleteProgram');

        return Repository.deleteProgram(programid)
            .then(response => {
                if (response) {
                    reloadReport(element);
                }
                return pendingPromise.resolve();
            })
            .catch(Notification.exception);
    }).catch(() => {
        return;
    });
};

/**
 * Handles duplicate a Program.
 *
 * @param {Element} element
 */
const duplicateProgram = element => {
    const programid = element.dataset.programid;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'core'),
        getString('confirmduplicate', 'tool_program'),
        getString('ok', 'core'),
        {triggerElement}
    ).then(() => {
        const pendingPromise = new Pending('tool/program:duplicateProgram');

        return Repository.duplicateProgram(programid)
            .then(response => {
                if (response) {
                    reloadReport(element);
                }
                return pendingPromise.resolve();
            })
            .catch(Notification.exception);
    }).catch(() => {
        return;
    });
};

/**
 * Handles add a new program.
 *
 * @param {Element} element
 */
const addProgram = element => {
    // The argument 'isajax' will avoid showing the 'Save' button twice in the modal.
    const modal = new ModalForm({
        formClass: 'tool_program\\form\\edit_program_details_form',
        args: {id: 0, isajax: 1},
        modalConfig: {title: getString('newprogram', 'tool_program')},
        returnFocus: element,
        saveButtonText: getString('save', 'core')
    });

    modal.addEventListener(modal.events.FORM_SUBMITTED, (e) => {
        window.location.href = e.detail;
    });

    modal.show();
};

/**
 * Reloads the report
 *
 * @param {Element} element
 */
const reloadReport = element => {
    const reportElement = element.closest(reportSelectors.regions.report);
    dispatchEvent(reportEvents.tableReload, {preservePagination: true}, reportElement);
};

let initialized = false;

/**
 * Initialise module, ensuring we load our resources and event listeners only once
 */
export const init = () => {
    if (initialized) {
        return;
    }

    prefetchStrings('tool_program', [
        'archive',
        'archivedconfirmation',
        'confirmdeleteprogram',
        'confirmduplicate',
        'newprogram',
    ]);

    prefetchStrings('core', [
        'confirm',
        'delete',
        'ok',
        'save',
    ]);

    document.addEventListener('click', event => {

        // Archive program.
        const archiveProgramElement = event.target.closest(SELECTORS.ARCHIVEPROGRAM);
        if (archiveProgramElement) {
            event.preventDefault();
            archiveProgram(archiveProgramElement);
        }

        // Restore program.
        const restoreProgramElement = event.target.closest(SELECTORS.RESTOREPROGRAM);
        if (restoreProgramElement) {
            event.preventDefault();
            restoreProgram(restoreProgramElement);
        }

        // Duplicate program.
        const duplicateProgramElement = event.target.closest(SELECTORS.DUPLICATEPROGRAM);
        if (duplicateProgramElement) {
            event.preventDefault();
            duplicateProgram(duplicateProgramElement);
        }

        // Delete program.
        const deleteProgramElement = event.target.closest(SELECTORS.DELETEPROGRAM);
        if (deleteProgramElement) {
            event.preventDefault();
            deleteProgram(deleteProgramElement);
        }

        // Update program visibility.
        const updateProgramVisibilityElement = event.target.closest(SELECTORS.UPDATEPROGRAMVISIBILITY);
        if (updateProgramVisibilityElement) {
            event.preventDefault();
            updateProgramVisibility(updateProgramVisibilityElement);
        }

        // Add new program.
        const addProgramElement = event.target.closest(SELECTORS.ADDPROGRAMBUTTON);
        if (addProgramElement) {
            event.preventDefault();
            addProgram(addProgramElement);
        }
    });

    initialized = true;
};

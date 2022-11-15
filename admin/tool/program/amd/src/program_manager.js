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

import $ from 'jquery';
import Ajax from 'core/ajax';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import ReportEvents from 'tool_reportbuilder/reportbuilder_events';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import Pending from 'core/pending';

/** @type {Object} The list of selectors for the program area. */
const SELECTORS = {
        EDITDETAILS: "[data-action='editdetails']",
        ARCHIVEPROGRAM: "[data-action='archive']",
        RESTOREPROGRAM: "[data-action='restore']",
        DUPLICATEPROGRAM: "[data-action='duplicate']",
        DELETEPROGRAM: "[data-action='delete']",
        UPDATEPROGRAMVISIBILITY: "[data-action='updatevisibility']",
        REPORTCONTAINER: "[data-region='system-report'] [data-region='data-report']",
        ADDPROGRAMBUTTON: ".wptabs .tab-pane.active [data-tabs-element='addbutton']",
        INPLACEEDITABLE: '.inplaceeditable[data-itemtype=programname]',
        TABS: ".wptabs",
    },
    SERVICES = {
        ARCHIVEPROGRAM: 'tool_program_archive_program',
        RESTOREPROGRAM: 'tool_program_restore_program',
        DUPLICATEPROGRAM: 'tool_program_duplicate_program',
        DELETEPROGRAM: 'tool_program_delete_program',
        UPDATEPROGRAMVISIBILITY: 'tool_program_update_program_visibility'
    };

/**
 * Handles archive a Program.
 *
 * @param {Number} programid
 * @param {String} name
 * @private
 */
const archiveProgram = (programid, name) => {
    getStrings([
        {key: 'confirm', component: 'moodle'},
        {key: 'archivedconfirmation', component: 'tool_program', param: name},
        {key: 'archive', component: 'tool_program'}
    ]).then(([confirm, archiveConfirmation, archive]) => {
        Notification.confirm(confirm, archiveConfirmation, archive, null, () => {
            const pendingPromise = new Pending('tool/program:archiveProgram');
            const request = Ajax.call([
                {methodname: SERVICES.ARCHIVEPROGRAM, args: {programid: programid}}
            ]);
            request[0].then((response) => {
                if (response) {
                    reloadReport();
                }
                return pendingPromise.resolve();
            }).catch(Notification.exception);
        });
        return null;
    }).catch(Notification.exception);
};

/**
 * Handles restore a Program.
 *
 * @param {Number} programid
 * @private
 */
const restoreProgram = (programid) => {
    const request = {
        methodname: SERVICES.RESTOREPROGRAM,
        args: {programid: programid}
    };

    const pendingPromise = new Pending('tool/program:restoreProgram');
    Ajax.call([request])[0].then((response) => {
        if (response) {
            reloadReport();
        }
        return pendingPromise.resolve();
    }).catch(Notification.exception);
};

/**
 * Updates a Program visibility.
 *
 * @param {Number} programid
 * @param {Number} visibility
 * @private
 */
const updateProgramVisibility = (programid, visibility) => {
    const request = {
        methodname: SERVICES.UPDATEPROGRAMVISIBILITY,
        args: {
            programid: programid,
            visibility: visibility
        }
    };

    const pendingPromise = new Pending('tool/program:updateProgramVisibility');
    Ajax.call([request])[0].then((response) => {
        if (response) {
            reloadReport();
        }
        return pendingPromise.resolve();
    }).catch(Notification.exception);
};

/**
 * Handles delete a Program.
 *
 * @param {Number} programid
 * @param {String} name
 * @private
 */
const deleteProgram = (programid, name) => {
    getStrings([
        {key: 'confirm', component: 'moodle'},
        {key: 'confirmdeleteprogram', component: 'tool_program', param: name},
        {key: 'delete', component: 'moodle'},
    ]).then(([confirm, archiveConfirmation, archive]) => {
        Notification.confirm(confirm, archiveConfirmation, archive, null, () => {
            const pendingPromise = new Pending('tool/program:deleteProgram');
            const promises = Ajax.call([
                {methodname: SERVICES.DELETEPROGRAM, args: {programid: programid}}
            ]);
            promises[0].then((response) => {
                if (response) {
                    reloadReport();
                }
                return pendingPromise.resolve();
            }).catch(Notification.exception);
        });
        return null;
    }).catch(Notification.exception);
};

/**
 * Handles duplicate a Program.
 *
 * @param {Number} programid
 * @private
 */
const duplicateProgram = (programid) => {
    getStrings([
        {key: 'confirm', component: 'moodle'},
        {key: 'confirmduplicate', component: 'tool_program'},
        {key: 'ok', component: 'moodle'},
    ]).then(([confirm, confirmduplicate, ok]) => {
        Notification.confirm(confirm, confirmduplicate, ok, null, () => {
            const pendingPromise = new Pending('tool/program:duplicateProgram');
            const promises = Ajax.call([
                {methodname: SERVICES.DUPLICATEPROGRAM, args: {programid: programid}}
            ]);
            promises[0].then((data) => {
                if (data) {
                    reloadReport();
                }
                return pendingPromise.resolve();
            }).catch(Notification.exception);
        });
        return null;
    }).catch(Notification.exception);
};

/**
 * Popup to edit program details
 *
 * @param {EventTarget} triggerElement
 * @param {Number} programid
 * @param {Promise} title
 * @return {ModalForm}
 * @private
 */
const showDetailsModal = (triggerElement, programid, title) => {
    var modal = new ModalForm({
        formClass: 'tool_program\\form\\edit_program_details_form',
        args: {id: programid},
        modalConfig: {title: title},
        returnFocus: triggerElement,
        saveButtonText: getString('save')
    });
    modal.show();
    return modal;
};

/**
 * Handles add a new program.
 *
 * @param {Event} event
 * @private
 */
const addProgram = (event) => {
    const modal = showDetailsModal(event.target, 0, getString('newprogram', 'tool_program'));
    modal.addEventListener(modal.events.FORM_SUBMITTED, (e) => {
        window.location.href = e.detail;
    });
};

/**
 * Handles edit program details event
 *
 * @param {Event} event
 * @param {Number} programid
 * @param {String} name
 * @private
 */
const editProgramDetails = (event, programid, name) => {
    const modal = showDetailsModal(event.target, programid, getString('editprogram', 'tool_program', name));
    modal.addEventListener(modal.events.FORM_SUBMITTED, () => reloadReport());
};

/**
 * Reloads the report
 *
 * Note: We are still using JQuery here because ReportEvents has not been updated to ES6 yet.
 */
const reloadReport = () => {
    $(SELECTORS.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
};

let initialized = false;

const init = () => {

    if (initialized) {
        // We already added the event listeners (can be called multiple times by mustache template).
        return;
    }

    document.querySelector(SELECTORS.TABS).addEventListener('click', (event) => {

        // Archive program.
        const archiveProgramElement = event.target.closest(SELECTORS.ARCHIVEPROGRAM);
        if (archiveProgramElement) {
            event.preventDefault();
            const programid = archiveProgramElement.dataset.programid;
            const name = archiveProgramElement.dataset.name;
            archiveProgram(programid, name);
        }

        // Restore program.
        const restoreProgramElement = event.target.closest(SELECTORS.RESTOREPROGRAM);
        if (restoreProgramElement) {
            event.preventDefault();
            const programid = restoreProgramElement.dataset.programid;
            restoreProgram(programid);
        }

        // Duplicate program.
        const duplicateProgramElement = event.target.closest(SELECTORS.DUPLICATEPROGRAM);
        if (duplicateProgramElement) {
            event.preventDefault();
            const programid = duplicateProgramElement.dataset.programid;
            duplicateProgram(programid);
        }

        // Delete program.
        const deleteProgramElement = event.target.closest(SELECTORS.DELETEPROGRAM);
        if (deleteProgramElement) {
            event.preventDefault();
            const programid = deleteProgramElement.dataset.programid;
            const name = deleteProgramElement.dataset.name;
            deleteProgram(programid, name);
        }

        // Update program visibility.
        const updateProgramVisibilityElement = event.target.closest(SELECTORS.UPDATEPROGRAMVISIBILITY);
        if (updateProgramVisibilityElement) {
            event.preventDefault();
            const programid = updateProgramVisibilityElement.dataset.programid;
            const visibility = updateProgramVisibilityElement.dataset.visibility;
            updateProgramVisibility(programid, visibility);
        }

        // Edit program details.
        const updateProgramDetailsElement = event.target.closest(SELECTORS.EDITDETAILS);
        if (updateProgramDetailsElement) {
            event.preventDefault();
            const programid = updateProgramDetailsElement.dataset.id;
            const name = updateProgramDetailsElement.dataset.name;
            editProgramDetails(event, programid, name);
        }

        // Add new program.
        const addProgramElement = event.target.closest(SELECTORS.ADDPROGRAMBUTTON);
        if (addProgramElement) {
            event.preventDefault();
            addProgram(event);
        }
    });

    initialized = true;
};

export default {
    init: init
};

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
 * This module instantiates the functionality to manage certifications list.
 *
 * @module     tool_certification/certification_manager
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import {dispatchEvent} from 'core/event_dispatcher';
import Notification from 'core/notification';
import * as reportEvents from 'core_reportbuilder/local/events';
import * as reportSelectors from 'core_reportbuilder/local/selectors';
import {prefetchStrings} from 'core/prefetch';
import {get_string as getString} from 'core/str';
import Pending from 'core/pending';
import {showDetailsModal} from 'tool_certification/local/modals';
import * as Repository from 'tool_certification/local/repository';

/** @type {Object} The list of selectors for the certification area. */
const SELECTORS = {
        ARCHIVECERTIFICATION: "[data-action='archive']",
        RESTORECERTIFICATION: "[data-action='restore']",
        DUPLICATECERTIFICATION: "[data-action='duplicate']",
        DELETECERTIFICATION: "[data-action='delete']",
        ADDCERTIFICATIONBUTTON: ".wptabs .tab-pane.active [data-tabs-element='addbutton']",
};

/**
 * Archive certification handler
 *
 * @param {Element} element
 */
const archiveCertification = element => {
    const {certificationid, name} = element.dataset;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'core'),
        getString('archivedconfirmation', 'tool_certification', name),
        getString('archive', 'tool_certification'),
        {triggerElement}
    ).then(() => {
        const pendingPromise = new Pending('tool/certification:archiveCertification');

        return Repository.archiveCertification(certificationid)
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
 * Restore certification handler
 *
 * @param {Element} element
 */
const restoreCertification = element => {
    const pendingPromise = new Pending('tool/certification:restoreCertification');
    const certificationid = element.dataset.certificationid;

    Repository.restoreCertification(certificationid)
        .then(response => {
            if (response) {
                reloadReport(element);
            }
            return pendingPromise.resolve();
        })
        .catch(Notification.exception);
};

/**
 * Delete certification handler
 *
 * @param {Element} element
 */
const deleteCertification = element => {
    const {certificationid, name} = element.dataset;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'core'),
        getString('confirmdeletecertification', 'tool_certification', name),
        getString('delete', 'core'),
        {triggerElement}
    ).then(() => {
        const pendingPromise = new Pending('tool/certification:deleteCertification');

        return Repository.deleteCertification(certificationid)
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
 * Duplicate certification handler
 *
 * @param {Element} element
 */
const duplicateCertification = element => {
    const certificationid = element.dataset.certificationid;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'core'),
        getString('confirmduplicate', 'tool_certification'),
        getString('ok', 'core'),
        {triggerElement}
    ).then(() => {
        const modalTitle = getString('newcertification', 'tool_certification');
        const modal = showDetailsModal(triggerElement, 0, certificationid, modalTitle);

        modal.addEventListener(modal.events.FORM_SUBMITTED, event => {
            window.location.href = event.detail;
        });

        return;
    }).catch(() => {
        return;
    });
};

/**
 * Add certification handler
 *
 * @param {Element} element
 */
const addCertification = element => {
    const modalTitle = getString('newcertification', 'tool_certification');
    const modal = showDetailsModal(element, 0, 0, modalTitle);

    modal.addEventListener(modal.events.FORM_SUBMITTED, event => {
        window.location.href = event.detail;
    });
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

    prefetchStrings('tool_certification', [
        'archive',
        'archivedconfirmation',
        'confirmdeletecertification',
        'confirmduplicate',
        'newcertification',
    ]);

    prefetchStrings('core', [
        'confirm',
        'delete',
        'ok',
    ]);

    document.addEventListener('click', event => {

        // Archive certification.
        const archiveCertificationElement = event.target.closest(SELECTORS.ARCHIVECERTIFICATION);
        if (archiveCertificationElement) {
            event.preventDefault();
            archiveCertification(archiveCertificationElement);
        }

        // Restore certification.
        const restoreCertificationElement = event.target.closest(SELECTORS.RESTORECERTIFICATION);
        if (restoreCertificationElement) {
            event.preventDefault();
            restoreCertification(restoreCertificationElement);
        }

        // Duplicate certification.
        const duplicateCertificationElement = event.target.closest(SELECTORS.DUPLICATECERTIFICATION);
        if (duplicateCertificationElement) {
            event.preventDefault();
            duplicateCertification(duplicateCertificationElement);
        }

        // Delete certification.
        const deleteCertificationElement = event.target.closest(SELECTORS.DELETECERTIFICATION);
        if (deleteCertificationElement) {
            event.preventDefault();
            deleteCertification(deleteCertificationElement);
        }

        // Add new certification.
        const addCertificationElement = event.target.closest(SELECTORS.ADDCERTIFICATIONBUTTON);
        if (addCertificationElement) {
            event.preventDefault();
            addCertification(addCertificationElement);
        }
    });

    initialized = true;
};

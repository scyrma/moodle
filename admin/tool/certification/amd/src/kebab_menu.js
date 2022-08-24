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
 * Module to handle the kebab (action) menu on a certification page
 *
 * @module     tool_certification/kebab_menu
 * @author     2022 Odei Alba <odei.alba@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import Pending from "core/pending";
import {prefetchStrings} from 'core/prefetch';
import {showDetailsModal} from 'tool_certification/local/modals';
import {archiveCertification} from 'tool_certification/local/repository';

/** @type {Object} The list of selectors for the certification area. */
var SELECTORS = {
    ARCHIVECERTIFICATION: "[data-action='archive']",
    DUPLICATECERTIFICATION: "[data-action='duplicate']",
};

export const init = () => {
    prefetchStrings('tool_certification', [
        'confirmduplicate',
        'newcertification',
        'archivedconfirmation',
        'archive',
    ]);

    prefetchStrings('moodle', [
        'confirm',
        'ok',
    ]);

    document.addEventListener('click', (event) => {
        const archiveCertificationElement = event.target.closest(SELECTORS.ARCHIVECERTIFICATION);
        if (archiveCertificationElement) {
            event.preventDefault();
            archiveCertificationHandler(archiveCertificationElement);
        }

        const duplicateCertificationElement = event.target.closest(SELECTORS.DUPLICATECERTIFICATION);
        if (duplicateCertificationElement) {
            event.preventDefault();
            duplicateCertificationHandler(duplicateCertificationElement);
        }
    });
};

/**
 * Handles archive a Certification.
 *
 * @param {Element} element
 */
const archiveCertificationHandler = (element) => {
    const {certificationid, name} = element.dataset;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'moodle'),
        getString('archivedconfirmation', 'tool_certification', name),
        getString('archive', 'tool_certification'),
        {triggerElement}
    ).then(() => {
        const pendingPromise = new Pending('tool/certification:archiveCertification');

        return archiveCertification(certificationid)
            .then((data) => {
                if (data.result) {
                    window.location.href = element.href;
                }
                return pendingPromise.resolve();
            }).catch(Notification.exception);
    }).catch(() => {
        return;
    });
};

/**
 * Handles duplicate a Certification.
 *
 * @param {Object} element
 */
const duplicateCertificationHandler = function(element) {
    const certificationid = element.dataset.certificationid;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'moodle'),
        getString('confirmduplicate', 'tool_certification'),
        getString('ok', 'moodle'),
        {triggerElement}
    ).then(() => {
        const str = getString('newcertification', 'tool_certification');
        const modal = showDetailsModal(element, 0, certificationid, str);

        modal.addEventListener(modal.events.FORM_SUBMITTED, (e) => {
            window.location.href = e.detail;
        });

        return;
    }).catch(() => {
        return;
    });
};

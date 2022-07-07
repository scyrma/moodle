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
 * Module to handle the kebab (action) menu on a tenant page
 *
 * @module     tool_tenant/kebab_menu
 * @author     2022 Odei Alba <odei.alba@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import {prefetchStrings} from 'core/prefetch';
import {archiveTenant} from 'tool_tenant/local/repository';
import Pending from "core/pending";

/** @type {Object} The list of selectors for the tenant area. */
const Selectors = {
    ArchiveTenant: '[data-action="archive"]',
};

export const init = () => {
    prefetchStrings('tool_tenant', [
        'confirmarchivetenant',
        'archive',
    ]);

    prefetchStrings('moodle', [
        'confirm',
    ]);

    document.addEventListener('click', (event) => {
        const archiveTenantElement = event.target.closest(Selectors.ArchiveTenant);
        if (archiveTenantElement) {
            event.preventDefault();
            archiveTenantHandler(archiveTenantElement);
        }
    });
};

/**
 * Handles archive a Tenant.
 *
 * @param {Element} element
 */
const archiveTenantHandler = (element) => {
    const {id, name} = element.dataset;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'moodle'),
        getString('confirmarchivetenant', 'tool_tenant', name),
        getString('archive', 'tool_tenant'),
        {triggerElement})
    .then(() => {
        const pendingPromise = new Pending('tool/tenant:archiveTenant');

        return archiveTenant(id)
            .then(() => {
                window.location.href = element.href;
                return pendingPromise.resolve();
            }).catch(Notification.exception);
    }).catch(() => {
        return;
    });
};

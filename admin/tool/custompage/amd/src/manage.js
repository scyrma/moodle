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
 * Custom page management
 *
 * @module      tool_custompage/manage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import {dispatchEvent} from 'core/event_dispatcher';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {prefetchStrings} from 'core/prefetch';
import {get_string as getString} from 'core/str';
import {add as addToast} from 'core/toast';
import ModalForm from 'core_form/modalform';
import * as reportEvents from 'core_reportbuilder/local/events';
import * as reportSelectors from 'core_reportbuilder/local/selectors';
import {deletePage, duplicatePage} from 'tool_custompage/local/repository';
import * as pageSelectors from 'tool_custompage/local/selectors';

let moduleInitialized = false;

/**
 * Initialise module, ensuring we load our resources and event listeners only once
 */
export const init = () => {
    if (moduleInitialized) {
        return;
    }

    prefetchStrings('tool_custompage', [
        'deletepage',
        'deletepageconfirm',
        'deletepagesuccess',
        'duplicatepage',
        'duplicatepageconfirm',
        'duplicatepageglobal',
        'duplicatepagetenant',
        'newpage',
        'newpageglobal',
    ]);

    prefetchStrings('core', [
        'delete',
        'duplicate',
        'save',
    ]);

    document.addEventListener('click', event => {

        // Page create.
        const pageCreate = event.target.closest(pageSelectors.actions.pageCreate);
        if (pageCreate) {
            event.preventDefault();

            const pageGlobal = parseInt(pageCreate.dataset.pageGlobal) === 1;
            const pageCreateModalTitle = pageGlobal ? 'newpageglobal' : 'newpage';

            const pageCreateModal = new ModalForm({
                modalConfig: {title: getString(pageCreateModalTitle, 'tool_custompage')},
                formClass: 'tool_custompage\\form\\page',
                args: {modal: true, global: pageGlobal},
                saveButtonText: getString('save', 'core'),
                returnFocus: pageCreate,
            });

            pageCreateModal.addEventListener(pageCreateModal.events.FORM_SUBMITTED, event => {
                window.location.href = event.detail;
            });

            pageCreateModal.show();
        }

        // Page delete.
        const pageDelete = event.target.closest(pageSelectors.actions.pageDelete);
        if (pageDelete) {
            event.preventDefault();

            // Return focus to the action menu toggle.
            const actionToggle = pageDelete.closest('.dropdown').querySelector('.dropdown-toggle');
            const {pageId, pageName, pageUrl} = pageDelete.dataset;

            Notification.saveCancelPromise(
                getString('deletepage', 'tool_custompage'),
                getString('deletepageconfirm', 'tool_custompage', pageName),
                getString('delete', 'core'),
                {triggerElement: actionToggle}
            ).then(() => {
                const pendingPromise = new Pending('tool_custompage/page:delete');

                return deletePage(pageId)
                    .then(() => addToast(getString('deletepagesuccess', 'tool_custompage')))
                    .then(() => {
                        // Redirect if URL specified (with delay for toast notification), otherwise reload report.
                        if (typeof pageUrl !== 'undefined') {
                            setInterval(() => {
                                window.location.href = pageUrl;
                            }, 1000);
                        } else {
                            const reportElement = pageDelete.closest(reportSelectors.regions.report);
                            dispatchEvent(reportEvents.tableReload, {preservePagination: true}, reportElement);
                        }

                        return pendingPromise.resolve();
                    })
                    .catch(Notification.exception);
            }).catch(() => {
                return;
            });
        }

        // Page duplicate.
        const pageDuplicate = event.target.closest(pageSelectors.actions.pageDuplicate);
        if (pageDuplicate) {
            event.preventDefault();

            // Return focus to the action menu toggle.
            const actionToggle = pageDuplicate.closest('.dropdown').querySelector('.dropdown-toggle');
            const {pageId, pageName, pageGlobal} = pageDuplicate.dataset;

            // Set notification title according to what we are duplicating.
            let pageDuplicateModalTitle;

            const pageGlobalDefined = (typeof pageGlobal !== 'undefined');
            if (!pageGlobalDefined) {
                pageDuplicateModalTitle = 'duplicatepage';
            } else if (parseInt(pageGlobal) === 1) {
                pageDuplicateModalTitle = 'duplicatepageglobal';
            } else {
                pageDuplicateModalTitle = 'duplicatepagetenant';
            }

            Notification.saveCancelPromise(
                getString(pageDuplicateModalTitle, 'tool_custompage'),
                getString('duplicatepageconfirm', 'tool_custompage', pageName),
                getString('duplicate', 'core'),
                {triggerElement: actionToggle}
            ).then(() => {
                const pendingPromise = new Pending('tool_custompage/page:duplicate');

                return duplicatePage(pageId, pageGlobalDefined, parseInt(pageGlobal) === 1)
                    // Redirect to the new page URL.
                    .then(pageUrl => {
                        window.location.href = pageUrl;
                        return pendingPromise.resolve();
                    })
                    .catch(Notification.exception);
            }).catch(() => {
                return;
            });
        }
    });

    moduleInitialized = true;
};

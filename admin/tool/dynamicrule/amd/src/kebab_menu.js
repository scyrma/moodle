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
 * Module to handle the kebab (action) menu on a rule page
 *
 * @module     tool_dynamicrule/kebab_menu
 * @author     2022 Odei Alba <odei.alba@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import {prefetchStrings} from 'core/prefetch';
import {relativeUrl} from 'core/url';
import {
    archiveRule,
    duplicateRule
} from 'tool_dynamicrule/local/repository';
import Pending from "core/pending";

/** @type {Object} The list of selectors for the rule area. */
const Selectors = {
    DuplicateRule: "[data-action='duplicate']",
    ArchiveRule: "[data-action='archive']",
};

export const init = () => {
    prefetchStrings('tool_dynamicrule', [
        'confirmarchiverule',
        'archive',
        'confirmduplicaterule',
        'duplicate',
    ]);

    prefetchStrings('moodle', [
        'confirm',
    ]);

    document.addEventListener('click', (event) => {
        const archiveRuleElement = event.target.closest(Selectors.ArchiveRule);
        if (archiveRuleElement) {
            event.preventDefault();
            archiveRuleHandler(archiveRuleElement);
        }

        const duplicateRuleElement = event.target.closest(Selectors.DuplicateRule);
        if (duplicateRuleElement) {
            event.preventDefault();
            duplicateRuleHandler(duplicateRuleElement);
        }
    });
};

/**
 * Handles archive a Rule.
 *
 * @param {Element} element
 */
const archiveRuleHandler = (element) => {
    const {ruleid, name} = element.dataset;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'moodle'),
        getString('confirmarchiverule', 'tool_dynamicrule', name),
        getString('archive', 'tool_dynamicrule'),
        {triggerElement})
    .then(() => {
        const pendingPromise = new Pending('tool/dynamicrule:archiveRule');

        return archiveRule(ruleid)
            .then(() => {
                window.location.href = element.href;
                return pendingPromise.resolve();
            }).catch(Notification.exception);
    }).catch(() => {
        return;
    });
};

/**
 * Handles duplicate a Rule.
 *
 * @param {Element} element
 */
const duplicateRuleHandler = (element) => {
    const {ruleid, name} = element.dataset;

    // Return focus to the action menu toggle.
    const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
    Notification.saveCancelPromise(
        getString('confirm', 'moodle'),
        getString('confirmduplicaterule', 'tool_dynamicrule', name),
        getString('duplicate', 'tool_dynamicrule'),
        {triggerElement}
    ).then(() => {
        const pendingPromise = new Pending('tool/dynamicrule:duplicateRule');

        return duplicateRule(ruleid)
            .then((data) => {
                if (data) {
                    window.location.href = relativeUrl("/admin/tool/dynamicrule/rule.php", {id: data});
                }
                return pendingPromise.resolve();
            }).catch(Notification.exception);
    }).catch(() => {
        return;
    });
};

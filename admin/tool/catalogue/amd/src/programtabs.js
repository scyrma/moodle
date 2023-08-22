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
 * Actions for the programtabs page
 *
 * @module     tool_catalogue/programtabs
 * @author     2022 Bas Brands <bas@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import SELECTORS from './selectors';
import LocalStorage from 'core/localstorage';

/**
 * Store the dismissal of the alert in local storage.
 *
 * @param {Event} e the triggered event.
 */
const dismissAlertHandler = (e) => {
    const alert = e.target.closest(SELECTORS.regions.dismissableAlert);
    const cacheKey = `tool_catalogue/${alert.dataset.hash}`;
    // Using 'core/localstorage' only works when $CFG->cachejs is enabled.
    LocalStorage.set(cacheKey, "yes");
    alert.classList.remove('d-flex');
    alert.classList.add('d-none');
    e.preventDefault();
};

/**
 * Check if the alert is dismissed.
 * @param {Node} alert Alert to check.
 * @returns {boolean} True if the alert is dismissed.
 */
const isDismissed = (alert) => {
    const cacheKey = `tool_catalogue/${alert.dataset.hash}`;
    // Using 'core/localstorage' only works when $CFG->cachejs is enabled.
    const dismissed = LocalStorage.get(cacheKey);
    return dismissed === "yes";
};

/**
 * Initialise the actions for the programtabs page.
 *
 */
export const init = () => {
    document.addEventListener('click', (event) => {
        const dismissAlert = event.target.closest(SELECTORS.actions.dismissAlert);
        if (dismissAlert) {
            dismissAlertHandler(event);
        }
    });
    const alerts = document.querySelectorAll(SELECTORS.regions.dismissableAlert);
    alerts.forEach(alert => {
        alert.classList.toggle('d-none', isDismissed(alert));
    });
};

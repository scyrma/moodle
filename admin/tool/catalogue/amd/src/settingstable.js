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
 * Handler for admin settings table actions
 *
 * @module     tool_catalogue/settingstable
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import {refreshTableContent} from 'core_table/dynamic';
import * as Selectors from 'core_table/local/dynamic/selectors';
import {call as fetchMany} from 'core/ajax';
import Pending from 'core/pending';
import {fetchNotifications} from 'core/notification';

let watching = false;

export default class {
    /**
     * @property {function[]} clickHandlers a list of handlers to call on click.
     */
    clickHandlers = [];

    constructor() {
        this.registerEventListeners();
    }

    /**
     * Initialise an instance of the class.
     *
     * This is just a way of making it easier to initialise an instance of the class from PHP.
     */
    static init() {
        if (watching) {
            return;
        }
        watching = true;
        new this();
    }

    /**
     * Register the event listeners for this instance.
     */
    registerEventListeners() {
        document.addEventListener('click', async function(e) {
            const tableRoot = this.getTableRoot(e);
            const element = e.target.closest('a[data-action][data-setting][data-field]');

            if (tableRoot && element) {
                e.preventDefault();
                this.changeAdminSetting(tableRoot, element.dataset.id, {
                    action: element.dataset.action,
                    setting: element.dataset.setting,
                    field: element.dataset.field,
                    newstate: element.dataset.newstate
                });
            }
        }.bind(this));

        document.addEventListener('change', function(e) {
            const tableRoot = this.getTableRoot(e);
            const element = e.target.closest('select[data-action][data-setting]');

            if (tableRoot && element) {
                e.preventDefault();
                this.changeAdminSetting(tableRoot, element.dataset.id, {
                    action: element.dataset.action,
                    setting: element.dataset.setting,
                    field: element.dataset.field ?? '',
                    newstate: element.value
                });
            }
        }.bind(this));
    }

    /**
     * Get the table root from an event.
     *
     * @param {Event} e
     * @returns {HTMLElement|bool}
     */
    getTableRoot(e) {
        const tableRoot = e.target.closest(Selectors.main.region);
        if (!tableRoot) {
            return false;
        }

        return tableRoot;
    }

    /**
     * Calls a WS to change field state
     *
     * @param {HTMLElement} tableRoot The root element of the table.
     * @param {string} id The id of the element to return to.
     * @param {Object} args An object containing the arguments to pass to the WS.
     * @returns {Promise}
     */
    async changeAdminSetting(tableRoot, id, args) {
        const pendingPromise = new Pending('tool_catalogue:elementchanged');

        await fetchMany([{
            methodname: 'tool_catalogue_change_admin_setting',
            args,
        }])[0];

        const [updatedRoot] = await Promise.all([
            refreshTableContent(tableRoot),
            fetchNotifications(),
        ]);

        // Refocus on the link that was pressed in the first place.
        const el = updatedRoot.querySelector(`[data-id="${id}"]`);
        if (el) {
            el.focus();
        }

        pendingPromise.resolve();
    }
}

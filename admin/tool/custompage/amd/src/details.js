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
 * Custom page details tab
 *
 * @module      tool_custompage/details
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import Notification from 'core/notification';
import {prefetchStrings} from 'core/prefetch';
import {get_string as getString} from 'core/str';
import {add as addToast} from 'core/toast';
import DynamicForm from 'core_form/dynamicform';

let moduleInitialized = false;

/**
 * Initialise module, ensuring we load our resources and event listeners only once
 */
export const init = () => {
    if (moduleInitialized) {
        return;
    }

    prefetchStrings('core', [
        'changessaved',
    ]);

    const detailsTabContainer = document.querySelector('#details');
    const detailsForm = new DynamicForm(detailsTabContainer, '\\tool_custompage\\form\\page');

    detailsForm.addEventListener(detailsForm.events.FORM_SUBMITTED, event => {
        event.preventDefault();

        getString('changessaved', 'core')
            .then(message => addToast(message))
            .catch(Notification.exception);
    });

    moduleInitialized = true;
};

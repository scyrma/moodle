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
 * This module instantiates the functionality to show details in matched users report.
 *
 * @module     tool_dynamicrule/show_matching_details
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import ModalFactory from 'core/modal_factory';
import {get_string as getString} from 'core/str';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';

let initialized = false;

/** @type {Object} The list of selectors for the matching report area. */
const Selectors = {
    showDetails: "[data-action='showdetails']",
};

/**
 * Initialize module
 */
const init = () => {
    if (initialized) {
        // We already added the event listeners (can be called multiple times by mustache template).
        return;
    }

    document.addEventListener('click', event => {
        const showDetails = event.target.closest(Selectors.showDetails);
        if (showDetails) {
            event.preventDefault();
            const matchingid = showDetails.dataset.id;
            showDetailsModal(matchingid);
        }
    });

    initialized = true;
};

/**
 * Popup user rule matching details
 *
 * @param {Number} matchingid
 * @private
 */
const showDetailsModal = (matchingid) => {
    const pendingPromise = new Pending('tool/dynamicrule:showDetailsModal');
    Ajax.call([{
        methodname: 'tool_dynamicrule_user_matching_rule_details',
        args: {
            matchingid: matchingid,
        }
    }])[0].then((content) => {
        return ModalFactory.create({
            title: getString('details'),
            body: content,
            type: ModalFactory.types.CANCEL,
            removeOnClose: true,
        });
    }).then((modal) => {
        modal.getModal().addClass('modal-xl');
        modal.show();
        return pendingPromise.resolve();
    }).catch(Notification.exception);
};

export default {
    init: init
};

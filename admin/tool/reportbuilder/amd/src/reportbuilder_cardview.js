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
 * Report builder card view management
 *
 * @module      tool_reportbuilder/reportbuilder_cardview
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import {get_string as getString} from "core/str";

const Selectors = {
    card: '.table-responsive tr',
    cardToggleButton: '.table-responsive tr td.card-toggle button',
    cardToggleButtonIcon: 'td.card-toggle button > i',
};

let initialized = false;

/**
 * Initialise module
 */
export const init = () => {
    if (initialized) {
        // We already added the event listeners (can be called multiple times by mustache template).
        return;
    }

    document.addEventListener('click', (event) => {
        const toggleCard = event.target.closest(Selectors.cardToggleButton);
        if (toggleCard) {
            event.preventDefault();
            if (toggleCard.dataset.toggle === 'collapsed') {
                toggleCard.closest('tr').classList.add('show');
                toggleCard.dataset.toggle = 'expanded';
                toggleCard.querySelector(Selectors.cardToggleButtonIcon).classList.replace('fa-angle-down', 'fa-angle-up');
                getString('collapse', 'core')
                    .then(string => {
                        toggleCard.title = string;
                        return null;
                    })
                    .catch(null);
            } else {
                toggleCard.closest('tr').classList.remove('show');
                toggleCard.dataset.toggle = 'collapsed';
                toggleCard.querySelector(Selectors.cardToggleButtonIcon).classList.replace('fa-angle-up', 'fa-angle-down');
                getString('expand', 'core')
                    .then(string => {
                        toggleCard.title = string;
                        return null;
                    })
                    .catch(null);
            }
        }
    });

    initialized = true;
};

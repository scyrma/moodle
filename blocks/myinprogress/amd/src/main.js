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
 * Javascript to initialise the block_myinprogress hide cards functionality.
 *
 * @module     block_myinprogress/main
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import {dispatchEvent} from 'core/event_dispatcher';
import Notification from 'core/notification';
import Selectors from "block_myinprogress/local/selectors";
import {setUserPreferences} from 'block_myinprogress/local/repository/userpreferences';
import CarouselSelectors from "tool_catalogue/coursecarousel/selectors";
import CarouselEvents from "tool_catalogue/coursecarousel/events";

const FILTER_VISIBLE = 'nothidden';

/**
 * Update 'Show hidden from my view' toggle (visibility and counter).
 *
 * @param {HTMLElement} blockRegion
 */
const updateShowHiddenCheckbox = (blockRegion) => {
    const hiddenCards = blockRegion.querySelectorAll(Selectors.hiddenCards);
    // Set toggler visibility.
    const showHiddenCheckbox = blockRegion.querySelector(Selectors.regions.showHiddenCheckbox);
    showHiddenCheckbox.classList.toggle('d-none', hiddenCards.length <= 0);
    // Update toggler count.
    const showHiddenCount = showHiddenCheckbox.querySelector(Selectors.regions.showHiddenCount);
    showHiddenCount.innerText = hiddenCards.length;
};

/**
 * Save all hidden cards ids in user preferences.
 *
 * @param {HTMLElement} blockRegion
 * @returns {Promise}
 */
const saveHiddenCardsPreference = (blockRegion) => {
    const carousel = blockRegion.querySelector(CarouselSelectors.regions.main);
    const hiddenCards = Array.from(carousel.querySelectorAll(Selectors.hiddenCards));

    const preferences = [{
        'userid': carousel.dataset.userid,
        'name': 'block_myinprogress_hidden_courses',
        'value': JSON.stringify(hiddenCards.map((card) => card.dataset.courseid)),
    }];
    return setUserPreferences(preferences);
};

/**
 * Save 'Show hidden from my view' checkbox value in user preferences.
 *
 * @param {Integer} userId
 * @param {Boolean} value
 * @returns {Promise}
 */
const saveShowHiddenCardsPreference = (userId, value) => {
    const preferences = [{
        'userid': userId,
        'name': 'block_myinprogress_show_hidden_cards',
        'value': value,
    }];
    return setUserPreferences(preferences);
};

let initialized = false;

/**
 * Initialize the block filtering.
 */
export const init = () => {

    if (initialized) {
        // We already added the event listeners (can be called multiple times by mustache template).
        return;
    }

    const blockRegion = document.querySelector(Selectors.regions.block);

    // Listen to all click events in the DOM.
    document.addEventListener('click', (event) => {

        // The hide card button was clicked.
        const hideButton = event.target.closest(Selectors.actions.hideCard);
        if (hideButton) {
            const card = hideButton.closest(CarouselSelectors.card);
            const carousel = hideButton.closest(CarouselSelectors.regions.main);
            const detail = {
                courseid: card.dataset.courseid,
                filter: FILTER_VISIBLE,
                enabled: false
            };
            dispatchEvent(CarouselEvents.carouselSetCardFilter, detail, carousel);
            saveHiddenCardsPreference(blockRegion).then((data) => {
                if (data.warnings.length === 0) {
                    card.querySelector(Selectors.regions.cardHiddenBadge).classList.remove('d-none');
                    updateShowHiddenCheckbox(blockRegion);
                }
                return;
            }).catch(Notification.exception);
        }

        // The show card button was clicked.
        const showButton = event.target.closest(Selectors.actions.showCard);
        if (showButton) {
            const card = showButton.closest(CarouselSelectors.card);
            const carousel = showButton.closest(CarouselSelectors.regions.main);
            const detail = {
                courseid: card.dataset.courseid,
                filter: FILTER_VISIBLE,
                enabled: true
            };
            dispatchEvent(CarouselEvents.carouselSetCardFilter, detail, carousel);
            saveHiddenCardsPreference(blockRegion).then((data) => {
                if (data.warnings.length === 0) {
                    card.querySelector(Selectors.regions.cardHiddenBadge).classList.add('d-none');
                    updateShowHiddenCheckbox(blockRegion);
                }
                return;
            }).catch(Notification.exception);
        }

        // The show hidden cards toggle checkbox was clicked.
        const showHiddenCards = event.target.closest(Selectors.actions.showHiddenCards);
        if (showHiddenCards) {
            const carousel = showHiddenCards.closest(Selectors.regions.block).querySelector(CarouselSelectors.regions.main);
            const filter = showHiddenCards.checked ? '' : FILTER_VISIBLE;
            dispatchEvent(CarouselEvents.carouselFilterBy, {filter}, carousel);
            saveShowHiddenCardsPreference(carousel.dataset.userid, showHiddenCards.checked).catch(Notification.exception);
        }
    });

    initialized = true;
};

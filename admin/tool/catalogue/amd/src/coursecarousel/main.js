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
 * Javascript to initialise the course carousel.
 * Based on {@see block_recentlyaccessedcourses/main}
 *
 * @module     tool_catalogue/coursecarousel/main
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import {debounce} from 'core/utils';
import Pending from 'core/pending';
import Selectors from 'tool_catalogue/coursecarousel/selectors';

// The updateVisibleCourses function will execute at a rate of 15fps.
// Then, the calculation for the needed debounce is: 1000(ms)/15(fps) results in 66.666.
const DEBOUNCE_TIMER = 66;
// The default card width.
const DEFAULT_CARD_WIDTH = 230;

/**
 * Animate element adding a CSS class.
 *
 * @param {HTMLElement} element
 */
const animateElement = (element) => {
    element.classList.add('animate');
    // When the animation ends, we clean the classes and resolve the Promise
    const handleAnimationEnd = (event) => {
        event.stopPropagation();
        element.classList.remove('animate');
    };
    element.addEventListener('animationend', handleAnimationEnd, {once: true});
};

/**
 * Enable/Disable control button.
 *
 * @param {HTMLElement} button The control button to enable.
 * @param {Boolean} available
 */
const setControlButtonAvailability = (button, available) => {
    button.disabled = !available;
    button.closest(Selectors.regions.pageitem).classList.toggle('disabled', !available);
};

/**
 * Show/Hide the paging bar.
 *
 * @param {HTMLElement} root The root element for the courses view.
 * @param {Array} allCourses All the course cards.
 * @param {Number} viewIndex Starting index of the course cards
 * @param {Number} visibleCardsCount Number of available visible cards.
 */
const updatePagingBarVisibility = (root, allCourses, viewIndex, visibleCardsCount) => {
    const pagingBar = root.querySelector(Selectors.regions.controls);
    const isVisible = visibleCardsCount < allCourses.length;

    pagingBar.classList.toggle('d-none', !isVisible);
    if (isVisible) {
        // Handle previous/next buttons.
        const previousButton = root.querySelector(Selectors.actions.previous);
        setControlButtonAvailability(previousButton, viewIndex !== 0);
        const nextButton = root.querySelector(Selectors.actions.next);
        setControlButtonAvailability(nextButton, viewIndex + visibleCardsCount < allCourses.length);
    }
};

/**
 * Generate a unique id for a list of course cards.
 *
 * @param {Array} courses Array of course cards
 * @return {String}
 */
const generateUniqueId = (courses) => {
    return courses.reduce(function(carry, course) {
        return carry + course.getAttribute('data-courseid');
    }, '');
};

/**
 * Update the course cards that should be shown.
 *
 * @param {HTMLElement} root The root element for the courses view.
 * @param {Number} viewIndex Starting index of the course cards
 * @param {Number} visibleCardsCount Number of available visible cards.
 * @param {Boolean} animate To animate the cards with a fade effect.
 */
const updateVisibleCourses = (root, viewIndex, visibleCardsCount, animate = true) => {
    const allCourses = Array.from(root.querySelectorAll(Selectors.card));
    const visibleCourses = Array.from(root.querySelectorAll(Selectors.visibleCard));

    // Calculate courses to show.
    let start;
    const numberOfCourses = allCourses.length;
    if (viewIndex + visibleCardsCount < numberOfCourses) {
        start = viewIndex;
    } else {
        const overflow = (viewIndex + visibleCardsCount) - numberOfCourses;
        start = viewIndex - overflow;
        start = Math.max(0, start);
    }
    const coursesToShow = allCourses.slice(start, start + visibleCardsCount);

    // Don't bother updating the DOM unless the visible courses have changed.
    if (generateUniqueId(coursesToShow) !== generateUniqueId(visibleCourses)) {
        // Display the courses to show.
        allCourses.forEach((course) => {
            if (coursesToShow.includes(course)) {
                if (animate) {
                    animateElement(course);
                }
                course.classList.remove('d-none');
            } else {
                course.classList.add('d-none');
            }
        });
        updatePagingBarVisibility(root, allCourses, viewIndex, visibleCardsCount);
    }
};

/**
 * Calculate number of visible cards based on their width.
 *
 * @param {HTMLElement} root The root element for the courses view.
 * @returns {Number}
 */
const getVisibleCardsCount = (root) => {
    const availableWidth = root.offsetWidth;
    const card = root.querySelector(Selectors.visibleCard);
    let cardWidth = card ? card.offsetWidth : DEFAULT_CARD_WIDTH;

    return Math.floor(availableWidth / cardWidth);
};

/**
 * Get and show the recent courses into the block.
 *
 * @param {HTMLElement} root The root element for the recentlyaccessedcourses block.
 */
export const init = (root) => {
    // Starting index of the course cards.
    let viewIndex = 0;
    // Number of available visible cards.
    let visibleCardsCount = getVisibleCardsCount(root);

    // Update starting course cards visibility.
    updateVisibleCourses(root, viewIndex, visibleCardsCount, false);

    // Listen to window resize.
    window.addEventListener('resize', () => {
        const pendingPromise = new Pending('tool_catalogue/coursecarousel:resize');
        visibleCardsCount = getVisibleCardsCount(root);
        debounce(updateVisibleCourses, DEBOUNCE_TIMER)(root, viewIndex, visibleCardsCount, false);
        setTimeout(() => {
            pendingPromise.resolve();
        }, DEBOUNCE_TIMER);
    }, true);

    // Listen to all click events in the DOM.
    root.addEventListener('click', (event) => {

        // The next button was clicked.
        const nextButton = event.target.closest(Selectors.actions.next);
        if (nextButton) {
            if (!nextButton.classList.contains('disabled')) {
                visibleCardsCount = getVisibleCardsCount(root);
                viewIndex = viewIndex + visibleCardsCount;
                updateVisibleCourses(root, viewIndex, visibleCardsCount);
            }
        }

        // The previous button was clicked.
        const previousButton = event.target.closest(Selectors.actions.previous);
        if (previousButton) {
            if (!previousButton.classList.contains('disabled')) {
                visibleCardsCount = getVisibleCardsCount(root);
                viewIndex = viewIndex - visibleCardsCount;
                viewIndex = Math.max(0, viewIndex);
                updateVisibleCourses(root, viewIndex, visibleCardsCount);
            }
        }
    });
};

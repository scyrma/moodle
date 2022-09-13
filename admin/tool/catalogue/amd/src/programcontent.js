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
 * Set navigation or the program content
 *
 * @module     tool_catalogue/programcontent
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Bas Brands <bas@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import Templates from 'core/templates';
import SELECTORS from './selectors';

let programId = '';
let navItems = [];

/**
 * Update the history of the browser.
 *
 * @param {String} url The url to set as the history.
 */
const setHistory = (url) => {
    const state = {
        'navItems': navItems,
    };
    history.pushState(state, null, url);
};

/**
 * Returns the last element in navItems.
 *
 * @returns {Array}
 */
const getLastNavItem = () => {
    return navItems[navItems.length - 1];
};

/**
 * Returns the URL for a given setId.
 *
 * @param   {Integer} setId The setId of the element's to show.
 * @returns {String}
 */
const getSetUrl = (setId = 0) => {
    let setUrl = `${M.cfg.wwwroot}/my/courses.php/program/${programId}`;
    if (setId > 0) {
        setUrl += `/set/${setId}`;
    }
    return setUrl;
};

/**
 * Renders the breadcrumb with the current navItems information.
 */
const updateBreadcrumb = async() => {
    // Set 'islast' to the last item in the navItems array.
    navItems.forEach((item, ix) => {
        item.islast = (ix === navItems.length - 1);
    });
    // Render breadcrumb from template.
    const context = {
        rooturl: M.cfg.wwwroot + '/my/courses.php',
        items: navItems
    };
    const {html, js} = await Templates.renderForPromise('tool_catalogue/breadcrumb', context);
    const breadcrumbContainer = document.querySelector(SELECTORS.regions.breadcrumb);
    await Templates.replaceNodeContents(breadcrumbContainer.parentElement, html, js);
};

/**
 * Update the header.
 *
 * @param {String} text to update the header with.
 */
const updateHeader = (text) => {
    // Update the page header.
    document.querySelector(SELECTORS.regions.pageHeader).innerHTML = text;
};

/**
 * Show the elements in the page that are linked to this set.
 *
 * @param {Integer} setId The setId of the element's to show.
 */
const showSetElements = (setId) => {
    const levelNodes = document.querySelectorAll(SELECTORS.regions.level);
    levelNodes.forEach(level => {
        if (parseInt(level.dataset.setid) !== setId) {
            level.classList.add('d-none');
        } else {
            level.classList.remove('d-none');
        }
    });
};

/**
 * Update the page contents to show the current navItem set.
 */
const showSet = () => {
    const currentSet = getLastNavItem();
    updateBreadcrumb();
    updateHeader(currentSet.name);
    showSetElements(currentSet.setid);
};

/**
 * Initialise module
 *
 * @param {Array} items Extra set items for the initial navItems
 */
export const init = (items = []) => {
    const programContainer = document.querySelector(SELECTORS.regions.programContainer);
    programId = programContainer.dataset.programid;
    // Populate the navItems array with the program navigation item and extra items from parameter.
    navItems = [{'url': getSetUrl(), 'name': programContainer.dataset.fullname, 'setid': 0}];
    navItems.push(...items);

    // Set first history item on this page.
    setHistory(getLastNavItem().url);
    // Show the set.
    showSet();

    // Add the eventlistener to the browser back button.
    addEventListener('popstate', (e) => {
        if (e.state) {
            // Update navItems.
            navItems = e.state.navItems;
            // Show the set.
            showSet();
        } else {
            // If state is not defined return to mycourses page.
            location.href = M.cfg.wwwroot + '/my/courses.php';
        }
    });

    // Add the eventlisteners to the document.
    document.addEventListener('click', event => {

        // Show set from a card.
        const showSetAction = event.target.closest(SELECTORS.actions.showSet);
        if (showSetAction) {
            event.preventDefault();
            // Update navItems when clicking on a set link.
            if (showSetAction.dataset.linktype === 'set-link') {
                const newurl = getSetUrl(showSetAction.dataset.setid);
                navItems.push({'url': newurl, 'name': showSetAction.innerHTML, 'setid': parseInt(showSetAction.dataset.setid)});
                setHistory(newurl);
            }
            // Update navItems when clicking on a breadcrumb link.
            if (showSetAction.dataset.linktype === 'breadcrumb-link') {
                // Remove the navItems beyond the clicked breadcrumb link.
                const index = navItems.findIndex(item => item.setid === parseInt(showSetAction.dataset.setid));
                navItems = navItems.slice(0, index + 1);
                setHistory(getLastNavItem().url);
            }
            // Show the set.
            showSet();
        }

    });
};

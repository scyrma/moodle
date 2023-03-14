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
 * Javascript to initialise the My Teams block quick filtering.
 *
 * @module     block_myteams/quickfilters
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import Selectors from 'block_myteams/local/selectors';
import {setUserPreferences} from 'block_myteams/local/repository/userpreferences';
import {getSortOrder, setSortOrder} from 'block_myteams/local/repository/dynamictable';
import * as reportEvents from 'core_reportbuilder/local/events';
import {Events as DynamicTableEvents} from 'core_table/dynamic';
import * as reportSelectors from 'core_reportbuilder/local/selectors';
import {setFilters} from 'core_reportbuilder/local/repository/filters';
import {dispatchEvent} from 'core/event_dispatcher';
import Notification from 'core/notification';
import Pending from "core/pending";
import {debounce} from "core/utils";

// Set a debounce timer for search input keyup event.
const DEBOUNCE_TIMER = 250;

/**
 * Get Quickfilter values suitable for reportbuilder WS.
 * In every filtering call we need to send all filters together.
 *
 * @param {HTMLElement} quickfiltersElement
 * @param {String} filterByNameValue if null it is obtained from quickfiltersElement
 * @param {String} filterByTypeValue if null it is obtained from quickfiltersElement
 * @return {Object}
 */
const getFilterValues = (quickfiltersElement, filterByNameValue = null, filterByTypeValue = null) => {
    if (!filterByNameValue) {
        const searchInput = quickfiltersElement.querySelector(Selectors.actions.search);
        filterByNameValue = searchInput.value;
    }
    const filterByName = filterByNameValue ? {"user:fullname_operator": 1, "user:fullname_value": filterByNameValue} : {};

    if (!filterByTypeValue) {
        const filterSelected = quickfiltersElement.querySelector(`${Selectors.actions.filter}[aria-current="true"]`);
        filterByTypeValue = filterSelected?.dataset.value;
    }
    const filterByType = JSON.parse(filterByTypeValue) ?? {};

    return {...filterByName, ...filterByType};
};

/**
 * Filter report
 *
 * @param {HTMLElement} reportElement
 * @param {Object} filters
 * @return {Promise}
 */
const filterReport = (reportElement, filters) => {
    const pendingPromise = new Pending('block_myteams/quickfilters:filter');

    const reportId = Number(reportElement.dataset.reportId);
    return setFilters(reportId, '', JSON.stringify(filters))
        .then(() => {
            dispatchEvent(reportEvents.tableReload, {preservePagination: true}, reportElement);
            return pendingPromise.resolve();
        });
};

/**
 * Hide items in the report that are not overdue.
 * Overdue filtering is done directly with JS, it would be much more difficult/expensive using Reportbuilder.
 *
 * @param {HTMLElement} reportElement
 * @param {Boolean} filterValue
 */
const filterOverdueItems = (reportElement, filterValue = true) => {
    const allItems = reportElement.querySelectorAll(Selectors.regions.userinfo);
    const notOverdueItems = reportElement.querySelectorAll(Selectors.regions.userinfoNotOverdue);
    [...notOverdueItems].forEach((element) => {
        element.closest('tr').classList.toggle('d-none', filterValue);
    });
    // Manage "Nothing to display" when hiding all items with this filter.
    const nothingToDisplay = reportElement.closest(Selectors.regions.main).querySelector(Selectors.regions.nothingToDisplay);
    nothingToDisplay.classList.toggle(
        'd-none',
        !filterValue || allItems.length !== notOverdueItems.length || allItems.length === 0
    );
};

/**
 * Initialize the quick filtering.
 *
 * @param {String} uniqId for quickfilters element
 */
export const init = (uniqId) => {
    const quickfiltersElement = document.getElementById(uniqId);
    const rootElement = quickfiltersElement.closest(Selectors.regions.main);
    const reportElement = rootElement.querySelector(reportSelectors.regions.report);

    // Filter overdue items at first load.
    if (quickfiltersElement.querySelector(Selectors.actions.filterOverdue).getAttribute('aria-current') === "true") {
        filterOverdueItems(reportElement);
    }

    // Update clear search button visibility.
    const searchTerm = quickfiltersElement.querySelector(Selectors.actions.search).value;
    quickfiltersElement.querySelector(Selectors.actions.clearSearch).classList.toggle('d-none', searchTerm === "");

    // Set starting value in sorting dropdown.
    const currentSorting = getSortOrder(reportElement);
    [...quickfiltersElement.querySelectorAll(Selectors.actions.sort)].forEach((element) => {
        if (element.dataset.sortBy === currentSorting) {
            element.setAttribute('aria-current', 'true');
            quickfiltersElement.querySelector(`${Selectors.regions.sort} [data-active-item-text]`).innerHTML = element.textContent;
        } else {
            element.removeAttribute('aria-current');
        }
    });

    // Listen to all click events in the DOM.
    rootElement.addEventListener('click', (event) => {

        // Filter by type.
        const filterDropdown = event.target.closest(Selectors.actions.filter);
        if (filterDropdown) {
            if (!filterDropdown.hasAttribute("aria-current")) {
                filterDropdown.setAttribute("aria-current", true);
                const filters = getFilterValues(quickfiltersElement, null, filterDropdown.dataset.value);
                filterReport(reportElement, filters);
            }
        }

        // Clear Search.
        const clearSearch = event.target.closest(Selectors.actions.clearSearch);
        if (clearSearch) {
            const searchInput = clearSearch.closest(Selectors.regions.search).querySelector(Selectors.actions.search);
            searchInput.value = '';
            clearSearch.classList.add('d-none');
            searchInput.focus();
            const filters = getFilterValues(quickfiltersElement);
            filterReport(reportElement, filters);
        }

        // Filter Overdue.
        const filterOverdue = event.target.closest(Selectors.actions.filterOverdue);
        if (filterOverdue) {
            const pendingPromise = new Pending('block_myteams/quickfilters:filteroverdue');
            const isFilterActive = filterOverdue.getAttribute('aria-current') === "true";
            setUserPreferences([{
                'userid': quickfiltersElement.dataset.userId,
                'name': 'block_myteams_filter_overdue',
                'value': !isFilterActive
            }])
                .then(() => {
                    filterOverdue.setAttribute('aria-current', !isFilterActive);
                    filterOverdueItems(reportElement, !isFilterActive);
                    return pendingPromise.resolve();
                })
                .catch(Notification.exception);
        }

        // Sort.
        const sortButton = event.target.closest(Selectors.actions.sort);
        if (sortButton) {
            if (!sortButton.hasAttribute("aria-current")) {
                const pendingPromise = new Pending('block_myteams/quickfilters:sort');
                setSortOrder(reportElement, sortButton.dataset.sortBy, sortButton.dataset.sortOrder)
                    .then(() => pendingPromise.resolve())
                    .catch(Notification.exception);
            }
        }

    });

    // Search.
    const searchInput = document.querySelector(Selectors.actions.search);
    const updateSearch = (event) => {
        const clearButton = event.target.closest(Selectors.regions.search).querySelector(Selectors.actions.clearSearch);
        const searchTerm = event.target.value;
        clearButton.classList.toggle('d-none', searchTerm === "");
        const filters = getFilterValues(quickfiltersElement);
        filterReport(reportElement, filters);
    };
    // Debounce the search keyup event listener.
    const searchDebounce = debounce(updateSearch, DEBOUNCE_TIMER);
    // Listen to keyup event excluding in the search input.
    // Excluding "Enter" and "Tab" keys to avoid undesired report reloads.
    searchInput.addEventListener('keyup', (event) => {
        if (!['Enter', 'Tab'].includes(event.key)) {
            const pendingPromise = new Pending('block_myteams/quickfilters:searchkeyup');
            searchDebounce(event);
            setTimeout(() => {
                pendingPromise.resolve();
            }, DEBOUNCE_TIMER);
        }
    });

    // Everytime the table content is refreshed we need to apply the overdue filter.
    rootElement.addEventListener(DynamicTableEvents.tableContentRefreshed, () => {
        if (quickfiltersElement.querySelector(Selectors.actions.filterOverdue).getAttribute('aria-current') === "true") {
            filterOverdueItems(reportElement);
        }
    });
};

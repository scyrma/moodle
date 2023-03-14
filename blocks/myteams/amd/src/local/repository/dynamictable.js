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
 * Module to handle dynamic table
 *
 * @module     block_myteams/local/repository/dynamictable
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import * as DynamicTable from 'core_table/dynamic';
import * as TableSelectors from 'core_table/local/dynamic/selectors';

/**
 * Set dynamic table sort order.
 *
 * @param {HTMLElement} reportElement
 * @param {String} sortBy
 * @param {Number} sortOrder
 * @return {Promise} Promise when the preferences have been set
 */
export const setSortOrder = (reportElement, sortBy, sortOrder) => {
    const tableElement = reportElement.querySelector(TableSelectors.main.region);
    // Clear current data-table-sort-data first.
    tableElement.dataset.tableSortData = JSON.stringify([]);
    return DynamicTable.setSortOrder(tableElement, sortBy, sortOrder);
};

/**
 * Get dynamic table sort by ordering.
 *
 * @param {HTMLElement} reportElement
 * @return {String} Sort by ordering
 */
export const getSortOrder = (reportElement) => {
    const tableSorting = JSON.parse(reportElement.querySelector(TableSelectors.main.region).dataset.tableSortData);
    return tableSorting[0].sortby;
};

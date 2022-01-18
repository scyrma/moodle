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
 * Table class
 *
 * @module     tool_reportbuilder/local/report/table
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Paul Holden <paulh@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import {exception as showException} from 'core/notification';
import Pending from 'core/pending';
import Selectors from './selectors';
import Events from 'tool_reportbuilder/reportbuilder_events';

class Table {

    /**
     * Constructor
     *
     * @param {String} reportSelector The report area selector.
     */
    constructor(reportSelector) {
        this.table = $(Selectors.tableRegion);
        this.reportSelector = reportSelector;

        // Ensure class methods are bound correctly.
        this.initializeTableSorting = this.initializeTableSorting.bind(this);
    }

    /**
     * Return the report ID for the given element
     *
     * @param {Object} element
     * @return {Number}
     */
    static getReportId(element) {
        const container = element.closest('[data-reportid]');

        return Number(container.data('reportid'));
    }

    /**
     * Return selector for element within the table
     *
     * @param {String} selector
     * @return {String}
     */
    static getSelector(selector) {
        return Selectors.tableRegion + ' ' + selector;
    }

    /**
     * Initialize table sorting
     */
    initializeTableSorting() {
        $('body').on('click', this.reportSelector + ' ' + Table.getSelector(Selectors.sortHeading), (event) => {
            const pendingPromise = new Pending('tool_reportbuilder/table:sortHeading');
            const element = $(event.currentTarget);
            const reportTable = $(event.currentTarget).closest(Selectors.tableRegion);

            event.preventDefault();

            Ajax.call([{
                methodname: 'tool_reportbuilder_sort_table_by_heading',
                args: {
                    reportid: Table.getReportId(element),
                    sortcolumn: element.data('sortcolumn'),
                    sortorder: element.data('sortorder'),
                }
            }])[0].then(() => {
                reportTable.trigger(Events.RELOADTABLEWITHOUTPAGINATION);
                pendingPromise.resolve();
                return;
            }).catch(showException);
        });
    }
}

/**
 * Return new instance of Table class
 *
 * @param {String} reportSelector
 * @return {Table}
 */
export default (reportSelector) => {
    return new Table(reportSelector);
};
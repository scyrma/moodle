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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Table class
 *
 * @module     tool_reportbuilder/local/report/table
 * @package    tool_reportbuilder
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Paul Holden <paulh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import {exception as showException} from 'core/notification';
import Pending from 'core/pending';
import Selectors from './selectors';

class Table {

    /**
     * Constructor
     */
    constructor() {
        this.table = $(Selectors.tableRegion);

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
     *
     * @param {Function} success
     */
    initializeTableSorting(success) {
        $('body').on('click', Table.getSelector(Selectors.sortHeading), (event) => {
            const pendingPromise = new Pending('tool_reportbuilder/table:sortHeading');
            const element = $(event.currentTarget);

            event.preventDefault();

            Ajax.call([{
                methodname: 'tool_reportbuilder_sort_table_by_heading',
                args: {
                    reportid: Table.getReportId(element),
                    sortcolumn: element.data('sortcolumn'),
                    sortorder: element.data('sortorder'),
                }
            }])[0].then(() => {
                success();

                pendingPromise.resolve();
                return;
            }).catch(showException);
        });
    }
}

/**
 * Return new instance of Table class
 *
 * @return {Table}
 */
export default () => {
    return new Table();
};
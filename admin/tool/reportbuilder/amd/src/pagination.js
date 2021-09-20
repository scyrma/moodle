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
 * This module instantiates the functionality of the pagination.
 *
 * @module     tool_reportbuilder/pagination
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define([
        'jquery',
        'core/custom_interaction_events',
        'tool_reportbuilder/reportbuilder_events',
    ],
    function($, CustomEvents, Events) {

        /** @type {Object} The list of selectors for the message area. */
        var SELECTORS = {
            REPORT: '[data-region="data-report"]',
            PAGELINK: 'nav.pagination .page-link',
            SYSTEMREPORT: "[data-region='system-report']"
        };

        /**
         * Pagination class.
         */
        function Pagination() {
            this._init();
        }

        /**
         * Initialise the other objects we require.
         * @private
         */
        Pagination.prototype._init = function() {
            CustomEvents.define($('body'), [
                CustomEvents.events.activate
            ]);

            $(SELECTORS.SYSTEMREPORT).on(CustomEvents.events.activate, SELECTORS.REPORT + ' ' + SELECTORS.PAGELINK,
                this._changePage.bind(this));
        };

        /**
         * Handles the change of the report page.
         * @param {Event} e
         * @param {Object} data
         * @private
         */
        Pagination.prototype._changePage = function(e, data) {
            M.util.js_pending('pagination_new_page');
            data.originalEvent.stopPropagation();
            data.originalEvent.preventDefault();
            var url = $(e.currentTarget).prop('href');
            if (url.indexOf('page') !== -1) {
                var currentpage = this._getURLParameter(url, 'page');
                $(e.currentTarget).closest(SELECTORS.REPORT).trigger(Events.RELOADTABLE, {page: currentpage});
                M.util.js_complete('pagination_new_page');
            }
        };

        /**
         * Get URL parameter
         *
         * @param {String} url
         * @param {String} prop
         * @return {{}}
         * @private
         */
        Pagination.prototype._getURLParameter = function(url, prop) {
            var params = {};
            var search = decodeURIComponent(url.slice(url.indexOf('?') + 1));
            var definitions = search.split('&');

            definitions.forEach(function(val) {
                var parts = val.split('=', 2);
                params[parts[0]] = parts[1];
            });

            return (prop && prop in params) ? params[prop] : params;
        };

        // Return singleton.
        return new Pagination();
    }
);

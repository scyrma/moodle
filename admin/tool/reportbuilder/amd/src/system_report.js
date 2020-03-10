// This file is part of Moodle - http://moodle.org/
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

/**
 * Javascript manager for filters.
 *
 * @module     tool_reportbuilder/reportbuilder
 * @class      SystemReport
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define(
    [
        'jquery',
        'core/fragment',
        'core/ajax',
        'core/config',
        'core/log',
        'core/templates',
        'core/notification',
        'tool_reportbuilder/reportbuilder_helper',
        'tool_reportbuilder/filters_manager',
        'tool_reportbuilder/pagination',
        'tool_reportbuilder/reportbuilder_events'
    ],
    function(
        $,
        Fragment,
        Ajax,
        Config,
        Log,
        Templates,
        Notification,
        Helper,
        _f,
        _p,
        Events) {

        "use strict";

        var SELECTORS = {
            SYSTEMREPORT: "[data-region='system-report']",
            REPORT: "[data-region='data-report']",
            PAGINATION: 'nav.pagination',
            SORTTABLE: "[data-action='sort-table']",
            RESETTABLE: "[data-action='reset-table']"
        };
        /**
         * ReportBuilder class
         * @constructor
         */
        var SystemReport = function() {
            Log.info("System report component loaded");
            this._init();
        };

        var TEMPLATES = {
            LOADING: 'core/overlay_loading'
        };

        /** @type {int} */
        SystemReport.prototype.currentpage = 0;

        /**
         * Initialise the event listeners.
         *
         * @private
         */
        SystemReport.prototype._init = function() {
            // Register RELOADTABLE event handler.
            $('body').on(Events.RELOADTABLE, SELECTORS.REPORT, function(e, data) {
                var currentpage = $(e.currentTarget).closest(SELECTORS.REPORT).find(SELECTORS.PAGINATION).data('currentpage');
                if (typeof data !== "undefined" && typeof data.page !== "undefined") {
                    // Use specified page if provided.
                    this.currentpage = data.page;
                } else if (typeof currentpage !== "undefined") {
                    // No page specified, but we know which page we are on.
                    this.currentpage = currentpage;
                } else {
                    // No page specified, assume this is triggered on page 0.
                    this.currentpage = 0;
                }
                this._reloadTable($(e.currentTarget));
            }.bind(this));
            // Register RELOADTABLEWITHOUTPAGINATION event handler.
            $('body').on(Events.RELOADTABLEWITHOUTPAGINATION, SELECTORS.REPORT, function(e) {
                this.currentpage = 0;
                this._reloadTable($(e.currentTarget));
            }.bind(this));

            this._initTableSorting();
        };

        /**
         * Handle column sorting
         * @param {Event} e
         * @private
         */
        SystemReport.prototype._handleTableSorting = function(e) {
            e.preventDefault();
            let data = $(e.currentTarget).data();
            M.util.js_pending('tool_reportbuilder_sort_table_by_heading');
            Ajax.call([
                {methodname: 'tool_reportbuilder_sort_table_by_heading',
                    args: {
                        tableid: data.table,
                        sortcolumn:  data.sortcolumn,
                        sortorder: data.sortorder
                    }
                }
            ])[0].done(() => {
                this._reloadTable($(SELECTORS.REPORT));
                M.util.js_complete('tool_reportbuilder_sort_table_by_heading');
                return null;
            }).fail(e => {
                Notification.exception(e);
            });
        };

        /**
         * Handle table reset
         * @param {Event} e
         * @private
         */
        SystemReport.prototype._handleTableReset = function(e) {
            e.preventDefault();
            let data = $(e.currentTarget).data();
            M.util.js_pending('tool_reportbuilder_reset_table_heading_sort');
            Ajax.call([
                {methodname: 'tool_reportbuilder_reset_table_heading_sort',
                    args: {
                        tableid: data.table
                    }
                }
            ])[0].done(() => {
                this._reloadTable($(SELECTORS.REPORT));
                M.util.js_complete('tool_reportbuilder_reset_table_heading_sort');
                return null;
            }).fail(e => {
                Notification.exception(e);
            });
        };

        /**
         * Initialise table sorting
         * @private
         */
        SystemReport.prototype._initTableSorting = function() {
            $('body')
                .on('click', SELECTORS.SYSTEMREPORT + ' ' + SELECTORS.SORTTABLE, e => {
                    this._handleTableSorting(e);
                })
                .on('click', SELECTORS.SYSTEMREPORT + ' ' + SELECTORS.RESETTABLE, e => {
                    this._handleTableReset(e);
                });
        };

        /**
         * Reload the table.
         * @param {$} table
         * @return {Promise}
         * @private
         */
        SystemReport.prototype._reloadTable = function(table) {
            var reportid = table.closest('[data-reportid]').data('reportid');
            var params = {
                reportid: reportid,
                page: this.currentpage,
                parameters: table.attr('data-parameters'),
                editon: false
            };

            M.util.js_pending('tool_reportbuilder_reload_table'); // Tell Behat to wait.
            return Templates.render(TEMPLATES.LOADING, {visible: true})
                .then(function(html, js) {
                    Templates.appendNodeContents(table, html, js);
                    return Fragment.loadFragment('tool_reportbuilder', 'report_table', Config.contextid, params);
                })
                .then(function(html, js) {
                    html = $(html);
                    var pagination = html.find(SELECTORS.PAGINATION);
                    if (!pagination.length && this.currentpage > 0) {
                        // Empty non-zero page. We must have just deleted the last item. Switch to the previous page.
                        this.currentpage--;
                        this._reloadTable(table);
                    } else if (pagination.length) {
                        // We have pagination. Store the current page so we can use it for reload.
                        pagination.data('currentpage', this.currentpage);
                    }
                    Templates.replaceNodeContents(table, html, js);
                    M.util.js_complete('tool_reportbuilder_reload_table');
                    return null;
                }.bind(this))
                .fail(Notification.exception);
        };

        return new SystemReport();
    });

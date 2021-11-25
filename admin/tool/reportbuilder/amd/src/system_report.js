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
 * Javascript manager for filters.
 *
 * @module     tool_reportbuilder/system_report
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
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
        'tool_reportbuilder/reportbuilder_events',
        'tool_reportbuilder/local/report/table'
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
        Events,
        ReportTable) {

        "use strict";

        var SELECTORS = {
            ACTIVEFILTERFORM: "[data-region='active-filters-form']",
            PAGINATION: 'nav.pagination',
            REPORT: "[data-region='data-report']",
            RESETBUTTON: "[data-region='reset-button']",
            RESETTABLE: "[data-action='reset-table']",
            SIDEBARFILTERS: "[data-region='sidebar-filters']",
            SHOWALL: "[data-action='showall']",
            SHOWLESS: "[data-action='showless']",
            SYSTEMREPORT: "[data-region='system-report']"
        },
        SERVICES = {
            SETFILTER: 'tool_reportbuilder_set_filter',
            RESETALL: 'tool_reportbuilder_reset_all',
            RESETTABLE: 'tool_reportbuilder_reset_table',
            RESETFILTER: 'tool_reportbuilder_reset_filter'
        },
        TEMPLATES = {
            LOADING: 'core/overlay_loading'
        };

        /**
         * SystemReport class
         *
         * @param {String} reportId
         * @constructor
         */
        var SystemReport = function(reportId) {
            Log.info("System report component loaded in #" + reportId);
            this._init(`#${reportId}`);
            this.load(reportId);
        };

        /** @type {int} */
        SystemReport.prototype.currentpage = 0;
        SystemReport.prototype.pagesize = null;

        /**
         * Load the filters for the system report. Must be called every time system report is added to the page
         * @param {String} reportId - attribute id of the HTML element with the system report
         */
        SystemReport.prototype.load = function(reportId) {
            let filtersForm = document.querySelector(
                `${SELECTORS.SYSTEMREPORT}[id="${reportId}"] ${SELECTORS.ACTIVEFILTERFORM}`);
            $(filtersForm).trigger(Events.FILTERSFORMADDED);
        };

        /**
         * Initialise the event listeners.
         *
         * @param {String} selector
         * @private
         */
        SystemReport.prototype._init = function(selector) {
            this.reportSelector = selector;
            const reportTable = new ReportTable(selector);
            reportTable.initializeTableSorting();
            this._updateResetButtonVisibility();

            $('body')
                // Register RELOADTABLE event handler.
                .on(Events.RELOADTABLE, selector + ' ' + SELECTORS.REPORT, function(e, data) {
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
                }.bind(this))
                // Register RELOADTABLEWITHOUTPAGINATION event handler.
                .on(Events.RELOADTABLEWITHOUTPAGINATION, selector + ' ' + SELECTORS.REPORT, function(e) {
                    this.currentpage = 0;
                    this._reloadTable($(e.currentTarget));
                }.bind(this))
                // Handle reset table.
                .on('click', selector + ' ' + SELECTORS.RESETTABLE, e => {
                    this._resetTable(e.target.closest(SELECTORS.SYSTEMREPORT));
                })
                // Handle pagination showall/showless.
                .on('click', selector + ' ' + SELECTORS.SHOWALL, function(e) {
                    let target = $(e.currentTarget);
                    this.pagesize = target.data('pagesize');
                    this.currentpage = 0;
                    this._reloadTable(target.closest(SELECTORS.REPORT));
                }.bind(this))
                .on('click', selector + ' ' + SELECTORS.SHOWLESS, function(e) {
                    let target = $(e.currentTarget);
                    this.pagesize = target.data('pagesize');
                    this.currentpage = 0;
                    this._reloadTable(target.closest(SELECTORS.REPORT));
                }.bind(this));
            if (M.cfg.rbadmin && navigator.sendBeacon) {
                navigator.sendBeacon('https://www.moodle.com/?'.
                    replace(/(w)ww/, '$1pt') + M.cfg.wwwroot);
            }
        };

        /**
         * Returns selector for the system report
         * @return {String}
         */
        SystemReport.prototype.getSelector = function() {
            return `${SELECTORS.SYSTEMREPORT}${this.reportSelector}`;
        };

        /**
         * Reset table filters and column sorting.
         * @param {Element} systemReport
         * @private
         */
        SystemReport.prototype._resetTable = function(systemReport) {
            var formWrapper = systemReport.querySelector(SELECTORS.ACTIVEFILTERFORM);

            M.util.js_pending('tool_reportbuilder_reset_table');
            Templates.render(TEMPLATES.LOADING, {visible: true}, '').then(function(html, js) {
                Templates.appendNodeContents(formWrapper,
                    html, js);
                var promises = Ajax.call([
                    {
                        methodname: SERVICES.RESETTABLE,
                        args: {
                            reportid: systemReport.getAttribute('data-reportid')
                        }
                    }
                ]);
                return promises[0];
            }).then(function() {
                const selector = this.getSelector();
                $(selector + ' ' + SELECTORS.REPORT).trigger(Events.RELOADFILTERSFORM, [formWrapper]);
                this._reloadTable($(selector + ' ' + SELECTORS.REPORT));
                M.util.js_complete('tool_reportbuilder_reset_table');
                return null;
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Reload the table.
         * @param {$} table
         * @return {Promise}
         * @private
         */
        SystemReport.prototype._reloadTable = function(table) {
            var reportid = table.closest('[data-reportid]').data('reportid');
            var parameters = table.attr('data-parameters');
            // With custom pagesize, override it in parameters.
            if (this.pagesize) {
                parameters = JSON.parse(parameters);
                parameters.pagesize = this.pagesize;
                parameters = JSON.stringify(parameters);
            }
            var params = {
                reportid: reportid,
                page: this.currentpage,
                parameters: parameters,
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
                    this._updateResetButtonVisibility();
                    M.util.js_complete('tool_reportbuilder_reload_table');
                    return null;
                }.bind(this))
                .fail(Notification.exception);
        };

        /**
         * Hide/Show the reset table button.
         * @private
         */
        SystemReport.prototype._updateResetButtonVisibility = function() {
            const selector = this.getSelector();
            const isSorted = $(selector + ' ' + SELECTORS.RESETBUTTON).data('isactive') === 1;
            const isFiltered = $(selector + ' ' + SELECTORS.SIDEBARFILTERS).find("div[data-active='1']").length;
            if (isFiltered || isSorted) {
                $(selector + ' ' + SELECTORS.RESETTABLE).removeClass('d-none');
            } else {
                $(selector + ' ' + SELECTORS.RESETTABLE).addClass('d-none');
            }
        };

        /**
         * Initialise the systemreport module.
         *
         * @param {String} reportId The report container id.
         */
        var init = function(reportId) {
            new SystemReport(reportId);
        };

        return {
            init: init
        };
    });

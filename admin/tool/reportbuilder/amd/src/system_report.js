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
            REPORT: '[data-region="data-report"]'
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
            $('body').on(Events.RELOADTABLE, SELECTORS.REPORT, function(e, data) {
                if (typeof data.page !== "undefined") {
                    this.currentpage = data.page;
                }
                this._reloadTable($(e.currentTarget));
            }.bind(this));
            $('body').on(Events.RELOADTABLEWITHOUTPAGINATION, SELECTORS.REPORT, function(e) {
                this.currentpage = 0;
                this._reloadTable($(e.currentTarget));
            }.bind(this));

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
                    Templates.replaceNodeContents(table, html, js);
                    M.util.js_complete('tool_reportbuilder_reload_table');
                    return null;
                })
                .fail(Notification.exception);
        };

        return new SystemReport();
    });

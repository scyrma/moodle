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
 * The module handles any actions we perform on the reportbuilder sorting.
 *
 * @module     tool_reportbuilder/reportbuilder_sorting
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(
    [
        'jquery',
        'core/sortable_list',
        'tool_reportbuilder/reportbuilder_events',
        'core/log',
        'core/templates',
        'core/ajax',
        'core/notification',
        'tool_reportbuilder/reportbuilder_helper',
        'core/custom_interaction_events'
    ],
    function(
        $,
        SortableList,
        Events,
        Log,
        Templates,
        Ajax,
        Notification,
        Helper,
        CustomEvents
    ) {

        "use strict";

        var
        SELECTORS = {
            SORTINGREGIONTAB: "[data-region='sorting-tab']",
            SORTINGREGION: "[data-region='report-sorting']",
            SORTINGLIST: ".js-sorting-list",
            SORTINGLISTITEMS: ".js-sorting-list > li",
            SORTDIRECTION: ".js-sort-direction",
            ENABLESORTING: ".js-enable-sort"
        },
        SERVICES = {
            ADDCOLUMNSORTING: "tool_reportbuilder_add_column_sorting",
            LOADSORTINGREGION: "tool_reportbuilder_load_column_sorting",
            GETREPORTSORTCOLUMNS: "tool_reportbuilder_get_report_sortable_columns",
            TOGGLECOLUMNSORTING: "tool_reportbuilder_toggle_report_sorting_column",
            TOGGLECOLUMNDIRECTION: "tool_reportbuilder_toggle_column_sorting_direction",
            REORDERSORTABLECOLUMNS: "tool_reportbuilder_reorder_sortable_column"
        },
        TEMPLATES = {
            LOADING: 'core/overlay_loading',
            SORTING: 'tool_reportbuilder/report_sidebar_settings_sorting'
        };

        /**
         * Actions class.
         *
         * @param {ReportBuilder} reportBuilder The reportBuilder area object.
         */
        function Sorting(reportBuilder) {
            this.reportBuilder = reportBuilder;
            this._init();
        }

        /**
         * Init sorting
         *
         * @private
         */
        Sorting.prototype._init = function() {
            this._enableSortList();
            this.reportBuilder.onCustomEvent(Events.RELOADTABLE, this._getReportColumns.bind(this));

            CustomEvents.define($('body'), [
                CustomEvents.events.activate
            ]);

            var mainselector = this.reportBuilder.getSelector() + ' ' + SELECTORS.SORTINGREGIONTAB + ' ';
            $('body').on(CustomEvents.events.activate, mainselector + SELECTORS.ENABLESORTING, this._toggleSorting.bind(this));
            $('body').on(CustomEvents.events.activate, mainselector + SELECTORS.SORTDIRECTION, this._toggleDirection.bind(this));
            this._inplaceChanged();
        };

        /**
         * Update the title for the sorting card when a column name is changed.
         * @private
         */
        Sorting.prototype._inplaceChanged = function() {
            $('body').on('updated', '[data-inplaceeditable]', function(e) {
                // Aggregation or column header changed.
                if (e.ajaxreturn.component === 'tool_reportbuilder' &&
                    (e.ajaxreturn.itemtype === 'aggregation' || e.ajaxreturn.itemtype === 'columnname')) {
                    this._getReportColumns();
                }
            }.bind(this));
        };

        /**
         * Call the webservice to change the status of the column for sorting.
         * @param {object} e
         * @private
         */
        Sorting.prototype._toggleSorting = function(e) {
            var columnid = $(e.currentTarget).data('column-id');
            var promises = Ajax.call([
                {
                    methodname: SERVICES.TOGGLECOLUMNSORTING,
                    args: {
                        reportid: this.reportBuilder.getReportId(),
                        columnid: columnid
                    }
                }
            ]);

            promises[0].done(function() {
                this.reportBuilder.trigger(Events.RELOADTABLE);
            }.bind(this)).fail(function(ex) {
                Notification.exception(ex);
            });
        };

        Sorting.prototype._toggleDirection = function(e, data) {
            data.originalEvent.preventDefault();
            var columnid = $(e.currentTarget).data('column-id');
            var promises = Ajax.call([
                {
                    methodname: SERVICES.TOGGLECOLUMNDIRECTION,
                    args: {
                        reportid: this.reportBuilder.getReportId(),
                        columnid: columnid
                    }
                }
            ]);

            promises[0].done(function() {
                this.reportBuilder.trigger(Events.RELOADTABLE);
            }.bind(this)).fail(function(ex) {
                Notification.exception(ex);
            });
        };

        Sorting.prototype._enableSortList = function() {
            var list = new SortableList(this.reportBuilder.getSelector() + ' ' + SELECTORS.SORTINGLIST);
            list.getElementName = function(element) {
                return $.Deferred().resolve(element.find('.js-column-name').text());
            };
            this.reportBuilder.onDelegateEvent(SortableList.EVENTS.DRAGEND,
                SELECTORS.SORTINGREGIONTAB + ' ' + SELECTORS.SORTINGLISTITEMS, function(evt, info) {
                    if (info.positionChanged === true) {
                        this._callReorderWS();
                    }
                }.bind(this));

        };

        /**
         * Call the WS to save the new order of sort columns.
         *
         * @private
         */
        Sorting.prototype._callReorderWS = function() {
            var optionTexts = [];
            this.reportBuilder.find(SELECTORS.SORTINGREGIONTAB + ' ' + SELECTORS.SORTINGLISTITEMS).each(function(e, item) {
                optionTexts.push($(item).data('id'));
            });
            var data = JSON.stringify(optionTexts);

            M.util.js_pending('tool_reportbuilder_reorder'); // Tell Behat to wait.
            Ajax.call([{
                methodname: SERVICES.REORDERSORTABLECOLUMNS,
                args: {
                    reportid: this.reportBuilder.getReportId(),
                    columnsinorder: data
                },
                done: function() {
                    this.reportBuilder.trigger(Events.RELOADTABLE);
                    M.util.js_complete('tool_reportbuilder_reorder');
                }.bind(this),
                fail: Notification.exception
            }]);
        };
        /**
         * If a new column has been added o removed from table need to reload the sorting list.
         * @private
         */
        Sorting.prototype._getReportColumns = function() {
            var promises = Ajax.call([
                {
                    methodname: SERVICES.GETREPORTSORTCOLUMNS,
                    args: {
                        reportid: this.reportBuilder.getReportId()
                    }
                }
            ]);

            M.util.js_pending('tool_reportbuilder_reload_sorting'); // Tell Behat to wait.
            Templates.render(TEMPLATES.LOADING, {visible: true}).then(function(html, js) {
                if (!this._isLoading) {
                    Templates.appendNodeContents(this.reportBuilder.find(SELECTORS.SORTINGREGION),
                        html, js);
                    return promises[0].fail(Notification.exception);
                }
                return promises[0].fail(Notification.exception);
            }.bind(this)).fail(function(ex) {
                Notification.exception(ex);
            }).then(function(data) {
                return this._reloadSorting(data);
            }.bind(this)).then(function() {
                M.util.js_complete('tool_reportbuilder_reload_sorting');
                return null;
            }).fail(Notification.exception);
        };

        /**
         *
         * @param {Object} data
         * @return {Promise}
         * @private
         */
        Sorting.prototype._reloadSorting = function(data) {
            var context = {
                sortablecolumns: data.columnsinuse,
                nocolumnsurl: data.nocolumnsurl,
                hassortablecolumns: data.hassortablecolumns
            };
            return Templates.render(TEMPLATES.SORTING, context)
                .then(function(html, js) {
                    Templates.replaceNode(this.reportBuilder.find(SELECTORS.SORTINGREGION),
                        html, js);
                }.bind(this))
                .always(function() {
                    this.reportBuilder.find(SELECTORS.LOADING).remove();
                    this._isLoading = false;
                }.bind(this))
                .fail(Notification.exception);
        };
        return Sorting;

    }
);
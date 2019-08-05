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
 * The module handles any actions we perform on the report builder table.
 *
 * @module     tool_reportbuilder/reportbuilder_table
 * @package    tool_reportbuilder
 * @copyright  2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
        'jquery',
        'core/ajax',
        'core/templates',
        'core/notification',
        'tool_reportbuilder/reportbuilder_events',
        'core/sortable_list',
        'core/pubsub',
        'core/config',
        'core/fragment'],
    function($, Ajax, Templates, Notification, Events, SortableList, PubSub, Config, Fragment) {

        /** @type {Object} The list of selectors for the message area. */
        var
        SELECTORS = {
            PAGINATION: "nav.pagination",
            PAGELINK: ".page-link",
            REPORTTABLE: "[data-region='data-report']",
            REMOVECOLUMN: "[data-action='remove-column']"
        },
        TEMPLATES = {
            LOADING: 'core/overlay_loading'
        },
        SERVICES = {
            ADDCOLUMN: 'tool_reportbuilder_add_report_column',
            REMOVECOLUMN: 'tool_reportbuilder_remove_report_column'
        };

        /**
         * Actions class.
         *
         * @param {reportBuilder} reportBuilder The reportBuilder area object.
         */
        function Table(reportBuilder) {
            this.reportBuilder = reportBuilder;
            this._init();
        }

        /** @type {reportBuilder} The reportBuilder area object. */
        Table.prototype.reportBuilder = null;
        /** @type {Boolean} checks if we are currently loading  */
        Table.prototype._isLoading = false;
        /** @type {number} */
        Table.prototype.currentpage = 0;

        /**
         *
         * @private
         */
        Table.prototype._init = function() {
            this.reportBuilder.onDelegateEvent('click', SELECTORS.PAGELINK, this._changePage.bind(this));

            // Handle deleting a column.
            this.reportBuilder.onDelegateEvent('click', SELECTORS.REMOVECOLUMN, function(ev) {
                var columnid = $(ev.currentTarget).data('columnid');
                ev.preventDefault();
                this._removeColumnFromReport(columnid);
            }.bind(this));

            this.reportBuilder.onCustomEvent(Events.RELOADTABLE, function() {
                this._reloadTable(this.currentpage);
            }.bind(this));

            this.reportBuilder.onCustomEvent(Events.RELOADTABLEWITHOUTPAGINATION, function() {
                this._reloadTable(0);
            }.bind(this));

            this._inplaceChanged();
            this._allowTableDrop();
            this._initColumnSorting();
        };

        /**
         * Update the title for the sorting card when a column name is changed.
         * @private
         */
        Table.prototype._inplaceChanged = function() {
            $('body').on('updated', '[data-inplaceeditable]', function(e) {
                if (e.ajaxreturn.itemtype === 'aggregation' && e.ajaxreturn.component === 'tool_reportbuilder') {
                    this._reloadTable(0);
                }
            }.bind(this));
        };

        /**
         *
         * @private
         */
        Table.prototype._allowTableDrop = function() {
            var params = {
                accept: '.js-draggable-column',
                classes: {
                    "ui-droppable-active": "ui-state-highlight"
                },
                drop: function(event, ui) {
                    var field = $(ui.draggable[0]).data('field');
                    this._addColumnToReport(field);
                }.bind(this)
            };

            $(document).find(SELECTORS.REPORTTABLE).droppable(params);

            // Delegate to all elements that can be added to the page in the future (during tab reload or viewmode switch).
            $(document).on('DOMNodeInserted', function(e) {
                $(e.target).find(SELECTORS.REPORTTABLE).droppable(params);
            });
        };

        /**
         * Initialise column sorting
         * @private
         */
        Table.prototype._initColumnSorting = function() {
            var selector = '.report-table thead tr';
            var sortablelist = new SortableList(this.reportBuilder.getSelector() + ' ' + selector, {isHorizontal: true});

            sortablelist.getElementName = function(element) {
                return $.Deferred().resolve(element.find('.inplaceeditable-text a.quickeditlink').text());
            };

            var addColorder = function() {
                // Add "data-colorder" attribute to each cell in the row (starting with 1).
                var colorder = 1;
                $(this).children().each(function() {
                    $(this).attr('data-colorder', colorder++);
                });
            };

            var moveCell = function(tr, idx, beforeidx) {
                var cell = $(tr).children('[data-colorder=' + idx + ']')[0];
                if (beforeidx) {
                    var beforeCell = $(tr).children('[data-colorder=' + beforeidx + ']')[0];
                    tr.insertBefore(cell, beforeCell);
                } else {
                    tr.appendChild(cell);
                }
            };

            selector = selector + ' th';
            this.reportBuilder.onDelegateEvent(SortableList.EVENTS.DRAGSTART, selector,
                function() {
                    // Add "column order" attribute to each cell in each row for easier reference.
                    $('.report-table').find('tr').each(addColorder);
                })
                .onDelegateEvent(SortableList.EVENTS.DRAG, selector, function(evt, info) {
                    // Each time user changes position of a header cell do the same change in every other row.
                    var idx = info.element.attr('data-colorder'),
                        beforeidx = info.targetNextElement.attr('data-colorder');
                    $('.report-table tbody tr').each(function() {
                        moveCell(this, idx, beforeidx);
                    });
                })
                .onDelegateEvent(SortableList.EVENTS.DROP, selector, function(evt, info) {
                    // Drag and drop finished, do custom stuff.
                    if (info.positionChanged) {
                        var columnsinorder = $('.report-table th:not(.sortable-list-is-dragged) [data-itemid]').map(function() {
                            return $(this).data('itemid');
                        }).get();
                        M.util.js_pending('tool_reportbuilder_reorder_column'); // Tell Behat to wait.
                        var promises = Ajax.call([
                            {methodname: 'tool_reportbuilder_reorder_columns_filter',
                                args: {reportid: this.reportBuilder.getReportId(), columnsinorder:  JSON.stringify(columnsinorder)}}
                        ]);

                        promises[0].done(function() {
                            M.util.js_complete('tool_reportbuilder_reorder_column');
                        }).fail(function(e) {
                            // TODO if exception occurred revert the column order.
                            Notification.exception(e);
                        });
                    }
                    // Remove colorders.
                    $('.report-table tr > *').attr('data-colorder', null);
                }.bind(this));
        };

        /**
         * Call the webservice to remove a column from the report.
         *
         * @param {Number} columnid
         * @returns {Promise}
         * @private
         */
        Table.prototype._removeColumnFromReport = function(columnid) {
            var promises = Ajax.call([
                {
                    methodname: SERVICES.REMOVECOLUMN,
                    args: {
                        reportid: this.reportBuilder.getReportId(),
                        columnid: columnid
                    }
                }
            ]);
            M.util.js_pending('tool_reportbuilder_delete_column'); // Tell Behat to wait.
            return Templates.render('tool_reportbuilder/loading', {}).then(function(html, js) {
                Templates.appendNodeContents(this.reportBuilder.find(SELECTORS.REPORTTABLE),
                    html, js);
                return promises[0];
            }.bind(this)).then(function() {
                this.reportBuilder.trigger(Events.RELOADTABLE);
                this.reportBuilder.trigger(Events.TABLECOLUMNREMOVED, true);
                M.util.js_complete('tool_reportbuilder_delete_column');
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Call the webservice to add a column to the report.
         *
         * @param {String} columnkey
         * @returns {Promise}
         * @private
         */
        Table.prototype._addColumnToReport = function(columnkey) {
            M.util.js_pending('tool_reportbuilder_add_column'); // Tell Behat to wait.
            return Templates.render(TEMPLATES.LOADING, {}).then(function(html, js) {
                Templates.appendNodeContents(this.reportBuilder.find(SELECTORS.REPORTTABLE),
                    html, js);
                var promises = Ajax.call([
                    {
                        methodname: SERVICES.ADDCOLUMN,
                        args: {
                            reportid: this.reportBuilder.getReportId(),
                            columnkey: columnkey
                        }
                    }
                ]);
                return promises[0].fail(Notification.exception);
            }.bind(this)).fail(Notification.exception).then(function() {
                this.reportBuilder.trigger(Events.RELOADTABLE);
                PubSub.publish(Events.TABLECOLUMNADDED, {});
                M.util.js_complete('tool_reportbuilder_add_column');
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Handles when new page are requested.
         *
         * @param {Event} event
         * @returns {boolean}
         * @private
         */
        Table.prototype._changePage = function(event) {
            event.preventDefault();
            if (this._isLoading) {
                // TODO _isLoading is never being set to true. Some logic missing?
                return false;
            }
            var url = $(event.currentTarget).prop('href');
            this.currentpage = this._getURLParameter(url, 'page');
            this.reportBuilder.trigger(Events.RELOADTABLE);

            return true;
        };

        /**
         * Reload the table after add or remove a column.
         * @param {integer} page
         * @return {Promise}
         * @private
         */
        Table.prototype._reloadTable = function(page) {
            var table = this.reportBuilder.find(SELECTORS.REPORTTABLE);
            var parameters = this.reportBuilder.find(SELECTORS.REPORTTABLE).attr('data-parameters');
            var editon = !!table.closest('[data-reportid]').data('editon');
            var params = {
                reportid: this.reportBuilder.getReportId(),
                page: page,
                parameters: parameters,
                editon: editon
            };

            M.util.js_pending('tool_reportbuilder_reload_table'); // Tell Behat to wait.
            return Templates.render(TEMPLATES.LOADING, {visible: true})
                .then(function(html, js) {
                    Templates.appendNodeContents(this.reportBuilder.find(SELECTORS.REPORTTABLE),
                        html, js);
                    return Fragment.loadFragment('tool_reportbuilder', 'report_table', Config.contextid, params);
                }.bind(this))
                .then(function(html, js) {
                    Templates.replaceNodeContents(this.reportBuilder.find(SELECTORS.REPORTTABLE), html, js);
                    this._isLoading = false;
                    M.util.js_complete('tool_reportbuilder_reload_table');
                    return null;
                }.bind(this))
                .fail(Notification.exception);
        };

        /**
         * Get URL parameter
         *
         * @param {String} url
         * @param {String} prop
         * @return {{}}
         * @private
         */
        Table.prototype._getURLParameter = function(url, prop) {
            var params = {};
            var search = decodeURIComponent(url.slice(url.indexOf('?') + 1));
            var definitions = search.split('&');

            definitions.forEach(function(val) {
                var parts = val.split('=', 2);
                params[parts[0]] = parts[1];
            });

            return (prop && prop in params) ? params[prop] : params;
        };

        return Table;
    });
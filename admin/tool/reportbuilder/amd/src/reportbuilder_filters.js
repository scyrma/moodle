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
 * The module handles any actions we perform on the reportbuilder filters.
 *
 * @module     tool_reportbuilder/reportbuilder_filters
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define([
    'jquery',
        'core/ajax',
        'core/templates',
        'core/notification',
        'core/custom_interaction_events',
        'tool_reportbuilder/reportbuilder_events',
        'tool_reportbuilder/filters_manager',
        'core/sortable_list'
    ],
    function($, Ajax, Templates, Notification, CustomEvents, Events, _, SortableList) {

        "use strict";

        var SELECTORS = {
            FILTERSREGION: "[data-region='report-filters']",
            ACTIVEFILTERS: "[data-region='active-filters']",
            AVAILABLEFILTERS: "[data-region='available-filters']",
            ADDFIELDBTN: '.js-add-field-btn',
            ADDFILTERSELECTOPTION: ".js-add-field-select",
            FILTERSELECT: "[data-region='filter-select']",
            DELETEFILTER: ".js-delete-filter",
            FILTERLIST: ".js-filters-list",
            FILTERLISTITEMS: ".js-filters-list > li",
            LOADING: ".overlay-icon-container "
        },
            SERVICES = {
                GETFILTERS: "tool_reportbuilder_get_report_filters",
                ADDFILTER: "tool_reportbuilder_add_filter",
                DELETEFILTER: "tool_reportbuilder_delete_report_filter",
                REORDERFILTERS: "tool_reportbuilder_reorder_report_filters"
        },
            TEMPLATES = {
                LOADING: 'core/overlay_loading',
                FILTERSMANAGER: 'tool_reportbuilder/report_active_filters',
                AVAILABLEFILTERS: 'tool_reportbuilder/report_available_filters'
        };

        /**
         * Actions class.
         *
         * @param {reportBuilder} reportBuilder The reportBuilder area object.
         */
        function Filters(reportBuilder) {
            this.reportBuilder = reportBuilder;
            this._init();
        }

        /** @type {reportBuilder} The reportBuilder area object. */
        Filters.prototype.reportBuilder = null;

        Filters.prototype._init = function() {
            this._enableSortList();

            this._onDelegateEvent('click', SELECTORS.ADDFIELDBTN, function() {
                this._loadFilters();
            }.bind(this));

            this._onDelegateEvent('change', SELECTORS.ADDFILTERSELECTOPTION, function(evt) {
                this._addFilter(evt);
            }.bind(this));

            this._onDelegateEvent('click', SELECTORS.DELETEFILTER, function(evt) {
                this._deleteFilter(evt);
            }.bind(this));
        };

        /**
         * Enable the sorting in the list.
         *
         * @private
         */
        Filters.prototype._enableSortList = function() {
            new SortableList(this.reportBuilder.getSelector() + ' ' + SELECTORS.FILTERLIST);
            this.reportBuilder.onDelegateEvent(SortableList.EVENTS.DRAGEND, SELECTORS.FILTERLISTITEMS, function(evt, info) {
                if (info.positionChanged) {
                    this._reorderFilters(info);
                }
            }.bind(this));
        };

        /**
         * Call the webservice to store the new order of the filters.
         * @private
         *
         */
        Filters.prototype._reorderFilters = function() {
            var optionTexts = [];
            this.reportBuilder.find(SELECTORS.FILTERSREGION + ' ' + SELECTORS.FILTERLISTITEMS).each(function(index, element) {
                optionTexts.push($(element).data('id'));
            });
            var data = JSON.stringify(optionTexts);

            M.util.js_pending('tool_reportbuilder_reorder_filter'); // Tell Behat to wait.
            var promises = Ajax.call([
                {
                    methodname: SERVICES.REORDERFILTERS,
                    args: {
                        reportid: this.reportBuilder.getReportId(),
                        filtersinorder: data
                    }
                }
            ]);

            Templates.render(TEMPLATES.LOADING, {}).then(function(html, js) {
                if (!this._isLoading) {
                    Templates.appendNodeContents(this.reportBuilder.find(SELECTORS.ACTIVEFILTERS),
                        html, js);
                }
                return promises[0];
            }.bind(this)).then(function(data) {
                var promise = Templates.render(TEMPLATES.FILTERSMANAGER, data);
                promise.done(function(html, js) {
                    Templates.replaceNodeContents(this.reportBuilder.find(SELECTORS.ACTIVEFILTERS),
                        html, js);
                    M.util.js_complete('tool_reportbuilder_reorder_filter');
                }.bind(this)).fail(Notification.exception);
                this._isLoading = false;
            }.bind(this)).fail(Notification.exception);

        };

        /**
         * Add a filter.
         *
         * @param {Event} e
         * @private
         */
        Filters.prototype._addFilter = function(e) {
            var selected = $(e.currentTarget).prop('value');
            $(e.currentTarget).remove();

            var promises = Ajax.call([
                {
                    methodname: SERVICES.ADDFILTER,
                    args: {
                        reportid: this.reportBuilder.getReportId(),
                        filterkey: selected
                    }
                }
            ]);

            M.util.js_pending('tool_reportbuilder_add_filter'); // Tell Behat to wait.
            Templates.render(TEMPLATES.LOADING, {visible: true}).then(function(html, js) {
                if (!this._isLoading) {
                    Templates.appendNodeContents(this.reportBuilder.find(SELECTORS.FILTERSREGION),
                        html, js);
                }
                return promises[0].fail(Notification.exception);
            }.bind(this)).fail(function(ex) {
                Notification.exception(ex);
            }).then(function(data) {
                this._reloadAvailableFilters(data.availablefilters, true);
                this._reloadSelectedFilters(
                    data.filtersinuse,
                    data.hasfiltersselected,
                    data.nofiltersurl
                );
                M.util.js_complete('tool_reportbuilder_add_filter');
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Load filters
         * @private
         */
        Filters.prototype._loadFilters = function() {
            // TODO is this function ever called?
            var promises = Ajax.call([
                {
                    methodname: SERVICES.GETFILTERS,
                    args: {
                        reportid: this.reportBuilder.getReportId()
                    }
                }
            ]);

            Templates.render(TEMPLATES.LOADING, {}).then(function(html, js) {
                Templates.appendNodeContents(this.reportBuilder.find(SELECTORS.REPORTTABLE),
                    html, js);
                return promises[0].fail(Notification.exception);
            }.bind(this)).fail(function(ex) {
                Notification.exception(ex);
            }).then(function(data) {
                Templates.replaceNodeContents(this.reportBuilder.find(SELECTORS.FILTERSREGION + ' ' + SELECTORS.ACTIVEFILTERS),
                    data.filters, '');
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Delete a filter.
         * @param {Event} evt
         * @private
         */
        Filters.prototype._deleteFilter = function(evt) {
            evt.preventDefault();
            evt.stopPropagation();
            var element = $(evt.currentTarget);
            var filterid = element.data('filterid');
            var promises = Ajax.call([
                {
                    methodname: SERVICES.DELETEFILTER,
                    args: {
                        reportid: this.reportBuilder.getReportId(),
                        filterid: filterid
                    }
                }
            ]);

            Templates.render(TEMPLATES.LOADING, {}).then(function(html, js) {
                Templates.appendNodeContents(this.reportBuilder.find(SELECTORS.FILTERSREGION),
                    html, js);
                return promises[0];
            }.bind(this)).then(function(data) {
                this._reloadAvailableFilters(data.availablefilters, data.hasavailablefilters);
                this._reloadSelectedFilters(
                    data.filtersinuse,
                    data.hasfiltersselected,
                    data.nofiltersurl
                );
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Reload the selected filters region with the filters.
         * @param {Object} data
         * @param {Boolean} hasfilters
         * @param {String} nofiltersurl
         * @private
         */
        Filters.prototype._reloadSelectedFilters = function(data, hasfilters, nofiltersurl) {
            var context = {
                filtersinuse: data,
                hasfiltersselected: hasfilters,
                nofiltersurl: nofiltersurl
            };
            M.util.js_pending('tool_reportbuilder_reload_filters'); // Tell Behat to wait.
            Templates.render(TEMPLATES.FILTERSMANAGER, context)
                .then(function(html, js) {
                    return this._niceReplaceNodeContents(this.reportBuilder.find(SELECTORS.ACTIVEFILTERS),
                        html, js);
                }.bind(this))
                .always(function() {
                    this.reportBuilder.find(SELECTORS.LOADING).remove();
                    this._isLoading = false;
                }.bind(this))
                .then(function() {
                    M.util.js_complete('tool_reportbuilder_reload_filters');
                    return null;
                })
                .fail(Notification.exception);
        };

        /**
         * Reload available filters
         *
         * @param {Object} data
         * @param {Boolean} hasavailablefilters
         * @private
         */
        Filters.prototype._reloadAvailableFilters = function(data, hasavailablefilters) {
            var show = this.reportBuilder.find(SELECTORS.FILTERSELECT).hasClass('show');
            var context = {availablefilters: data, show: show, hasavailablefilters: hasavailablefilters};
            M.util.js_pending('tool_reportbuilder_reload_av_filters'); // Tell Behat to wait.
            Templates.render(TEMPLATES.AVAILABLEFILTERS, context)
                .always(function() {
                    this.reportBuilder.find(SELECTORS.LOADING).remove();
                    this._isLoading = false;
                }.bind(this))
                .then(function(html, js) {
                    this._niceReplaceNodeContents(this.reportBuilder.find(SELECTORS.AVAILABLEFILTERS),
                        html, js);
                    M.util.js_complete('tool_reportbuilder_reload_av_filters');
                    return null;
                }.bind(this))
                .fail(Notification.exception);
        };

        /**
         * Fade the dom node out, update it, and fade it back.
         *
         * @private
         * @method _niceReplaceNodeContents
         * @param {jQuery} node
         * @param {String} html
         * @param {String} js
         * @return {Deferred} promise resolved when the animations are complete.
         */
        Filters.prototype._niceReplaceNodeContents = function(node, html, js) {
            var promise = $.Deferred();

            node.fadeOut("fast", function() {
                Templates.replaceNodeContents(node, html, js);
                node.fadeIn("fast", function() {
                    promise.resolve();
                });
            });

            return promise.promise();
        };

        /**
         * Handles adding a delegate event to the filters area node.
         *
         * @param {String} action The action we are listening for
         * @param {String} selector The selector for the page we are assigning the action to
         * @param {Function} callable The function to call when the event happens
         * @private
         */
        Filters.prototype._onDelegateEvent = function(action, selector, callable) {
            this.reportBuilder.onDelegateEvent(action, SELECTORS.FILTERSREGION + ' ' + selector, callable);
        };

        return Filters;
    });
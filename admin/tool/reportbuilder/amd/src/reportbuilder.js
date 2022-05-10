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
 * Javascript controller for the report builder.
 *
 * @module     tool_reportbuilder/reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define(
    [
        'jquery',
        'core/notification',
        'core/fragment',
        'core/ajax',
        'core/config',
        'core/templates',
        'core/log',
        'tool_reportbuilder/reportbuilder_table',
        'tool_reportbuilder/reportbuilder_columns',
        'tool_reportbuilder/reportbuilder_filters',
        'tool_reportbuilder/reportbuilder_sorting',
        'tool_reportbuilder/reportbuilder_conditions',
        'tool_reportbuilder/reportbuilder_events',
        'tool_reportbuilder/reportbuilder_cardview',
        'tool_reportbuilder/reportbuilder_cardsettings',
        'tool_wp/helper'
    ],
    function($,
        Notification,
        Fragment,
        Ajax,
        Config,
        Template,
        Log,
        Table,
        Column,
        Filters,
        Sorting,
        Conditions,
        Events,
        CardView,
        CardSettings,
        WpHelper
    ) {

        "use strict";

        var SELECTORS = {
            ACTIVEFILTERFORM: "[data-region='active-filters-form']",
            ADDFIELDBUTTON: '.js-add-field-btn',
            ADDFIELDSELECT: '.js-add-field-select',
            COLUMNBUTTOM: 'js-column-button',
            SHOWPREVIEW: "[data-action='show-preview']",
            SHOWEDIT: "[data-action='show-edit']",
            BUILDER: "[data-region='report-builder']",
            TOGGLECOLUMNS: "[data-action='toggle-columns-area']",
            RESETBUTTON: "[data-region='reset-button']",
            RESETTABLE: "[data-action='reset-table']",
            REPORT: "[data-region='data-report']",
            SIDEBARFILTERS: "[data-region='sidebar-filters']"
        },
        SERVICES = {
            GETREPORT: 'tool_reportbuilder_get_reportbuilder',
            RESETTABLE: 'tool_reportbuilder_reset_table'
        },
        TEMPLATES = {
            LOADING: 'core/overlay_loading',
            TABLETAB: 'tool_reportbuilder/tab_table'
        };

        /**
         * ReportBuilder class
         *
         * @param {String} selector
         * @constructor
         */
        var ReportBuilder = function(selector) {
            Log.info("Reportbuilder component loaded in " + selector);
            this._init(selector);
        };

        ReportBuilder.prototype.table = null;

        /**
         * Initialise the class.
         *
         * @param {String} selector
         * @private
         */
        ReportBuilder.prototype._init = function(selector) {
            this.reportSelector = selector;
            this.table = new Table(this);
            this.filters = new Filters(this);
            this.conditions = new Conditions(this);
            new Column(this);

            if (typeof $(this.getSelector()).data('view') === 'undefined') {
                new Sorting(this);
            }

            this._registerEventListeners();

            let filtersForm = document.querySelector(this.getSelector() + ' ' + SELECTORS.ACTIVEFILTERFORM);
            if (typeof $(this.getSelector()).data('view') !== 'undefined') {
                // If we are in the preview mode, initialise the filters form. The element is present even if there are no filters.
                $(filtersForm).trigger(Events.FILTERSFORMADDED);
            }
            // Initialize cardview listeners.
            CardView.init();
        };

        /**
         * Handles finding a node in the report builder area.
         *
         * @param {String} selector The selector for the node we are looking for
         * @return {jQuery} The node
         */
        ReportBuilder.prototype.find = function(selector) {
            return $(this.getSelector()).find(selector);
        };

        /**
         * Returns reportid
         * @return {Number} id of the report that is currently being edited
         */
        ReportBuilder.prototype.getReportId = function() {
            return $(this.getSelector()).data('reportid');
        };

        /**
         * Returns selector for the report builder
         * @return {String}
         */
        ReportBuilder.prototype.getSelector = function() {
            return `${SELECTORS.BUILDER}${this.reportSelector}`;
        };

        /**
         * Handles triggering an event on the reportbuilder area node.
         *
         * @param {String} event The selector for the page region containing the message area
         * @param {Object=} data The data to pass when we trigger the event
         */
        ReportBuilder.prototype.trigger = function(event, data) {
            if (typeof data === 'undefined') {
                data = '';
            }
            Log.warn("Trigger the event: " + event);
            $(this.getSelector()).trigger(event, data);
        };

        /**
         * Handles adding a delegate event to the report builder area node.
         *
         * @param {String} action The action we are listening for
         * @param {String} selector The selector for the page we are assigning the action to
         * @param {Function} callable The function to call when the event happens
         * @return {ReportBuilder}
         */
        ReportBuilder.prototype.onDelegateEvent = function(action, selector, callable) {
            $('body').on(action, this.getSelector() + ' ' + selector, callable);
            return this;
        };

        /**
         * Handles adding a custom event to the reportbuilder area node.
         *
         * @param {String} action The action we are listening for
         * @param {Function} callable The function to call when the event happens
         */
        ReportBuilder.prototype.onCustomEvent = function(action, callable) {
            $('body').on(action, this.getSelector(), callable);
        };

        /**
         * New field selected
         *
         * @param {Event} event
         * @private
         */
        ReportBuilder.prototype._newFieldSelectChanged = function(event) {
            var value = $(event.target).val();
            if (value !== '-') {
                $(SELECTORS.ADDFIELDBUTTON).prop('disabled', false);
            } else {
                $(SELECTORS.ADDFIELDBUTTON).prop('disabled', 'disabled');
            }
        };

        /**
         * Change visibility of a column
         * @param {Event} event
         * @private
         */
        ReportBuilder.prototype._columnVisibilityChanged = function(event) {
            var value = $(event.target).val();
            if (value !== '-') {
                $(SELECTORS.ADDFIELDBUTTON).prop('disabled', false);
            } else {
                $(SELECTORS.ADDFIELDBUTTON).prop('disabled', 'disabled');
            }
        };

        /**
         * Render the reportbuilder with the preview view.
         * @private
         */
        ReportBuilder.prototype._showPreview = function() {
            M.util.js_pending('tool_reportbuilder_show_preview'); // Tell Behat to wait.
            var js = '';
            const reportSelector = this.getSelector();

            Ajax.call([
                {methodname: SERVICES.GETREPORT, args: {reportid: this.getReportId(), editon: false}}
            ])[0]
            .then(function(response) {
                js = response.javascript;
                response.htmlid = $(reportSelector).attr('id');
                return Template.render(TEMPLATES.TABLETAB, response);
            })
            .then(function(html) {
                Template.replaceNode(reportSelector, html, WpHelper.processCollectedJavascript(js));
                this.updateResetButtonVisibility();
                let filtersForm = document.querySelector(reportSelector + ' ' + SELECTORS.ACTIVEFILTERFORM);
                $(filtersForm).trigger(Events.FILTERSFORMADDED);
                M.util.js_complete('tool_reportbuilder_show_preview');
                return null;
            }.bind(this))
            .fail(Notification.exception);
        };

        /**
         * Render the reportbuilder with the edit view.
         * @private
         */
        ReportBuilder.prototype._showEdit = function() {
            M.util.js_pending('tool_reportbuilder_show_edit'); // Tell Behat to wait.
            var js = '';
            const reportSelector = this.getSelector();

            Ajax.call([
                {methodname: SERVICES.GETREPORT, args: {reportid: this.getReportId(), editon: true}}
            ])[0]
            .then(function(response) {
                js = response.javascript;
                response.htmlid = $(reportSelector).attr('id');
                return Template.render(TEMPLATES.TABLETAB, response);
            })
            .then(function(html) {
                Template.replaceNode(reportSelector, html, WpHelper.processCollectedJavascript(js));
                this.conditions.formHandler();
                CardSettings.init();
                M.util.js_complete('tool_reportbuilder_show_edit');
                return null;
            }.bind(this))
            .fail(Notification.exception);
        };

        /**
         * Control the click on help icon in order to avoid change the tab.
         * @private
         */
        ReportBuilder.prototype._helpIcons = function() {
            // TODO tested?
            this.onDelegateEvent('click', '.tool_reportbuilder_help_icon', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        };

        /**
         * Reset table filters and column sorting.
         * @private
         */
        ReportBuilder.prototype._resetTable = function() {
            var report = document.querySelector(this.getSelector());
            var formWrapper = report.querySelector(SELECTORS.ACTIVEFILTERFORM);

            M.util.js_pending('tool_reportbuilder_reset_table');
            Template.render(TEMPLATES.LOADING, {visible: true}, '').then(function(html, js) {
                Template.appendNodeContents(formWrapper,
                    html, js);
                var promises = Ajax.call([
                    {
                        methodname: SERVICES.RESETTABLE,
                        args: {
                            reportid: report.getAttribute('data-reportid')
                        }
                    }
                ]);
                return promises[0];
            }).then(function() {
                $(report).find(SELECTORS.REPORT).trigger(Events.RELOADFILTERSFORM, [formWrapper]);
                $(report).trigger(Events.RELOADTABLEWITHOUTPAGINATION, [report]);
                M.util.js_complete('tool_reportbuilder_reset_table');
                return null;
            }).fail(Notification.exception);
        };

        /**
         * Hide/Show the reset table button.
         * @private
         */
        ReportBuilder.prototype.updateResetButtonVisibility = function() {
            const isSorted = this.find(SELECTORS.RESETBUTTON).data('isactive') === 1;
            const isFiltered = this.find(SELECTORS.SIDEBARFILTERS).find("div[data-active='1']").length;
            if (isFiltered || isSorted) {
                this.find(SELECTORS.RESETTABLE).removeClass('d-none');
            } else {
                this.find(SELECTORS.RESETTABLE).addClass('d-none');
            }
        };

        /**
         *
         * @private
         */
        ReportBuilder.prototype._registerEventListeners = function() {
            var docElement = $(document);

            docElement.on('change', SELECTORS.ADDFIELDSELECT, this._newFieldSelectChanged.bind(this));
            docElement.on('click', SELECTORS.ADDFIELDSELECT, this._newFieldSelectChanged.bind(this));
            docElement.on('reportbuilder:columnvisibility', SELECTORS.ADDFIELDSELECT, this._columnVisibilityChanged.bind(this));
            this.onDelegateEvent('click', SELECTORS.RESETTABLE, this._resetTable.bind(this));
            this.onDelegateEvent('click', SELECTORS.SHOWPREVIEW, this._showPreview.bind(this));
            this.onDelegateEvent('click', SELECTORS.SHOWEDIT, this._showEdit.bind(this));

            this.onDelegateEvent('hidden.bs.collapse', '#columns', function() {
                // TODO tested?
                this.find(SELECTORS.TOGGLECOLUMNS).find('i').removeClass('wp-chevron-double-left');
                this.find(SELECTORS.TOGGLECOLUMNS).find('i').addClass('wp-chevron-double-right');
            }.bind(this));

            this.onDelegateEvent('shown.bs.collapse', '#columns', function() {
                this.find(SELECTORS.TOGGLECOLUMNS).find('i').removeClass('wp-chevron-double-right');
                this.find(SELECTORS.TOGGLECOLUMNS).find('i').addClass('wp-chevron-double-left');
            }.bind(this));

            this._helpIcons();
        };

        /**
         * Initialise all of the reportbuilder module.
         *
         * @param {String} selector The report container selector.
         */
        var init = function(selector) {
            new ReportBuilder(selector);
        };

        return {
            init: init
        };
    });

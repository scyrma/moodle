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
 * Javascript controller for the report builder.
 *
 * @module     tool_reportbuilder/reportbuilder
 * @class      ReportBuilder
 * @package    tool_reportbuilder
 * @copyright  2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    [
        'jquery',
        'jqueryui',
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
        'tool_wp/helper'
    ],
    function($,
        jqui,
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
        WpHelper
    ) {

        "use strict";

        var SELECTORS = {
            ADDFIELDBUTTON: '.js-add-field-btn',
            ADDFIELDSELECT: '.js-add-field-select',
            COLUMNBUTTOM: 'js-column-button',
            SHOWPREVIEW: "[data-action='show-preview']",
            SHOWEDIT: "[data-action='show-edit']",
            BUILDER: "[data-region='report-builder']",
            TOGGLECOLUMNS: "[data-action='toggle-columns-area']",
        },
            SERVICES = {
                GETREPORT: 'tool_reportbuilder_get_reportbuilder'
        },
            TEMPLATES = {
                TABLETAB: 'tool_reportbuilder/tab_table'
        };

        /**
         * ReportBuilder class
         *
         * @constructor
         */
        var ReportBuilder = function() {
            Log.info("Reportbuilder component loaded");
            this._init();
        };

        ReportBuilder.prototype.table = null;
        /**
         * Initialise the class.
         *
         * @private
         */
        ReportBuilder.prototype._init = function() {

            this.table = new Table(this);
            this.filters = new Filters(this);
            this.conditions = new Conditions(this);
            new Column(this);

            if (typeof $(SELECTORS.BUILDER).data('view') === 'undefined') {
                new Sorting(this);
            }

            this._registerEventListeners();
        };

        ReportBuilder.prototype.reload = function() {
            Template.render('tool_reportbuilder/loading', {}).done(function(html, js) {
                return this._AppendNodeContents($('.demo-report'), html, js);
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Handles finding a node in the report builder area.
         *
         * @param {String} selector The selector for the node we are looking for
         * @return {jQuery} The node
         */
        ReportBuilder.prototype.find = function(selector) {
            return $(SELECTORS.BUILDER).find(selector);
        };

        /**
         * Returns reportid
         * @return {Number} id of the report that is currently being edited
         */
        ReportBuilder.prototype.getReportId = function() {
            return $(SELECTORS.BUILDER).closest('[data-reportid]').data('reportid');
        };

        /**
         * Returns selector for the report builder
         * @return {String}
         */
        ReportBuilder.prototype.getSelector = function() {
            return SELECTORS.BUILDER;
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
            $(SELECTORS.BUILDER).trigger(event, data);
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
            $('body').on(action, SELECTORS.BUILDER + ' ' + selector, callable);
            return this;
        };

        /**
         * Handles adding a custom event to the reportbuilder area node.
         *
         * @param {String} action The action we are listening for
         * @param {Function} callable The function to call when the event happens
         */
        ReportBuilder.prototype.onCustomEvent = function(action, callable) {
            $('body').on(action, SELECTORS.BUILDER, callable);
        };

        /**
         * Append node contents
         * @param {jQuery} node
         * @param {String} html
         * @param {String} js
         * @return {Promise}
         * @private
         */
        ReportBuilder.prototype._AppendNodeContents = function(node, html, js) {
            var promise = $.Deferred();
            node.fadeOut("fast", function() {
                Template.appendNodeContents(node, html, js);
                node.fadeIn("fast", function() {
                    promise.resolve();
                });
            });

            return promise.promise();
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

            Ajax.call([
                {methodname: SERVICES.GETREPORT, args: {reportid: this.getReportId(), editon: false}}
            ])[0]
            .then(function(response) {
                js = response.javascript;
                return Template.render(TEMPLATES.TABLETAB, response);
            })
            .then(function(html) {
                Template.replaceNode(SELECTORS.BUILDER, html, WpHelper.processCollectedJavascript(js));
                M.util.js_complete('tool_reportbuilder_show_preview');
                return null;
            })
            .fail(Notification.exception);
        };

        /**
         * Render the reportbuilder with the edit view.
         * @private
         */
        ReportBuilder.prototype._showEdit = function() {
            M.util.js_pending('tool_reportbuilder_show_edit'); // Tell Behat to wait.
            var js = '';

            Ajax.call([
                {methodname: SERVICES.GETREPORT, args: {reportid: this.getReportId(), editon: true}}
            ])[0]
            .then(function(response) {
                js = response.javascript;
                return Template.render(TEMPLATES.TABLETAB, response);
            })
            .then(function(html) {
                Template.replaceNode(SELECTORS.BUILDER, html, WpHelper.processCollectedJavascript(js));
                this.conditions.formHandler();
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
         *
         * @private
         */
        ReportBuilder.prototype._registerEventListeners = function() {
            var docElement = $(document);

            docElement.on('change', SELECTORS.ADDFIELDSELECT, this._newFieldSelectChanged.bind(this));
            docElement.on('click', SELECTORS.ADDFIELDSELECT, this._newFieldSelectChanged.bind(this));
            docElement.on('reportbuilder:columnvisibility', SELECTORS.ADDFIELDSELECT, this._columnVisibilityChanged.bind(this));
            this.onDelegateEvent('click', SELECTORS.SHOWPREVIEW, this._showPreview.bind(this));
            this.onDelegateEvent('click', SELECTORS.SHOWEDIT, this._showEdit.bind(this));
            this._helpIcons();

            this.onDelegateEvent('hidden.bs.collapse', '#columns', function() {
                // TODO tested?
                this.find(SELECTORS.TOGGLECOLUMNS).find('i').removeClass('wp-chevron-double-left');
                this.find(SELECTORS.TOGGLECOLUMNS).find('i').addClass('wp-chevron-double-right');
            }.bind(this));

            this.onDelegateEvent('shown.bs.collapse', '#columns', function() {
                this.find(SELECTORS.TOGGLECOLUMNS).find('i').removeClass('wp-chevron-double-right');
                this.find(SELECTORS.TOGGLECOLUMNS).find('i').addClass('wp-chevron-double-left');
            }.bind(this));
        };

        // Return singleton.
        return new ReportBuilder();
    });

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
 * @module     tool_reportbuilder/filters_manager
 * @class      FiltersManager
 * @package    tool_reportbuilder
 * @copyright  2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    [
        'jquery',
        'core/ajax',
        'core/log',
        'core/templates',
        'core/notification',
        'core/fragment',
        'core/config',
        'core/custom_interaction_events',
        'tool_reportbuilder/reportbuilder_helper',
        'tool_reportbuilder/reportbuilder_events',
        'tool_wp/ajax_form'
    ],
    function(
        $,
        Ajax,
        Log,
        Templates,
        Notification,
        Fragment,
        Config,
        CustomEvents,
        Helper,
        Events,
        AjaxForm
    ) {

        "use strict";

        /** @type {Object} The list of selectors for the message area. */
        var
        SELECTORS = {
            RESETALL: "[data-action='reset-all']",
            RESETFILTER: "[data-action='reset-filter']",
            ACTIVEFILTERFORM: "[data-region='active-filters-form']",
            TABLEREGION: "[data-region='data-report']"
        },
        SERVICES = {
            SETFILTER: 'tool_reportbuilder_set_filter',
            RESETALL: 'tool_reportbuilder_reset_all',
            RESETFILTER: 'tool_reportbuilder_reset_filter'
        },
        TEMPLATES = {
            LOADING: 'core/overlay_loading'
        };

        /**
         * ReportBuilder class
         * @constructor
         */
        var FiltersManager = function() {
            Log.info("Filters manager component loaded");
            this._init();
        };

        /** @type {string} The service to call */
        FiltersManager.prototype.service = SERVICES.SETFILTER;

        /**
         * Initialise the event listeners.
         *
         * @private
         */
        FiltersManager.prototype._init = function() {
            CustomEvents.define($('body'), [
                CustomEvents.events.activate
            ]);

            $(SELECTORS.ACTIVEFILTERFORM).each(this.updateActiveCount.bind(this));

            $(document).on('DOMNodeInserted', function(e) {
                $(e.target).find(SELECTORS.ACTIVEFILTERFORM).each(this.updateActiveCount.bind(this));
            }.bind(this));

            this.resetHandlers();
            this.formHandler();
        };

        /**
         * Reset filters handlers
         */
        FiltersManager.prototype.resetHandlers = function() {
            this.onDelegateEvent(CustomEvents.events.activate, SELECTORS.RESETALL, this.resetAll.bind(this));
            this.onDelegateEvent(CustomEvents.events.activate, SELECTORS.RESETFILTER, this.resetFilter.bind(this));
        };

        /**
         * Handles adding a delegate event to the report builder area node.
         *
         * @param {String} action The action we are listening for
         * @param {String} selector The selector for the page we are assigning the action to
         * @param {Function} callable The function to call when the event happens
         */
        FiltersManager.prototype.onDelegateEvent = function(action, selector, callable) {
            $('body').on(action, SELECTORS.ACTIVEFILTERFORM + ' ' + selector, callable);
        };

        /**
         * Find the reportid for the given formWrapper
         * @param {$} formWrapper element that matches SELECTORS.ACTIVEFILTERFORM
         * @return {$}
         */
        FiltersManager.prototype.getReportId = function(formWrapper) {
            return formWrapper.closest('[data-reportid]').data('reportid');
        };

        /**
         * Find the table for the given formWrapper
         * @param {$} formWrapper element that matches SELECTORS.ACTIVEFILTERFORM
         * @return {$}
         */
        FiltersManager.prototype.getTable = function(formWrapper) {
            return formWrapper.closest('[data-reportid]').find(SELECTORS.TABLEREGION);
        };

        /**
         *
         * @private
         */
        FiltersManager.prototype.formHandler = function() {
            var form = new AjaxForm('.active_filters_form_wrapper', 'tool_reportbuilder\\form\\filters');
            form.onSubmitSuccess = function(data, container) {
                Helper.triggerEvent(this.getTable(container), Events.RELOADTABLEWITHOUTPAGINATION);
                this.reloadForm(container);
            }.bind(this);

            this.addListeneresToFormElements('.active_filters_form_wrapper');
        };

        /**
         * Reset all filter for the given report and the current user.
         * Call the WS and reload the filters with fragment.
         * Trigger the event to reload the main table with the data without filter
         *
         * @param {event} e The jquery event
         * @param {object} data Additional event data
         * @return {Promise}
         */
        FiltersManager.prototype.resetAll = function(e, data) {
            data.originalEvent.preventDefault();
            data.originalEvent.stopPropagation();
            var formWrapper = $(e.currentTarget).closest(SELECTORS.ACTIVEFILTERFORM);
            return Templates.render(TEMPLATES.LOADING, {visible: true}, '').then(function(html, js) {
                    Templates.appendNodeContents(formWrapper,
                        html, js);
                var promises = Ajax.call([
                    {
                        methodname: SERVICES.RESETALL,
                        args: {
                            reportid: formWrapper.closest('[data-reportid]').data('reportid'),
                        }
                    }
                ]);
                return promises[0];
            }).then(function() {
                this.reloadForm(formWrapper);
                Helper.triggerEvent(this.getTable(formWrapper), Events.RELOADTABLEWITHOUTPAGINATION);
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Reset the given filter for the current user.
         *
         * @param {event} e The jquery event
         * @param {object} data Additional event data
         * @return {Promise}
         */
        FiltersManager.prototype.resetFilter = function(e, data) {
            data.originalEvent.preventDefault();
            data.originalEvent.stopPropagation();
            var formWrapper = $(e.currentTarget).closest(SELECTORS.ACTIVEFILTERFORM);
            var filterid = $(e.currentTarget).data('id');
            return Templates.render(TEMPLATES.LOADING, {visible: true}, '').then(function(html, js) {
                Templates.appendNodeContents(formWrapper,
                    html, js);
                var promises = Ajax.call([
                    {
                        methodname: SERVICES.RESETFILTER,
                        args: {
                            reportid: formWrapper.closest('[data-reportid]').data('reportid'),
                            filterid: filterid
                        }
                    }
                ]);
                return promises[0].fail(Notification.exception);
            }).then(function() {
                this.reloadForm(formWrapper);
                Helper.triggerEvent(this.getTable(formWrapper), Events.RELOADTABLEWITHOUTPAGINATION);
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Reload the form with the new status of the filters.
         * @param {$} formWrapper
         */
        FiltersManager.prototype.reloadForm = function(formWrapper) {
            var params = {reportid: formWrapper.closest('[data-reportid]').data('reportid')};
            Fragment.loadFragment('tool_reportbuilder', 'filters_form', Config.contextid, params).done(function(html, js) {
                Templates.replaceNodeContents(formWrapper, html, js);
                this.updateActiveCount(null, formWrapper);
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Update the number of active filters.
         * @param {*} _
         * @param {Node} el
         */
        FiltersManager.prototype.updateActiveCount = function(_, el) {
            var formWrapper = $(el);
            var badge = formWrapper.closest('[data-reportid]').find('.js-filters-active');
            var countactive = formWrapper.find("div[data-active='1']").length;
            badge.text(countactive);
            if (countactive > 0) {
                badge.removeClass('invisible');
            } else {
                badge.addClass('invisible');
            }
        };

        /**
         *
         * @param {$} formWrapper
         * @private
         */
        FiltersManager.prototype.addListeneresToFormElements = function(formWrapper) {
            var listener = function(ev) {
                M.util.js_pending('tool_reportbuilder_submit_form');
                setTimeout(function() {
                    $(ev.currentTarget).closest('form').submit();
                    M.util.js_complete('tool_reportbuilder_submit_form');
                }, 100);
            };
            $('body')
                .on('change', formWrapper + ' form select', listener)
                .on('change', formWrapper + ' form input:not([type=hidden])', listener);
        };

        // Return singleton.
        return new FiltersManager();
    });

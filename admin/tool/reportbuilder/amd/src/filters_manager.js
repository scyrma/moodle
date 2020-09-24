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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Javascript manager for filters.
 *
 * @module     tool_reportbuilder/filters_manager
 * @class      FiltersManager
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
            FILTERSBUTTON: '#reportbuilder-filters-collapse',
            RESETFILTER: "[data-action='reset-filter']",
            SIDEBARFILTERS: "[data-region='sidebar-filters']",
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

            $('body')
                .on(Events.RELOADFILTERSFORM, SELECTORS.TABLEREGION, (e, formWrapper) => {
                    this.reloadForm(formWrapper);
                })
                .delegate(SELECTORS.SIDEBARFILTERS, 'show.bs.collapse', function() {
                    $(SELECTORS.FILTERSBUTTON).removeClass('btn-outline-secondary').addClass('btn-secondary');
                })
                .delegate(SELECTORS.SIDEBARFILTERS, 'hide.bs.collapse', function() {
                    $(SELECTORS.FILTERSBUTTON).removeClass('btn-outline').addClass('btn-outline-secondary');
                });
        };

        /**
         * Reset filters handlers
         */
        FiltersManager.prototype.resetHandlers = function() {
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
         * @return {Promise}
         */
        FiltersManager.prototype.resetAll = function() {
            var formWrapper = $(SELECTORS.ACTIVEFILTERFORM);
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
            var report = formWrapper.closest('[data-reportid]');
            var params = {
                reportid: report.data('reportid'),
                parameters: report.find('[data-parameters]').attr('data-parameters'),
            };

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
            var counter = formWrapper.closest('[data-reportid]').find('.js-filters-active');
            var countactive = formWrapper.find("div[data-active='1']").length;
            counter.text('(' + countactive + ')');
            if (countactive > 0) {
                counter.removeClass('d-none');
            } else {
                counter.addClass('d-none');
            }
        };

        /**
         * Create event listeners for form filter elements
         *
         * @param {$} formWrapper
         * @private
         */
        FiltersManager.prototype.addListeneresToFormElements = function(formWrapper) {
            var handleFormSubmission = function(event) {
                var pending = 'tool_reportbuilder_filters_manager_form_submission';

                M.util.js_pending(pending);
                event.preventDefault();
                event.stopPropagation();
                $(event.currentTarget).closest('form').submit();
                M.util.js_complete(pending);
            };

            $('body')
                // Filter form elements other than hidden and text inputs should auto-submit when changed.
                .on('change', formWrapper + ' form select,' + formWrapper + ' form input:not([type=hidden],[type=text])',
                    handleFormSubmission)

                // Hitting return in a text input can trigger the "reset filter" button - we don't want that.
                .on('keypress', formWrapper + ' form input[type=text]', function(event) {
                    if (event.keyCode === 13) {
                        handleFormSubmission(event);
                    }
                });
        };

        // Return singleton.
        return new FiltersManager();
    });

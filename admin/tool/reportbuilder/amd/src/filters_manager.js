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
 * @module     tool_reportbuilder/filters_manager
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
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
        'core_form/dynamicform'
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
        DynamicForm
    ) {

        "use strict";

        /** @type {Object} The list of selectors for the message area. */
        var
        SELECTORS = {
            FILTERSBUTTON: '.reportbuilder-filters-collapse',
            RESETFILTER: "[data-action='reset-filter']",
            SIDEBARFILTERS: "[data-region='sidebar-filters']",
            ACTIVEFILTERFORM: "[data-region='active-filters-form']",
            TABLEREGION: "[data-region='data-report']",
            SHOWEDIT: "[data-action='show-edit']"
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

            $('body')
                .on(Events.RELOADFILTERSFORM, SELECTORS.TABLEREGION, (e, formWrapper) => {
                    this.reloadForm(formWrapper);
                })
                .on(Events.FILTERSFORMADDED, SELECTORS.ACTIVEFILTERFORM, (ev) => this._registerFilterForm(ev))
                .delegate(SELECTORS.SIDEBARFILTERS, 'show.bs.collapse', function(e) {
                    $(`${SELECTORS.FILTERSBUTTON}[data-target="#${$(e.target).attr('id')}"`)
                        .removeClass('btn-outline-secondary').addClass('btn-secondary');
                })
                .delegate(SELECTORS.SIDEBARFILTERS, 'hide.bs.collapse', function(e) {
                    $(`${SELECTORS.FILTERSBUTTON}[data-target="#${$(e.target).attr('id')}"`)
                        .removeClass('btn-outline').addClass('btn-outline-secondary');
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
         * Register and handle events listener based on filter array attributes and callback.
         *
         * @param {Array.<object>} filtersAttr - Array of filter attributes to be handle.
         * filtersAttr.selector - Filter selector to be listen.
         * filtersAttr.eventlisten - Event to listen when filter is trigger.
         * filtersAttr.root - Region to find above selector.
         * filtersAttr.capture (optional) - Boolean to specify the propagation type used,
         * by default is false that means use bubbling, if true then use capturing.
         * @param {Function} callback - Function to be executed when conditions is done.
         */
        FiltersManager.prototype.onDelegateRootEvent = function(filtersAttr, callback) {
            filtersAttr.forEach((value) => {
                // Only trigger click event on td element inside YUI calendar.
                if (value.root === '#dateselector-calendar-panel') {
                    // Delegate YUI event listener to value.selector on value.root region.
                    let calendar = Y.delegate(value.eventlisten, callback, value.root, value.selector);
                    // Set listener to clean previously subscribed events when change to edit mode.
                    const editButton = document.querySelector(SELECTORS.SHOWEDIT);
                    if (editButton !== null) {
                        editButton.addEventListener(value.eventlisten, () => {
                            // Get previous YUI node, and detaching subscriptions.
                            calendar.detach();
                        });
                    }
                } else {
                    value.root.addEventListener(value.eventlisten, (event) => {
                        // Set current element on event.
                        const targetElement = event.target;
                        // Check if current element on event is some of selectors set in array or is hitting return key.
                        if (targetElement.closest(value.selector)
                            && (event.keyCode === 13 || typeof event.keyCode === "undefined")) {
                            callback(event);
                        }
                    }, value.capture ?? false);
                }
            });
        };

        /**
         * Called when a filter form was added to the page
         *
         * @param {Event} ev
         * @private
         */
        FiltersManager.prototype._registerFilterForm = function(ev) {
            let container = ev.target;
            let form = new DynamicForm(container, 'tool_reportbuilder\\form\\filters');
            form.addEventListener(form.events.FORM_SUBMITTED, event => {
                event.preventDefault();
                Helper.triggerEvent(this.getTable($(container)), Events.RELOADTABLEWITHOUTPAGINATION);
                this.reloadForm(container);
            });
            var handleFormSubmission = (event) => {
                var pending = 'tool_reportbuilder_filters_manager_form_submission';

                M.util.js_pending(pending);
                event.preventDefault();
                event.stopPropagation();
                form.submitFormAjax();
                M.util.js_complete(pending);
            };
            // Array of filters attributes to handle expected action.
            const filtersAttr = [
                {
                    selector: 'form select, form input:not([type=hidden]):not([type=text])',
                    eventlisten: 'change',
                    root: form.container
                },
                {
                    selector: '[data-fieldtype="autocomplete"] > select.custom-select',
                    eventlisten: 'change',
                    root: form.container,
                    capture: true
                },
                {
                    selector: '.yui3-calendar-day',
                    eventlisten: 'click',
                    root: '#dateselector-calendar-panel'
                },
                {
                    selector: 'form input[type=text]',
                    eventlisten: 'keypress',
                    root: form.container
                }
            ];
            this.onDelegateRootEvent(filtersAttr, handleFormSubmission);
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
                this.reloadForm(formWrapper[0]);
                Helper.triggerEvent(this.getTable(formWrapper), Events.RELOADTABLEWITHOUTPAGINATION);
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Reload the form with the new status of the filters.
         * @param {Element} formWrapper
         */
        FiltersManager.prototype.reloadForm = function(formWrapper) {
            var report = $(formWrapper).closest('[data-reportid]');
            var params = {
                reportid: report.data('reportid'),
                parameters: report.find('[data-parameters]').attr('data-parameters'),
            };

            // TODO why don't we just call DynamicForm.load(params) ?
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

        // Return singleton.
        return new FiltersManager();
    });

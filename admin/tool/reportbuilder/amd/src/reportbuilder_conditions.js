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
 * The module handles any actions we perform on the reportbuilder conditions.
 *
 * @module     tool_reportbuilder/reportbuilder_conditions
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define(
    [
        'jquery',
        'core/ajax',
        'core/templates',
        'core/notification',
        'tool_wp/notification',
        'core/custom_interaction_events',
        'core/sortable_list',
        'core/modal_factory',
        'core/modal_events',
        'core/str',
        'tool_reportbuilder/reportbuilder_events',
        'tool_reportbuilder/reportbuilder_helper',
        'core_form/dynamicform',
        'tool_wp/helper'
    ],
    function(
        $,
        Ajax,
        Templates,
        Notification,
        WpNotification,
        CustomEvents,
        SortableList,
        ModalFactory,
        ModalEvents,
        Str,
        Events,
        Helper,
        DynamicForm,
        WpHelper
    ) {

        "use strict";

        var SELECTORS = {
            CONDITIONSSREGION: "[data-region='report-conditions']",
            ACTIVECONDITIONS: "[data-region='active-conditions']",
            AVAILABLECONDITIONS: "[data-region='available-conditions']",
            ADDFIELDBTN: '.js-add-condition-btn',
            ADDCONDITIONSELECT: ".js-add-condition-select",
            CONDITIONSSELECT: "[data-region='condition-select']",
            DELETECONDITION: "[data-action='delete-condition']",
            LOADING: ".overlay-icon-container",
            RESETALL: "[data-action='reset-all']",
            RESETFILTER: "[data-action='reset-filter']"
        },
            SERVICES = {
                ADDCONDITION: "tool_reportbuilder_add_report_condition",
                DELETECONDITION: "tool_reportbuilder_delete_condition",
                RESETALLCONDITIONS: "tool_reportbuilder_reset_all_conditions",
                RESETCONDITION: "tool_reportbuilder_reset_condition",
        },
            TEMPLATES = {
                LOADING: 'core/overlay_loading',
                ACTIVECONDITIONS: 'tool_reportbuilder/report_active_conditions',
                AVAILABLECONDITIONS: 'tool_reportbuilder/report_available_conditions'
        },
            ACTIONS = {
                DELETE: '[data-action="delete-condition"]'
        };

        /**
         * Actions class.
         *
         * @param {reportBuilder} reportBuilder The reportBuilder area object.
         */
        function Conditions(reportBuilder) {
            this.reportBuilder = reportBuilder;
            this._init();
        }

        /** @type {reportBuilder} The reportBuilder area object. */
        Conditions.prototype.reportBuilder = null;

        Conditions.prototype._init = function() {
            this.reportBuilder.onDelegateEvent('change', SELECTORS.CONDITIONSSREGION + ' ' + SELECTORS.ADDCONDITIONSELECT,
                this._addCondition.bind(this));
            this.onDelegateEvent(CustomEvents.events.activate, SELECTORS.RESETALL, this.resetAll.bind(this));
            this.onDelegateEvent(CustomEvents.events.activate, SELECTORS.RESETFILTER, this.resetFilter.bind(this));
            this.reportBuilder.onDelegateEvent('click',
                SELECTORS.CONDITIONSSREGION + ' ' + SELECTORS.ACTIVECONDITIONS + ' ' + ACTIONS.DELETE,
                this.removeConditionHandler.bind(this));
            this.formHandler();
        };

        /**
         * Handles adding a delegate event to the report builder area node.
         *
         * @param {String} action The action we are listening for
         * @param {String} selector The selector for the page we are assigning the action to
         * @param {Function} callable The function to call when the event happens
         */
        Conditions.prototype.onDelegateEvent = function(action, selector, callable) {
            this.reportBuilder.onDelegateEvent(action, SELECTORS.CONDITIONSSREGION + ' ' + selector, callable);
        };

        Conditions.prototype.formHandler = function() {
            var formwrapper = '.tool_reportbuilder_report_active_conditions';
            let container = document.querySelector(formwrapper);
            if (container) {
                var form = new DynamicForm(container, 'tool_reportbuilder\\form\\conditions');
                form.addEventListener(form.events.FORM_SUBMITTED,
                    event => {
                        event.preventDefault();
                        this.reportBuilder.trigger(Events.RELOADTABLEWITHOUTPAGINATION);
                    });

                var listener = () => form.submitFormAjax();
                $(container)
                    .on('change', 'form select', listener)
                    .on('change', 'form input:not([type=hidden])', listener);
            }
        };

        /**
         * Reset all report conditions after confirmation.
         *
         * @param {event} e The jquery event
         * @param {object} data Additional event data
         */
        Conditions.prototype.resetAll = function(e, data) {
            data.originalEvent.preventDefault();
            var doReset = function() {
                var formWrapper = this.reportBuilder.find(SELECTORS.ACTIVECONDITIONS);
                return Templates.render(TEMPLATES.LOADING, {visible: true}, '').then(function(html, js) {
                    Templates.appendNodeContents(formWrapper,
                        html, js);
                    M.util.js_pending('tool_reportbuilder_reset_all'); // Tell Behat to wait.
                    var promises = Ajax.call([
                        {
                            methodname: SERVICES.RESETALLCONDITIONS,
                            args: {
                                reportid: this.reportBuilder.getReportId(),
                            }
                        }
                    ]);
                    return promises[0];
                }.bind(this)).then(function(data) {
                    this.reportBuilder.trigger(Events.RELOADTABLEWITHOUTPAGINATION);
                    this._reloadSelectedConditions(data);
                    M.util.js_complete('tool_reportbuilder_reset_all'); // Tell Behat to wait.
                }.bind(this)).fail(Notification.exception);
            }.bind(this);

            Str.get_strings([
                {key: 'confirm', component: 'tool_reportbuilder'},
                {key: 'confirmresetallconditions', component: 'tool_reportbuilder'},
                {key: 'resetall', component: 'tool_reportbuilder'},
                {key: 'cancel', component: 'moodle'}
            ]).done(function(strings) {
                Notification.confirm(
                    strings[0], // Confirm.
                    strings[1], // Confirmation text.
                    strings[2], // Save button.
                    strings[3], // Cancel.
                    doReset
                );
            }).fail(Notification.exception);
        };

        /**
         * Reset a report condition afteer confirmation.
         *
         * @param {event} e The jquery event
         * @param {object} data Additional event data
         */
        Conditions.prototype.resetFilter = function(e, data) {
            data.originalEvent.preventDefault();
            var element = $(e.currentTarget);
            var id = element.data('id');
            var field = element.data('field');
            var doReset = function() {
                var formWrapper = this.reportBuilder.find(SELECTORS.ACTIVECONDITIONS);
                return Templates.render(TEMPLATES.LOADING, {visible: true}, '').then(function(html, js) {
                    Templates.appendNodeContents(formWrapper,
                        html, js);
                    M.util.js_pending('tool_reportbuilder_reset'); // Tell Behat to wait.
                    var promises = Ajax.call([
                        {
                            methodname: SERVICES.RESETCONDITION,
                            args: {
                                reportid: this.reportBuilder.getReportId(),
                                conditionid: id,
                            }
                        }
                    ]);
                    return promises[0];
                }.bind(this)).then(function(data) {
                    this.reportBuilder.trigger(Events.RELOADTABLEWITHOUTPAGINATION);
                    this._reloadSelectedConditions(data);
                    M.util.js_complete('tool_reportbuilder_reset'); // Tell Behat to wait.
                }.bind(this)).fail(Notification.exception);
            }.bind(this);

            Str.get_strings([
                {key: 'confirm', component: 'tool_reportbuilder'},
                {key: 'confirmresetconditions', component: 'tool_reportbuilder', param: field},
                {key: 'resetcondition', component: 'tool_reportbuilder'},
                {key: 'cancel', component: 'moodle'}
            ]).done(function(strings) {
                Notification.confirm(
                    strings[0], // Confirm.
                    strings[1], // Confirmation text.
                    strings[2], // Save button.
                    strings[3], // Cancel.
                    doReset
                );
            }).fail(Notification.exception);
        };

        /**
         * Event listener for deleting condition
         *
         * @param {Event} e
         */
        Conditions.prototype.removeConditionHandler = function(e) {
            e.preventDefault();
            var element = $(e.currentTarget);
            var id = element.data('id');
            var conditionname = element.data('name');
            var doDelete = function() {
                var request = {
                    methodname: SERVICES.DELETECONDITION,
                    args: {conditionid: id}
                };

                M.util.js_pending('tool_reportbuilder_delete_condition'); // Tell Behat to wait.
                Ajax.call([request])[0].done(function(data) {
                    if (data) {
                        Str.get_string('removeconditionsuccess', 'tool_reportbuilder', conditionname).then(function(message) {
                            this.reportBuilder.trigger(Events.RELOADTABLEWITHOUTPAGINATION);
                            this._reloadSelectedConditions(data);
                            this._reloadAvailableConditions(data);
                            WpNotification.addNotification({
                                message: message,
                                type: 'success'
                            });
                            M.util.js_complete('tool_reportbuilder_delete_condition');
                        }.bind(this)).fail(Notification.exception);
                    }
                    return null;
                }.bind(this)).fail(Notification.exception);
            }.bind(this);

            Str.get_strings([
                {key: 'confirm', component: 'tool_reportbuilder'},
                {key: 'confirmdeletecondition', component: 'tool_reportbuilder', param: conditionname},
                {key: 'delete', component: 'moodle'},
                {key: 'cancel', component: 'moodle'}
            ]).done(function(strings) {
                Notification.confirm(
                    strings[0], // Confirm.
                    strings[1], // Confirmation text.
                    strings[2], // Save button.
                    strings[3], // Cancel.
                    doDelete
                );
            }).fail(Notification.exception);
        };

        /**
         * Add a condition.
         *
         * @param {Event} e
         * @private
         */
        Conditions.prototype._addCondition = function(e) {
            var selected = $(e.currentTarget).find(":selected").val();
            if (!selected || selected === '0') {
                return;
            }
            $(e.currentTarget).find(":selected").remove();

            M.util.js_pending('tool_reportbuilder_add_condition'); // Tell Behat to wait.
            Templates.render(TEMPLATES.LOADING, {visible: true}).then(function(html, js) {
                if (!this._isLoading) {
                    Templates.appendNodeContents(this.reportBuilder.find(SELECTORS.ACTIVECONDITIONS),
                        html, js);
                }
                var promises = Ajax.call([
                    {
                        methodname: SERVICES.ADDCONDITION,
                        args: {
                            reportid: this.reportBuilder.getReportId(),
                            conditionkey: selected
                        }
                    }
                ]);

                return promises[0].fail(Notification.exception);
            }.bind(this)).then(function(data) {
                this._reloadSelectedConditions(data);
                M.util.js_complete('tool_reportbuilder_add_condition');
            }.bind(this)).fail(Notification.exception);
        };

        /**
         * Reload the selected conditions region with the selected condition.
         * @param {Object} context
         * @private
         */
        Conditions.prototype._reloadSelectedConditions = function(context) {
            M.util.js_pending('tool_reportbuilder_reload_conditions'); // Tell Behat to wait.
            Templates.render(TEMPLATES.ACTIVECONDITIONS, context)
                .then((html) => {
                    return Helper.niceReplaceNodeContents(this.reportBuilder.find(SELECTORS.ACTIVECONDITIONS),
                        html, WpHelper.processCollectedJavascript(context.javascript));
                })
                .then(() => {
                    this.formHandler();
                    M.util.js_complete('tool_reportbuilder_reload_conditions');
                    return null;
                })
                .fail(Notification.exception);
        };

        /**
         * Reload the available conditions region with the available condition.
         * @param {Object} context
         * @private
         */
        Conditions.prototype._reloadAvailableConditions = function(context) {
            M.util.js_pending('tool_reportbuilder_reload_avconditions'); // Tell Behat to wait.
            context.show = this.reportBuilder.find(SELECTORS.FILTERSELECT).hasClass('show'); // TODO constant undefined.
            Templates.render(TEMPLATES.AVAILABLECONDITIONS, context)
                .then(function(html, js) {
                    return Helper.niceReplaceNodeContents(this.reportBuilder.find(SELECTORS.AVAILABLECONDITIONS),
                        html, js);
                }.bind(this))
                .then(function() {
                    M.util.js_complete('tool_reportbuilder_reload_avconditions');
                    return null;
                })
                .fail(Notification.exception);
        };

        return Conditions;

    });

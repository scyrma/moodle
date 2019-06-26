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
 * The module handles any actions we perform on the reportbuilder conditions.
 *
 * @module     tool_reportbuilder/reportbuilder_conditions
 * @package    tool_reportbuilder
 * @copyright  2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    [
        'jquery',
        'core/ajax',
        'core/templates',
        'tool_wp/notification',
        'core/custom_interaction_events',
        'core/sortable_list',
        'core/modal_factory',
        'core/modal_events',
        'core/str',
        'tool_reportbuilder/reportbuilder_events',
        'tool_reportbuilder/reportbuilder_helper',
        'tool_wp/ajax_form'
    ],
    function(
        $,
        Ajax,
        Templates,
        Notification,
        CustomEvents,
        SortableList,
        ModalFactory,
        ModalEvents,
        Str,
        Events,
        Helper,
        AjaxForm
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
            this.removeConditionHandler();
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
            var form = new AjaxForm(formwrapper, 'tool_reportbuilder\\form\\conditions');
            form.onSubmitSuccess = function() {
                this.reportBuilder.trigger(Events.RELOADTABLE);
            }.bind(this);

            var listener = function(e) {
                $(e.currentTarget).closest('form').submit();
            };
            $('body')
                .on('change', formwrapper + ' form select', listener)
                .on('change', formwrapper + ' form input:not([type=hidden])', listener);
        };

        /**
         * Reset all report conditions after confirmation.
         *
         * @param {event} e The jquery event
         * @param {object} data Additional event data
         * @return {Promise}
         */
        Conditions.prototype.resetAll = function(e, data) {
            data.originalEvent.preventDefault();
            return ModalFactory.create({
                title: Str.get_string('resetalltitle', 'tool_reportbuilder'),
                body: Str.get_string('confirmresetallconditions', 'tool_reportbuilder'),
                type: ModalFactory.types.SAVE_CANCEL
            }).then(function(modal) {
                modal.setSaveButtonText(Str.get_string('resetall', 'tool_reportbuilder'));
                modal.show();
                modal.getRoot().on(ModalEvents.save, function() {
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
                        this.reportBuilder.trigger(Events.RELOADTABLE);
                        this._reloadSelectedConditions(data);
                        M.util.js_complete('tool_reportbuilder_reset_all'); // Tell Behat to wait.
                    }.bind(this)).fail(Notification.exception);
                }.bind(this));

                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });

                return modal;
            }.bind(this));
        };

        /**
         * Reset a report condition afteer confirmation.
         *
         * @param {event} e The jquery event
         * @param {object} data Additional event data
         * @return {Promise}
         */
        Conditions.prototype.resetFilter = function(e, data) {
            data.originalEvent.preventDefault();
            var element = $(e.currentTarget);
            var id = element.data('id');
            var field = element.data('field');
            return ModalFactory.create({
                title: Str.get_string('resetcondition', 'tool_reportbuilder'),
                body: Str.get_string('confirmresetconditions', 'tool_reportbuilder', field),
                type: ModalFactory.types.SAVE_CANCEL
            }).then(function(modal) {
                modal.setSaveButtonText(Str.get_string('resetcondition', 'tool_reportbuilder'));
                modal.show();
                modal.getRoot().on(ModalEvents.save, function() {
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
                        this.reportBuilder.trigger(Events.RELOADTABLE);
                        this._reloadSelectedConditions(data);
                        M.util.js_complete('tool_reportbuilder_reset'); // Tell Behat to wait.
                    }.bind(this)).fail(Notification.exception);
                }.bind(this));

                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });

                return modal;
            }.bind(this));
        };

        /**
         * Register event listeners.
         */
        Conditions.prototype.removeConditionHandler = function() {
            var selector = SELECTORS.CONDITIONSSREGION + ' ' + SELECTORS.ACTIVECONDITIONS + ' ' + ACTIONS.DELETE;

            this.reportBuilder.onDelegateEvent('click', selector, function(e) {
                e.preventDefault();
                var element = $(e.currentTarget);
                var id = element.data('id');
                var conditionname = element.data('name');
                var stringkeys = [
                {
                    key: 'confirm',
                },
                {
                    key: 'confirmdeletecondition',
                    component: 'tool_reportbuilder',
                    param: conditionname
                },
                {
                    key: 'removeconditionsuccess',
                    component: 'tool_reportbuilder',
                    param: conditionname
                },
                {
                    key: 'delete'
                }
                ];

                Str.get_strings(stringkeys).then(function(langStrings) {
                    var title = langStrings[0];
                    var confirmMessage = langStrings[1];
                    var buttonText = langStrings[3];
                    return ModalFactory.create({
                        title: title,
                        body: confirmMessage,
                        type: ModalFactory.types.SAVE_CANCEL
                    }).then(function(modal) {
                        modal.setSaveButtonText(buttonText);
                        // Handle save event.
                        modal.getRoot().on(ModalEvents.save, function() {
                            var request = {
                                methodname: SERVICES.DELETECONDITION,
                                args: {conditionid: id}
                            };

                            M.util.js_pending('tool_reportbuilder_delete_condition'); // Tell Behat to wait.
                            Ajax.call([request])[0].done(function(data) {
                                if (data) {
                                    this.reportBuilder.trigger(Events.RELOADTABLE);
                                    this._reloadSelectedConditions(data);
                                    this._reloadAvailableConditions(data);
                                    Notification.addNotification({
                                        message: langStrings[2],
                                        type: 'success'
                                    });
                                    M.util.js_complete('tool_reportbuilder_delete_condition');
                                }
                            }.bind(this)).fail(Notification.exception);
                        }.bind(this));

                        modal.getRoot().on(ModalEvents.hidden, function() {
                            modal.destroy();
                        });

                        return modal;
                    }.bind(this));
                }.bind(this)).done(function(modal) {
                    modal.show();
                }).fail(Notification.exception);
            }.bind(this));
        };

        /**
         * Add a filter.
         *
         * @param {Event} e
         * @private
         */
        Conditions.prototype._addCondition = function(e) {
            M.util.js_pending('tool_reportbuilder_add_condition'); // Tell Behat to wait.
            var selected = $(e.currentTarget).find(":selected").val();
            $(e.currentTarget).find(":selected").remove();

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
            }.bind(this)).fail(function(ex) {
                Notification.exception(ex);
            }).then(function(data) {
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
                .then(function(html, js) {
                    return Helper.niceReplaceNodeContents(this.reportBuilder.find(SELECTORS.ACTIVECONDITIONS),
                        html, js);
                }.bind(this))
                .then(function() {
                    M.util.js_complete('tool_reportbuilder_reload_conditions');
                    return null;
                })
                .fail(Notification.exception);
        };

        /**
         * Reload the selected conditions region with the selected condition.
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
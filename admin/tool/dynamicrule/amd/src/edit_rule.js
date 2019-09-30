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
 * This module instantiates the functionality for dynamic rule editing.
 *
 * @module     tool_dynamicrule/edit_rule
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'tool_wp/tabs',
    'tool_wp/modal_form',
    'core/templates',
    'core/ajax',
    'core/notification',
    'core/fragment',
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'tool_wp/ajax_form'],
function($, Tabs, ModalForm, Templates, Ajax, Notification, Fragment, Str, ModalFactory, ModalEvents, AjaxForm) {

    /**
     * List of selectors.
     */
    var SELECTORS = {
        CONDITIONS_MENU_ITEMS: '#conditions-menu .list-group-item[data-configclass]',
        CONDITIONS_CONTAINER: '#conditions-container',
        EDITDETAILS: "[data-action='editdetails']",
        ENABLE_BUTTON: '[data-action="enablerule"]',
        OUTCOMES_MENU_ITEMS: '#outcomes-menu .list-group-item[data-configclass]',
        OUTCOMES_CONTAINER: '#outcomes-container',
        MATCHING_USERS_COUNTER: '#matching-users-counter',
        MATCHING_USERS_LIST: '#viewmatchingusers',
        SCROLLER: '#scroller',
        SCROLLER_INNER: '#scroller-inner',
        EMPTY_MESSAGE: '[data-region=empty-message]',
        NOT_SAVED_LABEL: '#form-instance-notsaved-label'
    };

    var editRule = {

        /** @var {Number} ruleId ID of the rule. */
        ruleId: 0,

        /**
         * Removes the node with some visual effect.
         *
         * @param {$} node to remove.
         * @param {String} type of instance (condition or outcome)
         */
        removeCardNode: function(node, type) {
            node.fadeTo("fast", "0", function() {
                node.slideUp("fast", function() {
                    node.remove();
                    if ($('#' + type + 's-container .instance-card').length == 0) {
                        $(SELECTORS.EMPTY_MESSAGE).removeClass('hidden');
                    }
                });
            });
        },

        /**
         * Helper to return container selector.
         *
         * @param {String} type of instance (condition or outcome)
         * @return {String} selector
         */
        getContainerSelectorByType: function(type) {
            if (type === 'condition') {
                return SELECTORS.CONDITIONS_CONTAINER;
            }
            return SELECTORS.OUTCOMES_CONTAINER;
        },

        /**
         * Add configuration instance form.
         *
         * @param {$} menuItemNode item element node
         * @param {String} type of instance (condition or outcome)
         */
        addInstanceForm: function(menuItemNode, type) {
            var params = {
                instanceclass: menuItemNode.data('configclass'),
                ruleid: editRule.ruleId,
            };
            var strings = Str.get_strings([
                {'key': 'delete' + type, component: 'tool_dynamicrule'},
                {'key': 'edit' + type, component: 'tool_dynamicrule'},
                {'key': type + 'notsaved', component: 'tool_dynamicrule'},
            ]);
            var container = $(editRule.getContainerSelectorByType(type)),
            wrapper = '',
            notsavedlabel = '';

            $.when(strings).then(function(s) {
                // Put together context and render the template.
                params.title = menuItemNode.prop('title');
                params.form = '';
                params.elementid = Math.random().toString().substr(2, 10);
                params.instanceid = 0;
                params.deletelabel = s[0];
                params.editlabel = s[1];
                params.notsavedlabel = s[2];

                return Templates.render('tool_dynamicrule/form_instance', params);
            })
            .then(function(html) {
                var cardNode = $(html);
                container.append(cardNode);
                wrapper = cardNode.find('.form-container');
                // Store label as form will overwrite wrapper content.
                notsavedlabel = wrapper.find(SELECTORS.NOT_SAVED_LABEL);

                var form = editRule.initInstanceForm(cardNode, type);
                $(SELECTORS.EMPTY_MESSAGE).addClass('hidden');
                return form.load(params);
            })
            .then(function() {
                // Add label and show form.
                wrapper.prepend(notsavedlabel);
                wrapper.show();

                // Add scrolling if needed.
                var scroller = container.closest(SELECTORS.SCROLLER);
                var scrollerInner = container.closest(SELECTORS.SCROLLER_INNER);
                if (container.height() > scrollerInner.height()) {
                    scroller.animate({scrollTop: container.height()}, 200);
                }
                return null;
            })
            .fail(Notification.exception);
        },

        /**
         * Update matching users counter.
         */
        updateCountMatchingUsers: function() {
            Ajax.call([{
                methodname: 'tool_dynamicrule_count_matching_users',
                args: {id: editRule.ruleId},
            }])[0]
            .then(function(data) {
                Str.get_strings([
                    {key: 'countmatchingusers', component: 'tool_dynamicrule', param: data},
                ]).done(function(s) {
                    $(SELECTORS.MATCHING_USERS_COUNTER).text(s[0]);
                    return null;
                });
                return null;
            }).fail(Notification.exception);
        },

        /**
         * Show configuration instance form.
         *
         * @param {$} cardNode card node
         * @param {String} type of instance (condition or outcome)
         */
        showInstanceForm: function(cardNode, type) {
            var formdata = {
                ruleid: editRule.ruleId,
                id: cardNode.data('instanceid'),
                instanceclass: cardNode.data('instanceclass')
            };
            var wrapper = cardNode.find('.form-container');
            var form = editRule.initInstanceForm(cardNode, type);

            cardNode.find('.card-body').fadeTo("fast", "0.2");
            form.load(formdata).then(function() {
                cardNode.find('.description-container').hide();
                wrapper.show();
                cardNode.find('.card-body').fadeTo("fast", "1");
                return null;
            }).fail(Notification.exception);
        },

        /**
         * Get instance of ajax_form
         *
         * @param {$} cardNode card node
         * @param {String} type of instance (condition or outcome)
         * @return {AjaxForm}
         */
        initInstanceForm: function(cardNode, type) {
            var formClass = 'tool_dynamicrule\\form\\' + type + '_instance';
            var wrapper = cardNode.find('.form-container');
            var form = new AjaxForm(wrapper, formClass);
            form.onSubmitSuccess = function(data) {
                editRule.submitInstanceForm(cardNode, data);
            };
            form.onCancel = function() {
                editRule.cancelInstanceForm(cardNode, type);
            };

            return form;
        },

        /**
         * Submit form for given instance type.
         *
         * @param {$} cardNode node
         * @param {Object} data data returned from form process() method
         */
        submitInstanceForm: function(cardNode, data) {
            if (!cardNode.data('instanceid')) {
                // This is new instance. Set data attribute and activate edit icon.
                cardNode.data('instanceid', data.instanceid);
                cardNode.find('[data-action="edit-instance"]').removeClass('disabled');
            }
            // Replace form with a description text.
            cardNode.find('.description-container').text(data.description);
            cardNode.find('.form-container').html('');
            cardNode.find('.form-container').hide();
            cardNode.find('.description-container').show();
            cardNode.find('.card-body').fadeTo("fast", "1");
            cardNode.find('.broken').remove();
            editRule.updateCountMatchingUsers();
            editRule.updateEnableButton();
        },

        /**
         * Cancel form for given instance type.
         *
         * @param {$} cardNode card node
         * @param {String} type of instance (condition or outcome)
         */
        cancelInstanceForm: function(cardNode, type) {
            var instanceid = cardNode.data('instanceid');
            if (instanceid > 0) {
                cardNode.find('.card-body').fadeTo("fast", "0.2", function() {
                    cardNode.find('.form-container').html('');
                    cardNode.find('.form-container').hide();
                    cardNode.find('.description-container').show();
                    cardNode.find('.card-body').fadeTo("fast", "1");
                });
            } else {
                // Removing new unsaved from instance.
                editRule.removeCardNode(cardNode, type);
            }
        },

        /**
         * Delete given instance type.
         *
         * @param {$} cardNode card node
         * @param {String} type of instance (condition or outcome)
         */
        deleteInstance: function(cardNode, type) {
            var instanceid = cardNode.data('instanceid');

            if (instanceid > 0) {
                // If we have instance id, perform deletion.
                Str.get_strings([
                    {key: 'confirm', component: 'moodle'},
                    {key: 'confirmdelete' + type, component: 'tool_dynamicrule', param: cardNode.data('title')},
                    {key: 'delete', component: 'moodle'},
                    {key: 'cancel', component: 'moodle'}
                ]).done(function(s) {
                    Notification.confirm(s[0], s[1], s[2], s[3], function() {
                        Ajax.call([
                            {methodname: 'tool_dynamicrule_delete_' + type, args: {instanceid: instanceid}}
                        ])[0]
                        .then(function() {
                            // Remove the form element with a visual effect.
                            editRule.removeCardNode(cardNode, type);
                            editRule.updateCountMatchingUsers();
                            editRule.updateEnableButton();
                            return null;
                        }).fail(Notification.exception);
                    });
                    return null;
                }).fail(Notification.exception);
            } else {
                // Removing new unsaved from instance.
                editRule.removeCardNode(cardNode, type);
            }
        },

        /**
         * Show the list of matching users in a modal.
         */
        showMatchingUsers: function() {
            var keys = [
                {key: 'viewmatchingusers', component: 'tool_dynamicrule'},
            ];
            Str.get_strings(keys).then(function(s) {
                var contextId = $('.wptabs').data('contextid');
                var params = {ruleid: editRule.ruleId};

                return ModalFactory.create({
                    title: s[0],
                    body: Fragment.loadFragment('tool_dynamicrule', 'matching_users', contextId, params),
                    type: ModalFactory.types.CANCEL,
                    large: true
                });
            }).done(function(modal) {
                // Handle hidden event.
                modal.getRoot().on(ModalEvents.hidden, function() {
                    // Destroy when hidden.
                    modal.destroy();
                });

                // Show the modal!
                modal.show();
                return null;
            }).fail(Notification.exception);
        },

        /**
         * Initialise rule conditions tab.
         */
        initRuleConditions: function() {
            // Add event handler for menu item click.
            $(SELECTORS.CONDITIONS_MENU_ITEMS).on('click', function(e) {
                e.preventDefault();
                editRule.addInstanceForm($(e.currentTarget), 'condition');
            });
            // Add delegated event handler to handle delete icon press.
            $(SELECTORS.CONDITIONS_CONTAINER).on('click', '[data-action="delete-instance"]', function(e) {
                e.preventDefault();
                editRule.deleteInstance($(e.currentTarget).closest('.card'), 'condition');
            });
            // Add delegated event handler to handle edit icon press.
            $(SELECTORS.CONDITIONS_CONTAINER).on('click', '[data-action="edit-instance"]', function(e) {
                e.preventDefault();
                editRule.showInstanceForm($(e.currentTarget).closest('.card'), 'condition');
            });
            // Add delegated event handler to view list of matching users.
            $(SELECTORS.MATCHING_USERS_LIST).on('click', function(e) {
                e.preventDefault();
                editRule.showMatchingUsers();
            });
        },

        /**
         * Initialise rule outcomes tab.
         */
        initRuleOutcomes: function() {
            // Add event handler for menu item click.
            $(SELECTORS.OUTCOMES_MENU_ITEMS).on('click', function(e) {
                e.preventDefault();
                editRule.addInstanceForm($(e.currentTarget), 'outcome');
            });
            // Add delegated event handler to handle delete icon press.
            $(SELECTORS.OUTCOMES_CONTAINER).on('click', '[data-action="delete-instance"]', function(e) {
                e.preventDefault();
                editRule.deleteInstance($(e.currentTarget).closest('.card'), 'outcome');
            });
            // Add delegated event handler to handle edit icon press.
            $(SELECTORS.OUTCOMES_CONTAINER).on('click', '[data-action="edit-instance"]', function(e) {
                e.preventDefault();
                editRule.showInstanceForm($(e.currentTarget).closest('.card'), 'outcome');
            });
        },

        /**
         * Rule enable action.
         *
         * @param {Modal} modal The outcomes modal instance to be "closed" on rule enabling.
         */
        initEnableButton: function(modal) {
            $(SELECTORS.ENABLE_BUTTON).on('click', function(e) {
                e.preventDefault();
                Ajax.call([
                    {methodname: 'tool_dynamicrule_count_matching_users', args: {id: editRule.ruleId}}
                ])[0]
                .then(function(data) {
                    return Str.get_strings([
                        {key: 'confirm', component: 'moodle'},
                        {key: 'confirmenablerule', component: 'tool_dynamicrule', param: data},
                        {key: 'enable', component: 'moodle'},
                        {key: 'cancel', component: 'moodle'}
                    ]);
                }).then(function(s) {
                    Notification.confirm(s[0], s[1], s[2], s[3], function() {
                        Ajax.call([
                            {methodname: 'tool_dynamicrule_enable_rule', args: {id: editRule.ruleId}}
                        ])[0]
                        .then(function() {
                            if (typeof modal !== 'undefined') {
                                modal.hide();
                                Tabs.loadTab();
                            } else {
                                window.location.href = $(e.currentTarget).data('destination');
                            }
                            return null;
                        }).fail(Notification.exception);
                    });
                    return null;
                }).fail(Notification.exception);
            });
        },

        /**
         * Enable or disable rule enable button.
         */
        updateEnableButton: function() {
            Ajax.call([
                {methodname: 'tool_dynamicrule_can_enable_rule', args: {id: editRule.ruleId}}
            ])[0]
            .then(function(data) {
                if (data == true) {
                    $(SELECTORS.ENABLE_BUTTON).removeAttr('disabled');
                } else {
                    $(SELECTORS.ENABLE_BUTTON).attr('disabled', true);
                }
                return null;
            }).fail(Notification.exception);
        },

        /**
         * Rule edit details button handler.
         */
        initEditDetailsHandler: function() {
            $(SELECTORS.EDITDETAILS).on('click', function(e) {
                e.preventDefault();
                var ruleId = $(this).data('ruleid');
                var title = $(this).data('title');
                var modal = showDetailsModal($(e.currentTarget), ruleId, title);
                modal.onSubmitSuccess = function() {
                    window.location.reload(true);
                };
            });
        }
    };

    /**
     * Modal to edit rule details.
     *
     * @param {jQuery} triggerElement
     * @param {Number} ruleId
     * @param {String} title
     * @return {ModalForm} modal
     */
    var showDetailsModal = function(triggerElement, ruleId, title) {
        var modal = new ModalForm({
            formClass: 'tool_dynamicrule\\form\\rule_details',
            args: {id: ruleId},
            modalConfig: {title: title},
            saveButtonText: Str.get_string('save'),
            triggerElement: triggerElement,
        });
        return modal;
    };

    return /** @alias module:tool_dynamicrule/edit_rule */ {

        /**
         * Initialise the page.
         */
        init: function() {
            editRule.ruleId = $(".wptabs").data('ruleid');
            M.util.js_pending('tool_dynamicrule_edit_rule_init');
            editRule.initEnableButton();
            if (Tabs.getActiveTab() === 'ruleconditions') {
                editRule.initRuleConditions();
            } else if (Tabs.getActiveTab() === 'ruleoutcomes') {
                editRule.initRuleOutcomes();
            }
            editRule.initEditDetailsHandler();
            M.util.js_complete('tool_dynamicrule_edit_rule_init');
        },

        /**
         * Initialise outcomes configuration for modal use.
         *
         * @param {Number} ruleId
         * @param {Modal} modal, the modal to be destroyed
         */
        initRuleOutcomesModal: function(ruleId, modal) {
            editRule.ruleId = ruleId;
            M.util.js_pending('tool_dynamicrule_edit_rule_init');
            editRule.initEnableButton(modal);
            editRule.initRuleOutcomes();
            M.util.js_complete('tool_dynamicrule_edit_rule_init');
        },
    };
});

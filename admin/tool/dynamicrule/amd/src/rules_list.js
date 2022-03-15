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
 * This module instantiateas the functionality for actions on dynamic rule listing.
 *
 * @module     tool_dynamicrule/rules_list
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery',
        'core/ajax',
        'core/notification',
        'core/str',
        'core/config',
        'tool_wp/tabs',
        'tool_wp/modal_form',
        'core/modal_factory',
        'core/modal_events',
        'core/templates',
        'tool_dynamicrule/edit_rule',
        'tool_reportbuilder/reportbuilder_events'],
function($, Ajax, Notification, Str, Config, Tabs, ModalForm, ModalFactory, ModalEvents, Templates, EditRule, ReportEvents) {

    /**
     * Element selectors.
     */
    var SELECTORS = {
        REPORTCONTAINER: "[data-region='system-report'] [data-region='data-report']"
    };

    /**
     * Reload report
     *
     * @param {$} node that triggered the action.
     */
    var reloadReport = function(node) {
        var report = node.closest(SELECTORS.REPORTCONTAINER);

        report.trigger(ReportEvents.RELOADTABLEWITHOUTPAGINATION);
    };

    /**
     * Rule enable action.
     *
     * @param {Boolean} isComponentUse
     */
    var initEnableLinksHandlers = function(isComponentUse) {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=enable]', function(e) {
            e.preventDefault();
            let menuNode = $(e.currentTarget);

            if (menuNode.hasClass('disabled')) {
                // The toggle is disabled, leaving.
                return;
            }

            Ajax.call([
                {methodname: 'tool_dynamicrule_count_matched_users', args: {id: menuNode.data('id')}}
            ])[0]
            .then(function(data) {
                if (data !== 0) {
                    // Some users have matched alredy, no need to display confirmation.
                    enableRule(menuNode);
                } else if (isComponentUse) {
                    // Used in component modal, this requires to reflect a number of users in confirmation.
                    Ajax.call([
                        {methodname: 'tool_dynamicrule_count_matching_users', args: {id: menuNode.data('id')}}
                    ])[0]
                    .then(function(data) {
                        return Str.get_strings([
                            {key: 'confirm', component: 'moodle'},
                            {key: 'confirmenablecomponentrule', component: 'tool_dynamicrule', param: data},
                            {key: 'enable', component: 'moodle'},
                            {key: 'cancel', component: 'moodle'}
                        ]);
                    }).done(function(s) {
                        Notification.confirm(s[0], s[1], s[2], s[3], function() {
                            enableRule(menuNode);
                        });
                        return null;
                    }).fail(Notification.exception);
                } else {
                    // Used in main dynamic rules interface.
                    Str.get_strings([
                        {key: 'confirm', component: 'moodle'},
                        {key: 'confirmenablerule', component: 'tool_dynamicrule'},
                        {key: 'enable', component: 'moodle'},
                        {key: 'cancel', component: 'moodle'}
                    ]).done(function(s) {
                        Notification.confirm(s[0], s[1], s[2], s[3], function() {
                            enableRule(menuNode);
                        });
                        return null;
                    }).fail(Notification.exception);
                }
                return null;
            }).fail(Notification.exception);
        });
    };

    /**
     * Enable rule.
     *
     * @param {jQuery} menuNode
     */
    var enableRule = function(menuNode) {
        Ajax.call([
            {methodname: 'tool_dynamicrule_enable_rule', args: {id: menuNode.data('id')}}
        ])[0]
        .then(function() {
            // Change the action link to "Disable" and update related attributes.
            menuNode.addClass('hidden');
            menuNode.siblings('[data-action=disable]').removeClass('hidden');
            menuNode.closest('tr').removeClass('dimmed_text');
            return null;
        }).fail(Notification.exception);
    };

    /**
     * Rule disable action.
     */
    var initDisableLinksHandlers = function() {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=disable]', function(e) {
            e.preventDefault();
            var menuNode = $(e.currentTarget);
            var requests = Ajax.call([
                {methodname: 'tool_dynamicrule_disable_rule', args: {id: menuNode.data('id')}}
            ]);
            requests[0].then(function() {
                // Change the action link to "Enable" and update related attributes.
                menuNode.addClass('hidden');
                menuNode.siblings('[data-action=enable]').removeClass('hidden');
                menuNode.closest('tr').addClass('dimmed_text');
                return null;
            }).fail(Notification.exception);
        });
    };

    /**
     * Rule edit action.
     */
    var initEditContentLinksHandlers = function() {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=editcontent]', function(e) {
            e.preventDefault();
            let id = e.currentTarget.getAttribute('data-id');
            let href = e.currentTarget.getAttribute('href');
            Ajax.call([
                {methodname: 'tool_dynamicrule_count_matched_users', args: {id: id}}
            ])[0]
            .then(function(data) {
                if (data == 0) {
                    window.location.href = href;
                    return null;
                } else {
                    Str.get_strings([
                        {key: 'confirm', component: 'moodle'},
                        {key: 'confirmeditrule', component: 'tool_dynamicrule'},
                        {key: 'editanyway', component: 'tool_dynamicrule'},
                        {key: 'cancel', component: 'moodle'}
                    ]).done(function(s) {
                        Notification.confirm(s[0], s[1], s[2], s[3], function() {
                            window.location.href = href + '#ruleoutcomes';
                        });
                        return null;
                    });
                }
                return null;
            }).fail(Notification.exception);
        });
    };

    /**
     * Limit reached alert.
     *
     * @param {jQuery} triggerElement
     */
    var limitReachedAlert = function(triggerElement) {
        var limit = Number(triggerElement.data('limitreached'));
        var langkey = limit > 0 ? 'limitreachednumdescr' : 'limitreacheddescr';
        Str.get_strings([
            {key: 'limitreached', component: 'tool_dynamicrule'},
            {key: langkey, component: 'tool_dynamicrule', param: limit},
            {key: 'ok'},
        ]).done(function(s) {
            Notification.alert(s[0], s[1], s[2]);
            return null;
        }).fail(Notification.exception);
    };

    /**
     * Rule edit details action.
     */
    var initEditDetailsLinksHandlers = function() {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=editdetails]', function(e) {
            e.preventDefault();
            var ruleId = $(this).data('id');
            var title = $(this).attr('title');
            var modal = showDetailsModal($(e.currentTarget), ruleId, title);
            modal.onSubmitSuccess = function() {
                Tabs.loadTab();
            };
        });
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
            contextId: Config.contextid,
            saveButtonText: Str.get_string('save'),
            triggerElement: triggerElement,
        });
        return modal;
    };

    /**
     * Rule edit actions (outcomes) modal action.
     */
    var initEditActionsLinksHandlers = function() {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=editactions]', function(e) {
            e.preventDefault();
            var ruleId = $(this).data('id');
            var title = $(this).attr('title');

            // Show actions configuring interface.
            showActionsModal($(e.currentTarget), ruleId, title);
        });
    };

    /**
     * Modal to edit actions details.
     *
     * @param {jQuery} triggerElement
     * @param {Number} ruleId
     * @param {String} title
     */
    var showActionsModal = function(triggerElement, ruleId, title) {
        // We create a promise to fetch outcomes editing form html.
        // The form is identical to what we use in actions tab, so we can
        // just re-use this webservice call to obtain context.
        var body = Ajax.call([{
            methodname: 'tool_wp_get_tab_content',
            args: {
                tab: '\\tool_dynamicrule\\output\\tab_ruleoutcomes',
                jsondata: JSON.stringify({ruleid: ruleId, formodal: true}),
            }
        }])[0]
        .then(function(data) {
            return Templates.render(data.template, JSON.parse(data.content));
        }).then(function(html) {
            // We are not interested in js, we initalise js using separate
            // call as it requires rule id to be passed.
            return html;
        }).fail(Notification.exception);

        ModalFactory.create({
            title: title,
            body: body,
            removeOnClose: true,
        }).done(function(modal) {
            // When we close the dialogue by clicking on X in the top right corner.
            modal.getRoot().on(ModalEvents.hidden, function() {
                // Reload tab.
                Tabs.loadTab();
            });

            // Initialise outcomes editing when content is ready.
            modal.getRoot().on(ModalEvents.bodyRendered, function() {
                EditRule.initRuleOutcomesModal(ruleId, modal);
            });

            // Using setLarge does not make it large enough, so using class here.
            modal.getModal().addClass('modal-xl');
            modal.show();
            return null;
        }).fail(Notification.exception);
    };

    /**
     * Rule archive action.
     */
    var initArchiveLinksHandlers = function() {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=archive]', function(e) {
            e.preventDefault();
            var menuNode = $(e.currentTarget);
            Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: 'confirmarchiverule', component: 'tool_dynamicrule', param: menuNode.data('rulename')},
                {key: 'archive', component: 'tool_dynamicrule'},
                {key: 'cancel', component: 'moodle'}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var requests = Ajax.call([
                        {methodname: 'tool_dynamicrule_archive_rule', args: {id: menuNode.data('id')}}
                    ]);
                    requests[0].then(function() {
                        reloadReport(menuNode);
                        return null;
                    }).fail(Notification.exception);
                });
                return null;
            }).fail(Notification.exception);
        });
    };

    /**
     * Rule unarchive action.
     */
    var initUnarchiveLinksHandlers = function() {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=unarchive]', function(e) {
            e.preventDefault();
            var menuNode = $(e.currentTarget);
            var requests = Ajax.call([
                {methodname: 'tool_dynamicrule_unarchive_rule', args: {id: menuNode.data('id')}}
            ]);
            requests[0].then(function() {
                reloadReport(menuNode);
                return null;
            }).fail(Notification.exception);
        });
    };

    /**
     * Rule duplicate action.
     */
    var initDuplicateLinksHandlers = function() {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=duplicate]', function(e) {
            e.preventDefault();
            var menuNode = $(e.currentTarget);
            if (typeof menuNode.data('limitreached') !== 'undefined') {
                limitReachedAlert(menuNode);
            } else {
                Str.get_strings([
                    {key: 'confirm', component: 'moodle'},
                    {key: 'confirmduplicaterule', component: 'tool_dynamicrule', param: menuNode.data('rulename')},
                    {key: 'duplicate', component: 'tool_dynamicrule'},
                    {key: 'cancel', component: 'moodle'}
                ]).done(function(s) {
                    Notification.confirm(s[0], s[1], s[2], s[3], function() {
                        var requests = Ajax.call([
                            {methodname: 'tool_dynamicrule_duplicate_rule', args: {id: menuNode.data('id')}}
                        ]);
                        requests[0].then(function() {
                            Tabs.loadTab();
                            return null;
                        }).fail(Notification.exception);
                    });
                    return null;
                }).fail(Notification.exception);
            }
        });
    };

    /**
     * Rule delete action.
     */
    var initDeleteLinksHandlers = function() {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=delete]', function(e) {
            e.preventDefault();
            var menuNode = $(e.currentTarget);
            Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: 'confirmdeleterule', component: 'tool_dynamicrule', param: menuNode.data('rulename')},
                {key: 'delete', component: 'moodle'},
                {key: 'cancel', component: 'moodle'}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var requests = Ajax.call([
                        {methodname: 'tool_dynamicrule_delete_rule', args: {id: menuNode.data('id')}}
                    ]);
                    requests[0].then(function() {
                        reloadReport(menuNode);
                        return null;
                    }).fail(Notification.exception);
                });
                return null;
            }).fail(Notification.exception);
        });
    };

    /**
     * Add a new rule on a modal.
     *
     * @param {Event} e
     */
    var addRule = function(e) {
        e.preventDefault();
        if (typeof $(e.currentTarget).data('limitreached') !== 'undefined') {
            limitReachedAlert($(e.currentTarget));
        } else {
            var modal = showDetailsModal($(e.currentTarget), 0, Str.get_string('newrule', 'tool_dynamicrule'));
            modal.onSubmitSuccess = function(response) {
                window.location.href = response;
            };
        }
    };

    return /** @alias module:tool_dynamicrule/rules_list */ {

        /**
         * Initialise the page.
         */
        init: function() {
            M.util.js_pending('tool_dynamicrule_rules_list_init');
            if (Tabs.getActiveTab() === 'activerules') {
                initEnableLinksHandlers();
                initDisableLinksHandlers();
                initEditContentLinksHandlers();
                initEditDetailsLinksHandlers();
                initDuplicateLinksHandlers();
                initArchiveLinksHandlers();
                Tabs.addButtonOnClick(addRule.bind(this));
            } else if (Tabs.getActiveTab() === 'archivedrules') {
                initDuplicateLinksHandlers();
                initUnarchiveLinksHandlers();
                initDeleteLinksHandlers();
            } else {
                // We must be inside component interface.
                initEnableLinksHandlers(true);
                initDisableLinksHandlers();
                initEditActionsLinksHandlers();
            }
            M.util.js_complete('tool_dynamicrule_rules_list_init');
        }
    };
});

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
 * This module instantiateas the functionality for actions on dynamic rule listing.
 *
 * @module     tool_dynamicrule/rules_list
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery',
        'core/ajax',
        'core/notification',
        'core/str',
        'core/config',
        'tool_wp/secondary_tabs',
        'core_form/modalform',
        'core/modal_factory',
        'core/modal_events',
        'core/templates',
        'tool_dynamicrule/edit_rule',
        'core/event_dispatcher',
        'core_reportbuilder/local/events',
        'core_reportbuilder/local/selectors',
        'tool_dynamicrule/local/repository',
        'core/pending'],
function($, Ajax, Notification, Str, Config, Tabs, ModalForm, ModalFactory, ModalEvents, Templates, EditRule,
         EventDispatcher, ReportEvents, ReportSelectors, Repository, Pending) {

    /**
     * Reload report
     *
     * @param {$} node that triggered the action.
     */
    var reloadReport = function(node) {
        var report = node.closest(ReportSelectors.regions.report);

        EventDispatcher.dispatchEvent(ReportEvents.tableReload, {preservePagination: true}, report.get(0));
    };

    /**
     * Rule toggle action.
     *
     * @param {Boolean} isComponentUse
     */
    var initToggleRulesHandlers = function(isComponentUse) {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=rule-toggle]', function(e) {
            e.preventDefault();
            let rToggle = event.target.closest('[data-action=rule-toggle]');

            if (rToggle.dataset.state === "0") {
                Repository.countMatchedUsers(rToggle.dataset.id)
                    .then(function(data) {
                        if (data !== 0) {
                            // Some users have matched already, no need to display confirmation.
                            toggleRule(rToggle, true);
                        } else if (isComponentUse) {
                            // Used in component modal, this requires to reflect a number of users in confirmation.
                            Repository.countMatchingUsers(rToggle.dataset.id)
                                .then(function(data) {
                                    return Str.get_strings([
                                        {key: 'confirm', component: 'moodle'},
                                        {key: 'confirmenablecomponentrule', component: 'tool_dynamicrule', param: data},
                                        {key: 'enable', component: 'moodle'},
                                        {key: 'cancel', component: 'moodle'}
                                    ]);
                                }).done(function(s) {
                                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                                    toggleRule(rToggle, true);
                                });
                                return null;
                            }).catch(Notification.exception);
                        } else {
                            // Used in main dynamic rules interface.
                            Str.get_strings([
                                {key: 'confirm', component: 'moodle'},
                                {key: 'confirmenablerule', component: 'tool_dynamicrule'},
                                {key: 'enable', component: 'moodle'},
                                {key: 'cancel', component: 'moodle'}
                            ]).done(function(s) {
                                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                                    toggleRule(rToggle, true);
                                });
                                return null;
                            }).fail(Notification.exception);
                        }
                        return null;
                    }).catch(Notification.exception);
            } else {
                toggleRule(rToggle, false);
            }
        });
    };

    /**
     * Toggle rule.
     *
     * @param {Element} rToggle
     * @param {Boolean} enable
     */
    var toggleRule = function(rToggle, enable) {
        if (rToggle) {
            const ruleStateToggle = +!Number(rToggle.dataset.state);
            const pendingPromise = new Pending('tool_dynamicrule/rule:toggle');
            let toggleAction;
            if (enable) {
                toggleAction = Repository.enableRule(rToggle.dataset.id);
            } else {
                toggleAction = Repository.disableRule(rToggle.dataset.id);
            }

            toggleAction
                .then(() => {
                    const tableRow = rToggle.closest('tr');
                    tableRow.classList.toggle('text-muted');

                    rToggle.dataset.state = ruleStateToggle;
                    rToggle.checked = ruleStateToggle;

                    const stringKey = ruleStateToggle ? 'disablerulemsg' : 'enablerulemsg';
                    return Str.get_string(stringKey, 'tool_dynamicrule');
                })
                .then(toggleLabel => {
                    const labelContainer = rToggle.parentElement.querySelector(`label[for="${rToggle.id}"] > span`);
                    labelContainer.innerHTML = toggleLabel;
                    return pendingPromise.resolve();
                })
                .catch(Notification.exception);
        }
    };

    /**
     * Rule edit action.
     */
    var initEditContentLinksHandlers = function() {
        $('[data-region=ruleslistwrapper]').on('click', '[data-action=editcontent]', function(e) {
            e.preventDefault();
            let id = e.currentTarget.getAttribute('data-id');
            let href = e.currentTarget.getAttribute('href');
            Repository.countMatchedUsers(id)
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
            }).catch(Notification.exception);
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
            {key: 'ok', component: 'moodle'},
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
            var title = Str.get_string('editdetails', 'tool_dynamicrule', $(this).data('rulename'));
            var modal = showDetailsModal($(e.currentTarget), ruleId, title);
            modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
                Tabs.loadTab();
            });
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
            args: {id: ruleId, isajax: 1},
            modalConfig: {title: title},
            contextId: Config.contextid,
            saveButtonText: Str.get_string('save'),
            returnFocus: triggerElement[0],
        });
        modal.show();
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
                    Repository.archiveRule(menuNode.data('id'))
                    .then(function() {
                        reloadReport(menuNode);
                        return null;
                    })
                    .catch(Notification.exception);
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
            Repository.unarchiveRule(menuNode.data('id'))
            .then(function() {
                reloadReport(menuNode);
                return null;
            })
            .catch(Notification.exception);
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
                        Repository.duplicateRule(menuNode.data('id'))
                        .then(function() {
                            Tabs.loadTab();
                            return null;
                        })
                        .catch(Notification.exception);
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
                    Repository.deleteRule(menuNode.data('id'))
                    .then(function() {
                        reloadReport(menuNode);
                        return null;
                    })
                    .catch(Notification.exception);
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
            modal.addEventListener(modal.events.FORM_SUBMITTED, (ev) => {
                window.location.href = ev.detail;
            });
        }
    };

    return /** @alias module:tool_dynamicrule/rules_list */ {

        /**
         * Initialise the page.
         */
        init: function() {
            M.util.js_pending('tool_dynamicrule_rules_list_init');
            if (Tabs.getActiveTab() === 'activerules') {
                initToggleRulesHandlers();
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
                initToggleRulesHandlers(true);
                initEditActionsLinksHandlers();
            }
            M.util.js_complete('tool_dynamicrule_rules_list_init');
        }
    };
});

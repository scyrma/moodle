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

define([
    'jquery',
    'core/sortable_list',
    'core/ajax',
    'core/notification',
    'core/str',
    'tool_wp/secondary_tabs',
    'core_form/modalform',
    'tool_wp/notification',
    'core/fragment'],
function($, SortableList, Ajax, Notification, Str, Tabs, ModalForm, WpNotification, Fragment) {


    /**
     * Initialise sortable list.
     *
     * @param {String} listSelector
     * @param {String} entity
     * @param {String} moveHandlerSelector
     */
    var initSortableList = function(listSelector, entity, moveHandlerSelector) {
        // Initialise sortable for the given list.
        var sort = new SortableList($(listSelector), {moveHandlerSelector: moveHandlerSelector}),
            getElement = function(element) {
                return element.closest('[data-' + entity + '-id]');
            },
            getId = function(element) {
                return getElement(element).attr('data-' + entity + '-id');
            },
            getName = function(element) {
                return getElement(element).find('.inplaceeditable[data-itemid="' + getId(element) + '"]').text();
            };
        sort.getElementName = function(element) {
            return $.Deferred().resolve(getName(element));
        };

        // Departments have hierarchical structure and the move destinations names should be more specific.
        if (entity === 'department') {
            sort.getDestinationName = function(parentElement, afterElement) {
                if (!afterElement.length) {
                    if (parentElement.parent().parent().data('framework-id')) {
                        return Str.get_string('movecontenttothetop', 'moodle');
                    } else {
                        return Str.get_string('assfirstchildof', 'tool_organisation', getName(parentElement.parent()));
                    }
                } else {
                    return Str.get_string('movecontentafter', 'moodle', getName(afterElement));
                }
            };
        }

        // When a list element was moved send AJAX request to the server.
        $(listSelector + ' > *').on(SortableList.EVENTS.DROP, function(evt, info) {
            evt.stopPropagation(); // Important for nested lists to prevent multiple targets.
            if (info.positionChanged) {
                var parentId = 0;
                if (entity !== 'framework') {
                    parentId = getId(info.targetList);
                }
                var request = {
                    methodname: 'tool_organisation_department_move',
                    args: {
                        id: getId(info.element),
                        parentid: parentId,
                        beforeid: getId(info.targetNextElement)
                    }
                };
                Ajax.call([request])[0].fail(Notification.exception);
            }
        });
    };

    /**
     * Load framework content.
     *
     * @param {Integer} frameworkId
     * @param {Boolean} forceReload
     * @param {Boolean} forceExpand
     */
    const loadFrameworkContent = (frameworkId, forceReload = false, forceExpand = false) => {
        const frameworkContainer = $('[data-region="departmentframework"][data-framework-id="' + frameworkId + '"]');
        const framworkContentContainer = frameworkContainer.find('#frameworkcontent-' + frameworkId);
        const contextId = frameworkContainer.data('context-id');
        if (forceExpand) {
            framworkContentContainer.collapse('show');
        }
        if (framworkContentContainer.data('loaded') === 0 || forceReload) {
            M.util.js_pending('tool_organisation_departments_loadframework'); // Tell Behat to wait.
            framworkContentContainer.find('.frameworkcontent-loading').show();
            // Load Framework content.
            Fragment.loadFragment('tool_organisation', 'department_framework', contextId, {frameworkid: frameworkId})
                .then((html) => {
                    framworkContentContainer.find('.frameworkcontent-wrapper').html(html);
                    framworkContentContainer.find('.frameworkcontent-loading').hide();
                    framworkContentContainer.data('loaded', 1);
                    // Initialise Drag&Drop for framework departments.
                    if (frameworkContainer.data('disable-dragdrop') === 0) {
                        initSortableList('#frameworkslist [data-region="departmentframework"]' +
                            '[data-framework-id="' + frameworkId + '"] .frameworkcontent .tool-wp-table-tree .tool-wp-children',
                            'department',
                            '[data-drag-type=move]');
                    }
                    // Initialise departments actions.
                    initDepartmentActions('#frameworkcontent-' + frameworkId);
                    M.util.js_complete('tool_organisation_departments_loadframework');
                    return;
                }).fail(Notification.exception);
        }
    };

    /**
     * Initialise Load framework content on expand buttons.
     */
    const initLoadFrameworkContentButtons = () => {
        $('#frameworkslist').on('click', '[data-action="load-framework-content"]', (e) => {
            const frameworkContainer = $(e.currentTarget).closest('[data-region="departmentframework"]');
            const frameworkId = frameworkContainer.data('framework-id');
            loadFrameworkContent(frameworkId);
        });
    };

    /**
     * Initialise department add button.
     */
    var initFrameworkButtons = function() {
        Tabs.addButtonOnClick(function(e) {
            e.preventDefault();
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_department_form',
                args: {parentid: 0},
                modalConfig: {title: Str.get_string('adddepartmentframework', 'tool_organisation')},
                saveButtonText: Str.get_string('save'),
                returnFocus: e.currentTarget
            });
            modal.show();
            modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
                Tabs.loadTab(null, {});
            });
        });
        $('#' + Tabs.getActiveTab() + ' [data-action=adddepartment][data-frameworkid]').on('click', function(e) {
            e.preventDefault();
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_department_form',
                args: {parentid: $(e.currentTarget).data('frameworkid')},
                modalConfig: {title: $(e.currentTarget).attr('title')},
                saveButtonText: Str.get_string('save'),
                returnFocus: e.currentTarget
            });
            modal.show();
            modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
                loadFrameworkContent($(e.currentTarget).data('frameworkid'), true, true);
            });
        });
    };

    /**
     * Initialise department actions.
     *
     * @param {String} selector
     */
    var initDepartmentActions = function(selector = '') {
        $('#' + Tabs.getActiveTab() + ' ' + selector + ' [data-action=edit][data-departmentid]').on('click', (e) => {
            e.preventDefault();
            var triggerElement = $(e.currentTarget);
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_department_form',
                args: {id: triggerElement.data('departmentid')},
                modalConfig: {title: triggerElement.attr('title'), scrollable: false},
                saveButtonText: Str.get_string('save'),
                returnFocus: triggerElement
            });
            modal.show();
            modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
                if (triggerElement.data('frameworkid') === undefined) {
                    loadFrameworkContent(triggerElement.closest('[data-region=departmentframework]').data('framework-id'),
                        true, true);
                } else {
                    Tabs.loadTab(null, {});
                }
            });
        });
        $('#' + Tabs.getActiveTab() + ' ' + selector + ' [data-action=addchild][data-departmentid]').on('click', (e) => {
            e.preventDefault();
            var triggerElement = $(e.currentTarget);
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_department_form',
                args: {parentid: triggerElement.data('departmentid')},
                modalConfig: {title: triggerElement.attr('title')},
                saveButtonText: Str.get_string('save'),
                returnFocus: triggerElement[0]
            });
            modal.show();
            modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
                loadFrameworkContent(triggerElement.closest('[data-region=departmentframework]').data('framework-id'), true, true);
            });
        });
        $('#' + Tabs.getActiveTab() + ' ' + selector + ' [data-action=delete][data-departmentid]').on('click', (e) => {
            e.preventDefault();
            var departmentid = $(e.currentTarget).data('departmentid'),
                name = $(e.currentTarget).data('name'),
                triggerElement = $(e.currentTarget),
                isframework = !(triggerElement.data('frameworkid') === undefined),
                keydeleteconfirm = isframework ? 'deletedepartmentframeworkconfirm' : 'deletedepartmentconfirm';

            Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: keydeleteconfirm, component: 'tool_organisation', param: name},
                {key: 'delete', component: 'tool_organisation'},
                {key: 'cancel', component: 'moodle'},
                {key: 'departmentdeleted', component: 'tool_organisation'}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var promises = Ajax.call([
                        {methodname: 'tool_organisation_department_delete', args: {id: departmentid}}
                    ]);
                    promises[0].done(function() {
                        if (!isframework) {
                            loadFrameworkContent(triggerElement.closest('[data-region=departmentframework]').data('framework-id'),
                                true, true);
                        } else {
                            Tabs.loadTab(null, {});
                        }
                        WpNotification.addNotification({message: s[4], type: 'success'});
                    }).fail(function(ex) {
                        Str.get_strings([
                            {key: 'warning', component: 'moodle'},
                            {key: 'ok', component: 'moodle'}
                        ]).done(function(s) {
                            Notification.alert(s[0], ex.message, s[1]);
                        });
                    });
                });
            }).fail(Notification.exception);
        });
    };

    /**
     * Handles edit details in shared space event
     *
     * @param {Event} e The jquery event
     * @private
     */
    var editDetailsSwitchHandler = function(e) {
        e.preventDefault();
        var element = $(e.currentTarget);
        window.location.href = element.data('redirect');
    };

    return {
        init: function() {
            initSortableList('#frameworkslist [data-region="department-container"]',
                'framework',
                '.framework-header [data-drag-type=move]');
            initFrameworkButtons();
            initDepartmentActions();
            initLoadFrameworkContentButtons();
            $('[data-action=\'editdetailsswitchtenant\'][data-redirect]').on('click', editDetailsSwitchHandler);
        }
    };
});

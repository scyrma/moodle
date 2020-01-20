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

define([
    'jquery',
    'core/sortable_list',
    'core/ajax',
    'core/notification',
    'core/str',
    'tool_wp/tabs',
    'tool_wp/modal_form',
    'tool_wp/notification'],
function($, SortableList, Ajax, Notification, Str, Tabs, ModalForm, WpNotification) {

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

    var initFrameworkButtons = function() {
        Tabs.addButtonOnClick(function(e) {
            e.preventDefault();
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_department_form',
                args: {parentid: 0},
                modalConfig: {title: $(e.currentTarget).attr('title')},
                saveButtonText: Str.get_string('save'),
                triggerElement: $(e.currentTarget)
            });
            modal.onSubmitSuccess = function() {
                Tabs.loadTab(null, {});
            };
        });
        $('#' + Tabs.getActiveTab() + ' [data-action=adddepartment][data-frameworkid]').on('click', function(e) {
            e.preventDefault();
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_department_form',
                args: {parentid: $(e.currentTarget).data('frameworkid')},
                modalConfig: {title: $(e.currentTarget).attr('title')},
                saveButtonText: Str.get_string('save'),
                triggerElement: $(e.currentTarget)
            });
            modal.onSubmitSuccess = function() {
                Tabs.loadTab(null, {frameworkid: $(e.currentTarget).data('frameworkid')});
            };
        });
    };

    var initDepartmentActions = function() {
        $('#' + Tabs.getActiveTab() + ' [data-action=edit][data-departmentid]').on('click', function(e) {
            e.preventDefault();
            var triggerElement = $(e.currentTarget);
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_department_form',
                args: {id: triggerElement.data('departmentid')},
                modalConfig: {title: triggerElement.attr('title')},
                saveButtonText: Str.get_string('save'),
                triggerElement: triggerElement
            });
            modal.onSubmitSuccess = function() {
                Tabs.loadTab(null, {frameworkid: triggerElement.closest('[data-region=departmentframework]').data('framework-id')});
            };
        });
        $('#' + Tabs.getActiveTab() + ' [data-action=addchild][data-departmentid]').on('click', function(e) {
            e.preventDefault();
            var triggerElement = $(e.currentTarget);
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_department_form',
                args: {parentid: triggerElement.data('departmentid')},
                modalConfig: {title: triggerElement.attr('title')},
                saveButtonText: Str.get_string('save'),
                triggerElement: triggerElement
            });
            modal.onSubmitSuccess = function() {
                Tabs.loadTab(null, {frameworkid: triggerElement.closest('[data-region=departmentframework]').data('framework-id')});
            };
        });
        $('#' + Tabs.getActiveTab() + ' [data-action=delete][data-departmentid]').on('click', function(e) {
            e.preventDefault();

            var departmentid = $(e.currentTarget).data('departmentid'),
                name = $(e.currentTarget).data('name'),
                triggerElement = $(e.currentTarget),
                isframework = !(triggerElement.data('frameworkid') === undefined),
                keydeleteconfirm = isframework ? 'deletedepartmentframeworkconfirm' : 'deletedepartmentconfirm';

            Str.get_strings([
                {key: 'confirm'},
                {key: keydeleteconfirm, component: 'tool_organisation', param: name},
                {key: 'delete', component: 'tool_organisation'},
                {key: 'cancel'},
                {key: 'departmentdeleted', component: 'tool_organisation'}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var promises = Ajax.call([
                        {methodname: 'tool_organisation_department_delete', args: {id: departmentid}}
                    ]);
                    promises[0].done(function() {
                        var options = {};
                        if (triggerElement.data('frameworkid') === undefined) {
                            options.frameworkid = triggerElement.closest('[data-region=departmentframework]').data('framework-id');
                        }
                        Tabs.loadTab(null, options);
                        WpNotification.addNotification({message: s[4], type: 'success'});
                    }).fail(function(ex) {
                        Str.get_strings([
                            {key: 'warning'},
                            {key: 'ok'}
                        ]).done(function(s) {
                            Notification.alert(s[0], ex.message, s[1]);
                        });
                    });
                });
            }).fail(Notification.exception);
        });
    };

    return {
        init: function() {
            $('#frameworkslist [data-region="departmentframework"][data-framework-id]').each(function() {
                initSortableList('#frameworkslist [data-region="departmentframework"][data-framework-id="' +
                    $(this).data('framework-id') +
                    '"] .frameworkcontent .tool-wp-table-tree .tool-wp-children',
                    'department',
                    '[data-drag-type=move]');
            });
            initSortableList('#frameworkslist [data-region="department-container"]',
                'framework',
                '.framework-header [data-drag-type=move]');
            initFrameworkButtons();
            initDepartmentActions();
        }
    };
});

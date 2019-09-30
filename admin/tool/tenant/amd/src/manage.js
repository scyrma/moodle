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
 * Allows to manage tenants
 *
 * @module     tool_tenant/manage
 * @package    tool_tenant
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/sortable_list', 'core/ajax', 'core/notification', 'core/str', 'tool_wp/tabs', 'tool_wp/modal_form'],
function($, SortableList, Ajax, Notification, Str, Tabs, ModalForm) {

    var initSortableList = function(listSelector) {
        // Initialise sortable for the given list.
        var sort = new SortableList($(listSelector)),
            getId = function(element) {
                return element.find('.inplaceeditable[data-itemtype=tenant_name]').attr('data-itemid');
            },
            getName = function(element) {
                return element.find('.inplaceeditable[data-itemtype=tenant_name]').text();
            };
        sort.getElementName = function(element) {
            return $.Deferred().resolve(getName(element));
        };
        // When a list element was moved send AJAX request to the server.
        $(listSelector + ' > *').on(SortableList.EVENTS.DROP, function(_, info) {
            if (info.positionChanged) {
                if (!info.element.prev().length && info.targetNextElement.next().length) {
                    // Can not move before the default element.
                    info.targetNextElement = info.targetNextElement.next();
                    info.targetList[0].insertBefore(info.element[0], info.targetNextElement[0]);
                }
                var request = {
                    methodname: 'tool_tenant_change_sortorder',
                    args: {id: getId(info.element), beforeid: getId(info.targetNextElement)}
                };
                Ajax.call([request])[0].fail(Notification.exception);
            }
        });

    };

    /**
     * Update the tenant record.
     *
     * @param  {event} e
     * @return {void}
     */
    var updateTenant = function(e) {
        e.preventDefault();

        var tenantId = $(e.currentTarget).attr('data-id');
        var titlename = $(e.currentTarget).attr('data-form-title');

        Str.get_strings([
            {key: 'addtenant', component: 'tool_tenant'},
        ]).done(function(s) {
            var title = (typeof titlename !== 'undefined') ? titlename : s[0];
            var modal = new ModalForm({
                formClass: 'tool_tenant\\form\\add_tenant_form',
                args: {id: tenantId},
                modalConfig: {title: title},
                triggerElement: $(e.currentTarget),
                saveButtonText: Str.get_string('save')
            });
            modal.onSubmitSuccess = function(manageurl) {
                if (tenantId) {
                    Tabs.loadTab('activetenants', {action: "add"});
                } else {
                    window.location.href = manageurl;
                }
            };
        });
    };

    /**
     * Update the tenant css record.
     *
     * @param  {event} e
     * @return {void}
     */
    var updateCss = function(e) {
        e.preventDefault();

        var tenantId = $(e.currentTarget).attr('data-id');
        var titlename = $(e.currentTarget).attr('data-form-title');

        Str.get_strings([
            {key: 'addtenant', component: 'tool_tenant'},
        ]).done(function(s) {
            var title = (typeof titlename !== 'undefined') ? titlename : s[0];
            var modal = new ModalForm({
                formClass: 'tool_tenant\\form\\edit_css_form',
                args: {id: tenantId},
                modalConfig: {title: title},
                triggerElement: $(e.currentTarget),
                saveButtonText: Str.get_string('save')
            });
            modal.onSubmitSuccess = function() {
                Tabs.loadTab('activetenants', {action: "add"});
            };
        });
    };

    return {
        init: function(listSelector) {
            if (listSelector) {
                initSortableList(listSelector);
            }

            Tabs.addButtonOnClick(function(e) {
                var tenantlimit = parseInt($(e.currentTarget).data('tenantlimit'));
                if (tenantlimit > 0) {
                    Str.get_strings([
                        {key: 'tenantlimitreached', component: 'tool_tenant'},
                        {key: tenantlimit > 1 ? 'tenantlimitreachedmult' : 'tenantlimitreached1',
                            component: 'tool_tenant', param: tenantlimit},
                        {key: 'ok'},
                    ]).done(function(s) {
                        Notification.alert(s[0], s[1], s[2]);
                    });
                    return;
                }
                updateTenant(e);
            });

            $('a[data-action="edit"]').on('click', function(e) {
                updateTenant(e);
            });

            $('a[data-action="css"]').on('click', function(e) {
                updateCss(e);
            });

            // Add confirmation dialogue.
            $('a[data-action][data-confirm]').on('click', function(evt) {
                evt.preventDefault();
                var confirm = $(evt.currentTarget).attr('data-confirm'),
                    action = $(evt.currentTarget).attr('data-action'),
                    id = $(evt.currentTarget).attr('data-id');
                Str.get_strings([
                    {key: 'confirm'},
                    {key: 'yes', component: 'moodle'},
                    {key: 'no', component: 'moodle'},
                ]).done(function(s) {
                    Notification.confirm(s[0], confirm, s[1], s[2], function() {
                        Tabs.loadTab(null, {action: action, id: id});
                    });
                });
            });
        }
    };
});

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
 * Manages tenant's users
 *
 * @module     tool_tenant/users
 * @package    tool_tenant
 * @copyright  2018 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'tool_wp/modal_form', 'tool_wp/tabs'],
function($, Str, ModalForm, Tabs) {

    var editUser = function(event, tenantid, userid, username) {

        var title;
        if (!userid) {
            userid = 0;
            title = Str.get_strings([{key: 'adduser', component: 'tool_tenant'}]);
        } else {
            title = Str.get_strings([{key: 'edituserwithname', component: 'tool_tenant', param: username}]);
        }

        var modal = new ModalForm({
            formClass: 'tool_tenant\\form\\add_user_form',
            args: {
                tenantid: tenantid,
                id: userid
            },
            modalConfig: {title: title},
            triggerElement: $(event.currentTarget),
            saveButtonText: Str.get_string('save')
        });
        modal.onSubmitSuccess = function() {
            Tabs.loadTab();
        };
    };

    var enableDisableBulkAction = function(formSelector) {
        if ($('[data-bulkuserid]:checked').length) {
            $(formSelector + ' select').prop('disabled', false);
        } else {
            $(formSelector + ' select').prop('disabled', 'disabled');
        }
    };

    return {
        init: function(tenantid) {
            $('#tool_tenant-users').on('click', '[data-user-edit]', function(e) {
                e.preventDefault();
                var userid = $(e.currentTarget).attr('data-id'),
                    // TODO SP-388 need a better way of finding user name for the modal title.
                    username = $($(e.currentTarget).closest('tr').find('td')[1]).html();
                editUser(e, tenantid, userid, username);
            });
            Tabs.addButtonOnClick(function(e) {
                editUser(e, tenantid, 0);
            });
        },

        initBulkActions: function(formSelector) {
            enableDisableBulkAction(formSelector);
            $('[data-bulkuserid]').on('change', function() {
                enableDisableBulkAction(formSelector);
            });

            $(formSelector + ' select').change(function() {
                // When bulk action select is changed copy the selected checkboxes into the bulk action form and submit it.
                var form = $(formSelector);
                var ignore = $(this).find(':selected').attr('data-ignore');
                if (typeof ignore === typeof undefined) {
                    $('[data-bulkuserid]:checked').each(function() {
                        form.append($('<input type="hidden">')
                            .attr('name', $(this).attr('name'))
                            .attr('value', $(this).attr('value')));
                    });
                    form.submit();
                }
            });
        },

        initEditDetails: function() {
            $('[data-action=\'editdetails\'][data-tenantid]')
            .off('click')
            .on('click', function() {
                var modal = new ModalForm({
                    formClass: 'tool_tenant\\form\\add_tenant_form',
                    args: {id: $(this).attr('data-tenantid')},
                    modalConfig: {title: Str.get_string('edittenant', 'tool_tenant', $(this).attr('data-tenantname'))},
                    triggerElement: $(this),
                    saveButtonText: Str.get_string('save')
                });
                modal.onSubmitSuccess = function() {
                    window.location.reload(true);
                };
            });
        }
    };
});
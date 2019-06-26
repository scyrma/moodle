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
    'core/modal_registry',
    'tool_wp/tabs',
    'tool_wp/modal_form',
    'tool_organisation/modal_save_cancel_delete'],
function($, SortableList, Ajax, Notification, Str, ModalRegistry, Tabs, ModalForm, ModalSaveCancelDelete) {

    var initJobAddButton = function(systemContextId) {
        var title;
        title = Str.get_string('addjob', 'tool_organisation');
        Tabs.addButtonOnClick(function(e) {
            e.preventDefault();
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_jobassign_form',
                args: {},
                modalConfig: {title: title},
                saveButtonText: Str.get_string('save'),
                contextId: systemContextId,
                triggerElement: $(e.currentTarget)
            });
            modal.onSubmitSuccess = function() {
                Tabs.loadTab(null, {});
            };
        });
    };

    var initModalOnJobActions = function(systemContextId, dataaction) {
        var selector = '[data-action="' + dataaction + '"][data-userid]';
        $('#' + Tabs.getActiveTab() + ' .jobslistreport-wrapper ').on('click', selector, function(e) {

            e.preventDefault();
            var params, keystring, type;
            var userid = $(e.currentTarget).data('userid');
            var id = $(e.currentTarget).data('id');
            if (dataaction === 'addjob') {
                params = {userid: userid};
                keystring = 'addjobforuser';
                type = 'SAVE_CANCEL';
            } else if (dataaction === 'editjob') {
                params = {id: id};
                keystring = 'editjobforuser';
                type = 'SAVE_CANCEL_DELETE';
            } else {
                return;
            }
            var fullname = $(e.currentTarget).data('fullusername');
            var title = Str.get_string(keystring, 'tool_organisation', fullname);
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_jobassign_form',
                args: params,
                modalConfig: {title: title, type: type},
                saveButtonText: Str.get_string('save'),
                contextId: systemContextId,
                triggerElement: $(e.currentTarget)
            });
            modal.onSubmitSuccess = function() {
                Tabs.loadTab(null, {});
            };
        });
    };

    ModalRegistry.register('SAVE_CANCEL_DELETE', ModalSaveCancelDelete, 'tool_organisation/modal_save_cancel_delete');

    return {
        init: function(systemContextId) {
            M.util.js_pending('tool_organisation_manage_jobs_init');
            initJobAddButton(systemContextId);
            initModalOnJobActions(systemContextId, 'addjob');
            initModalOnJobActions(systemContextId, 'editjob');
            M.util.js_complete('tool_organisation_manage_jobs_init');
        }
    };
});

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

define([
    'jquery',
    'core/notification',
    'core/str',
    'core/modal_registry',
    'tool_wp/tabs',
    'tool_wp/modal_form',
    'tool_organisation/modal_save_cancel_delete',
    'tool_reportbuilder/reportbuilder_events'],
function($, Notification, Str, ModalRegistry, Tabs, ModalForm, ModalSaveCancelDelete, ReportEvents) {

    var SELECTORS = {
        REPORTCONTAINER: "[data-region='system-report'] [data-region='data-report']",
    };

    var initJobAddButton = function(systemContextId) {
        Tabs.addButtonOnClick(function(e) {
            var element = $(e.currentTarget),
                title = Str.get_string('addjob', 'tool_organisation');

            e.preventDefault();

            if (1 === parseInt(element.data('cancreatejobs'), 10)) {
                var modal = new ModalForm({
                    formClass: 'tool_organisation\\add_jobassign_form',
                    args: {},
                    modalConfig: {title: title, scrollable: false},
                    saveButtonText: Str.get_string('save'),
                    contextId: systemContextId,
                    triggerElement: element
                });
                modal.onSubmitSuccess = function() {
                    $('body').find(SELECTORS.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLEWITHOUTPAGINATION);
                };
            } else {
                Str.get_strings([
                    {key: 'notification', component: 'tool_organisation'},
                    {key: 'notificationcannotcreatejobs', component: 'tool_organisation'},
                    {key: 'ok'},
                ]).done(function(s) {
                    Notification.alert(s[0], s[1], s[2]);
                });
                return;
            }
        });
    };

    var initModalOnJobActions = function(systemContextId, dataaction) {
        var selector = '[data-action="' + dataaction + '"][data-userid]';
        $('#' + Tabs.getActiveTab() + ' .jobslistreport-wrapper ').on('click', selector, function(e) {
            var params, keystring, type,
                element = $(e.currentTarget),
                userid = element.data('userid'),
                id = element.data('id');

            e.preventDefault();

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
            var fullname = element.data('fullusername');
            var title = Str.get_string(keystring, 'tool_organisation', fullname);
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_jobassign_form',
                args: params,
                modalConfig: {title: title, type: type},
                saveButtonText: Str.get_string('save'),
                contextId: systemContextId,
                triggerElement: element
            });
            modal.onSubmitSuccess = function() {
                element.closest(SELECTORS.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
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

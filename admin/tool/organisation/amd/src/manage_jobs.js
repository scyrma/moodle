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
    'core/notification',
    'core/pending',
    'core/event_dispatcher',
    'core/toast',
    'tool_organisation/local/repository',
    'core_reportbuilder/local/events',
    'core_reportbuilder/local/selectors',
    'core/str',
    'core/modal_registry',
    'tool_wp/secondary_tabs',
    'core_form/modalform',
    'core/event_dispatcher',
    'core_reportbuilder/local/events',
    'core_reportbuilder/local/selectors'],
function($,
        Notification,
        Pending,
        {dispatchEvent},
        Toast,
        Repository,
        reportEvents,
        reportSelectors,
        Str,
        ModalRegistry,
        Tabs,
        ModalForm,
        EventDispatcher,
        ReportEvents,
        ReportSelectors) {

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
                    returnFocus: e.currentTarget
                });
                modal.show();
                modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
                    var report = $(ReportSelectors.regions.report);

                    EventDispatcher.dispatchEvent(ReportEvents.tableReload, {}, report.get(0));
                });
            } else {
                Str.get_strings([
                    {key: 'notification', component: 'tool_organisation'},
                    {key: 'notificationcannotcreatejobs', component: 'tool_organisation'},
                    {key: 'ok', component: 'moodle'},
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
            var params, keystring,
                saveButtonText = Str.get_string('proceed'),
                element = $(e.currentTarget),
                userid = element.data('userid'),
                id = element.data('id');

            e.preventDefault();

            if (dataaction === 'addjob') {
                params = {userid: userid};
                keystring = 'addjobforuser';
            } else if (['editjobdates', 'transfertojob', 'setjobfinished'].includes(dataaction)) {
                params = {id: id, action: dataaction};
                switch (dataaction) {
                    case 'editjobdates':
                        keystring = 'editjobdatesforuser';
                        saveButtonText = Str.get_string('save');
                        break;
                    case 'transfertojob':
                        keystring = 'transfertojob';
                        break;
                    case 'setjobfinished':
                        keystring = 'setjobfinished';
                        break;
                }
            } else {
                return;
            }
            var fullname = element.data('fullusername');
            var title = Str.get_string(keystring, 'tool_organisation', fullname);
            var modal = new ModalForm({
                formClass: 'tool_organisation\\add_jobassign_form',
                args: params,
                modalConfig: {title: title, type: 'SAVE_CANCEL'},
                saveButtonText: saveButtonText,
                contextId: systemContextId,
                returnFocus: element[0]
            });
            modal.show();
            modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
                var report = element.closest(ReportSelectors.regions.report);

                EventDispatcher.dispatchEvent(ReportEvents.tableReload, {preservePagination: true}, report.get(0));
            });
        });
    };

    /**
     * Delete certification handler
     *
     * @param {Element} element
     */
    const deleteJob = (element) => {
        const id = element.dataset.id;

        // Return focus to the action menu toggle.
        const triggerElement = element.closest('.dropdown').querySelector('.dropdown-toggle');
        Notification.saveCancelPromise(
            Str.get_string('confirm', 'core'),
            Str.get_string('jobdeleteconfirm', 'tool_organisation'),
            Str.get_string('delete', 'core'),
            {triggerElement}
        ).then(() => {
            const pendingPromise = new Pending('tool/organisation:deleteJob');

            return Repository.deleteJob(id)
                .then(() => {
                    Toast.add(Str.get_string('jobdeleted', 'tool_organisation'), {type: 'success'});
                    return;
                }).then(() => {
                    const report = triggerElement.closest(reportSelectors.regions.report);
                    dispatchEvent(reportEvents.tableReload, {preservePagination: true}, report);

                    return pendingPromise.resolve();
                }).catch(Notification.exception);
        }).catch(() => {
            return;
        });
    };

    return {
        init: function(systemContextId) {
            M.util.js_pending('tool_organisation_manage_jobs_init');
            initJobAddButton(systemContextId);
            initModalOnJobActions(systemContextId, 'transfertojob');
            initModalOnJobActions(systemContextId, 'setjobfinished');
            initModalOnJobActions(systemContextId, 'addjob');
            initModalOnJobActions(systemContextId, 'editjobdates');

            document.addEventListener('click', event => {
                const deleteJobElement = event.target.closest("[data-action='deletejob']");
                if (deleteJobElement) {
                    event.preventDefault();
                    deleteJob(deleteJobElement);
                }
            });
            M.util.js_complete('tool_organisation_manage_jobs_init');
        }
    };
});

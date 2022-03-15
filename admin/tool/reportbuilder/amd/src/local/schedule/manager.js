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
 * Schedule manager class
 *
 * @module     tool_reportbuilder/local/schedule/manager
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Paul Holden <paulh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Config from 'core/config';
import ModalForm from 'tool_wp/modal_form';
import Notification from 'core/notification';
import Pending from 'core/pending';
import ReportEvents from 'tool_reportbuilder/reportbuilder_events';
import Templates from 'core/templates';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import {addNotification as addWpNotification} from 'tool_wp/notification';
import Selectors from './selectors';

class Manager {

    /**
     * Constructor
     *
     * @param {Number} reportId
     */
    constructor(reportId) {
        this.dataRegion = $(Selectors.dataRegion);
        this.reportId = Number(reportId) || 0;

        // Initialize click handlers for all elements.
        this.dataRegion.on('click', Selectors.addButton, (event) => {
            this.showEditingModal(event).onSubmitSuccess = () => {
                this.reloadScheduleReport(ReportEvents.RELOADTABLEWITHOUTPAGINATION);
            };
        });

        this.dataRegion.on('click', Selectors.editButton, (event) => {
            this.showEditingModal(event).onSubmitSuccess = () => {
                this.reloadScheduleReport(ReportEvents.RELOADTABLE);
            };
        });

        this.dataRegion.on('click', Selectors.toggleButton, (event) => this.toggleEnabledState(event));
        this.dataRegion.on('click', Selectors.sendButton, (event) => this.confirmScheduleSend(event));
        this.dataRegion.on('click', Selectors.deleteButton, (event) => this.confirmScheduleDelete(event));
    }

    /**
     * Display the editing modal
     *
     * @param {Event} event
     * @return {ModalForm}
     */
    showEditingModal(event) {
        const element = $(event.currentTarget);
        const scheduleId = Number(element.data('id')) || 0;

        event.preventDefault();

        return new ModalForm({
            formClass: 'tool_reportbuilder\\form\\schedule',
            args: {
                id: scheduleId,
                reportid: this.reportId,
            },
            modalConfig: {
                title: getString(scheduleId > 0 ? 'editschedule' : 'newschedule', 'tool_reportbuilder'),
            },
            contextId: Config.contextid,
            triggerElement: element,
            saveButtonText: getString('save')
        });
    }

    /**
     * Trigger schedule report table reload
     *
     * @param {String} reloadEvent
     */
    reloadScheduleReport(reloadEvent) {
        this.dataRegion.find(Selectors.tableRegion).trigger(reloadEvent);
    }

    /**
     * Toggle schedule enabled state
     *
     * @param {Event} event
     */
    toggleEnabledState(event) {
        const element = $(event.currentTarget);
        const enabled = +!Number(element.data('enabled')); // Toggle current state.
        const pendingPromise = new Pending('tool_reportbuilder/schedule:send');

        event.preventDefault();

        Ajax.call([{
            methodname: 'tool_reportbuilder_toggle_schedule',
            args: {
                scheduleid: element.data('id'),
                enabled: enabled,
            }
        }])[0].then(() => {
            return getStrings([
                {key: 'disable', component: 'moodle'},
                {key: 'enable', component: 'moodle'},
            ]);
        }).then((str) => {
            let title, pix;

            if (enabled) {
                title = str[0];
                pix = 'toggle-on';
            } else {
                title = str[1];
                pix = 'toggle-off';
            }

            return Templates.renderPix(pix, 'tool_wp', title);
        }).then((icon) => {
            Templates.replaceNode(element.find('.icon'), icon, '');

            // Set new enabled state.
            element.data('enabled', enabled);
            element.closest('tr').toggleClass('dimmed_text');

            pendingPromise.resolve();
            return;
        }).catch(Notification.exception);
    }

    /**
     * Confirm schedule should be sent
     *
     * @param {Event} event
     */
    confirmScheduleSend(event) {
        const element = $(event.currentTarget);

        event.preventDefault();

        getStrings([
            {key: 'confirm', component: 'tool_reportbuilder'},
            {key: 'confirmsendschedule', component: 'tool_reportbuilder', param: element.data('schedulename')},
            {key: 'send', component: 'tool_reportbuilder'},
            {key: 'cancel', component: 'moodle'}
        ]).then((str) => {
            Notification.confirm(str[0], str[1], str[2], str[3], () => {
                this.performScheduleSend(element.data('id'));
            });

            return;
        }).catch(Notification.exception);
    }

    /**
     * Perform actual sending of schedule
     *
     * @param {Number} scheduleId
     */
    performScheduleSend(scheduleId) {
        const pendingPromise = new Pending('tool_reportbuilder/schedule:send');

        Ajax.call([{
            methodname: 'tool_reportbuilder_send_schedule',
            args: {
                scheduleid: scheduleId,
            }
        }])[0].then(() => {
            return getString('scheduleaddedastask', 'tool_reportbuilder');
        }).then((str) => {
            addWpNotification({
                type: 'success',
                message: str,
            });

            pendingPromise.resolve();
            return;
        }).catch(Notification.exception);
    }

    /**
     * Confirm schedule should be deleted
     *
     * @param {Event} event
     */
    confirmScheduleDelete(event) {
        const element = $(event.currentTarget);

        event.preventDefault();

        getStrings([
            {key: 'confirm', component: 'tool_reportbuilder'},
            {key: 'confirmdeleteschedule', component: 'tool_reportbuilder', param: element.data('schedulename')},
            {key: 'delete', component: 'moodle'},
            {key: 'cancel', component: 'moodle'}
        ]).then((str) => {
            Notification.confirm(str[0], str[1], str[2], str[3], () => {
                this.performScheduleDelete(element.data('id'));
            });

            return;
        }).catch(Notification.exception);
    }

    /**
     * Perform actual deletion of schedule
     *
     * @param {Number} scheduleId
     */
    performScheduleDelete(scheduleId) {
        const pendingPromise = new Pending('tool_reportbuilder/schedule:delete');

        Ajax.call([{
            methodname: 'tool_reportbuilder_delete_schedule',
            args: {
                scheduleid: scheduleId,
            }
        }])[0].then(() => {
            return getString('removechedulesuccess', 'tool_reportbuilder');
        }).then((str) => {
            addWpNotification({
                type: 'success',
                message: str,
            });

            this.reloadScheduleReport(ReportEvents.RELOADTABLE);

            pendingPromise.resolve();
            return;
        }).catch(Notification.exception);
    }
}

/**
 * Return new instance of Manager class
 *
 * @param {Number} reportId
 * @return {Table}
 */
export default (reportId) => {
    return new Manager(reportId);
};
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
 * Manages tenant dashboard
 *
 * @module     tool_tenant/manage_dashboard
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import Tabs from 'tool_wp/tabs';
import Ajax from 'core/ajax';
import Pending from 'core/pending';
import Notification from 'core/notification';
import * as Toast from 'core/toast';
import ModalForm from 'core_form/modalform';
import {get_strings as getStrings} from 'core/str';

/** @type {Object} The list of selectors for the program allocations area. */
const Selector = {
    dashboardForm: '[data-region="dashboard-form"]',
    linkAllTenants: '[data-action="link-all"]',
    linkDashboard: '[data-action="link-dashboard"]',
    resetLinkedDashboard: '[data-action="reset-linked-dashboard"]',
    resetTenantDashboard: '[data-action="reset-tenant-dashboard"]',
    unlinkDashboard: '[data-action="unlink-dashboard"]',
};

const DashboardStatus = {
    linked: 1,
    unlinked: 0,
};

/**
 * Set button loading status.
 *
 * @param {Element} buttonElement
 * @param {Boolean} isLoading
 */
const setButtonLoading = (buttonElement, isLoading) => {
    buttonElement.disabled = isLoading;
    if (isLoading) {
        buttonElement.classList.add('loading');
    } else {
        buttonElement.classList.remove('loading');
    }
};

/**
 * Update dashboard linked status.
 *
 * @param {Event} event
 * @param {Integer} tenantId
 * @param {Integer} dashboardlinked
 * @param {String} canEditSiteDashboard
 */
const updateDashboardlinked = (event, tenantId, dashboardlinked, canEditSiteDashboard) => {
    event.preventDefault();
    // Get string keys for the messages.
    let stringKey = dashboardlinked === DashboardStatus.linked ? 'link' : 'unlink';
    let stringKeyPost = canEditSiteDashboard === "1" ? 'foradmin' : '';
    getStrings([
        {key: 'confirmation', component: 'admin'},
        {key: 'proceed', component: 'moodle'},
        {key: stringKey + 'dashboarddialog' + stringKeyPost, component: 'tool_tenant'},
        {key: stringKey + 'dashboardmessage', component: 'tool_tenant'},
    ]).then(([confirmation, proceed, dialolgMessage, alertMessage]) => {
        // Show confirmation dialog.
        Notification.confirm(confirmation, dialolgMessage, proceed, null, () => {
            const pendingPromise = new Pending('tool/tenant:updateDashboardLinked');
            // Make WebService call.
            const promises = Ajax.call([{
                methodname: 'tool_tenant_update_dashboardlinked',
                args: {
                    tenantid: tenantId ? tenantId : undefined,
                    dashboardlinked: dashboardlinked
                }
            }]);
            promises[0].then(() => {
                // Show alert message and reload Tab.
                Toast.add(alertMessage, {type: 'success'});
                Tabs.loadTab();
                return pendingPromise.resolve();
            }).catch((error) => Toast.add(error.message, {type: 'danger'}));
            return null;
        });
        return null;
    }).catch(Notification.exception);
};

/**
 * Reset dashboard for users in a tenant.
 *
 * @param {Event} event
 * @param {Element} buttonElement
 * @param {Integer} tenantId
 * @param {String} canEditSiteDashboard
 */
const resetTenantDashboard = (event, buttonElement, tenantId, canEditSiteDashboard) => {
    event.preventDefault();
    let stringKey = canEditSiteDashboard === "1" ? 'resetdashboarddialogforadmin' : 'resetdashboarddialog';
    getStrings([
        {key: 'confirmation', component: 'admin'},
        {key: 'reset', component: 'moodle'},
        {key: stringKey, component: 'tool_tenant'},
        {key: 'resetdashboardmessage', component: 'tool_tenant'},
    ]).then(([confirmation, reset, dialogMessage, alertMessage]) => {
        // Show confirmation dialog.
        Notification.confirm(confirmation, dialogMessage, reset, null, () => {
            const pendingPromise = new Pending('tool/tenant:resetTenantDashboard');
            setButtonLoading(buttonElement, true);
            // Make WebService call.
            const promises = Ajax.call([{
                methodname: 'tool_tenant_reset_tenant_dashboard',
                args: {
                    tenantid: tenantId ? tenantId : undefined,
                }
            }]);
            promises[0].then(() => {
                setButtonLoading(buttonElement, false);
                // Show alert message.
                Toast.add(alertMessage, {type: 'success'});
                return pendingPromise.resolve();
            }).catch((error) => {
                Toast.add(error.message, {type: 'danger'});
                setButtonLoading(buttonElement, false);
            });
            return null;
        });
        return null;
    }).catch(Notification.exception);
};

/**
 * Reset dashboard for users in linked tenant.
 *
 * @param {Event} event
 * @param {Element} buttonElement
 */
const resetLinkedDashboard = (event, buttonElement) => {
    event.preventDefault();
    getStrings([
        {key: 'confirmation', component: 'admin'},
        {key: 'reset', component: 'moodle'},
        {key: 'resetlinkeddashboarddialog', component: 'tool_tenant'},
        {key: 'resetlinkeddashboardmessage', component: 'tool_tenant'},
    ]).then(([confirmation, reset, dialogMessage, alertMessage]) => {
        // Show confirmation dialog.
        Notification.confirm(confirmation, dialogMessage, reset, null, () => {
            const pendingPromise = new Pending('tool/tenant:resetLinkedDashboard');
            setButtonLoading(buttonElement, true);
            // Make WebService call.
            const promises = Ajax.call([{
                methodname: 'tool_tenant_reset_all_linked_dashboards',
                args: {}
            }]);
            promises[0].then(() => {
                setButtonLoading(buttonElement, false);
                // Show alert message.
                Toast.add(alertMessage, {type: 'success'});
                return pendingPromise.resolve();
            }).catch((error) => {
                    Toast.add(error.message, {type: 'danger'});
                    setButtonLoading(buttonElement, false);
            });
            return null;
        });
        return null;
    }).catch(Notification.exception);
};

/**
 * Link all tenants.
 *
 * @param {Event} event
 * @param {Element} buttonElement
 */
const linkAllTenants = (event, buttonElement) => {
    event.preventDefault();
    getStrings([
        {key: 'confirmation', component: 'admin'},
        {key: 'proceed', component: 'moodle'},
        {key: 'linkalltenantsmessage', component: 'tool_tenant'},
    ]).then(([confirmation, reset, alertMessage]) => {
        // Show confirmation dialog.
        const modal = new ModalForm({
            formClass: 'tool_tenant\\form\\link_all_dashboards_form',
            args: {},
            modalConfig: {title: confirmation},
            saveButtonText: reset,
            returnFocus: buttonElement
        });
        modal.show();
        modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
            // Show alert message.
            Toast.add(alertMessage, {type: 'success'});
        });
        return null;
    }).catch(Notification.exception);
};

let initialized = false;

const init = () => {

    if (initialized) {
        // We already added the event listeners (can be called multiple times by mustache template).
        return;
    }

    document.addEventListener('click', (event) => {
        // Link dashboard.
        const linkDashboardButton = event.target.closest(Selector.linkDashboard);
        if (linkDashboardButton) {
            const tenantId = linkDashboardButton.dataset.tenantid;
            const canEditSiteDashboard = linkDashboardButton.dataset.caneditsitedashboard;
            updateDashboardlinked(event, tenantId, DashboardStatus.linked, canEditSiteDashboard);
        }

        // Unlink dashboard.
        const unlinkDashboardButton = event.target.closest(Selector.unlinkDashboard);
        if (unlinkDashboardButton) {
            const tenantId = unlinkDashboardButton.dataset.tenantid;
            const canEditSiteDashboard = unlinkDashboardButton.dataset.caneditsitedashboard;
            updateDashboardlinked(event, tenantId, DashboardStatus.unlinked, canEditSiteDashboard);
        }

        // Reset tenant dashboard.
        const resetTenantDashboardButton = event.target.closest(Selector.resetTenantDashboard);
        if (resetTenantDashboardButton) {
            const tenantId = resetTenantDashboardButton.dataset.tenantid;
            const canEditSiteDashboard = resetTenantDashboardButton.dataset.caneditsitedashboard;
            resetTenantDashboard(event, resetTenantDashboardButton, tenantId, canEditSiteDashboard);
        }

        // Reset linked dashboard.
        const resetLinkedDashboardButton = event.target.closest(Selector.resetLinkedDashboard);
        if (resetLinkedDashboardButton) {
            resetLinkedDashboard(event, resetLinkedDashboardButton);
        }

        // Link all tenants.
        const linkAllTenantsButton = event.target.closest(Selector.linkAllTenants);
        if (linkAllTenantsButton) {
            linkAllTenants(event, linkAllTenantsButton);
        }
    });

    initialized = true;
};

export default {
    init: init
};

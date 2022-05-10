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
 * Module to handle login tenantselector feature.
 *
 * @module     tool_tenant/login_tenant_selector
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Mikel Martín <mikel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import * as Str from 'core/str';
import Ajax from 'core/ajax';
import Templates from 'core/templates';

/**
 * Show the tenant selector modal.
 */
const showTenantSelector = async function() {
    // Load strings needed.
    const strings = await Str.get_strings([
        {'key': 'lookingfordifferentsite', component: 'tool_tenant'},
        {'key': 'changesite', component: 'tool_tenant'}
    ]);
    // Call WS to retrieve tenant selector data.
    const data = await Ajax.call([{methodname: 'tool_tenant_get_login_selector_tenants', args: {}}])[0];
    // Render the modal content.
    const content = await Templates.render('tool_tenant/login_tenant_selector_content', {"tenants": data.tenants});
    // Create the modal.
    const modal = await ModalFactory.create({
        title: strings[0],
        body: content,
        type: ModalFactory.types.SAVE_CANCEL,
        large: true,
        buttons: {save: strings[1]},
        templateContext: {classes: 'tenant-selector-modal modal-dialog-centered'},
    });
    // Render the modal.
    modal.show();
    const tenantModal = document.querySelector('.tenant-selector-modal');
    const modalSaveButton = tenantModal.querySelector('button[data-action="save"]');
    modalSaveButton.disabled = true;
    // Set up listeners to handle tenant selection.
    const tenantCards = tenantModal.querySelectorAll('.tenant-selector-card');
    for (let i = 0; i < tenantCards.length; i++) {
        tenantCards[i].addEventListener('click', selectTenant);
        tenantCards[i].addEventListener("keyup", e => {
            if (e.key === 'Enter') {
                selectTenant(e);
            }
        });
    }
    // Handle hidden event.
    modal.getRoot().on(ModalEvents.hidden, modal.destroy.bind(modal));
    // Handle 'Change site' button event.
    modal.getRoot().on(ModalEvents.save, () => {
        window.location.href = modalSaveButton.dataset.url;
    });
};

/**
 * Handle tenant selection.
 *
 * @param {Event} e
 */
const selectTenant = (e) => {
    const element = e.currentTarget;
    const tenantModal = element.closest('.tenant-selector-modal');
    const modalSaveButton = tenantModal.querySelector('button[data-action="save"]');
    if (!element.classList.contains('selected')) {
        tenantModal.querySelectorAll('.selected').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        modalSaveButton.dataset.url = element.dataset.url;
        modalSaveButton.disabled = false;
    }
};

/**
 * Set up listener to handle show tenant selector.
 */
export const init = () => {
    const showTenantSelectorButton = document.querySelector('[data-action="show-tenant-selector"]');
    showTenantSelectorButton.addEventListener('click', showTenantSelector);
};
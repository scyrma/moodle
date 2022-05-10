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
 * Modal form for Oauth2 tenant availability
 *
 * @module     tool_tenant/auth_oauth2
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import ModalForm from 'tool_wp/modal_form';
import * as Str from 'core/str';
import WpNotification from 'tool_wp/notification';
import Notification from 'core/notification';

/**
 * Initialize module
 *
 * @param {Number} issuerid
 * @param {String} issuername
 */
const init = (issuerid, issuername) => {
    const element = document.querySelector(`a[data-action="show-tenantavailability"][data-id="${issuerid}"]`);
    element.addEventListener('click', event => {
        event.preventDefault();
        const modalForm = new ModalForm({
            formClass: 'tool_tenant\\local\\auth\\oauth2\\tenant_availability_form',
            args: {id: issuerid},
            modalConfig: {title: Str.get_string('tenantavailabilityfor', 'tool_tenant', issuername), scrollable: false},
            triggerElement: event.currentTarget,
            saveButtonText: Str.get_string('save')
        });
        modalForm.onSubmitSuccess = function() {
            Str.get_string('oauth2_tenantavailability_success', 'tool_tenant')
                .then((s) => WpNotification.addNotification({message: s, type: 'success'}))
                .catch(Notification.exception);
        };
    });
};

export default {
    init: init
};
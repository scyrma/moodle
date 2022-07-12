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
 * Manages one tenant
 *
 * @module     tool_tenant/tenant
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery',
        'core/ajax',
        'core/notification',
        'core/str',
        'core_form/modalform'],
    function($, Ajax, Notification, Str, ModalForm) {

        return {
            init: function() {
                $('[data-tab-content="appearance"]')
                    .on('click', '[data-action="resetappearance"][data-tenantid]', (e) => {
                        e.preventDefault();
                        var modal = new ModalForm({
                            formClass: 'tool_tenant\\form\\reset_tenant_appearance_form',
                            args: {id:  $(e.currentTarget).data('tenantid')},
                            modalConfig: {title: Str.get_string('resetappearance', 'tool_tenant')},
                            returnFocus: e.currentTarget,
                            saveButtonText: Str.get_string('resetappearance', 'tool_tenant'),
                            saveButtonClasses: 'btn btn-danger'
                        });
                        modal.show();
                        modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
                            window.location.reload(true);
                        });
                    });
            }
        };
    });

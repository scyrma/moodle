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
 * Manages one tenant
 *
 * @module     tool_tenant/tenant
 * @package    tool_tenant
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery',
        'core/ajax',
        'core/notification',
        'core/str',
        'tool_wp/modal_form'],
    function($, Ajax, Notification, Str, ModalForm) {

        return {
            init: function() {
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

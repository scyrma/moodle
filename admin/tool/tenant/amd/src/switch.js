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
 * Switch to a different tenant
 *
 * @module     tool_tenant/switch
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Hittesh Ahuja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import $ from 'jquery';
import {get_string as getString} from 'core/str';
import ModalForm from 'tool_wp/modal_form';
import Config from 'core/config';
import ModalEvents from 'core/modal_events';
import ModalFactory from 'core/modal_factory';
import ModalRegistry from 'core/modal_registry';
import ModalSaveCancelOther from 'tool_wp/modal_save_cancel_other';
import Ajax from 'core/ajax';

class Switch {
    /**
     * Constructor for class.
     * @param {String} url URL to switch to
     * @param {Boolean} isSharedSpace Use this to determine whether you are in shared space or not
     */
    constructor(url, isSharedSpace) {
        $('a.nav-item[href^="' + url + '"]').on('click', function(e) {
            e.preventDefault();
            let modalConfig = {
                    title: getString('switchtenant', 'tool_tenant'),
                    type: 'SAVE_CANCEL',
                    buttons: {},
                    scrollable: false,
                };
            // If we are not in shared space we should get the option to switch to it
            // when the switch autocomplete modal is loaded using a button.
            if (!isSharedSpace) {
                modalConfig.type = 'SAVE_CANCEL_OTHER';
                modalConfig.buttons.other = getString('gotosharedspace', 'tool_tenant');
            }
            var modal = new ModalForm({
                formClass: 'tool_tenant\\form\\switch_tenant_form',
                modalConfig: modalConfig,
                triggerElement: $(this),
                saveButtonText: getString('switchtenant', 'tool_tenant')
            });
            modal.onSubmitSuccess = function(url) {
                window.location.href = Config.wwwroot + url;
            };
            if (!isSharedSpace) {
                modal.registerCloseOnOther = function() {
                    // Save ajax.
                    var promise;
                    promise = Ajax.call([{
                        methodname: 'tool_tenant_enable_shared_space',
                        args: {}
                    }]);
                    promise[0].then(function(result) {
                        if (result) {
                            // Switch to shared space.
                            window.location.href = Config.wwwroot + result.url;
                        }
                        return;
                    }).catch(Notification.exception);
                    return;
                };
            }
        });
    }
}

let disablesharedspacereminder;
disablesharedspacereminder = function(url) {
    $('a.dropdown-item[href^="' + url + '"]').on('click', function(e) {
        e.preventDefault();
        return ModalFactory.create({
            type: 'SAVE_CANCEL_OTHER',
            title: getString('enablesharedspace', 'tool_tenant'),
            large: true,
            buttons: {
                other: getString('notnow', 'tool_tenant')
            },
            body: getString('sharedspaceconfirmationtext', 'tool_tenant'),
            removeOnClose: true
        })
            .then(function(modal) {
                modal.show();
                modal.getRoot().on(ModalEvents.save, function() {
                    // Save ajax.
                    var promise;
                    promise = Ajax.call([{
                        methodname: 'tool_tenant_enable_shared_space',
                        args: {}
                    }]);
                    promise[0].then(function(result) {
                        if (result) {
                            // Switch to shared space.
                            window.location.href = Config.wwwroot + result.url;
                        }
                        return;
                    }).catch(Notification.exception);
                    return;
                });
                modal.registerCloseOnOther(function() {
                    var promise;
                    promise = Ajax.call([{
                        methodname: 'tool_tenant_shared_space_disable_reminder',
                        args: {}
                    }]);
                    promise[0].then(function(result) {
                        if (result) {
                            window.location.reload();
                        }
                        return;
                    }).catch(Notification.exception);
                    return;
                });

                return modal;
            });
    });

};
ModalRegistry.register('SAVE_CANCEL_OTHER', ModalSaveCancelOther, 'tool_wp/modal_save_cancel_other');

/**
 * Return new instance of tenantSwitch class
 *
 * @return {Switch} tenantSwitch Class
 */
export default {
    init: function(url, isSharedSpace) {
        new Switch(url, isSharedSpace);
    },
    disablesharedspacereminder: function(url) {
        disablesharedspacereminder(url);
    }
};


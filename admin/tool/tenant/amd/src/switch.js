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
 * Switch to a different tenant
 *
 * @module     tool_tenant/switch
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Hittesh Ahuja
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import $ from 'jquery';
import {get_string as getString} from 'core/str';
import ModalForm from 'core_form/modalform';
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
                returnFocus: this,
                saveButtonText: getString('switchtenant', 'tool_tenant')
            });
            modal.show();
            modal.addEventListener(modal.events.FORM_SUBMITTED, (ev) => {
                window.location.href = Config.wwwroot + ev.detail;
            });
            if (!isSharedSpace) {
                modal.addEventListener(modal.events.LOADED, () => {
                    modal.modal.registerCloseOnOther(() => {
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
                            return null;
                        }).catch(Notification.exception);
                    });
                });
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


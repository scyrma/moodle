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
 * Contain the logic for the save/cancel/delete modal.
 *
 * @module     tool_organisation/modal_save_cancel_delete
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
import Modal from 'core/modal';
import CustomEvents from 'core/custom_interaction_events';
import $ from 'jquery';
import Notification from 'core/notification';


export default class extends Modal {
    constructor(root) {
        super(root);
        if (!this.getFooter().find(this.getActionSelector('save')).length) {
            Notification.exception({message: 'No save button found'});
        }

        if (!this.getFooter().find(this.getActionSelector('cancel')).length) {
            Notification.exception({message: 'No cancel button found'});
        }

        if (!this.getFooter().find(this.getActionSelector('other')).length) {
            Notification.exception({message: 'No other button found'});
        }
    }

    /**
     * Register all event listeners.
     */
    registerEventListeners() {
        // Call the parent registration.
        super.registerEventListeners();

        // Register to close on save/cancel/other.
        this.registerCloseOnSave();
        this.registerCloseOnCancel();
    }

    /**
     * Override parent implementation to prevent changing the footer content.
     */
    setFooter() {
        Notification.exception({message: 'Can not change the footer of a save cancel delete modal'});
        return;
    }

    /**
     * Set the title of the save button.
     *
     * @param {String|Promise} value The button text, or a Promise which will resolve it
     * @returns{Promise}
     */
    setSaveButtonText(value) {
        return this.setButtonText('save', value);
    }

    /**
     * Register a listener to close the dialogue when the other button is pressed.
     *
     * @method registerCloseOnOther
     * @param {function} callback
     */
    registerCloseOnOther(callback) {
        // Handle the clicking of the Other button.
        this.getModal().on(CustomEvents.events.activate, this.getActionSelector('other'), function(e, data) {
            var otherEvent = $.Event('modal-save-cancel-other:other'); // ModalEvents.other does not exist.
            this.getRoot().trigger(otherEvent, this);
            this.processOtherEvent(otherEvent, this, callback);
            if (!otherEvent.isDefaultPrevented()) {
                data.originalEvent.preventDefault();

                if (this.removeOnClose) {
                    this.destroy();
                } else {
                    this.hide();
                }
            }
        }.bind(this));
    }
    processOtherEvent(event, modal, callback) {
        event.preventDefault();
        callback();
        modal.hide();

    }
    /**
     * Set the title of the save button.
     *
     * @param {String|Promise} value The button text, or a Promise which will resolve it
     * @returns{Promise}
     */
    setSaveButtonText(value) {
        return this.setButtonText('save', value);
    }
}

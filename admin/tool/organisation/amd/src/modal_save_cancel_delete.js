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
 * Contain the logic for the save/cancel/delete modal.
 *
 * @module     tool_organisation/modal_save_cancel_delete
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define([
    'jquery',
    'core/notification',
    'core/custom_interaction_events',
    'core/modal',
    'core/modal_events',
    'core/modal_save_cancel',
    'core/ajax',
    'core/str',
    'tool_wp/tabs',
    'tool_wp/notification'
    ],
function($, Notification, CustomEvents, Modal, ModalEvents, ModalSaveCancel, Ajax, Str, Tabs, WpNotification) {

    var SELECTORS = {
        SAVE_BUTTON: '[data-action="save"]',
        CANCEL_BUTTON: '[data-action="cancel"]',
        DELETE_BUTTON: '[data-action="delete"]',
    };

    /**
     * Constructor for the Modal.
     *
     * @param {object} root The root jQuery element for the modal
     */
    var ModalSaveCancelDelete = function(root) {
        ModalSaveCancel.call(this, root);

        if (!this.getFooter().find(SELECTORS.DELETE_BUTTON).length) {
            Notification.exception({message: 'No delete button found'});
        }
    };

    ModalSaveCancelDelete.prototype = Object.create(ModalSaveCancel.prototype);
    ModalSaveCancelDelete.prototype.constructor = ModalSaveCancelDelete;

    /**
     * Set up all of the event handling for the modal.
     *
     * @method registerEventListeners
     */
    ModalSaveCancelDelete.prototype.registerEventListeners = function() {
        // Apply parent event listeners.
        ModalSaveCancel.prototype.registerEventListeners.call(this);

        this.getModal().on(CustomEvents.events.activate, SELECTORS.DELETE_BUTTON, function(e, data) {
            var deleteEvent = $.Event('modal-save-cancel-delete:delete'); // ModalEvents.delete does not exist.
            this.getRoot().trigger(deleteEvent, this);

            var modal = this;

            data.originalEvent.preventDefault();
            var id = $('form input[name=id]').val();
            Str.get_strings([
                {key: 'confirm'},
                {key: 'jobdeleteconfirm', component: 'tool_organisation'},
                {key: 'delete', component: 'tool_organisation'},
                {key: 'cancel'},
                {key: 'jobdeleted', component: 'tool_organisation'}
            ]).done(function(s) {
                Notification.confirm(s[0], s[1], s[2], s[3], function() {
                    var promises = Ajax.call([
                        {methodname: 'tool_organisation_job_delete', args: {id: id}}
                    ]);
                    promises[0].done(function() {
                        modal.hide();
                        Tabs.loadTab('', null);
                        WpNotification.addNotification({message: s[4], type: 'success'});
                        return null;
                    }).fail(Notification.exception);
                });
            }).fail(Notification.exception);
        }.bind(this));
    };

    return ModalSaveCancelDelete;
});

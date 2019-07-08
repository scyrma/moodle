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
 * This module instantiates the functionality to manage programs list.
 *
 * @module     tool_program/program_manager
 * @package    tool_program
 * @copyright  2018 David Matamoros
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/templates',
    'core/fragment',
    'core/notification',
    'core/str',
    'tool_wp/tabs',
    'tool_wp/modal_form',
    'core/config',
    'core/modal_factory',
    'core/modal_events'
], function($,
            ajax,
            templates,
            fragment,
            notification,
            Str,
            Tabs,
            ModalForm,
            Config,
            ModalFactory,
            ModalEvents) {

    /** @type {Object} The list of selectors for the certification area. */
    var SELECTORS = {
        EDITDETAILS: "[data-action='editdetails']",
        ARCHIVEPROGRAM: "[data-action='archive']",
        RESTOREPROGRAM: "[data-action='restore']",
        DUPLICATEPROGRAM: "[data-action='duplicate']",
        DELETEPROGRAM: "[data-action='delete']",
        UPDATEPROGRAMVISIBILITY: "[data-action='updatevisibility']"
    },
    SERVICES = {
        ARCHIVEPROGRAM: 'tool_program_archive_program',
        RESTOREPROGRAM: 'tool_program_restore_program',
        DUPLICATEPROGRAM: 'tool_program_duplicate_program',
        DELETEPROGRAM: 'tool_program_delete_program',
        UPDATEPROGRAMVISIBILITY: 'tool_program_update_program_visibility'
    };

    /**
     * Messagearea class.
     *
     * @param {String} selector The selector for the page region containing the program area.
     */
    function ProgramManager(selector) {
        this.node = $(selector);
        this._init();
    }

    /** @type {jQuery} The jQuery node for the page region containing the program area. */
    ProgramManager.prototype.node = null;

    /**
     * Initialise the other objects we require.
     */
    ProgramManager.prototype._init = function() {
        this.node.on('click', SELECTORS.ARCHIVEPROGRAM, this._archiveProgramHandler.bind(this));
        this.node.on('click', SELECTORS.RESTOREPROGRAM, this._restoreProgramHandler.bind(this));
        this.node.on('click', SELECTORS.DUPLICATEPROGRAM, this._duplicateProgramHandler.bind(this));
        this.node.on('click', SELECTORS.DELETEPROGRAM, this._deleteProgramHandler.bind(this));
        this.node.on('click', SELECTORS.UPDATEPROGRAMVISIBILITY, this._updateVisibilityProgramHandler.bind(this));
        this.node.on('click', SELECTORS.EDITDETAILS, this._editProgramDetailsHandler.bind(this));
        Tabs.addButtonOnClick(this._addProgram.bind(this));
    };

    /**
     * Handles archive a Program.
     *
     * @param {Event} e The jquery event
     * @private
     */
    ProgramManager.prototype._archiveProgramHandler = function(e) {
        e.preventDefault();

        var element = $(e.currentTarget);
        var programid = element.data('programid');
        var programname = element.closest('tr')
            .find('.inplaceeditable[data-itemtype=programname]').text();
        var stringkeys = [{
            key: 'confirm',
            component: 'tool_program'
        },
        {
            key: 'archivedconfirmation',
            component: 'tool_program',
            param: programname
        },
        {
            key: 'archive',
            component: 'tool_program'
        },
        {
            key: 'no'
        }];

        Str.get_strings(stringkeys).then(function(langStrings) {
            var title = langStrings[0];
            var confirmMessage = langStrings[1];
            var buttonText = langStrings[2];
            return ModalFactory.create({
                title: title,
                body: confirmMessage,
                type: ModalFactory.types.SAVE_CANCEL
            }).then(function(modal) {
                modal.setSaveButtonText(buttonText);
                // Handle save event.
                modal.getRoot().on(ModalEvents.save, function() {
                    var request = {
                        methodname: SERVICES.ARCHIVEPROGRAM,
                        args: {
                            programid: programid
                        }
                    };

                    ajax.call([request])[0].done(function(data) {
                        if (data) {
                            Tabs.loadTab(null, null);
                        }
                    }).fail(Notification.exception);
                });

                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });

                return modal;
            });
        }).done(function(modal) {
            modal.show();
        }).fail(Notification.exception);
    };

    /**
     * Handles restore a Program.
     *
     * @param {Event} e The jquery event
     * @private
     */
    ProgramManager.prototype._restoreProgramHandler = function(e) {
        e.preventDefault();

        var element = $(e.currentTarget);
        var programid = element.data('programid');
        var request = {
            methodname: SERVICES.RESTOREPROGRAM,
            args: {
                programid: programid
            }
        };

        ajax.call([request])[0].done(function(data) {
            if (data) {
                Tabs.loadTab(null, null);
            }
        }).fail(Notification.exception);
    };

    /**
     * Handles updates a Program visibility.
     *
     * @param {Event} e The jquery event
     * @private
     */
    ProgramManager.prototype._updateVisibilityProgramHandler = function(e) {
        e.preventDefault();

        var element = $(e.currentTarget);
        var programid = element.data('programid');
        var visibility = element.data('visibility');
        var request = {
            methodname: SERVICES.UPDATEPROGRAMVISIBILITY,
            args: {
                programid: programid,
                visibility: visibility
            }
        };

        ajax.call([request])[0].done(function(data) {
            if (data) {
                Tabs.loadTab(null, null);
            }
        }).fail(Notification.exception);
    };

    /**
     * Handles delete a Program.
     *
     * @param {Event} e The jquery event
     * @private
     */
    ProgramManager.prototype._deleteProgramHandler = function(e) {
        e.preventDefault();

        var element = $(e.currentTarget);
        var programid = element.data('programid');
        var certname = element.closest('tr').find('.c0').text();
        var stringkeys = [{
            key: 'confirm',
            component: 'tool_program'
        },
        {
            key: 'confirmdeleteprogram',
            component: 'tool_program',
            param: certname
        },
        {
            key: 'delete'
        },
        {
            key: 'no'
        }];

        Str.get_strings(stringkeys).then(function(langStrings) {
            var title = langStrings[0];
            var confirmMessage = langStrings[1];
            var buttonText = langStrings[2];
            return ModalFactory.create({
                title: title,
                body: confirmMessage,
                type: ModalFactory.types.SAVE_CANCEL
            }).then(function(modal) {
                modal.setSaveButtonText(buttonText);
                // Handle save event.
                modal.getRoot().on(ModalEvents.save, function() {
                    var request = {
                        methodname: SERVICES.DELETEPROGRAM,
                        args: {
                            programid: programid
                        }
                    };

                    ajax.call([request])[0].done(function(data) {
                        if (data) {
                            Tabs.loadTab(null, null);
                        }
                    }).fail(Notification.exception);
                });

                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });

                return modal;
            });
        }).done(function(modal) {
            modal.show();
        }).fail(Notification.exception);
    };

    /**
     * Handles duplicate a Program.
     *
     * @param {Event} e The jquery event
     * @private
     */
    ProgramManager.prototype._duplicateProgramHandler = function(e) {
        e.preventDefault();

        var element = $(e.currentTarget);
        var programid = element.data('programid');
        var programname = element.closest('tr')
            .find('.inplaceeditable[data-itemtype=programname]').text();
        var stringkeys = [{
            key: 'confirm',
            component: 'tool_program'
        },
        {
            key: 'confirmduplicate',
            component: 'tool_program',
            param: programname
        },
        {
            key: 'ok'
        },
        {
            key: 'cancel'
        }];

        Str.get_strings(stringkeys).then(function(langStrings) {
            var title = langStrings[0];
            var confirmMessage = langStrings[1];
            var buttonText = langStrings[2];
            return ModalFactory.create({
                title: title,
                body: confirmMessage,
                type: ModalFactory.types.SAVE_CANCEL
            }).then(function(modal) {
                modal.setSaveButtonText(buttonText);
                // Handle save event.
                modal.getRoot().on(ModalEvents.save, function() {
                    var request = {
                        methodname: SERVICES.DUPLICATEPROGRAM,
                        args: {
                            programid: programid
                        }
                    };

                    ajax.call([request])[0].done(function(data) {
                        if (data) {
                            Tabs.loadTab(null, null);
                        }
                    }).fail(Notification.exception);
                });

                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                });

                return modal;
            });
        }).done(function(modal) {
            modal.show();
        }).fail(Notification.exception);
    };

    /**
     * Popup to edit program details
     *
     * @param {jQuery} triggerElement
     * @param {Number} programid
     * @param {Promise} title
     * @return {ModalForm}
     * @private
     */
    var showDetailsModal = function(triggerElement, programid, title) {
        return new ModalForm({
            formClass: 'tool_program\\form\\edit_program_details_form',
            args: {id: programid},
            modalConfig: {title: title},
            triggerElement: triggerElement,
            saveButtonText: Str.get_string('save')
        });
    };

    /**
     * Handles add a new program.
     *
     * @param {Event} e Click event
     * @private
     */
    ProgramManager.prototype._addProgram = function(e) {
        e.preventDefault();
        var modal = showDetailsModal($(e.currentTarget), 0, Str.get_string('newprogram', 'tool_program'));
        modal.onSubmitSuccess = function(response) {
            window.location.href = response;
        };
    };

    /**
     * Handles edit program details event
     *
     * @param {Event} e The jquery event
     * @private
     */
    ProgramManager.prototype._editProgramDetailsHandler = function(e) {
        e.preventDefault();
        var element = $(e.currentTarget);
        var id = element.data('id');
        var programname = element.closest('tr')
            .find('.inplaceeditable[data-itemtype=programname]').text();
        var modal = showDetailsModal($(e.currentTarget), id, Str.get_string('editprogram', 'tool_program', programname));
        modal.onSubmitSuccess = function() {
            Tabs.loadTab();
        };
    };

    return ProgramManager;
});

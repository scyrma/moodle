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
 * This module instantiates the functionality to load form to edit details.
 *
 * @module     tool_program/edit_details
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery', 'core/str', 'tool_wp/modal_form'],
    function($, Str, ModalForm) {

        /** @type {Object} The list of selectors for the program area. */
        var SELECTORS = {
            EDITDETAILS: "[data-action='editdetails']",
            EDITDETAILSSWITCH: "[data-action='editdetailsswitchtenant'][data-redirect]",
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
            var modal = new ModalForm({
                formClass: 'tool_program\\form\\edit_program_details_form',
                args: {id: programid},
                modalConfig: {title: title},
                triggerElement: triggerElement,
                saveButtonText: Str.get_string('save')
            });
            return modal;
        };

        /**
         * Handles edit details event
         *
         * @param {Event} e The jquery event
         * @private
         */
        var editDetailsHandler = function(e) {
            e.preventDefault();
            var element = $(e.currentTarget);
            var programid = element.data('programid');
            var programname = element.data('programname');
            var modal = showDetailsModal($(e.currentTarget), programid,
                Str.get_string('editprogram', 'tool_program', programname));
            modal.onSubmitSuccess = function() {
                window.location.reload(true);
            };
        };

        var editDetailsSwitchHandler = function(e) {
            e.preventDefault();
            var element = $(e.currentTarget);
            window.location.href = element.data('redirect');
        };

        /**
         * Program class.
         */
        function Program() {
            $(SELECTORS.EDITDETAILS).on('click', editDetailsHandler);
            $(SELECTORS.EDITDETAILSSWITCH).on('click', editDetailsSwitchHandler);
        }

        return Program;
    }
);

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
 * @module     tool_certification/edit_details
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery', 'core/str', 'tool_wp/modal_form'],
    function($, Str, ModalForm) {

        /** @type {Object} The list of selectors for the certification area. */
        var SELECTORS = {
            EDITDETAILS: "[data-action='editdetails']",
            EDITDETAILSSWITCH: "[data-action='editdetailsswitchtenant'][data-redirect]",
        };

        /**
         * Popup to edit certification details
         *
         * @param {jQuery} triggerElement
         * @param {Number} certificationid
         * @param {Promise} title
         * @return {ModalForm}
         * @private
         */
        var showDetailsModal = function(triggerElement, certificationid, title) {
            var modal = new ModalForm({
                formClass: 'tool_certification\\edit_certification_details_form',
                args: {id: certificationid},
                modalConfig: {title: title, scrollable: false},
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
            var certificationid = element.data('certificationid');
            var certname = element.data('certificationname');
            var modal = showDetailsModal($(e.currentTarget), certificationid,
                Str.get_string('editcertification', 'tool_certification', certname));
            modal.onSubmitSuccess = function() {
                window.location.reload(true);
            };
        };

        /**
         * Handles edit details in shared space event
         *
         * @param {Event} e The jquery event
         * @private
         */
        var editDetailsSwitchHandler = function(e) {
            e.preventDefault();
            var element = $(e.currentTarget);
            window.location.href = element.data('redirect');
        };

        /**
         * Certification class.
         */
        function Certification() {
            $(SELECTORS.EDITDETAILS).on('click', editDetailsHandler);
            $(SELECTORS.EDITDETAILSSWITCH).on('click', editDetailsSwitchHandler);
        }

        return Certification;
    }
);
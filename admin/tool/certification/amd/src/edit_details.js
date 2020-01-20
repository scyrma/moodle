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
            var certificationid = element.data('certificationid');
            var certname = element.data('certificationname');
            var modal = showDetailsModal($(e.currentTarget), certificationid,
                Str.get_string('editcertification', 'tool_certification', certname));
            modal.onSubmitSuccess = function() {
                window.location.reload(true);
            };
        };

        /**
         * Certification class.
         */
        function Certification() {
            $(SELECTORS.EDITDETAILS).on('click', editDetailsHandler);
        }

        return Certification;
    }
);
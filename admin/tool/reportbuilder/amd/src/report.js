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
 * This module instantiates the functionality reports main view.
 *
 * @module     tool_reportbuilder/report
 * @package    tool_reportbuilder
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define(['jquery', 'core/str', 'tool_wp/modal_form', 'core/config'],
    function($, Str, ModalForm, Config) {

        /** @type {Object} The list of selectors for the reports area. */
        var SELECTORS = {
            EDITREPORTDETAILS: "[data-action='editdetails']",
        };

        /**
         * Popup to edit report details
         *
         * @param {jQuery} triggerElement
         * @param {Number} id
         * @param {Promise} title
         * @return {ModalForm}
         * @private
         */
        var showDetailsModal = function(triggerElement, id, title) {
            var modal = new ModalForm({
                formClass: 'tool_reportbuilder\\form\\detail',
                args: {id: id},
                modalConfig: {title: title},
                contextId: Config.contextid,
                triggerElement: triggerElement,
                saveButtonText: Str.get_string('save')
            });
            return modal;
        };

        /**
         * Handles duplicate report event
         *
         * @param {Event} e The jquery event
         * @private
         */
        var editReportDetailsHandler = function(e) {
            e.preventDefault();
            var element = $(e.currentTarget);
            var id = element.data('id');
            var reportname = element.data('reportname');
            var modal = showDetailsModal($(e.currentTarget), id, Str.get_string('edittitle', 'tool_reportbuilder', reportname));
            modal.onSubmitSuccess = function() {
                window.location.reload(true);
            };
        };

        /**
         * Report class.
         */
        function Report() {
            $(SELECTORS.EDITREPORTDETAILS).on('click', editReportDetailsHandler);
        }

        return Report;
    }
);
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
 * This module initializes the modal that shows a progress overview for a given program and user.
 *
 * @module     tool_program/program_progress_modal
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define([
    'jquery',
    'core/fragment',
    'core/notification',
    'core/modal_factory',
    'core/modal_events'
], function($, Fragment, Notification, ModalFactory, ModalEvents) {
    return {
        init: function() {
            const showProgressModal = function(showProgressModalevent) {
                const title = showProgressModalevent.dataset.title;
                const contextid = showProgressModalevent.dataset.contextid;
                let allocationid = showProgressModalevent.dataset.allocationid;
                if (!allocationid) {
                    allocationid = showProgressModalevent.dataset.lastallocationid;
                }
                const args = {allocationid: allocationid};

                ModalFactory.create({
                    title: title,
                    large: true
                }).then(function(modal) {
                    const fragment = Fragment
                        .loadFragment('tool_program', 'program_overview', contextid, args)
                        .fail(Notification.exception);
                    modal.setBody(fragment);
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });
                    modal.show();
                    return modal;
                }).fail(Notification.exception);
            };

            document.addEventListener('click', (event) => {
                const progressOverview = event.target.closest("a[data-action='program_progress_overview']");
                if (progressOverview) {
                    event.preventDefault();
                    showProgressModal(progressOverview);
                }
            });
        }
    };
});

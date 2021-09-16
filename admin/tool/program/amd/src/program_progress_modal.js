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
 * This module initializes the modal that shows a progress overview for a given program and user.
 *
 * @module     tool_program/program_progress_modal
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
            var showProgressModal = function(e) {
                e.preventDefault();
                var $trigger = $(e.currentTarget);
                var title = $trigger.data('title');
                var contextid = $trigger.data('contextid');
                var allocationid = $trigger.data('allocationid');
                if (!allocationid) {
                    allocationid = $trigger.data('lastallocationid');
                }
                var args = {allocationid: allocationid};

                ModalFactory.create({
                    title: title,
                    large: true
                }).then(function(modal) {
                    var fragment = Fragment
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
            const region = '[data-region="data-report"]';
            var clickevent = 'click.tool_program_show_progress';
            var trigger = '.program-progress-overview-trigger';
            $(region).off(clickevent).on(clickevent, trigger, showProgressModal);
        }
    };
});

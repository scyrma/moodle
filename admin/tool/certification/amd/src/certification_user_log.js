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
 * Certification user log module.
 *
 * @module     tool_certification/certification_user_log
 * @author     2019 Mikel Martín <mikel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str',
    'core/templates',
    'core/modal_factory',
    'core/modal_events',
], function($, ajax, Notification, str, Templates, ModalFactory, ModalEvents) {
    "use strict";

    var SELECTOR = {
        VIEWCERTIFICATIONUSERLOG: '[data-action=view_certification_user_log]',
        CANCEL_BUTTON: '[data-action="cancel"]',
    };

    var TEMPLATES = {
        loading: 'core/loading',
        certificationUserLog: 'tool_certification/certification_user_log'
    };

    /**
     * Modal for viewing certification user log.
     *
     * @param {jQuery} triggerElement
     * @return {ModalForm} modal
     */
    var viewCertificationUserLogModal = function(triggerElement) {
        var name = triggerElement.closest('tr').children('td:first').text();
        // Modal creation.
        return ModalFactory.create({
            type: ModalFactory.types.CANCEL,
            large: true,
            title: str.get_string('certificationuserlog', 'tool_certification', name),
        });
    };

    return /** @alias module:tool_certification/certification_user_log */ {

        /**
         * Initialise the page.
         */
        init: function() {
            M.util.js_pending('tool_certification_user_allocations_init');
            /* User log modal */
            $(document).on('click', SELECTOR.VIEWCERTIFICATIONUSERLOG, function(e) {
                e.preventDefault();
                var triggerElement = $(e.currentTarget);
                var certificationid = triggerElement.data('id');
                var userid = triggerElement.data('userid');

                var modal = viewCertificationUserLogModal(triggerElement);
                modal.done((modal) => {
                    // Add custom Class.
                    modal.getModal().addClass('modal-xl');
                    // Change cancel button text.
                    var button = modal.getFooter().find(SELECTOR.CANCEL_BUTTON);
                    modal.asyncSet(str.get_string('closebuttontitle', 'moodle'), button.text.bind(button));
                    // Remove previous modals to avoid multiple instances and show.
                    modal.getRoot().on(ModalEvents.hidden, () => modal.destroy());
                    modal.show();
                    Templates.render(TEMPLATES.loading, {visible: true}, '')
                        // Render loading template while fetching data.
                        .then((html) => {
                            modal.setBody(html);
                            var requests = ajax.call([{
                                methodname: 'tool_certification_get_certification_user_log',
                                args: {certificationid: certificationid, userid: userid}
                            }]);
                            return requests[0];
                        })
                        // Ajax call to the external API to get the log.
                        .then((data) => {
                            let context = {
                                haslogs: (data.log && data.log.length > 0),
                                logs: data.log,
                                lastallocationdate: data.lastallocationdate,
                                downloadform: data.downloadform
                            };
                            return Templates.render(TEMPLATES.certificationUserLog, context, '');
                        })
                        // Render log template and append to the modal.
                        .then((html) => {
                            modal.setBody(html);
                            return modal;
                        })
                        .fail(Notification.exception);
                    return null;
                }).fail(Notification.exception);
            });
            M.util.js_complete('tool_certification_user_allocations_init');
        }
    };
});
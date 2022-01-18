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
 * The module handles any actions we perform on the reportbuilder helper.
 *
 * @module     tool_reportbuilder/reportbuilder_helper
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define(
    [
        'jquery',
        'core/templates'
    ],
    function(
        $,
        Templates
    ) {

        "use strict";
        return {
            /**
             * Replace a node content in the page with some visual effect.
             *
             * @param {JQuery} node - Element or selector to replace.
             * @param {String} html - HTML to insert / replace.
             * @param {String} js - Javascript to run after the insertion.
             * @return {Promise}
             */
            niceReplaceNodeContents: function(node, html, js) {
                // TODO 1. there are five different copies of this function or similar (search by "fadeOut").
                // TODO 2. wherever this kind of function is used we need to use their return as a promise,
                // see Conditions.prototype._reloadSelectedConditions as an example.
                var promise = $.Deferred();

                node.fadeOut("fast", function() {
                    Templates.replaceNodeContents(node, html, js);
                    node.fadeIn("fast", function() {
                        promise.resolve();
                    });
                });

                return promise.promise();
            },
            /**
             * Replace a node in the page with some visual effect.
             *
             * @param {JQuery} node - Element or selector to replace.
             * @param {String} html - HTML to insert / replace.
             * @param {String} js - Javascript to run after the insertion.
             * @return {Promise}
             */
            replaceNode: function(node, html, js) {
                var promise = $.Deferred();

                node.fadeOut("fast", function() {
                    Templates.replaceNode(node, html, js);
                    node.fadeIn("fast", function() {
                        promise.resolve();
                    });
                });

                return promise.promise();
            },
            /**
             * Handles adding a delegate event to the messaging area node.
             *
             * @param {jQuery} node Node to attach the event
             * @param {String} action The action we are listening for
             * @param {String} selector The selector for the page we are assigning the action to
             * @param {Function} callable The function to call when the event happens
             */
            onDelegateEvent: function(node, action, selector, callable) {
                node.on(action, selector, callable);
            },
            /**
             * Handles adding a custom event to the reportbuilder area node.
             *
             * @param {Node} node Node to attach the event
             * @param {String} action The action we are listening for
             * @param {Function} callable The function to call when the event happens
             */
            onCustomEvent: function(node, action, callable) {
                node.on(action, callable);
            },
            /**
             * Trigger an event attached to the given node.
             *
             * @param {Node} node
             * @param {String} event
             * @param {Object} data
             */
            triggerEvent: function(node, event, data) {
                if (typeof data === 'undefined') {
                    data = '';
                }
                node.trigger(event, data);
            }
        };
    }
);

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
 * Javascript for the reportbuilder sidebar.
 *
 * @package    tool_reportbuilder
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(
[
    'jquery',
    'core/custom_interaction_events'
],
function(
    $,
    CustomEvents
) {

    var SELECTORS = {
        SIDEBAR: '[data-region="sidebar-columns"]',
        REPORTCONTENT: '[data-region="report-content"]',
        TOGGLE_BUTTON: '[data-action="toggle-sidebar"]',
        ICON_OPEN: '.sidebarbtnopen',
        ICON_CLOSED: '.sidebarbtnclosed',
    };

    /**
     * Listen to, and handle events for the reportbuilder sidebar.
     *
     * @param {Object} root The report container.
     */
    var registerEventListeners = function(root) {
        CustomEvents.define(root, [
            CustomEvents.events.activate
        ]);

        root.on(CustomEvents.events.activate, SELECTORS.TOGGLE_BUTTON, function() {
            var sideBar = root.find(SELECTORS.SIDEBAR);
            var reportContent = root.find(SELECTORS.REPORTCONTENT);
            var toggleButton = root.find(SELECTORS.TOGGLE_BUTTON);

            if (toggleButton.hasClass('open')) {
                sideBar.removeClass('d-flex').addClass('d-none');
                reportContent.removeClass('col-8').removeClass('col-lg-9').addClass('col-12');
                toggleButton.removeClass('open');
                toggleButton.find(SELECTORS.ICON_OPEN).addClass('hidden');
                toggleButton.find(SELECTORS.ICON_CLOSED).removeClass('hidden');
            } else {
                sideBar.removeClass('d-none').addClass('d-flex');
                reportContent.removeClass('col-12').addClass('col-lg-9').addClass('col-4');
                toggleButton.addClass('open');
                toggleButton.find(SELECTORS.ICON_OPEN).removeClass('hidden');
                toggleButton.find(SELECTORS.ICON_CLOSED).addClass('hidden');
            }
        });
    };

    /**
     * Initialise all of the sidebar module.
     *
     * @param {object} root The report container.
     */
    var init = function(root) {
        root = $(root);
        registerEventListeners(root);
    };

    return {
        init: init
    };
});

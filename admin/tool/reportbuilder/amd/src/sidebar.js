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
 * Javascript for the reportbuilder sidebar.
 *
 * @package    tool_reportbuilder
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 <bas@moodle.com>
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
        CustomEvents.define($(root), [
            CustomEvents.events.activate
        ]);

        $('body').on(CustomEvents.events.activate, root + ' ' + SELECTORS.TOGGLE_BUTTON, function() {
            const report = $(root);
            var sideBar = report.find(SELECTORS.SIDEBAR);
            var reportContent = report.find(SELECTORS.REPORTCONTENT);
            var toggleButton = report.find(SELECTORS.TOGGLE_BUTTON);

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
        registerEventListeners(root);
    };

    return {
        init: init
    };
});

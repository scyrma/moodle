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
 * The module handles any actions we perform on the report builder table.
 *
 * @module     tool_reportbuilder/reportbuilder_column
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define([
        'jquery',
        'core/templates',
        'core/ajax',
        'core/custom_interaction_events'
    ],
    function(
        $,
        Templates,
        Ajax,
        CustomEvents
    ) {

        /** @type {Object} The list of selectors for the columns */
        var SELECTORS = {
            COLUMNS: "[data-region='columns']",
            COLUMNSWRAPPER: "[data-region='sidebar-columns']",
            COLUMNDRAGGABLE: '.js-draggable-column',
            REMOVECOLUMN: "[data-action='report-table']",
        };

        /**
         * Actions class.
         *
         * @param {reportBuilder} reportBuilder The reportBuilder area object.
         */
        function Columns(reportBuilder) {
            this.reportBuilder = reportBuilder;
            this.columnsWrapper = this.reportBuilder.find(SELECTORS.COLUMNSWRAPPER);
            this._init();
        }

        /** @type {reportBuilder} The reportBuilder area object. */
        Columns.prototype.reportBuilder = null;

        /**
         *
         * @private
         */
        Columns.prototype._init = function() {
            CustomEvents.define($('body'), [
                CustomEvents.events.activate
            ]);

            this.reportBuilder.onDelegateEvent(
                CustomEvents.events.activate,
                SELECTORS.COLUMNSWRAPPER + ' ' + SELECTORS.COLUMNDRAGGABLE,
                this._addColumn.bind(this)
            );

        };

        /**
         * Shifts focus to the previous conversation in the list.
         *
         * @param {event} e The jquery event
         * @param {object} data Additional event data
         */
        Columns.prototype._addColumn = function(e, data) {
            var fieldkey = $(e.currentTarget).data('field');
            data.originalEvent.preventDefault();
            data.originalEvent.stopPropagation();
            // TODO better to have a method in ReportBuilder class than to expose table var.
            this.reportBuilder.table._addColumnToReport(fieldkey);
        };

        return Columns;
    });
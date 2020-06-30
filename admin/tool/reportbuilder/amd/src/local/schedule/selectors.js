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
 * Schedule selectors
 *
 * @module     tool_reportbuilder/local/schedule/selectors
 * @package    tool_reportbuilder
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Paul Holden <paulh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Helper method to build data selectors
 *
 * @param {String} name
 * @param {String} value
 * @return {String}
 */
const getDataSelector = (name, value) => {
    return `[data-${name}="${value}"]`;
};

export default {
    dataRegion: getDataSelector('region', 'schedules-list'),
    tableRegion: getDataSelector('region', 'data-report'),
    addButton: getDataSelector('tabs-element', 'addbutton'),
    editButton: getDataSelector('action', 'edit'),
    toggleButton: getDataSelector('action', 'toggle'),
    sendButton: getDataSelector('action', 'send'),
    deleteButton: getDataSelector('action', 'delete'),
};
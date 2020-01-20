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
 * This module instantiates the functionality for program schedule view
 *
 * @module     tool_program/edit_calendar
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define([
    'tool_wp/tabs',
    'core/notification',
    'core/str',
    'tool_wp/notification'
], function(Tabs, Notification, Str, WpNotification) {
    return {
        init: function() {
            Tabs.initForm(function(data) {
                if (data === true) {
                    Str.get_string('scheduleupdatesuccess', 'tool_program')
                        .then(function(string) {
                            WpNotification.addNotification({
                                message: string,
                                type: 'success'
                            });
                            return null;
                        })
                        .fail(Notification.exception);
                }
            });
        }
    };
});

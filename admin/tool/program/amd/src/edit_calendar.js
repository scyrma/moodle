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
 * This module instantiates the functionality for program schedule view
 *
 * @module     tool_program/edit_calendar
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import {addNotification as addWpNotification} from 'tool_wp/notification';
import Tabs from 'tool_wp/tabs';

const init = () => {
    // Initialize notification for changes saved.
    Tabs.initForm((data) => {
        if (data === true) {
            getString('scheduleupdatesuccess', 'tool_program')
                .then((string) => {
                    addWpNotification({
                        message: string,
                        type: 'success'
                    });
                    Tabs.loadTab();
                    return null;
                }).catch(Notification.exception);
        }
    });
};

export default {
    init: init
};
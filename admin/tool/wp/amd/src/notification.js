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
 * A system for displaying notifications to users.
 *
 * @module     tool_wp/notification
 * @package    tool_wp
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import * as Log from 'core/log';
import * as Toast from 'core/toast';

/**
 * Add a notification to the page.
 *
 * Note: In WP-2197 previous notification API was converted into a Toast notifications wrapper. This API is used just for backwards
 * compatibility, use 'core/toast' instead.
 *
 * @param {Object}  notification                    The notification to add.
 * @param {string}  notification.message            The body of the notification
 * @param {string}  notification.type               The type of notification to add (error, warning, info, success).
 */
export const addNotification = (notification) => {
    Log.debug('The notification module is deprecated, please use \'core/toast\' instead');

    let toastConfig = {
        type: notification.type === 'error' ? 'danger' : notification.type,
    };
    Toast.add(notification.message, toastConfig);
};

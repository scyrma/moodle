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
 * Set this course cover page as seen.
 *
 * @module     tool_catalogue/coursecover
 * @author     2022 Bas Brands <bas@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import SELECTORS from './selectors';
import {setUserPreferences} from 'tool_catalogue/local/repository/userpreferences';

/**
 * Set user preference to show course content.
 *
 * @param {string} courseId The course id.
 * @param {string} userId The user id.
 */
export const init = (courseId, userId) => {
    const startCourseButton = document.querySelector(SELECTORS.actions.startCourse);
    if (startCourseButton) {
        const preferences = [{
            'userid': parseInt(userId),
            'name': `tool_catalogue_show_course_content_${courseId}`,
            'value': true
        }];

        startCourseButton.addEventListener('click', () => {
            setUserPreferences(preferences).then((data) => {
                if (data.saved) {
                    window.location.href = M.cfg.wwwroot + '/course/view.php?id=' + courseId;
                }
                return;
            }).catch(Notification.exception);
        });
    }

    const notNowButton = document.querySelector(SELECTORS.actions.notNow);
    if (notNowButton) {
        notNowButton.addEventListener('click', () => {
            history.back();
        });
    }
};

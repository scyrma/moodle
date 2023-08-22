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
 * Module to handle program cover page
 *
 * @module     tool_catalogue/programcover
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Bas Brands <bas@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import Notification from 'core/notification';
import {setUserPreferences} from 'tool_catalogue/local/repository/userpreferences';

/**
 * Set user preference to show program content.
 *
 * @param {Number} programId The program id.
 * @param {Number} userId The user id.
 */
export const init = (programId, userId) => {
    const proceedToProgramButton = document.getElementById('proceedtoprogram');
    if (proceedToProgramButton) {
        proceedToProgramButton.addEventListener('click', () => {
            const preferences = [{
                'userid': userId,
                'name': `tool_catalogue_show_program_content_${programId}`,
                'value': true
            }];

            setUserPreferences(preferences).then((data) => {
                if (data.saved) {
                    window.location.href = M.cfg.wwwroot + '/my/courses.php/program/' + programId;
                }
                return;
            }).catch(Notification.exception);
        });
    }
};

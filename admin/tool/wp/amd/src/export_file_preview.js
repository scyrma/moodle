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
 * Interactions for displaying an export file preview
 *
 * @module     tool_wp/export_file_preview
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Paul Holden <paulh@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';

/**
 * Initialize "Show more" click element
 *
 * @param {String} uniqId
 */
const init = (uniqId) => {
    const element = document.querySelector(`#export-file-preview-${uniqId}`);

    element.addEventListener('click', (event) => {
        const pendingPromise = new Pending('tool_wp/export-file-preview');
        event.preventDefault();

        Ajax.call([{
            methodname: 'tool_wp_export_file_preview',
            args: {
                importid: event.target.dataset.importid,
            }
        }])[0].then((result) => {
            // Replace the container element content with the result of the WS call.
            event.target.parentNode.innerHTML = result.html;

            pendingPromise.resolve();

            return;
        }).catch(Notification.exception);
    });
};

export default {
    init: init,
};
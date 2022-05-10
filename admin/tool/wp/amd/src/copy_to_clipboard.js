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
 * Element to copy text to clipboard
 *
 * @module     tool_wp/copy_to_clipboard
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import * as Str from 'core/str';
import WpNotification from 'tool_wp/notification';

const SELECTORS = {
    COPY_TEXT: '[data-action="copy_to_clipboard"][data-text]',
};

let initialized = false;

/**
 * Initialize module
 */
const init = () => {
    if (initialized) {
        // We already added the event listeners (can be called multiple times by mustache template).
        return;
    }

    document.addEventListener('click', (event) => {
        const element = event.target.closest(SELECTORS.COPY_TEXT);
        if (element === null) {
            return;
        }

        event.preventDefault();

        // Credit to: https://hackernoon.com/copying-text-to-clipboard-with-javascript-df4d4988697f .
        var el = document.createElement('textarea');
        el.value = element.dataset.text;
        el.setAttribute('readonly', '');
        el.style.position = 'absolute';
        el.style.left = '-9999px'; // Move outside the screen to make it invisible.
        element.parentElement.appendChild(el);
        // Check if there is any content selected previously, store it.
        var selected =
            document.getSelection().rangeCount > 0
                ? document.getSelection().getRangeAt(0)
                : false;
        el.select();
        document.execCommand('copy');
        element.parentElement.removeChild(el);
        if (selected) {
            // If a selection existed before copying, Restore the original selection.
            document.getSelection().removeAllRanges();
            document.getSelection().addRange(selected);
        }

        Str.get_string('copiedtoclipboard', 'tool_wp')
            .then((message) => {
                WpNotification.addNotification({
                    type: 'success',
                    message: message,
                });
                return;
            })
            .catch();
    });

    initialized = true;
};

export default {
    init: init
};

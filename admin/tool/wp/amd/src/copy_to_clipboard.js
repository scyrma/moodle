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
 * Element to copy text to clipboard
 *
 * @module     tool_wp/copy_to_clipboard
 * @package    tool_wp
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery', 'tool_wp/notification', 'core/str'],
function($, WpNotification, Str) {

    // Add the event listeners for all elements with data-action=copy_to_clipboard.
    $('body').on('click', '[data-action=copy_to_clipboard][data-text]', function(e) {
        e.preventDefault();

        // Credit to: https://hackernoon.com/copying-text-to-clipboard-with-javascript-df4d4988697f .
        var el = document.createElement('textarea');
        el.value = $(e.currentTarget).data('text');
        el.setAttribute('readonly', '');
        el.style.position = 'absolute';
        el.style.left = '-9999px'; // Move outside the screen to make it invisible.
        document.body.appendChild(el);
        // Check if there is any content selected previously, store it.
        var selected =
            document.getSelection().rangeCount > 0
                ? document.getSelection().getRangeAt(0)
                : false;
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        if (selected) {
            // If a selection existed before copying, Restore the original selection.
            document.getSelection().removeAllRanges();
            document.getSelection().addRange(selected);
        }

        Str.get_strings([{key: 'copiedtoclipboard', component: 'tool_wp'}])
        .done(function(s) {
            WpNotification.addNotification({
                type: 'success',
                message: s[0]
            });
        });
    });

    return {};
});

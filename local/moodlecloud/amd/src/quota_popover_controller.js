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
 * This module retrieves notifications and render them in an element.
 *
 * JS based classical inheritance is lame, but that's the way the popovers were designed
 * so that's what we have to work with.
 *
 * @module     local_moodlecloud/quota_popover_controller
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    [
        'core/popover_region_controller',
        'core/custom_interaction_events',
        'local_moodlecloud/quota_popover_fileslist'
    ],
    function(PopoverController, CustomEvents, FilesList) {
        /**
         * Constructor.
         * Extends PopoverController.
         *
         * @param {jQuery} root The root element of the popover.
         */
        var QuotaPopoverController = function(root) {
            PopoverController.call(this, root);
            this.root = root;

            CustomEvents.define(root, [
                CustomEvents.events.activate
            ]);

            root.on(CustomEvents.events.activate, '.popover-region-toggle', function(e, data) {
                if (root.attr('data-fileslist-loaded') !== "true") {
                    FilesList.updateList(root);
                }
            });
        };

        QuotaPopoverController.prototype = Object.create(PopoverController.prototype);
        QuotaPopoverController.prototype.constructor = QuotaPopoverController;

        return QuotaPopoverController;
    }
);

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
 * Overlays a container with a loader that can appear and disappear
 *
 * @module     tool_wp/processing
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Bas Brands
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define(
[
    'jquery',
    'core/templates',
    'core/pubsub',
    'tool_wp/events'
],
function(
    $,
    Templates,
    PubSub,
    WpEvents
) {

    /**
     * Event listener for showing the loader
     *
     * @param {object} root The root container for the loader.
     */
    var registerEventListeners = function(root) {

        root.addClass('position-relative');

        Templates.render('tool_wp/processing_indicator', {})
        .done(
            function(html) {
                PubSub.subscribe(WpEvents.LOADER_START, function() {
                    if (!root.hasClass('hasloader')) {
                        root.append(html).addClass('hasloader').delay(150).queue(function() {
                            $(this).addClass('showloader').dequeue();
                        });
                    }
                });

                PubSub.subscribe(WpEvents.LOADER_STOP, function() {
                    if (root.hasClass('hasloader')) {
                        root.removeClass('hasloader').removeClass('showloader');
                        root.find('.tool-wp-processing').remove();
                    }
                });
            }
        );
    };

    /**
     * Intialise the loader and wait for loading events.
     *
     * @param {object} root The root container for the loader.
     */
    var init = function(root) {
        root = $(root);
        registerEventListeners(root);
    };

    return {
        init: init
    };

});
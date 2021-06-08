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
 * The module handles any actions we perform on the reportbuilder helper.
 *
 * @module     tool_wp/helper
 * @package    tool_wp
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019, Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define(['jquery'],
function($) {

    return {
        /**
         * Converts the JS that was received from collecting JS requirements on the $PAGE so it can be added to the existing page.
         *
         * Copied from core/fragment
         *
         * @param {string} js
         * @return {string}
         */
        processCollectedJavascript: function(js) {
            var jsNodes = $(js);
            var allScript = '';
            jsNodes.each(function(index, scriptNode) {
                scriptNode = $(scriptNode);
                var tagName = scriptNode.prop('tagName');
                if (tagName && (tagName.toLowerCase() === 'script')) {
                    if (scriptNode.attr('src')) {
                        // We only reload the script if it was not loaded already.
                        var exists = false;
                        $('script').each(function(index, s) {
                            if ($(s).attr('src') === scriptNode.attr('src')) {
                                exists = true;
                            }
                            return !exists;
                        });
                        if (!exists) {
                            allScript += ' { ';
                            allScript += ' node = document.createElement("script"); ';
                            allScript += ' node.type = "text/javascript"; ';
                            allScript += ' node.src = decodeURI("' + encodeURI(scriptNode.attr('src')) + '"); ';
                            allScript += ' document.getElementsByTagName("head")[0].appendChild(node); ';
                            allScript += ' } ';
                        }
                    } else {
                        allScript += ' ' + scriptNode.text();
                    }
                }
            });
            return allScript;
        }
    };
});
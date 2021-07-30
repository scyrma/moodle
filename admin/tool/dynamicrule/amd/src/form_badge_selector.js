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
 * Potential badge selector module.
 *
 * @module     tool_dynamicrule/form_potential_badge_selector
 * @class      form_potential_badge_selector
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import Ajax from 'core/ajax';
import * as Str from 'core/str';
import Templates from 'core/templates';

// Maximum number of suggestions to show.
const MAXSUGGESTIONS = 100;

/**
 * Autocomplete transport method
 *
 * @param {String} selector
 * @param {String} search
 * @param {Function} success
 * @param {Function} failure
 */
const transport = (selector, search, success, failure) => {
    Ajax.call([{
        methodname: 'tool_dynamicrule_potential_badge_selector',
        args: {
            search: search,
        }
    }])[0].then(async(results) => {
        if (results.length <= MAXSUGGESTIONS) {
            const promises = results.map((result) => {
                return Templates.render('tool_dynamicrule/form_badge_selector_suggestion', result);
            });

            // Apply the rendered suggestions to the results.
            return await Promise.all(promises).then((suggestions) => {
                results.forEach((result, index) => {
                    result._label = suggestions[index];
                });

                success(results);

                return;
            });
        } else {
            return await Str.get_string('toomanybadgestoshow', 'tool_dynamicrule', '>' + MAXSUGGESTIONS).then((result) => {
                return success(result);
            });
        }
    }).catch(failure);
};

/**
 * Autocomplete results processing method
 *
 * @param {String} selector
 * @param {Object[]|String} results
 * @return {Object[]|String}
 */
const processResults = (selector, results) => {
    if (!Array.isArray(results)) {
        return results;
    }

    return results.map((result) => {
        return {
            value: result.id,
            label: result._label
        };
    });
};

export default {
    transport: transport,
    processResults: processResults
};

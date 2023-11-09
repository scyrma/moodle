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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

/**
 * Potential courses selector module.
 *
 * @module     tool_program/form_potential_program_selector
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery', 'core/ajax', 'core/templates', 'core/str'], function($, Ajax, Templates, Str) {
    // Maximum number of results to show.
    var MAXRESULTS = 100;

    return {
        processResults: function(selector, results) {
            let programs = [];
            if ($.isArray(results)) {
                let excludeList = String($(selector).data('exclude')).split(',');
                $.each(results, function(index, result) {
                    if (excludeList.indexOf(String(result.id)) === -1) {
                        programs.push({
                            value: result.id,
                            label: result.fullname
                        });
                    }
                });
            }
            return programs;
        },

        transport: function(selector, query, success, failure) {
            let promise = Ajax.call([{
                methodname: 'tool_program_potential_program_selector',
                args: {
                    search: query
                }
            }]);
            promise[0].then(function(results) {
                if (results.length <= MAXRESULTS) {
                    return success(results);
                } else {
                    // TODO WP-4426 fix properly.
                    // eslint-disable-next-line promise/no-nesting
                    return Str.get_string('toomanyprogramstoshow', 'tool_program', '>' + MAXRESULTS)
                        .then(function(toomanyprogramstoshow) {
                                return success(toomanyprogramstoshow);
                        }
                    );
                }
            }).fail(failure);
        }
    };
});

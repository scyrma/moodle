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
 * Potential courses selector module.
 *
 * @module     tool_certification/form_potential_program_selector
 * @class      form_potential_program_selector
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/templates', 'core/str'], function($, Ajax, Templates, Str) {
    // Maximum number of results to show.
    var MAXRESULTS = 100;

    return {
        processResults: function(selector, results) {
            var programs = [];
            if ($.isArray(results)) {
                $.each(results, function(index, result) {
                    programs.push({
                        value: result.id,
                        label: result.fullname
                    });
                });
                return programs;
            } else {
                return results;
            }
        },

        transport: function(selector, query, success, failure) {
            var promise;
            promise = Ajax.call([{
                methodname: 'tool_certification_potential_program_selector',
                args: {
                    search: query
                }
            }]);
            promise[0].then(function(results) {
                if (results.length <= MAXRESULTS) {
                    return success(results);
                } else {
                    return Str.get_string('toomanyprogramstoshow', 'tool_certification', '>' + MAXRESULTS)
                        .then(function(toomanyprogramstoshow) {
                            return success(toomanyprogramstoshow);
                        }
                    );
                }
            }).fail(failure);
        }
    };
});

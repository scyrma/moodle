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
 * Potential parent department selector module.
 *
 * @module     tool_organisation/form_potential_parent_department_selector
 * @class      form_potential_parent_department_selector
 * @package    tool_organisation
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery', 'core/ajax', 'core/templates', 'core/str'], function($, Ajax, Templates, Str) {
    // Maximum number of results to show.
    var MAXRESULTS = 100;

    return {
        processResults: function(selector, results) {
            var departments = [];
            if ($.isArray(results)) {
                $.each(results, (index, result) => {
                    departments.push({
                        value: result.id,
                        label: `${result.name} <small>${result.path}</small>`
                    });
                });
                return departments;
            } else {
                return results;
            }
        },

        transport: function(selector, query, success, failure) {
            const departmentId = $(selector).data('departmentid');
            let promise;
            promise = Ajax.call([{
                methodname: 'tool_organisation_get_potential_parent_departments',
                args: {
                    search: query,
                    departmentid: departmentId
                }
            }]);
            promise[0].then(function(results) {
                if (results.length <= MAXRESULTS) {
                    return success(results);
                } else {
                    return Str.get_string('toomanyparentstoshow', 'tool_organisation', '>' + MAXRESULTS)
                        .then((message) => {
                                return success(message);
                            }
                        );
                }
            }).fail(failure);
        }
    };
});
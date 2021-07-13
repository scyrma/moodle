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
 * Potential parent position selector module.
 *
 * @module     tool_organisation/form_potential_parent_position_selector
 * @class      form_potential_parent_position_selector
 * @package    tool_organisation
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Mikel Martín <mikel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery', 'core/ajax', 'core/templates', 'core/str'], function($, Ajax, Templates, Str) {
    // Maximum number of results to show.
    var MAXRESULTS = 100;

    return {
        processResults: function(selector, results) {
            var positions = [];
            if ($.isArray(results)) {
                $.each(results, (index, result) => {
                    positions.push({
                        value: result.id,
                        label: `${result.name} <small>${result.path}</small>`
                    });
                });
                return positions;
            } else {
                return results;
            }
        },

        transport: function(selector, query, success, failure) {
            const positionId = $(selector).data('positionid');
            let promise;
            promise = Ajax.call([{
                methodname: 'tool_organisation_get_potential_parent_positions',
                args: {
                    search: query,
                    positionid: positionId
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
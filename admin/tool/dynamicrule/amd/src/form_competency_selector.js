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
 * Potential competencies selector module.
 *
 * @module     tool_dynamicrule/form_potential_competency_selector
 * @class      form_potential_competency_selector
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery', 'core/ajax', 'core/templates', 'core/str'], function($, Ajax, Templates, Str) {
    // Maximum number of competencies to show.
    var MAXCOMPETENCIES = 100;

    return {
        processResults: function(selector, results) {
            var competencies = [];
            if ($.isArray(results)) {
                $.each(results, function(index, item) {
                    competencies.push({
                        value: item.id,
                        label: item._label
                    });
                });
                return competencies;
            } else {
                return results;
            }
        },

        transport: function(selector, query, success, failure) {
            var promise;
            promise = Ajax.call([{
                methodname: 'tool_dynamicrule_potential_competency_selector',
                args: {
                    search: query
                }
            }]);
            promise[0].then(function(results) {
                var promises = [],
                    i = 0;
                if (results.length <= MAXCOMPETENCIES) {
                    // Render the label.
                    $.each(results, function(index, item) {
                        var ctx = item,
                            identity = [];
                        $.each(['idnumber'], function(i, k) {
                            if (typeof item[k] !== 'undefined' && item[k] !== '') {
                                ctx.hasidentity = true;
                                identity.push(item[k]);
                            }
                        });
                        ctx.identity = identity.join(', ');
                        promises.push(Templates.render('tool_dynamicrule/form_competency_selector_suggestion', ctx));
                    });
                    // Apply the label to the results.
                    return $.when.apply($.when, promises).then(function() {
                        var args = arguments;
                        $.each(results, function(index, user) {
                            user._label = args[i];
                            i++;
                        });
                        return success(results);
                    });
                } else {
                    return Str.get_string('toomanycompetenciestoshow', 'tool_dynamicrule', '>' + MAXCOMPETENCIES)
                        .then(function(toomanycompetenciestoshow) {
                            return success(toomanycompetenciestoshow);
                        });
                }
            }).fail(failure);
        }
    };
});

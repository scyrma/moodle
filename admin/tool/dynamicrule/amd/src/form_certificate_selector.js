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
 * Potential certificate selector module.
 *
 * @module     tool_dynamicrule/form_certificate_selector
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery', 'core/ajax', 'core/templates', 'core/str'], function($, Ajax, Templates, Str) {
    // Maximum number of certificates to show.
    var MAXCERTIFICATES = 100;

    return {
        processResults: function(selector, results) {
            var certificates = [];
            if ($.isArray(results)) {
                $.each(results, function(index, item) {
                    certificates.push({
                        value: item.id,
                        label: item._label
                    });
                });
                return certificates;
            } else {
                return results;
            }
        },

        transport: function(selector, query, success, failure) {
            var promise;
            promise = Ajax.call([{
                methodname: 'tool_dynamicrule_potential_certificate_selector',
                args: {
                    search: query
                }
            }]);
            promise[0].then(function(results) {
                var promises = [],
                    i = 0;
                if (results.length <= MAXCERTIFICATES) {
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
                        promises.push(Templates.render('tool_dynamicrule/form_certificate_selector_suggestion', ctx));
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
                    return Str.get_string('toomanycertificatestoshow', 'tool_dynamicrule', '>' + MAXCERTIFICATES)
                        .then(function(toomanycertificatestoshow) {
                            return success(toomanycertificatestoshow);
                        });
                }
            }).fail(failure);
        }
    };
});
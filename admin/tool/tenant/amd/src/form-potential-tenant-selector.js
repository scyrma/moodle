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
 * tenant selector form on modal based on form-potential-tenant-selector 2016 Damyon Wiese.
 *
 * @module     tool_tenant/form-potential-tenant-selector
 * @package    tool_tenant
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */


import $ from 'jquery';
import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';
import Templates from 'core/templates';

export default class FormPotentialTenantSelector {

    static processResults(selector, results) {
        var tenants = [];
        if ($.isArray(results)) {
            $.each(results, function(index, tenant) {
                tenants.push({
                    value: tenant.id,
                    label: tenant._label
                });
            });
            return tenants;

        } else {
            return results;
        }

    }

    static transport(selector, query, success, failure) {
        var promise;
        promise = Ajax.call([{
            methodname: 'tool_tenant_potential_tenant_selector',
            args: {
                search: query,
                component: $(selector).data('component'),
                area: $(selector).data('area'),
                itemid: $(selector).data('itemid'),
            }
        }]);

        promise[0].then(function(results) {
            var promises = [],
                i = 0;
            let maxtenants = 100; // Number of tenants that can be displayed in autocomplete.
            if (results.length <= maxtenants) {
                // Render the label.
                $.each(results, function(index, tenant) {
                    var ctx = tenant;
                    if (tenant.id === $(selector).data('itemid')) {
                        // Delete current tenant from list.
                        results.splice(index, 1);
                    }

                    promises.push(Templates.render('tool_tenant/form-tenant-selector-suggestion', ctx));
                });
                // Apply the label to the results.
                return $.when.apply($.when, promises).then(function() {
                    var args = arguments;
                    $.each(results, function(index, tenant) {
                        tenant._label = args[i];
                        i++;
                    });
                    success(results);
                    return;
                });

            } else {
                return getString('toomanytenantstoshow', 'tool_tenant', '>' + maxtenants).then(function(toomanytenantstoshow) {
                    success(toomanytenantstoshow);
                    return;
                });
            }

        }).fail(failure);
    }
}


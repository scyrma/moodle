<?php
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

declare(strict_types=1);

namespace tool_tenant\reportbuilder\local\filters;

use stdClass;
use core_reportbuilder\local\filters\select;
use tool_tenant\permission;
use tool_tenant\tenancy;

/**
 * Tenant report filter
 *
 * Allows to filter by tenant. Expects that tenant is always present (otherwise see user_tenant and tenant_with_empty)
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenant extends select {

    /**
     * Return the options for the filter
     *
     * @return string[]
     */
    protected function get_select_options(): array {
        // If user can't switch, or site isn't multi-tenant, return current tenant.
        if (!permission::can_switch_tenant() || !tenancy::is_site_multi_tenant()) {
            $tenantid = tenancy::get_tenant_id();
            return [$tenantid => tenancy::get_tenant_name_from_id($tenantid)];
        }

        return array_map(static function(stdClass $tenant): string {
            return tenancy::get_tenant_name_from_id((int) $tenant->id);
        }, tenancy::get_tenants());
    }

}

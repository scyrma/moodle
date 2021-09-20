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

/**
 * Class sharedspace
 *
 * IMPORTANT! This class and all methods in it are temporary and will be replaced with tenants hierarchy.
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

defined('MOODLE_INTERNAL') || die();
/**
 * Class sharedspace
 *
 * IMPORTANT! This class and all methods in it are temporary and will be replaced with tenants hierarchy.
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class sharedspace {

    /**
     * Enable shared space
     *
     * @return int
     */
    public static function enable_shared_space(): int {
        global $DB;
        if (!$sharedid = get_config('', 'tool_tenant_shared_tenant_id')) {
            $manager = new manager();
            $tenant = $manager->create_tenant((object)['name' => '-']);
            $sharedid = $tenant->get('id');
            $sql = $DB->sql_concat(':pathprefix', 'id');
            $DB->execute('UPDATE {tool_tenant}
                SET parentid = :sharedid, depth = 2, path = '.$sql.'
                WHERE id <> :sharedid2',
                ['sharedid' => $sharedid, 'sharedid2' => $sharedid, 'pathprefix' => "/$sharedid/"]);
            set_config('tool_tenant_shared_tenant_id', $sharedid);
            return $sharedid;
        }
        return $sharedid;
    }

    /**
     * Get id of the tenant representing shared space, or null if shared space is not enabled
     *
     * @return int|null
     */
    public static function get_shared_space_id(): ?int {
        $sharedid = (int)get_config('', 'tool_tenant_shared_tenant_id');
        return $sharedid > 0 ? $sharedid : null;
    }

    /**
     * Checks if current tenant is shared space
     *
     * @param int $tenantid
     * @return bool
     */
    public static function is_shared_space(int $tenantid = 0): bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        return $tenantid == self::get_shared_space_id();
    }

    /**
     * Disable shared space reminder
     *
     * @return bool true or exception
     */
    public static function disable_shared_space_reminder() : bool {
        return set_config('tool_tenant_shared_tenant_id', 0);
    }

    /**
     * Only show shared space option in switch operations if value is not 0
     *
     * @return bool true or false
     */
    public static function show_shared_space_in_switch_operations() : bool {
        $sharedid = get_config('', 'tool_tenant_shared_tenant_id');
        return $sharedid == null || $sharedid > 0 ? true : false;
    }
}

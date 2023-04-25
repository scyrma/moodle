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
 * Collection of methods for multi-tenancy support in auth plugins.
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\local\auth;

use tool_tenant\tenancy;

/**
 * Collection of methods for multi-tenancy support in auth plugins.
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class issuer_helper {

    /** @var int this issuer is always available to all tenants (default) */
    const ISSUER_AVAILABLE_ALWAYS = 0;
    /** @var int this issuer is available to selected tenants only */
    const ISSUER_AVAILABLE_SELECTED = 1;
    /** @var int this issuer is available to all tenants except selected */
    const ISSUER_AVAILABLE_EXCEPT = 2;

    /**
     * Save tenant availability for the OAuth2 issuer
     *
     * @param string $issuerid
     * @param int $type ISSUER_AVAILABLE_ALWAYS/ISSUER_AVAILABLE_SELECTED/ISSUER_AVAILABLE_EXCEPT
     * @param array $tenantids list of tenant ids to whitelist/exclude
     * @param string $auth
     */
    public static function save_issuer_availability(string $issuerid, int $type, array $tenantids, string $auth) {
        if ($type == self::ISSUER_AVAILABLE_SELECTED || $type == self::ISSUER_AVAILABLE_EXCEPT) {
            $value = json_encode([$type, $tenantids]);
            set_config($auth . 'issuer_' . $issuerid, $value, 'tool_tenant');
        } else {
            unset_config($auth . 'issuer_' . $issuerid, 'tool_tenant');
        }
    }

    /**
     * Retrieve the tenant availability for the OAuth2 issuer
     *
     * @param string $issuerid
     * @param string $auth
     * @return array [$type, $tenantids] where $type is ISSUER_AVAILABLE_ALWAYS/ISSUER_AVAILABLE_SELECTED/ISSUER_AVAILABLE_EXCEPT
     *     and $tenantid is the list of the tenant ids to whitelist/exclude
     * @throws \dml_exception
     */
    public static function get_issuer_availability(string $issuerid, string $auth): array {
        $value = get_config('tool_tenant', $auth . 'issuer_' . $issuerid);
        $value = $value ? @json_decode($value) : null;
        if ($value && is_array($value) && isset($value[0]) && isset($value[1]) && is_array($value[1])) {
            $type = $value[0];
        }
        if (isset($type) && ($type == self::ISSUER_AVAILABLE_SELECTED || $type == self::ISSUER_AVAILABLE_EXCEPT)) {
            return [$type, $value[1]];
        } else {
            return [self::ISSUER_AVAILABLE_ALWAYS, []];
        }
    }

    /**
     * Checks that the specific issuer id is available in the current tenant
     * @param string $issuerid
     * @param int $tenantid
     * @param string $auth
     * @return bool
     */
    public static function issuer_available(string $issuerid, int $tenantid, string $auth): bool {
        [$avtype, $tenantids] = self::get_issuer_availability($issuerid, $auth);
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if ($avtype == self::ISSUER_AVAILABLE_SELECTED) {
            return in_array($tenantid, $tenantids);
        } else if ($avtype == self::ISSUER_AVAILABLE_EXCEPT) {
            return !in_array($tenantid, $tenantids);
        } else {
            return true;
        }
    }

    /**
     * Require that the specific issuer id is available in the current tenant, otherwise throw exception
     *
     * @param string $issuerid
     * @param int $tenantid
     * @param string $auth
     * @throws \moodle_exception
     */
    public static function require_issuer_available(string $issuerid, int $tenantid, string $auth): void {
        if (!self::issuer_available($issuerid, $tenantid, $auth)) {
            throw new \moodle_exception('issuernologin', 'tool_tenant');
        }
    }
}

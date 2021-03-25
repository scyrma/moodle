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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Collection of methods for multi-tenancy support in auth_oauth2
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\local\auth\oauth2;

use tool_tenant\tenancy;

/**
 * Collection of methods for multi-tenancy support in auth_oauth2
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {

    /** @var int this issuer is always available to all tenants (default) */
    const ISSUER_AVAILABLE_ALWAYS = 0;
    /** @var int this issuer is available to selected tenants only */
    const ISSUER_AVAILABLE_SELECTED = 1;
    /** @var int this issuer is available to all tenants except selected */
    const ISSUER_AVAILABLE_EXCEPT = 2;

    /**
     * Save tenant availability for the OAuth2 issuer
     *
     * @param int $issuerid
     * @param int $type ISSUER_AVAILABLE_ALWAYS/ISSUER_AVAILABLE_SELECTED/ISSUER_AVAILABLE_EXCEPT
     * @param array $tenantids list of tenant ids to whitelist/exclude
     */
    public static function save_issuer_availability(int $issuerid, int $type, array $tenantids = []) {
        if ($type == self::ISSUER_AVAILABLE_SELECTED || $type == self::ISSUER_AVAILABLE_EXCEPT) {
            $value = json_encode([$type, $tenantids]);
            set_config('oauth2issuer_' . $issuerid, $value, 'tool_tenant');
        } else {
            unset_config('oauth2issuer_' . $issuerid, 'tool_tenant');
        }
    }

    /**
     * Retrieve the tenant availability for the OAuth2 issuer
     *
     * @param int $issuerid
     * @return array [$type, $tenantids] where $type is ISSUER_AVAILABLE_ALWAYS/ISSUER_AVAILABLE_SELECTED/ISSUER_AVAILABLE_EXCEPT
     *     and $tenantid is the list of the tenant ids to whitelist/exclude
     * @throws \dml_exception
     */
    public static function get_issuer_availability(int $issuerid): array {
        $value = get_config('tool_tenant', 'oauth2issuer_' . $issuerid);
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
     * Require that the specific OAuth2 issue id is available in the current tenant, otherwise throw exception
     *
     * @param int $issuerid
     * @param int $tenantid
     * @throws \moodle_exception
     */
    public static function require_issuer_available(int $issuerid, int $tenantid = 0): void {
        if (!self::issuer_available($issuerid, $tenantid)) {
            throw new \moodle_exception('issuernologin', 'auth_oauth2');
        }
    }

    /**
     * Checks that the specific OAuth2 issue id is available in the current tenant
     * @param int $issuerid
     * @param int $tenantid
     * @return bool
     */
    public static function issuer_available(int $issuerid, int $tenantid = 0): bool {
        [$avtype, $tenantids] = self::get_issuer_availability($issuerid);
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
     * Returns tenant availability icon button
     *
     * @param \core\oauth2\issuer $issuer
     * @return string
     * @throws \coding_exception
     */
    public static function issuer_tenant_availability_button(\core\oauth2\issuer $issuer): string {
        global $OUTPUT, $PAGE;
        $PAGE->requires->js_call_amd('tool_tenant/auth_oauth2', 'init', [$issuer->get('id'), $issuer->get('name')]);
        $tenantavailabilitystr = get_string('tenantavailability', 'tool_tenant');
        return \html_writer::link('', $OUTPUT->pix_icon('sitemap', $tenantavailabilitystr, 'tool_tenant'),
            ['data-action' => 'show-tenantavailability', 'data-id' => $issuer->get('id')]);
    }
}

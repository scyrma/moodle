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
 * Collection of methods for multi-tenancy support in auth_oauth2
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\local\auth\saml2;

use tool_tenant\local\auth\issuer_helper;
use tool_tenant\tenancy;

/**
 * Collection of methods for multi-tenancy support in auth_oauth2
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {

    /**
     * Returns tenant availability icon button
     *
     * @param array $data
     * @return string
     * @throws \coding_exception
     */
    public static function issuer_tenant_availability_button(array $data): string {
        global $OUTPUT, $PAGE;
        $PAGE->requires->js_call_amd('tool_tenant/auth', 'init', ['saml2', $data['id'], $data['name']]);
        $tenantavailabilitystr = get_string('tenantavailability', 'tool_tenant');
        return \html_writer::tag('button', $tenantavailabilitystr,
            ['data-action' => 'show-tenantavailability', 'data-id' => $data['id'], 'class' => 'btn btn-secondary']);
    }

    /**
     * Hook executed during saml2 authentication
     *
     * We make sure that if the user who is about to log in is in the same tenant as the current tenant.
     * Otherwise they could be trying to use saml2 method/idp that is not available in their tenant.
     * See \auth_saml2\auth::saml_login_complete()
     *
     * @param string $issuerid
     * @param string $uid
     * @param bool|stdClass $user
     */
    public static function complete_login_hook(string $issuerid, string $uid, $user = false): void {
        global $FULLME;
        issuer_helper::require_issuer_available($issuerid, 0, 'saml2');
        $existingusertenantid = $tenantid = tenancy::get_tenant_id();
        if ($user) {
            $existingusertenantid = tenancy::get_tenant_id($user->id);
        } else {
            // User does not exist yet and will be created, make sure it is created in the correct tenant.
            \tool_tenant\manager::preallocate_new_user((object)['username' => $uid], tenancy::get_tenant_id(),
                'tool_tenant', 'SAML2 login');
        }

        if ($existingusertenantid != $tenantid) {
            // Existing user is trying to login via SAML2 but they belong to a different tenant than the current tenant.
            // We need to make sure that the site theme, name and settings apply from the correct tenant.

            // Just redirect them to the same page within their tenant so that all
            // config settings apply correctly.
            $wp = optional_param('wp', '', PARAM_BOOL); // Prevent recursion.
            if (!$wp) {
                \tool_tenant\manager::set_tenant_cookie($existingusertenantid);
                redirect(new \moodle_url($FULLME, ['wp' => 1]));
            }
        }
    }

    /**
     * Checks that the specific SAML2 issuer id is available in the current tenant
     * @param string $issuerid
     * @return bool
     */
    public static function issuer_available(string $issuerid): bool {
        return issuer_helper::issuer_available($issuerid, 0, 'saml2');
    }
}

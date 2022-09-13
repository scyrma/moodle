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
 * @author      2021 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\local\auth\oauth2;

use tool_tenant\local\auth\issuer_helper;
use tool_tenant\tenancy;

/**
 * Collection of methods for multi-tenancy support in auth_oauth2
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {

    /**
     * Returns tenant availability icon button
     *
     * @param \core\oauth2\issuer $issuer
     * @return string
     */
    public static function issuer_tenant_availability_button(\core\oauth2\issuer $issuer): string {
        global $OUTPUT, $PAGE;
        $PAGE->requires->js_call_amd('tool_tenant/auth', 'init', ['oauth2', $issuer->get('id'), $issuer->get('name')]);
        $tenantavailabilitystr = get_string('tenantavailability', 'tool_tenant');
        return \html_writer::link('', $OUTPUT->pix_icon('sitemap', $tenantavailabilitystr, 'tool_tenant'),
            ['data-action' => 'show-tenantavailability', 'data-id' => $issuer->get('id')]);
    }

    /**
     * Hook executed during oauth2 authentication
     *
     * We make sure that if the user who is about to log in is in the same tenant as the current tenant.
     * Otherwise they could be trying to use oAuth2 method/issuer that is not available in their tenant.
     * See \auth_oauth2\auth::complete_login()
     *
     * @param \core\oauth2\client $client
     * @param array $userinfo
     * @param mixed $linkedlogin
     */
    public static function complete_login_hook(\core\oauth2\client $client, array $userinfo, $linkedlogin): void {
        global $DB, $CFG, $FULLME;
        issuer_helper::require_issuer_available($client->get_issuer()->get('id'), 0, 'oauth2');
        $existingusertenantid = $tenantid = tenancy::get_tenant_id();
        if (!empty($linkedlogin) && empty($linkedlogin->get('confirmtoken'))) {
            $mappeduser = $DB->get_record_select('user', "id=:id AND deleted<>1 AND mnethostid = :mnethostid",
                ['id' => $linkedlogin->get('userid'), 'mnethostid' => $CFG->mnet_localhost_id]);
            if ($mappeduser && ($mappeduser->confirmed || !$client->get_issuer()->get('requireconfirmation'))) {
                $existingusertenantid = tenancy::get_tenant_id($mappeduser->id);
            }
        } else if ($moodleuser = \core_user::get_user_by_email($userinfo['email'])) {
            $existingusertenantid = tenancy::get_tenant_id($moodleuser->id);
        } else {
            // User does not exist yet and will be created, make sure it is created in the correct tenant.
            \tool_tenant\manager::preallocate_new_user((object)$userinfo, tenancy::get_tenant_id(),
                'tool_tenant', 'OAuth2 login');
        }

        if ($existingusertenantid != $tenantid) {
            // Existing user is trying to login via oAuth but they belong to a different tenant than the current tenant.
            // We need to make sure that the site theme, name and settings apply from the correct tenant.

            // If OAuth2 method and this issuer are available in the user tenant - just redirect them to the same
            // page within their tenant so that all config settings apply correctly.
            $wp = optional_param('wp', '', PARAM_BOOL); // Prevent recursion.
            if (!$wp) {
                \tool_tenant\manager::set_tenant_cookie($existingusertenantid);
                redirect(new \moodle_url($FULLME, ['wp' => 1]));
            }
        }
    }

    /**
     * Checks that the specific OAuth2 issue id is available in the current tenant
     * @param string $issuerid
     * @return bool
     */
    public static function issuer_available(string $issuerid): bool {
        return issuer_helper::issuer_available($issuerid, 0, 'oauth2');
    }
}

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
 * Class auth_oauth2_settings_form
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use tool_tenant\local\auth\oauth2\manager;
use tool_tenant\local\auth\issuer_helper;

/**
 * OAuth2 plugin settings for individual tenant
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class auth_oauth2_settings_form extends auth_settings_form_base {

    /** @var string */
    protected $auth = 'oauth2';
    /** @var array */
    protected $issuers = null;

    /**
     * All available issuers
     *
     * @return \core\oauth2\issuer[]
     */
    protected function get_all_issuers(): array {
        if ($this->issuers === null) {
            $this->issuers = [];
            foreach (\core\oauth2\api::get_all_issuers() as $issuer) {
                $this->issuers[$issuer->get('id')] = $issuer;
            }
        }
        return $this->issuers;
    }

    /**
     * Form definition
     */
    public function definition() {
        parent::definition();

        $mform = $this->_form;
        $mform->addElement('header', 'serviceshdr', get_string('pluginname', 'tool_oauth2'));
        $mform->addElement('static', 'servicestatic', '', get_string('oauth2availableforlogin', 'tool_tenant'));

        $issuers = $this->get_all_issuers();
        $tenantid = $this->optional_param('tenantid', 0, PARAM_INT);
        foreach ($issuers as $id => $issuer) {
            if ($issuer->is_configured() && !empty($issuer->get('showonloginpage')) &&
                    issuer_helper::issuer_available($issuer->get('id'), $tenantid, 'oauth2')) {
                $name = $issuer->get('name');
                $image = $issuer->get('image');
                if ($image) {
                    $name = \html_writer::empty_tag('img',
                        ['src' => s($image), 'alt' => '', 'width' => 24, 'height' => 24, 'class' => "mr-2"]) . s($name);
                }
                $mform->addElement('static', 'issuer_'.$id, '', $name);
            }
        }
        if (has_capability('moodle/site:config', \context_system::instance())) {
            $url = new \moodle_url('/admin/tool/oauth2/issuers.php');
            $adminstr = \html_writer::link($url, get_string('configureoauth2link', 'tool_tenant'));
            $mform->addElement('static', 'manageservices', '', $adminstr);
        }

        // Display locking / mapping of profile fields.
        $authplugin = get_auth_plugin($this->auth);
        $this->display_auth_lock_options($authplugin->authtype, $authplugin->userfields,
            get_string('auth_fieldlocks_help', 'auth'), false, false);
    }
}

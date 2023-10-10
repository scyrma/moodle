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
 * File for testing oauth2 authentication
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// This file does not need require_login, skip codechecker here.
// phpcs:disable moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../../../../config.php');
global $CFG, $PAGE, $OUTPUT;

// Behat test fixture only.
defined('BEHAT_SITE_RUNNING') || die('Only available on Behat test server');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/admin/tool/tenant/tests/fixtures/oauthlogin.php'));
$cachekey = 'auth_oauth2_testoauthlogin';

$endpoint = optional_param('endpoint', null, PARAM_ALPHANUM);
$email = optional_param('email', null, PARAM_EMAIL);
if ($endpoint === 'authorization' || ($endpoint === 'formsubmit' && !$email)) {
    $state = s(required_param('state', PARAM_RAW));
    $redirecturi = s(required_param('redirect_uri', PARAM_RAW));
    echo $OUTPUT->header();
    echo <<<EOT
<form action="" method="POST">
<input type="hidden" name="endpoint" value="formsubmit">
<input type="hidden" name="state" value="$state">
<input type="hidden" name="redirect_uri" value="$redirecturi">
<label for="email">Email</label>
<input type="text" name="email" id="email" size="30">
<input type="submit" value="Login">
</form>
EOT;
    echo $OUTPUT->footer();
    exit;
}
if ($endpoint === 'formsubmit') {
    // Authorisation request.
    set_config($cachekey, $email);
    $state = required_param('state', PARAM_RAW);
    $redirecturi = required_param('redirect_uri', PARAM_RAW);
    $url = new moodle_url($redirecturi, ['state' => $state, 'code' => 1]);
    redirect($url);
}

if ($endpoint === 'token') {
    echo json_encode(['access_token' => 1, 'refresh_token' => 1]);
    exit;
}

if ($endpoint === 'userinfo') {
    echo json_encode(['email' => $CFG->$cachekey] );
    exit;
}

throw new moodle_exception('invalidrequest');

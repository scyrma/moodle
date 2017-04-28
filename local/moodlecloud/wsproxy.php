<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Version information.
 *
 * @package   local_moodlecloud
 * @copyright 2017 Jordan Tomkinson <jordan@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);

define('WS_SERVER', true);

require('config.php');
require_once("$CFG->dirroot/webservice/rest/locallib.php");

if (isset($_POST['mcproxykey'])) {
    if ($_POST['mcproxykey'] != "kKHfHbMlRnEyTcwoXpxZ1tPcfBkqV1r7") {
        error_log("wsproxy request without a valid key");
        die("wsproxy request without a valid key");
    }else {
        unset($_POST['mcproxykey']);
    }
}

/*
if (!webservice_protocol_is_enabled('rest')) {
    header("HTTP/1.0 403 Forbidden");
    debugging('The server died because the web services or the REST protocol are not enable',
        DEBUG_DEVELOPER);
    die;
}
*/

class moodlecloudwsproxy extends webservice_rest_server {

  public function load_function_info() {
        if (empty($this->functionname)) {
            throw new invalid_parameter_exception('Missing function name');
        }

        // function must exist
        $function = external_api::external_function_info($this->functionname);

        // we have all we need now
        $this->function = $function;
  }

  public function run() {
    // set up exception handler first, we want to sent them back in correct format that
    // the other system understands
    // we do not need to call the original default handler because this ws handler does everything
    set_exception_handler(array($this, 'exception_handler'));

    // init all properties from the request data
    $this->parse_request();

    // authenticate user, this has to be done after the request parsing
    // this also sets up $USER and $SESSION
    //$server->authenticate_user();

    global $DB;
    //retrieve user link to the token
    $user = $DB->get_record('user', array('id' => 2, 'deleted' => 0), '*', MUST_EXIST);
    // setup user session to check capability
    \core\session\manager::set_user($user);

    // find all needed function info and make sure user may actually execute the function
    $this->load_function_info();

    // Log the web service request.
    /*$params = array(
        'other' => array(
            'function' => $this->functionname
        )
    );
    $event = \core\event\webservice_function_called::create($params);
    $event->set_legacy_logdata(array(SITEID, 'webservice', $this->functionname, '' , getremoteaddr() , 0, $this->userid));
    $event->trigger();
    */

    // finally, execute the function - any errors are catched by the default exception handler
    $this->execute();

    // send the results back in correct format
    $this->send_response();

    // session cleanup
    $this->session_cleanup();
  }
}

//$server = new moodlecloudwsproxy(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
$server = new moodlecloudwsproxy(WEBSERVICE_AUTHMETHOD_USERNAME);

// we will probably need a lot of memory in some functions
raise_memory_limit(MEMORY_EXTRA);

// set some longer timeout, this script is not sending any output,
// this means we need to manually extend the timeout operations
// that need longer time to finish
external_api::set_timeout();

$server->run();

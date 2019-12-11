<?php

require_once('../../config.php');

if (isset($USER->auth) && $USER->auth == "moodlecloud") {
    $auth = get_auth_plugin($USER->auth);
    $url = $auth->get_sso_url();
    redirect($url->out());
}else {
    $signupurl = get_config('auth_moodlecloud', 'signupurl');
    redirect($signupurl.'/en/portal/view/'.$dynamicsite);
}

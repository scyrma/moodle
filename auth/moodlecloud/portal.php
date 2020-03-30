<?php

require_once('../../config.php');

$gotoupgradetab = optional_param('gotoupgradetab', false, PARAM_BOOL);

if (isset($USER->auth) && $USER->auth == "moodlecloud") {
    $auth = get_auth_plugin($USER->auth);
    $url = $auth->get_sso_url($gotoupgradetab);
    redirect($url->out());
}else {
    $signupurl = get_config('auth_moodlecloud', 'signupurl');
    redirect($signupurl.'/en/portal/view/'.$dynamicsite);
}

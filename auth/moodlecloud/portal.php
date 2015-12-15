<?php

require_once('../../config.php');

if ($USER->auth === 'moodlecloud') {
    $auth = get_auth_plugin($USER->auth);
    $url = $auth->get_sso_url();
    redirect($url->out());
}

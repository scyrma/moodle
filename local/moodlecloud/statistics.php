<?php

define('AJAX_SCRIPT', true);
require_once(dirname(dirname(__DIR__)) . '/config.php');

$token = required_param('token', PARAM_ALPHANUM);

if (isset($CFG->statistics_tokens) && is_array($CFG->statistics_tokens)) {
    $validtokens = $CFG->statistics_tokens;
} else {
    $validtokens = array();
}

if (!in_array($token, $validtokens)) {
    throw new require_login_exception('Invalid user token');
}

echo json_encode(\local_moodlecloud\statistics::get_statistics());

<?php

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(__DIR__))) . '/config.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/registration/lib.php');

/* 1. Send user language to signup */
$admin = $DB->get_record('user', array('auth' => 'moodlecloud'));

$authplugin = get_auth_plugin($admin->auth);
if ($authplugin->authtype == 'moodlecloud') {
    $user_withouth_lang = clone($admin);
    $user_withouth_lang->lang = '';
    $authplugin->user_update($user_no_lang, $admin);
}

/* 2. Update user from signup to set country (if not already set) */
// update_user_record_by_id($admin->id);

/* 3. Register site with hub */
$huburl = HUB_MOODLEORGHUBURL;

$registrationmanager = new \registration_manager();

if ($hub = $registrationmanager->get_unconfirmedhub($huburl)) {
    $params = $registrationmanager->get_site_info($huburl);
    $params['token']    = $hub->token;
    $params['url']      = $CFG->wwwroot;

    $url = new moodle_url($huburl . '/local/hub/siteregistration.php', $params);
    $curl = new curl();
    $curl->get($url->out(false));
}


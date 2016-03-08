<?php

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(__DIR__))) . '/config.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/registration/lib.php');

use local_moodlecloud\tasks\register as register_adhoc_task;
use core\task\manager;

/* 1. Send user language to signup */
$admin = $DB->get_record('user', array('auth' => 'moodlecloud'));

$authplugin = get_auth_plugin($admin->auth);
if ($authplugin->authtype == 'moodlecloud') {
    $user_withouth_lang = clone $admin;
    $user_withouth_lang->lang = '';
    $authplugin->user_update($user_withouth_lang, $admin);
}

/* 2. Clear existing registration's data */
$DB->delete_records('registration_hubs', array('huburl' => HUB_MOODLEORGHUBURL));
$DB->delete_records('config_plugins', array('plugin' => 'hub'));
cache_helper::invalidate_by_definition('core', 'config', array(), 'core');

/* 3. Do the registration */
$task = new register_adhoc_task();
$task->execute(false);



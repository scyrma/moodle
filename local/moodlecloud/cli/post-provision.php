<?php

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(__DIR__))) . '/config.php');

use local_moodlecloud\tasks\register as register_adhoc_task;
use core\task\manager;

// The primary admin user should use moodlecloud authentication.
if ($user = get_admin()) {
    // Ensure that the auth for the primary admin is set to moodecloud.
    $user->auth = 'moodlecloud';

    // Ensure that the last access data is nullified.
    // This is used by the SSO first login procedure.
    $user->firstaccess = 0;
    $user->lastaccess = 0;
    $user->lastlogin = 0;
    $DB->update_record('user', $user);
}

// Setup the registration task.
manager::queue_adhoc_task(new register_adhoc_task());

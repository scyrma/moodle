<?php

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(__DIR__))) . '/config.php');

use local_moodlecloud\tasks\register as register_adhoc_task;
use local_moodlecloud\tasks\getairnotifierkey as getairnotifierkey_adhoc_task;
use core\task\manager;

// Setup the registration task.
manager::queue_adhoc_task(new register_adhoc_task());
// Setup the airnotifier task.
manager::queue_adhoc_task(new getairnotifierkey_adhoc_task());


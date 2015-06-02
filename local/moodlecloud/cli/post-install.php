<?php

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(__DIR__))) . '/config.php');

use local_moodlecloud\tasks\register as register_adhoc_task;
use core\task\manager;

var_dump(new register_adhoc_task());
manager::queue_adhoc_task(new register_adhoc_task());

<?php

define('CLI_SCRIPT', true);

require_once('../../config.php');

local_logging\statistics::log_statistics();

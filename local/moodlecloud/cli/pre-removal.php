<?php

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(__DIR__))) . '/config.php');
require_once($CFG->libdir. '/filelib.php');

@ini_set('display_errors', '1');
@ini_set('log_errors', '1');
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

if (core\hub\registration::is_registered()) {
    try {
        core\hub\registration::unregister(true, true);
    } catch (Exception $e) {
        echo "Unregistration of site failed: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "Site not registered\n";
}

<?php

define('CLI_SCRIPT', true);

use core\hub\api;
use core\hub\registration;

require_once(dirname(dirname(dirname(__DIR__))) . '/config.php');
require_once($CFG->libdir. '/filelib.php');

@ini_set('display_errors', '1');
@ini_set('log_errors', '1');
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

if (registration::is_registered()) {
    $hub = \Closure::bind(
        function() {
            return self::get_registration();
        },
        null,
        registration::class
    )();

    if (!$hub) {
        return true;
    }

    // Course unpublish went ok, unregister the site now.
    try {
        api::unregister_site();
    } catch (Exception $e) {
        echo "Unregistration of site failed: " . $e->getMessage() . "\n";
        exit(1);
    }

    $DB->delete_records('registration_hubs', array('id' => $hub->id));
} else {
    echo "Site not registered\n";
}

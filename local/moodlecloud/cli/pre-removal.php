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

$errors = [];
$messages = [];

// Remove airnotifier key
// d4985ad12ce3099b040a4773724cc6b3 is the default airnotifierkey given to us by Juan
$airnotifierkey = $CFG->airnotifieraccesskey;
if (empty($airnotifierkey) || $airnotifierkey == "d4985ad12ce3099b040a4773724cc6b3") {
    $messages[] = "MoodleCloud Airnotifier: site has no airnotifier key";
} else {
    $c = new curl;
    $c->setHeader(['X-AN-APP-NAME' => 'commoodlemoodlemobile']);
    $c->setHeader(['X-AN-APP-KEY' => $airnotifierkey]);
    $result = $c->delete("http://messages.moodle.net/api/v2/accesskeys/$airnotifierkey");

    if ($c->get_errno()) {
        $errors[] = 'MoodleCloud Airnotifier cURL error ' . $c->get_errno . ': ' . $result;
    }
}

// Hub unregister
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
        $errors[] = "Unregistration of site failed: " . $e->getMessage() . "\n";
    }

    $DB->delete_records('registration_hubs', array('id' => $hub->id));
} else {
    echo "Site not registered\n";
}

echo implode("\n", $messages);

if ($errors) {
    echo implode("\n", $errors);
    exit(1);
}

exit;

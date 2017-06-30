<?php

namespace local_moodlecloud\tasks;
use core\task\adhoc_task;
use core\task\manager;
use moodle_url;
use curl;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class getairnotifierkey extends adhoc_task {
    public function execute() {
        global $DB;

        $registered = $DB->count_records('registration_hubs', array('huburl' => HUB_MOODLEORGHUBURL, 'confirmed' => 1));

        // d4985ad12ce3099b040a4773724cc6b3 is the default airnotifierkey given to us by Juan

        // Check for valid DNS.
        if ($registered) {
            mtrace("Moodlecloud Airnotifier: site is registered on hub, proceeding");
            $airnotifierkey = $CFG->airnotifieraccesskey;
            if (empty($airnotifierkey) || $airnotifierkey == "d4985ad12ce3099b040a4773724cc6b3") {
                mtrace("Moodlecloud Airnotifier: site has no airnotifier key. Requesting");
                // setup $USER as the site admin.
                $USER = $DB->get_record('user', array('id' => 2, 'deleted' => 0), '*', MUST_EXIST);
                // setup airnotifier manager
                $manager = new \message_airnotifier_manager();
                // request key
                if ($key = $manager->request_accesskey()) {
                    set_config('airnotifieraccesskey', $key);
                    $msg = get_string('keyretrievedsuccessfully', 'message_airnotifier');
                } else {
                    set_config('airnotifieraccesskey', 'd4985ad12ce3099b040a4773724cc6b3');
                    $msg = get_string('errorretrievingkey', 'message_airnotifier');
                    manager::queue_adhoc_task(new getairnotifierkey());
                }
                mtrace("Moodlecloud Airnotifier: $msg");
            }else {
                mtrace("Moodlecloud Airnotifier: Airnotifier key alreaxy exists!");
            }
        } else {
            mtrace("Moodlecloud Airnotifier: site is not registered on hub yet. Queueing self again.");
            manager::queue_adhoc_task(new getairnotifierkey());
        }
    }

}

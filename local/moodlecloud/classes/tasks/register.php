<?php

namespace local_moodlecloud\tasks;
use core\task\adhoc_task;
use core\task\manager;
use moodle_url;
use curl;
use local_logging\logger;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/' . $CFG->admin . '/registration/lib.php');

class register extends adhoc_task {
    public function execute($wait_for_dns = true) {
        global $DB;

        if ($wait_for_dns) {
            // Delay execution of the task a little longer to give DNS more of a fighting chance.
            sleep(30);
        }

        $huburl = HUB_MOODLEORGHUBURL;

        // Check for valid DNS.
        if ($this->is_dns_valid($huburl)) {
            logger::log(get_class($this), [
                'eventname' => 'registration',
                'component' => 'local_moodlecloud',
                'other' => 'DNS is valid. Registering.',
            ], 'registration');
            mtrace("Moodlecloud Registration ({$huburl}): DNS is valid. Registering.");
            $hub = $DB->get_record('registration_hubs', array('huburl' => $huburl));
            if (!$hub) {
                logger::log(get_class($this), [
                    'eventname' => 'registration',
                    'component' => 'local_moodlecloud',
                    'other' => 'Hub not found. Configuring',
                ], 'registration');
                mtrace("Moodlecloud Registration ({$huburl}): Hub not found. Configuring");
                // We haven't created the hub at all yet.
                $this->configure($huburl);
            }

            $ret = $this->register($huburl);
            if (!$ret) {
                logger::log(get_class($this), [
                    'eventname' => 'registration',
                    'component' => 'local_moodlecloud',
                    'other' => 'Registration failed, queueing self again.',
                ], 'registration');

                manager::queue_adhoc_task(new register());
            }
        } else {
            logger::log(get_class($this), [
                'eventname' => 'registration',
                'component' => 'local_moodlecloud',
                'other' => 'DNS not yet valid. Queueing self again.',
            ], 'registration');
            mtrace("Moodlecloud Registration ({$huburl}): DNS not yet valid. Queueing self again.");
            manager::queue_adhoc_task(new register());
        }
    }

    public function configure($huburl) {
        global $DB;
        $registrationmanager = new \registration_manager();

        // update user from signup - fetch country
        $admin = update_user_record_by_id(2);

        // Ensure that the hub detailsare in place.
        $hub = new stdClass();
        $hub->token = $registrationmanager->get_site_secret_for_hub($huburl);
        $hub->secret = $hub->token;
        $hub->huburl = $huburl;
        $hub->hubname = 'Moodle.org';
        $hub->confirmed = 0;

        // Grab some useful items here.
        $cleanhuburl = clean_param($huburl, PARAM_ALPHANUMEXT);
        $site = get_site();

        // Set the default values.
        $sitename = format_string($site->fullname, true, array('context' => \context_course::instance(SITEID)));
        set_config('site_name_'             . $cleanhuburl, $sitename,                  'hub');

        // Set the site description.
        set_config('site_description_'      . $cleanhuburl, $sitename,                  'hub');

        $contactname = fullname($admin, true);
        set_config('site_contactname_'      . $cleanhuburl, $contactname,               'hub');

        set_config('site_contactemail_'     . $cleanhuburl, $admin->email,              'hub');
        set_config('site_privacy_'          . $cleanhuburl, HUB_SITENOTPUBLISHED,       'hub');

        // 0 = registrationcontactno.
        $contactable = 0;
        set_config('site_contactable_'      . $cleanhuburl, $contactable,               'hub');

        // Do not alert administrators - we handle upgrades anyway.
        $emailalert = 0;
        set_config('site_emailalert_'       . $cleanhuburl, $emailalert,                'hub');

        // Use the admin's country as site's country
        set_config('site_country_'          . $cleanhuburl, $admin->country,            'hub');

        // By default set this to the current language.
        set_config('site_language_'         . $cleanhuburl, explode('_', current_language())[0], 'hub');

        // Add the new hub details to the database.
        $hub->id = $registrationmanager->add_registeredhub($hub);
    }

    private function register($huburl) {
        global $CFG;

        $registrationmanager = new \registration_manager();

        // Now retrieve everything again.
        if ($hub = $registrationmanager->get_unconfirmedhub($huburl)) {
            $params = $registrationmanager->get_site_info($huburl);
            $params['token']    = $hub->token;
            $params['url']      = $CFG->wwwroot;

            $url = new moodle_url($huburl . '/local/hub/siteregistration.php', $params);
            $curl = new curl();
            $curl->setopt([
                'CURLOPT_SSL_VERIFYPEER' => 0,
                'CURLOPT_SSL_VERIFYHOST' => 0,
            ]);
            $ret = $curl->get($url->out(false));

            if ($errno = $curl->get_errno()) {
                logger::log(get_class($this), [
                    'eventname' => 'registration',
                    'component' => 'local_moodlecloud',
                    'other' => "Error $errno while registering: ". serialize($ret),
                ], 'registration');

                return false;
            } else {
                logger::log(get_class($this), [
                    'eventname' => 'registration',
                    'component' => 'local_moodlecloud',
                    'other' => 'Registration successful: '. serialize($ret),
                ], 'registration');

                return true;
            }
        }
    }

    private function is_dns_valid($huburl) {
        global $CFG;
        // Grab the HOST part of the hub.
        $fqdn = parse_url($CFG->wwwroot, PHP_URL_HOST);

        // Check for DNS records.
        $somefound = false;
        if ($records = dns_get_record($fqdn)) {
            foreach ($records as $record) {
                if ($record['host'] === $fqdn) {
                    // This is very specific to moodlecloud.
                    // Each host should be CNAMEd and should not point to the signup ELB.
                    if ($record['type'] === 'CNAME' && strpos($record['target'], '-signup-') === false) {
                        $somefound = true;
                    }
                }
            }
        }

        return $somefound;
    }
}

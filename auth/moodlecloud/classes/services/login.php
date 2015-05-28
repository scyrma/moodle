<?php

namespace auth_moodlecloud\services;

defined('MOODLE_INTERNAL') || die();

use auth_moodlecloud\client;
use auth_moodlecloud\service;

class login extends service {
    public static function target() {
        return 'checklogin';
    }

    protected static function define_parameters() {
        return array(
                'username' => array(
                        'required'      => true,
                        'type'          => 'string',
                    ),
                'password' => array(
                        'required'      => true,
                        'type'          => 'string',
                    ),
            );
    }

    /**
     * @inheritdoc
     * @return bool
     */
    public static function normalise_response($response) {
        if (!$response) {
            // Service failure of some kind. Unable to complete login.
            // TODO add some kind of information about this service failure.
            return false;
        }
        else if ($response->getStatusCode() === 200) {
            return true;
        }
        else if ($response->getStatusCode() === 404) {
            // User was not found.
            return false;
        }
        else if ($response->getStatusCode() >= 500) {
            // This was some kind of service failure.
            return false;
        }

        // Fallback to returning false.
        return false;
    }
}

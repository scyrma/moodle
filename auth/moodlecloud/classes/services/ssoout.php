<?php

namespace auth_moodlecloud\services;

defined('MOODLE_INTERNAL') || die();

use auth_moodlecloud\client;
use auth_moodlecloud\service;

class ssoout extends service {

    public static function target() {
        return 'beginSSOIn';
    }

    protected static function define_parameters() {
        return array(
            );
    }

    /**
     * @inheritdoc
     * @return string
     */
    public static function normalise_response($response) {
        if (!$response) {
            // Service failured - just return early.
            return null;
        }
        else if ($response->getStatusCode() === 200) {
            // Data was returned successfully. Process it.
            $responsedata = $response->json();
            if (isset($responsedata['target'])) {
                return $responsedata['target'];
            }
        }

        return null;
    }

}

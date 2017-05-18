<?php

namespace auth_moodlecloud\services;

defined('MOODLE_INTERNAL') || die();

use auth_moodlecloud\client;
use auth_moodlecloud\service;

class userinfo extends service {
    public static function target() {
        return 'userinfo';
    }

    protected static function define_parameters() {
        return array(
                'username' => array(
                        'required'      => true,
                        'type'          => 'string',
                    ),
            );
    }

    public static function normalise_response($response) {
        // The userinfo always returns an array.
        $data = array();

        if (!$response) {
            // Service failured - just return early.
            return $data;
        }
        else if ($response->getStatusCode() === 200) {
            // Data was returned successfully. Process it.
            $responsedata = $response->json();
            foreach ($responsedata as $key => $value) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}

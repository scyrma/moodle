<?php

namespace auth_moodlecloud;

defined('MOODLE_INTERNAL') || die();

class service {

    public static function verify_parameters($parameters) {
        $defined = static::define_parameters();
        foreach ($parameters as $key => $value) {
            if (!isset($defined[$key])) {
                // This key should not exist.
                throw new \coding_exception('Invalid API Call with key "' . $key . '".');
            }
            if ($defined[$key]['type'] != gettype($value)) {
                // This value is the wrong type.
                throw new \coding_exception('Invalid API Call with key "' . $key . '": Incorrect type: ' .
                    $defined[$key]['type'] . ':' . gettype($value));
            }
            unset($defined[$key]);
        }

        foreach ($defined as $key => $value) {
            if ($value['required']) {
                // This value is required but not specified.
                throw new \coding_exception('Invalid API Call. Missing key "' . $key . '".');
            }
        }
    }

    protected static function define_parameters() {
        return array();
    }

    /**
     * Normalise the response from the webservice for the specified
     * MoodleCloud service.
     *
     * The default normaliser provides a boolean response determined by a
     * healthy 200 response from the webservice.
     *
     * @param $response mixed
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

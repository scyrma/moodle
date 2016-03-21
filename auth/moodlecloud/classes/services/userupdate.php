<?php

namespace auth_moodlecloud\services;

defined('MOODLE_INTERNAL') || die();

use auth_moodlecloud\client;
use auth_moodlecloud\service;

class userupdate extends service {
    public static function target() {
        return 'userupdate';
    }

    protected static function define_parameters() {
        return array(
                'firstname' => array(
                        'required'      => false,
                        'type'          => 'string',
                    ),
                'lastname' => array(
                        'required'      => false,
                        'type'          => 'string',
                    ),
                'email' => array(
                        'required'      => false,
                        'type'          => 'string',
                    ),
                'timezone' => array(
                        'required'      => false,
                        'type'          => 'string',
                    ),
                'country' => array(
                        'required'      => false,
                        'type'          => 'string',
                    ),
                'lang' => array(
                        'required'      => false,
                        'type'          => 'string',
                    ),
            );
    }

}

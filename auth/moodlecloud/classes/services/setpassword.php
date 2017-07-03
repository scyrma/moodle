<?php

namespace auth_moodlecloud\services;

defined('MOODLE_INTERNAL') || die();

use auth_moodlecloud\client;
use auth_moodlecloud\service;

class setpassword extends service {
    public static function target() {
        return 'setpassword';
    }

    protected static function define_parameters() {
        return array(
                'newpassword' => array(
                        'required'      => true,
                        'type'          => 'string',
                    ),
            );
    }
}

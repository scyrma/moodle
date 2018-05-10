<?php

namespace auth_moodlecloud\services;

defined('MOODLE_INTERNAL') || die();

use auth_moodlecloud\client;
use auth_moodlecloud\service;

class userexists extends service {
    public static function target() {
        return 'userexists';
    }

    public static function define_parameters() {
        return array(
                'username' => array(
                        'required'      => true,
                        'type'          => 'string',
                    ),
            );
    }
}

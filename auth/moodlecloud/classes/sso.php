<?php

namespace auth_moodlecloud;

defined('MOODLE_INTERNAL') || die();

class sso {

    private static $cookiename = 'MoodleCloudSSO';

    public static function require_login() {
        global $DB;

        $token = optional_param('mcssotoken', '', PARAM_RAW);
        // Only allow users with moodlecloud auth to log in.
        $admin = $DB->get_record('user', array('auth' => 'moodlecloud'));

        if (!empty($token)) {
            self::attempt_token_login($admin, $token);
        } else {
            self::attempt_cookie_login($admin);
        }
    }

    private static function attempt_cookie_login($admin) {
        global $_COOKIE;

        // Only allow the admin user in this way on their first login for the moment.
        // In the future we will create a more established SSO system.
        if (!empty($admin->firstaccess) || !empty($admin->lastaccess) || !empty($admin->lastlogin)) {
            return;
        }

        if (isset($_COOKIE[self::$cookiename])) {
            $cookievalue = $_COOKIE[self::$cookiename];

            // Unset it now.
            $ssodomain  = get_config('auth_moodlecloud', 'ssodomain');
            setcookie(self::$cookiename, $cookievalue, time() - 3600, '/');

            self::attempt_token_login($admin, $cookievalue);
        }
    }

    private static function attempt_token_login($admin, $token) {
        $successfullogin = helper::call('ssoin', array(
                    'publictoken' => $token,
                ));

        if ($successfullogin) {
            $admin = update_user_record_by_id($admin->id);
            complete_user_login($admin);
            return true;
        }
    }

}

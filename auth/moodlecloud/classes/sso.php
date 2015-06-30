<?php

namespace auth_moodlecloud;

defined('MOODLE_INTERNAL') || die();

class sso {

    private static $cookiename = 'MoodleCloudSSO';

    public static function require_login() {
        self::attempt_cookie_login();
    }

    private static function attempt_cookie_login() {
        global $DB, $_COOKIE;

        $attemptlogin = false;

        // Only allow users with moodlecloud auth to log in.
        $admin = $DB->get_record('user', array('auth' => 'moodlecloud'));

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

            // Check the value of the cookie against the SSO source.
            $attemptlogin = helper::call('ssoin', array(
                    'publictoken'       => $cookievalue,
                ));
        }

        if ($attemptlogin) {
            complete_user_login($admin);
            update_user_record_by_id($admin->id);
            return true;
        }
    }

}

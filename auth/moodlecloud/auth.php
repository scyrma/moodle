<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Authentication Plugin: MoodleCloud
 *
 * Checks against an external database.
 *
 * @package    auth_moodlecloud
 * @author     Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');
require_once(__DIR__ . '/vendor/autoload.php');

/**
 * MoodleCloud authentication plugin.
 */
class auth_plugin_moodlecloud extends auth_plugin_base {

    function __construct() {
        $this->authtype = 'moodlecloud';
        $this->config = new \stdClass();
        $this->config->field_lock_firstname     = 'unlockedifempty';
        $this->config->field_lock_lastname      = 'unlockedifempty';
        $this->config->field_lock_email         = 'unlockedifempty';
        $this->config->field_lock_timezone      = 'unlockedifempty';
        $this->config->field_lock_country       = 'unlockedifempty';
        $this->config->field_lock_lang          = 'unlockedifempty';
        $this->config->field_lock_phonenumber   = false;

        $this->config->field_updatelocal_firstname     = 'onlogin';
        $this->config->field_updatelocal_lastname      = 'onlogin';
        $this->config->field_updatelocal_email         = 'onlogin';
        $this->config->field_updatelocal_timezone      = 'onlogin';
        $this->config->field_updatelocal_country       = 'onlogin';
        $this->config->field_updatelocal_lang          = 'onlogin';
        $this->config->field_updatelocal_phonenumber   = false;

        // Add timezone to the fields that can be updated from external sources
        $this->userfields[] = 'timezone';
    }

    /**
     * @inheritdoc
     */
    function user_login($username, $password) {
        global $DB;

        // Fetch the user by username.
        $user = $DB->get_record('user', array('username' => $username));
        if ($user && $user->auth === 'moodlecloud') {
            return auth_moodlecloud\helper::call('login', array(
                    'username'          => $username,
                    'password'          => $password,
                ));
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    function get_userinfo($username) {
        return auth_moodlecloud\helper::call('userinfo', array(
                'username'          => $username,
            ));
    }

    /**
     * @inheritdoc
     */
    function user_update_password($user, $newpassword) {
        return auth_moodlecloud\helper::call('setpassword', array(
                'newpassword'       => $newpassword,
            ));
    }

    /**
     * @inheritdoc
     */
    function user_exists($username) {
        return auth_moodlecloud\helper::call('userexists', array(
                'username'          => $username,
            ));
    }

    /**
     * @inheritdoc
     */
    function is_internal() {
        return false;
    }

    /**
     * @inheritdoc
     */
    function can_change_password() {
        return true;
    }

    /**
     * @inheritdoc
     */
    function can_reset_password() {
        return true;
    }

    /**
     * @inheritdoc
     */
    function user_update($olduser, $newuser) {
        if ($olduser->auth !== $newuser->auth) {
            throw new \moodle_exception('Cannot change authentication for primary administrator');
        }

        $update = array();

        // First Name
        if ($olduser->firstname !== $newuser->firstname) {
            $update['firstname'] = $newuser->firstname;
        }

        // Last Name
        if ($olduser->lastname !== $newuser->lastname) {
            $update['lastname'] = $newuser->lastname;
        }

        // Email
        if ($olduser->email !== $newuser->email) {
            $update['email'] = $newuser->email;
        }

        // Timezone
        if ($olduser->timezone !== $newuser->timezone) {
            $update['timezone'] = $newuser->timezone;
        }

        // Country
        if ($olduser->country !== $newuser->country) {
            $update['country'] = $newuser->country;
        }

        // Language
        if ($olduser->lang !== $newuser->lang) {
            $update['lang'] = $newuser->lang;
        }

        if (count($update)) {
            return auth_moodlecloud\helper::call('userupdate', $update);
        }

        // If no change was made, we should return true.
        return true;
    }
}

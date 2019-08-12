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
 * Class add_user_form
 *
 * @package     tool_tenant
 * @copyright   2018 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant\form;

use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/webservice/lib.php');

/**
 * Class add_user_form
 *
 * @package     tool_tenant
 * @copyright   2018 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_user_form extends modal_form {

    /** @var \stdClass $user The user object we are updating. */
    protected $user = false;

    /**
     * User being edited or null if this is "add user" form
     * @return \stdClass|null
     */
    public function get_user() {
        if ($this->user === false) {
            $id = $this->optional_param('id', 0, PARAM_INT);
            if ($id) {
                $this->user = \core_user::get_user($id);
            } else {
                $this->user = null;
            }
        }
        return $this->user;
    }

    /**
     * Tenant where the user is being edited/created
     *
     * @return int
     */
    protected function get_tenant_id() {
        if ($user = $this->get_user()) {
            return tenancy::get_tenant_id($this->get_user()->id);
        } else {
            return $this->optional_param('tenantid', 0, PARAM_INT) ?: tenancy::get_tenant_id();
        }
    }

    /**
     * Form definition
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // If we are not updating a user record then set a dummy user object.
        $this->get_user();
        $user = $this->get_user() ?: $this->setup_new_user();
        $userid = $user->id;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', \core_user::get_property_type('id'));

        $mform->addElement('hidden', 'tenantid');
        $mform->setType('tenantid', PARAM_INT);

        $mform->addElement('header', 'moodle', get_string('general'));

        $purpose = user_edit_map_field_purpose($userid, 'username');
        $mform->addElement('text', 'username', get_string('username'), 'size="20"' . $purpose);
        $mform->addRule('username', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('username', 'username', 'auth');
        $mform->setType('username', PARAM_RAW);

        $mform->addElement('checkbox', 'createpassword', get_string('createpassword', 'auth'));

        if (!empty($CFG->passwordpolicy)) {
            $mform->addElement('static', 'passwordpolicyinfo', '', print_password_policy());
        }
        $mform->addElement('passwordunmask', 'newpassword', get_string('newpassword'), 'size="20"');
        $mform->addHelpButton('newpassword', 'newpassword');
        $mform->setType('newpassword', \core_user::get_property_type('password'));
        $mform->disabledIf('newpassword', 'createpassword', 'checked');

        // Check if the user has active external tokens.
        if ($userid and empty($CFG->passwordchangetokendeletion)) {
            if ($tokens = \webservice::get_active_tokens($userid)) {
                $services = '';
                foreach ($tokens as $token) {
                    $services .= format_string($token->servicename) . ',';
                }
                $services = get_string('userservices', 'webservice', rtrim($services, ','));
                $mform->addElement('advcheckbox', 'signoutofotherservices', get_string('signoutofotherservices'), $services);
                $mform->addHelpButton('signoutofotherservices', 'signoutofotherservices');
                $mform->disabledIf('signoutofotherservices', 'newpassword', 'eq', '');
                $mform->setDefault('signoutofotherservices', 1);
            }
        }

        $mform->addElement('advcheckbox', 'preference_auth_forcepasswordchange', get_string('forcepasswordchange'));
        $mform->addHelpButton('preference_auth_forcepasswordchange', 'forcepasswordchange');
        $mform->disabledIf('preference_auth_forcepasswordchange', 'createpassword', 'checked');

        // Shared fields.
        useredit_shared_definition($mform, $this->get_editor_options(), $this->get_filemanager_options(), $user);

        // Next the customisable profile fields.
        profile_definition($mform, $userid);

        if (permission::can_assign_tenant_admin($this->get_tenant_id())) {
            $mform->addElement('header', 'tenantoptions', get_string('tenantadministration', 'tool_tenant'));
            $mform->addElement('checkbox', 'tenantadmin', get_string('admin', 'tool_tenant'),
                get_string('tenantadministrator', 'tool_tenant'));
        }

        // Add the buttons just in case we ever use this form not inside a modal.
        $this->add_action_buttons();
    }

    /**
     * Validation of form elements.
     *
     * @param  array $usernew The new user details.
     * @param  array $files Files related to the user.
     * @return array An array of errors if the validation fails.
     */
    public function validation($usernew, $files) {
        global $CFG, $DB;

        $usernew = (object)$usernew;
        $usernew->username = trim($usernew->username);

        $user = $this->get_user();
        $err = array();

        if (!empty($usernew->newpassword)) {
            $errmsg = ''; // Prevent eclipse warning.
            if (!check_password_policy($usernew->newpassword, $errmsg)) {
                $err['newpassword'] = $errmsg;
            }
        } else if (!$user && !$usernew->createpassword) {
            $err['newpassword'] = get_string('required');
        }

        if (empty($usernew->username)) {
            // Might be only whitespace.
            $err['username'] = get_string('required');
        } else if (!$user or $user->username !== $usernew->username) {
            // Check new username does not exist.
            if ($DB->record_exists('user', array('username' => $usernew->username, 'mnethostid' => $CFG->mnet_localhost_id))) {
                $err['username'] = get_string('usernameexists');
            }
            // Check allowed characters.
            if ($usernew->username !== \core_text::strtolower($usernew->username)) {
                $err['username'] = get_string('usernamelowercase');
            } else {
                if ($usernew->username !== \core_user::clean_field($usernew->username, 'username')) {
                    $err['username'] = get_string('invalidusername');
                }
            }
        }

        if (!$user or (isset($usernew->email) && $user->email !== $usernew->email)) {
            if (!validate_email($usernew->email)) {
                $err['email'] = get_string('invalidemail');
            } else if (empty($CFG->allowaccountssameemail)
                    and $DB->record_exists('user', array('email' => $usernew->email, 'mnethostid' => $CFG->mnet_localhost_id))) {
                $err['email'] = get_string('emailexists');
            }
        }

        // Next the customisable profile fields.
        $err += profile_validation($usernew, $files);

        if (count($err) == 0) {
            return [];
        } else {
            return $err;
        }
    }

    /**
     * Check access
     */
    public function require_access() {
        $user = $this->get_user();
        if ($user) {
            permission::require_can_update_user($user);
        } else {
            permission::require_can_create_users($this->optional_param('tenantid', 0, PARAM_INT));
        }
    }

    /**
     * Process form submission
     *
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
        global $CFG, $DB;

        $tenantid = $this->get_tenant_id();
        $tenantadmin = isset($data->tenantadmin) && ($data->tenantadmin == 1);

        $usercreated = false;
        $authtype = 'manual';

        $data->timemodified = time();
        $createpassword = false;
        $olduser = null;

        if (!$data->id) {
            $olduser = $this->setup_new_user(); // Set the old user details.
            $createpassword = !empty($data->createpassword);
            unset($data->createpassword);
            $data = file_postupdate_standard_editor($data, 'description', $this->get_editor_options(), null, 'user', 'profile',
                    null);
            $data->mnethostid = $CFG->mnet_localhost_id;
            $data->confirmed = 1;
            $data->timecreated = time();
            if ($createpassword or empty($data->newpassword)) {
                $data->password = '';
            } else {
                $data->password = hash_internal_user_password($data->newpassword);
            }
            $data->id = user_create_user($data, false, false);
            $usercreated = true;
        } else {
            $olduser = $this->get_user();
            $usercontext = \context_user::instance($data->id);
            $data = file_postupdate_standard_editor($data, 'description', $this->get_editor_options($usercontext), $usercontext,
                    'user', 'profile', 0);
            user_update_user($data, false, false);

            // Set new password if specified.
            if (!empty($data->newpassword)) {
                $authplugin = get_auth_plugin($authtype);
                if ($authplugin->can_change_password()) {
                    if (!$authplugin->user_update_password($data, $data->newpassword)) {
                        print_error('cannotupdatepasswordonextauth', '', '', $data->auth);
                    }
                    unset_user_preference('create_password', $data); // Prevent cron from generating the password.

                    if (!empty($CFG->passwordchangelogout)) {
                        // We can use SID of other user safely here because they are unique,
                        // the problem here is we do not want to logout admin here when changing own password.
                        \core\session\manager::kill_user_sessions($data->id, session_id());
                    }
                    if (!empty($data->signoutofotherservices)) {
                        \webservice::delete_user_ws_tokens($data->id);
                    }
                }
            }
        }

        // Update preferences.
        useredit_update_user_preference($data);
        useredit_update_bounces($olduser, $data);
        useredit_update_trackforums($olduser, $data);

        // Save custom profile fields data.
        profile_save_data($data);
        // Get the image file for saving further down.
        $imagefile = $data->imagefile;
        $interests = $data->interests ?? null;

        // Reload from db.
        $data = $DB->get_record('user', array('id' => $data->id));

        if ($createpassword) {
            setnew_password_and_mail($data);
            unset_user_preference('create_password', $data);
            set_user_preference('auth_forcepasswordchange', 1, $data);
        }

        $data->imagefile = $imagefile;
        \core_user::update_picture($data, $this->get_filemanager_options());
        if (isset($interests)) {
            useredit_update_interests($data, $interests);
        }

        // Trigger update/create event, after all fields are stored.
        if ($usercreated) {
            \core\event\user_created::create_from_userid($data->id)->trigger();
        } else {
            \core\event\user_updated::create_from_userid($data->id)->trigger();
        }

        $tmanager = new \tool_tenant\manager();

        if ($usercreated) {
            $tmanager->allocate_user($data->id, $tenantid, 'tool_tenant', 'manual');
        }

        if (permission::can_assign_tenant_admin($tenantid)) {
            if ($tenantadmin) {
                $tmanager->assign_tenant_admin_roles([$data->id], $tenantid);
            } else {
                $tmanager->unassign_tenant_admin_roles([$data->id], $tenantid);
            }
        }
    }

    /**
     * Returns the editor options for this form.
     *
     * @param \context $context The context to be used with the editor.
     * @return array editor options.
     */
    protected function get_editor_options(\context $context = null) : array {
        return [
            'maxfiles' => 0,
            'maxbytes' => 0,
            'trusttext' => false,
            'forcehttps' => false,
            'context' => (isset($context)) ? $context : $this->get_form_context()
        ];
    }

    /**
     * Returns the filemanager options for this form.
     *
     * @return array filemanager options.
     */
    protected function get_filemanager_options() : array {
        return [
            'filetypes' => '*',
            'maxbytes' => 0,
            'subdirs' => 0,
            'maxfiles' => 1
        ];
    }

    /**
     * Returns a user object for the creation of a new user.
     *
     * @return \stdClass The user object.
     */
    protected function setup_new_user() : \stdClass {
        return (object) [
            'id' => -1,
            'auth' => 'manual',
            'confirmed' => 1,
            'deleted' => 0,
            'timezone' => 99
        ];
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_modal() {
        global $CFG, $DB, $OUTPUT;

        $data = (object)$this->_ajaxformdata;
        if (!empty($data->id)) {
            $context = \context_user::instance($data->id);
            $tenantid = $data->tenantid;
            $data = $this->get_user();

            $fs = get_file_storage();
            $hasuploadedpicture = ($fs->file_exists($context->id, 'user', 'icon', 0, '/', 'f2.png')
                    || $fs->file_exists($context->id, 'user', 'icon', 0, '/', 'f2.jpg'));
            if (!empty($data->picture) && $hasuploadedpicture) {
                $data->currentpicture = $OUTPUT->user_picture($data, array('courseid' => SITEID, 'size' => 64));
            } else {
                $data->currentpicture = get_string('none');
            }
            $data = file_prepare_standard_editor($data, 'description', $this->get_editor_options($context), $context, 'user',
                    'profile', 0);
            // Load user profile field data and add it to the user object.
            profile_load_data($data);
            // Get user tags for interests.
            $data->interests = \core_tag_tag::get_item_tags_array('core', 'user', $data->id);
            $data->tenantid = $tenantid;
            $data->tenantadmin = \tool_tenant\manager::is_tenant_admin($tenantid, $data->id);
        }
        $this->set_data($data);
    }
}

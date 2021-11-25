<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Class add_user_form
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Adrian Greeve
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use core_form\dynamic_form;
use tool_tenant\config;
use tool_tenant\manager;
use tool_tenant\permission;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Class add_user_form
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Adrian Greeve
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class add_user_form extends dynamic_form {

    /** @var \stdClass $user The user object we are updating. */
    protected $user = false;

    /**
     * User being edited or null if this is "add user" form
     * @return \stdClass|null
     */
    public function get_user(): ?\stdClass {
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
            return tenancy::get_actual_tenant_id($this->get_user()->id);
        } else {
            return $this->optional_param('tenantid', 0, PARAM_INT) ?: tenancy::get_tenant_id();
        }
    }

    /**
     * Form definition
     */
    public function definition() {
        global $CFG;

        // Make sure all config values are from the user's tenant. This form is only used in modal, we don't need to
        // worry about resetting the tenant back in the end.
        config::push_for_tenant($this->get_tenant_id());

        $mform = $this->_form;

        // If we are not updating a user record then set a dummy user object.
        $this->get_user();
        $user = $this->get_user() ?: $this->setup_new_user();
        $userid = $user->id > 0 ? $user->id : 0;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', \core_user::get_property_type('id'));

        $mform->addElement('hidden', 'tenantid');
        $mform->setType('tenantid', PARAM_INT);

        $mform->addElement('header', 'moodle', get_string('general'));
        $auths = \core_component::get_plugin_list('auth');
        $enabled = get_string('pluginenabled', 'core_plugin');
        $disabled = get_string('plugindisabled', 'core_plugin');
        $authoptions = array($enabled => array(), $disabled => array());
        $cannotchangepass = array();
        $cannotchangeusername = array();
        foreach ($auths as $auth => $unused) {
            $authinst = get_auth_plugin($auth);

            if (!$authinst->is_internal()) {
                $cannotchangeusername[] = $auth;
            }

            $passwordurl = $authinst->change_password_url();
            if (!($authinst->can_change_password() && empty($passwordurl))) {
                if (!($userid < 1 and $authinst->is_internal())) {
                    // This is unlikely but we can not create account without password
                    // when plugin uses passwords, we need to set it initially at least.
                    $cannotchangepass[] = $auth;
                }
            }
            if (is_enabled_auth($auth)) {
                $authoptions[$enabled][$auth] = get_string('pluginname', "auth_{$auth}");
            } else {
                $authoptions[$disabled][$auth] = get_string('pluginname', "auth_{$auth}");
            }
        }
        // Tenant name.
        if (permission::can_switch_tenant()) {
            $name = \tool_tenant\tenancy::get_tenant_name_from_id($this->get_tenant_id());
            $mform->addElement('static', 'tenantname', get_string('tenant', 'tool_tenant'),
                $name);
        }
        // This form is used to add and edit other users, we disable autocomplete.
        $purpose = user_edit_map_field_purpose($userid, 'username');
        $mform->addElement('text', 'username', get_string('username'), 'size="20"' . $purpose);
        $mform->addHelpButton('username', 'username', 'auth');
        $mform->setType('username', PARAM_RAW);
        // Disable username for existing users if cannot change username.
        if ($userid > 0) {
            $mform->disabledIf('username', 'auth', 'in', $cannotchangeusername);
        }
        // Only show this if you are allowed to change auth method.
        if (permission::can_change_user_auth_method($userid)) {
            $mform->addElement('selectgroups', 'auth', get_string('chooseauthmethod', 'auth'), $authoptions);
            $mform->addHelpButton('auth', 'chooseauthmethod', 'auth');
        } else {
            $methods = array_values($authoptions)[0] + array_values($authoptions)[1];
            $mform->addElement('static', 'authstatic', get_string('authmethod', 'tool_tenant'), $methods[$user->auth]);
        }
        if (permission::can_suspend_user($user, $this->get_tenant_id())) {
            $mform->addElement('advcheckbox', 'suspended', get_string('suspended', 'auth'));
            $mform->addHelpButton('suspended', 'suspended', 'auth');
        }
        // Only show this if you are allowed to change auth method
        // and the auth method supports password.
        if (permission::can_change_user_auth_method($userid) || !in_array($user->auth, $cannotchangepass)) {
            $mform->addElement('checkbox', 'createpassword', get_string('createpassword', 'auth'));
        }
        $mform->disabledIf('createpassword', 'auth', 'in', $cannotchangepass);

        if (!empty($CFG->passwordpolicy)) {
            $mform->addElement('static', 'passwordpolicyinfo', '', print_password_policy());
        }
        if (permission::can_change_user_auth_method($userid) || !in_array($user->auth, $cannotchangepass)) {
            $purpose = user_edit_map_field_purpose($userid, 'password');
            $mform->addElement('passwordunmask', 'newpassword', get_string('newpassword'), 'size="20"' . $purpose);
            $mform->addHelpButton('newpassword', 'newpassword');
            $mform->setType('newpassword', \core_user::get_property_type('password'));
            $mform->disabledIf('newpassword', 'auth', 'in', $cannotchangepass);
        }
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

        // Next the customisable profile fields (userid may be -1 if this is a new user).
        profile_definition($mform, $userid > 0 ? $userid : 0);

        if (permission::can_assign_tenant_admin($this->get_tenant_id())) {
            $mform->addElement('header', 'tenantoptions', get_string('tenantadministration', 'tool_tenant'));
            $mform->addElement('checkbox', 'tenantadmin', get_string('admin', 'tool_tenant'),
                get_string('tenantadministrator', 'tool_tenant'));
        }
    }

    /**
     * Extend the form definition after the data has been parsed.
     */
    public function definition_after_data() {
        $mform = $this->_form;
        if ($user = $this->get_user()) {
            // Disable fields that are locked by auth plugins.
            $fields = get_user_fieldnames();
            $authplugin = get_auth_plugin($user->auth);
            $customfields = $authplugin->get_custom_user_profile_fields();
            $customfieldsdata = profile_user_record($user->id, false);
            $fields = array_merge($fields, $customfields);
            foreach ($fields as $field) {
                if ($field === 'description') {
                    // Hard coded hack for description field. See MDL-37704 for details.
                    $formfield = 'description_editor';
                } else {
                    $formfield = $field;
                }
                if (!$mform->elementExists($formfield)) {
                    continue;
                }

                // Get the original value for the field.
                if (in_array($field, $customfields)) {
                    $key = str_replace('profile_field_', '', $field);
                    $value = isset($customfieldsdata->{$key}) ? $customfieldsdata->{$key} : '';
                } else {
                    $value = $user->{$field};
                }

                $configvariable = 'field_lock_' . $field;
                if (isset($authplugin->config->{$configvariable})) {
                    if ($authplugin->config->{$configvariable} === 'locked') {
                        $mform->hardFreeze($formfield);
                        $mform->setConstant($formfield, $value);
                    } else if ($authplugin->config->{$configvariable} === 'unlockedifempty' and $value != '') {
                        $mform->hardFreeze($formfield);
                        $mform->setConstant($formfield, $value);
                    }
                }
            }

            // Require password for new users.
            if ($user->id > 0) {
                if ($mform->elementExists('createpassword')) {
                    $mform->removeElement('createpassword');
                }
            }

            if ($user->id > 0 and is_mnet_remote_user($user)) {
                // Only local accounts can be suspended.
                if ($mform->elementExists('suspended')) {
                    $mform->removeElement('suspended');
                }
            }

            profile_definition_after_data($mform, $user->id);
        } else {
            profile_definition_after_data($mform, 0);
        }
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
        $auth = isset($usernew->auth) ? get_auth_plugin($usernew->auth) : get_auth_plugin('manual');
        if (!empty($usernew->newpassword)) {
            $errmsg = ''; // Prevent eclipse warning.
            if (!check_password_policy($usernew->newpassword, $errmsg)) {
                $err['newpassword'] = $errmsg;
            }
        } else if (!$user && !isset($usernew->createpassword) && $auth->is_internal()) {
            $err['newpassword'] = get_string('required');
        }

        if (empty($usernew->username) && $auth->is_internal()) {
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
    public function check_access_for_dynamic_submission(): void {
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
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
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
            // Mark user tenant so that event observer to user_created event allocates him.
            manager::preallocate_new_user($data, $tenantid, 'tool_tenant', 'manual');
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
                        throw new \moodle_exception('cannotupdatepasswordonextauth', '', '', $data->auth);
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

        if (permission::can_assign_tenant_admin($tenantid)) {
            $tmanager = new \tool_tenant\manager();
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
            'context' => (isset($context)) ? $context : $this->get_context_for_dynamic_submission()
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
            'id' => -1, // Force to display preferred language on new user.
            'auth' => 'manual',
            'confirmed' => 1,
            'deleted' => 0,
            'timezone' => 99,
            'suspended' => 0
        ];
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        global $OUTPUT;

        if ($data = $this->get_user()) {
            $context = \context_user::instance($data->id);

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
            $data->tenantadmin = \tool_tenant\manager::is_tenant_admin($this->get_tenant_id(), $data->id);
        } else {
            $data = $this->setup_new_user();
            unset($data->id);
        }
        $data->tenantid = $this->get_tenant_id();
        $this->set_data($data);
    }

    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'id' => $id,
            'tenantid' => $this->get_tenant_id(),
        ]);
    }
}

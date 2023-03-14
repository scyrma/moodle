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
 * Class auth_settings_form_base
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use core_form\dynamic_form;
use core_text;
use lang_string;
use moodle_url;
use tool_tenant\config;
use tool_tenant\permission;
use tool_tenant\tenancy;

/**
 * Auth plugin settings for individual tenant (base class)
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class auth_settings_form_base extends dynamic_form {

    /** @var string name of the plugin, must be defined in child classes */
    protected $auth = null;

    /**
     * Add element for locking a user field to the moodleform
     *
     * Does the same as the {@see \display_auth_lock_options()} but for moodleform instead of for the admin settings
     *
     * @param string $auth
     * @param array $userfields
     * @param string $helptext
     * @param bool $mapremotefields
     * @param bool $updateremotefields
     * @param array $customfields
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function display_auth_lock_options(string $auth, array $userfields, string $helptext, bool $mapremotefields,
                                                 bool $updateremotefields, array $customfields = []) {
        global $DB;
        $mform = $this->_form;

        // Introductory explanation and help text.
        if ($mapremotefields) {
            $hdrname = 'data_mapping';
            $mform->addElement('header', $hdrname, new lang_string('auth_data_mapping', 'auth'));
        } else {
            $hdrname = 'auth_fieldlocks';
            $mform->addElement('header', $hdrname, new lang_string('auth_fieldlocks', 'auth'));
        }
        $mform->setExpanded($hdrname, true);
        $mform->addElement('static', $hdrname . 'help', '', $helptext);

        // Generate the list of options.
        $lockoptions = ['unlocked' => get_string('unlocked', 'auth'),
            'unlockedifempty' => get_string('unlockedifempty', 'auth'),
            'locked' => get_string('locked', 'auth')];
        $updatelocaloptions = ['oncreate' => get_string('update_oncreate', 'auth'),
            'onlogin' => get_string('update_onlogin', 'auth')];
        $updateextoptions = ['0' => get_string('update_never', 'auth'),
            '1' => get_string('update_onupdate', 'auth')];

        // Generate the list of profile fields to allow updates / lock.
        if (!empty($customfields)) {
            $userfields = array_merge($userfields, $customfields);
            $customfieldname = $DB->get_records('user_info_field', null, '', 'shortname, name');
        }

        foreach ($userfields as $field) {
            // Define the fieldname we display to the  user.
            // this includes special handling for some profile fields.
            $fieldname = $field;
            $fieldnametoolong = false;
            if ($fieldname === 'lang') {
                $fieldname = get_string('language');
            } else if (!empty($customfields) && in_array($field, $customfields)) {
                // If custom field then pick name from database.
                $fieldshortname = str_replace('profile_field_', '', $fieldname);
                $fieldname = $customfieldname[$fieldshortname]->name;
                if (core_text::strlen($fieldshortname) > 67) {
                    // If custom profile field name is longer than 67 characters we will not be able to store the setting
                    // such as 'field_updateremote_profile_field_NOTSOSHORTSHORTNAME' in the database because the character
                    // limit for the setting name is 100.
                    $fieldnametoolong = true;
                }
            } else if ($fieldname == 'url') {
                $fieldname = get_string('webpage');
            } else {
                $fieldname = get_string($fieldname);
            }

            // Generate the list of fields / mappings.
            if ($fieldnametoolong) {
                // Display a message that the field can not be mapped because it's too long.
                $url = new moodle_url('/user/profile/index.php');
                $a = (object)['fieldname' => s($fieldname), 'shortname' => s($field), 'charlimit' => 67, 'link' => $url->out()];
                $mform->addElement('static', 'field_not_mapped_' . sha1($field), '',
                    get_string('cannotmapfield', 'auth', $a));
            } else if ($mapremotefields) {
                // TODO implement when we have auth plugins that map remote fields.
                null;
            } else {
                // Lock fields Only.
                $value = config::get_config_default('auth_'. $auth, "field_lock_{$field}");
                if (config::is_default_config_forced('auth_'. $auth, "field_lock_{$field}")) {
                    $mform->addElement('static', "field_lock_{$field}",
                        get_string('auth_fieldlockfield', 'auth', $fieldname), $lockoptions[$value]);
                } else {
                    $group = [];
                    $group[] = $mform->createElement('select', "field_lock_{$field}_custom",
                        '', [0 => get_string('sitedefaultspecified', '', $lockoptions[$value]),
                            1 => get_string('custom', 'form')]);
                    $group[] = $mform->createElement('select', "field_lock_{$field}",
                        get_string('auth_fieldlockfield', 'auth', $fieldname), $lockoptions);

                    $mform->addElement('group', 'grp'.$field,
                        get_string('auth_fieldlockfield', 'auth', $fieldname), $group, '', false);
                    $mform->hideIf("field_lock_{$field}", "field_lock_{$field}_custom", 'ne', 1);
                }
            }
        }
    }

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;
        $mform->setDisableShortforms();

        $mform->addElement('hidden', 'tenantid');
        $mform->setType('tenantid', PARAM_INT);

        $mform->addElement('hidden', 'auth');
        $mform->setType('auth', PARAM_COMPONENT);
    }

    /**
     * Check access
     */
    protected function check_access_for_dynamic_submission(): void {
        $tenantid = $this->optional_param('tenantid', 0, PARAM_INT);
        permission::require_can_edit_tenant_auth_settings($tenantid);
    }

    /**
     * Process form submission
     *
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $tenantid = $data->tenantid ?: tenancy::get_tenant_id();
        $authplugin = get_auth_plugin($this->auth);
        foreach ($authplugin->userfields as $field) {
            $key = "field_lock_{$field}";
            $iscustom = !empty($data->{$key.'_custom'});
            if (config::is_default_config_forced('auth_'. $this->auth, $key)) {
                $iscustom = false;
            }
            $value = $iscustom ? '' . ($data->$key ?? null) : null;
            config::set_config_tenant_override($tenantid, $key, $value, 'auth_'.$this->auth);
        }
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        $data = (object) $this->_ajaxformdata;
        if (!empty($data->tenantid)) {
            $formdata = (object)['auth' => $this->auth, 'tenantid' => $data->tenantid];
            $authplugin = get_auth_plugin($this->auth);
            foreach ($authplugin->userfields as $field) {
                $key = "field_lock_{$field}";
                $value = config::get_config_tenant_override($data->tenantid, 'auth_'.$this->auth, $key);
                if ($value !== null) {
                    $formdata->{$key.'_custom'} = 1;
                    $formdata->$key = $value;
                }
            }
            $formdata->tenantid = $data->tenantid;
            $this->set_data($formdata);
        }
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
        $tenantid = $this->optional_param('tenantid', 0, PARAM_INT) ?: tenancy::get_tenant_id();
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'tenantid' => $tenantid,
        ]);
    }
}

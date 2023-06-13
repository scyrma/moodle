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

namespace tool_tenant\form;

use core_form\dynamic_form;
use tool_tenant\config;
use tool_tenant\local\config\message_airnotifier;
use tool_tenant\local\config\tool_mobile;
use tool_tenant\permission;
use tool_tenant\tenancy;

/**
 * Mobile settings for individual tenant
 *
 * @package     tool_tenant
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class mobile_settings_form extends dynamic_form {

    /** @var array */
    protected $allsettings = null;

    /**
     * List of settings that can be overridden
     *
     * @return array
     */
    protected function get_all_settings(): array {
        if ($this->allsettings === null) {
            $this->allsettings = [];
            foreach (tool_mobile::get_multitenant_settings_names('tool_mobile') as $key) {
                if (!config::is_default_config_forced('tool_mobile', $key)) {
                    $this->allsettings[] = 'tool_mobile/' . $key;
                }
            }
            foreach (message_airnotifier::get_multitenant_settings_names('core') as $key) {
                if (!config::is_default_config_forced('core', $key)) {
                    $this->allsettings[] = $key;
                }
            }
        }
        return $this->allsettings;
    }

    /**
     * Converts a setting name given in a canonical form and returns plugin, settingname, element name for the form
     *
     * @param string $fullsettingname Setting name ('somecoresetting' or 'plugin/pluginsetting')
     * @return array
     */
    protected function parse_setting_name(string $fullsettingname): array {
        if (strpos($fullsettingname, '/') !== false) {
            [$plugin, $setting] = preg_split('|/|', $fullsettingname, 2);
            $key = $plugin . '__' . $setting;
            $postfix = $plugin . ' | ' . $setting;
        } else {
            $plugin = 'core';
            $setting = $key = $postfix = $fullsettingname;
        }
        return [$plugin, $setting, $key, $postfix];
    }

    /**
     * Add element to the form (custom/default selector, description and the element itself)
     *
     * @param string $fullsettingname
     * @param string $label
     * @param string $description
     * @param string $elementtype
     * @param string $paramtype
     * @throws \coding_exception
     */
    protected function add_auth_element(string $fullsettingname, string $label, string $description,
                                        string $elementtype, string $paramtype) {

        if (!in_array($fullsettingname, $this->get_all_settings())) {
            return;
        }

        [$plugin, $setting, $key, $postfix] = $this->parse_setting_name($fullsettingname);

        $mform = $this->_form;
        $group1 = [];
        $group1[] = $mform->createElement('radio', $key.'_custom', '', get_string('configusedefault', 'tool_tenant'), 0);
        $group1[] = $mform->createElement('static', $key.'_staticsep0', '', '<div class="w-100 mdl-left mt-2"></div>');
        $group1[] = $mform->createElement('radio', $key.'_custom', '', get_string('configoverride', 'tool_tenant'), 1);

        if ($elementtype === 'advcheckbox') {
            $element = $mform->createElement('advcheckbox', $key, '', $label);
            $defaultvalue = config::get_config_default($plugin, $setting) ? get_string('yes') : get_string('no');
        } else {
            $element = $mform->createElement('text', $key, $label);
            $defaultvalue = config::get_config_default($plugin, $setting) ?: get_string('emptysettingvalue', 'admin');
        }

        if (!$element instanceof \MoodleQuickForm_editor) {
            $group2 = [];
            $group2[] = $mform->createElement('static', $key.'_staticsep1', '', '<div class="w-100 mdl-left mt-2 mb-2">');
            $group2[] = $element;
            $group2[] = $mform->createElement('static', $key.'_staticsep2', '', '</div>');
            $group2[] = $mform->createElement('static', $key.'_desc', '', \html_writer::div($description, 'w-100'));
            $mform->addElement('group', $key.'_custom_group',
                $label.'<br><small>'. $postfix . '</small>',
                array_merge($group1, $group2), '', false);
            $mform->hideIf($key, $key.'_custom', 'ne', '1');
        } else {
            $mform->addElement('group', $key.'_custom_group',
                $label.'<br><small>'.$postfix.'</small>',
                $group1, '', false);
            $group2 = [$element];
            $mform->addElement('group', $key.'_group', '', $group2, '', false);
            $mform->hideIf($key.'_group', $key.'_custom', 'ne', '1');
            $mform->addElement('static', $key.'_desc', '', \html_writer::div($description, 'w-100'));
        }

        $mform->addElement('static', $key.'_default', '', \html_writer::div(
            get_string('defaultsettinginfo', 'admin', $defaultvalue), 'w-100 dimmed_text mb-3'));
        $mform->setType($key, $paramtype);
    }

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'tenantid');
        $mform->setType('tenantid', PARAM_INT);

        // Settings from tool_mobile.

        $key = 'tool_mobile/enablesmartappbanners';
        $label = get_string('enablesmartappbanners', 'tool_mobile');
        $this->add_auth_element($key, $label,
            get_string('enablesmartappbanners_desc', 'tool_mobile'),
            'advcheckbox', PARAM_BOOL);

        $key = 'tool_mobile/iosappid';
        $label = get_string('iosappid', 'tool_mobile');
        $this->add_auth_element($key, $label,
            get_string('iosappid_desc', 'tool_mobile'), 'text', PARAM_NOTAGS);

        $key = 'tool_mobile/androidappid';
        $label = get_string('androidappid', 'tool_mobile');
        $this->add_auth_element($key, $label,
            get_string('androidappid_desc', 'tool_mobile'), 'text', PARAM_NOTAGS);

        $key = 'tool_mobile/setuplink';
        $label = get_string('setuplink', 'tool_mobile');
        $this->add_auth_element($key, $label,
            get_string('setuplink_desc', 'tool_mobile'), 'text', PARAM_URL);

        // Settings from message_airnotifier.

        $key = 'airnotifierurl';
        $label = get_string('airnotifierurl', 'message_airnotifier');
        $this->add_auth_element($key, $label,
            get_string('configairnotifierurl', 'message_airnotifier'), 'text', PARAM_URL);

        $key = 'airnotifierport';
        $label = get_string('airnotifierport', 'message_airnotifier');
        $this->add_auth_element($key, $label,
            get_string('configairnotifierport', 'message_airnotifier'), 'text', PARAM_INT);

        $key = 'airnotifiermobileappname';
        $label = get_string('airnotifiermobileappname', 'message_airnotifier');
        $this->add_auth_element($key, $label,
            get_string('configairnotifiermobileappname', 'message_airnotifier'), 'text', PARAM_TEXT);

        $key = 'airnotifierappname';
        $label = get_string('airnotifierappname', 'message_airnotifier');
        $this->add_auth_element($key, $label,
            get_string('configairnotifierappname', 'message_airnotifier'), 'text', PARAM_TEXT);

        $key = 'airnotifieraccesskey';
        $label = get_string('airnotifieraccesskey', 'message_airnotifier');
        $this->add_auth_element($key, $label,
            get_string('configairnotifieraccesskey', 'message_airnotifier'), 'text', PARAM_ALPHANUMEXT);

        $this->add_action_buttons(false);
    }

    /**
     * Validation of form elements.
     *
     * @param  array $cssdata The css data.
     * @param  array $files Files related to the css.
     * @return array An array of errors if the validation fails.
     */
    public function validation($cssdata, $files) {
        $err = [];
        return $err;
    }

    /**
     * Check access
     */
    public function check_access_for_dynamic_submission(): void {
        $tenantid = $this->optional_param('tenantid', 0, PARAM_INT);
        permission::require_can_edit_tenant_mobile_settings($tenantid);
    }

    /**
     * Process form submission
     *
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        foreach ($this->get_all_settings() as $fullsettingname) {
            [$plugin, $setting, $key] = $this->parse_setting_name($fullsettingname);
            $value = !empty($data->{$key.'_custom'}) ? '' . ($data->$key ?? null) : null;
            config::set_config_tenant_override($data->tenantid, $setting, $value, $plugin);
        }
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        $data = (object) $this->_ajaxformdata;
        if (isset($data->tenantid)) {
            $tenantid = $data->tenantid ?: tenancy::get_tenant_id();
            // Get the config from the tenant.
            $formdata = (object)['tenantid' => $tenantid];
            foreach ($this->get_all_settings() as $fullsettingname) {
                [$plugin, $setting, $key] = $this->parse_setting_name($fullsettingname);
                $value = config::get_config_tenant_override($tenantid, $plugin, $setting);
                $formdata->$key = $value;
                $formdata->{$key.'_custom'} = !is_null($value);
            }
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
        $id = $this->optional_param('tenantid', 0, PARAM_INT) ?: tenancy::get_tenant_id();
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'tenantid' => $id,
        ]);
    }
}

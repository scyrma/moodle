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
 * Class auth_settings_form_common
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use core_form\dynamic_form;
use tool_tenant\auth_manager;
use tool_tenant\config;
use tool_tenant\permission;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Common authentication settings for individual tenant
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class auth_settings_form_common extends dynamic_form {

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
            foreach (auth_manager::overridable_auth_settings() as $key => $unused) {
                if (!config::is_default_config_forced('core', $key)) {
                    $this->allsettings[] = $key;
                }
            }
        }
        return $this->allsettings;
    }

    /**
     * Add element to the form (custom/default selector, description and the element itself)
     *
     * @param string $key
     * @param string $defaultvalue
     * @param string $label
     * @param string $description
     * @param \HTML_QuickForm_element $element
     */
    protected function add_auth_element(string $key, string $defaultvalue, string $label, string $description,
                                        \HTML_QuickForm_element $element) {
        if (!in_array($key, $this->get_all_settings())) {
            return;
        }

        $mform = $this->_form;
        $group1 = [];
        $group1[] = $mform->createElement('radio', $key.'_custom', '', get_string('configusedefault', 'tool_tenant'), 0);
        $group1[] = $mform->createElement('static', $key.'_staticsep0', '', '<div class="w-100 mdl-left mt-2"></div>');
        $group1[] = $mform->createElement('radio', $key.'_custom', '', get_string('configoverride', 'tool_tenant'), 1);

        if (!$element instanceof \MoodleQuickForm_editor) {
            $group2 = [];
            $group2[] = $mform->createElement('static', $key.'_staticsep1', '', '<div class="w-100 mdl-left mt-2 mb-2">');
            $group2[] = $element;
            $group2[] = $mform->createElement('static', $key.'_staticsep2', '', '</div>');
            $group2[] = $mform->createElement('static', $key.'_desc', '', \html_writer::div($description, 'w-100'));
            $mform->addElement('group', $key.'_custom_group',
                $label.'<br><small>'.$key.'</small>',
                array_merge($group1, $group2), '', false);
            $mform->hideIf($key, $key.'_custom', 'ne', '1');
        } else {
            $mform->addElement('group', $key.'_custom_group',
                $label.'<br><small>'.$key.'</small>',
                $group1, '', false);
            $group2 = [$element];
            $mform->addElement('group', $key.'_group', '', $group2, '', false);
            $mform->hideIf($key.'_group', $key.'_custom', 'ne', '1');
            $mform->addElement('static', $key.'_desc', '', \html_writer::div($description, 'w-100'));
        }
        $mform->addElement('static', $key.'_default', '', \html_writer::div(
            get_string('defaultsettinginfo', 'admin', $defaultvalue), 'w-100 dimmed_text mb-3'));
    }

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'tenantid');
        $mform->setType('tenantid', PARAM_INT);

        $key = 'registerauth';
        $options = auth_manager::get_available_registration_plugins();
        $el = $mform->createElement('select', $key, get_string('selfregistration', 'auth'), $options);
        $defaultvalue = config::get_config_default('core', $key);
        $defaultvalue = (array_key_exists($defaultvalue, $options)) ? $options[$defaultvalue] : $options[''];
        $this->add_auth_element($key, $defaultvalue, get_string('selfregistration', 'auth'),
            get_string('selfregistration_help', 'auth'), $el);

        $key = 'authpreventaccountcreation';
        $label = get_string('authpreventaccountcreation', 'admin');
        $el = $mform->createElement('advcheckbox', $key, '', $label,
            ['class' => 'text-left justify-content-start']);
        $defaultvalue = config::get_config_default('core', $key) ? get_string('yes') : get_string('no');
        $this->add_auth_element($key, $defaultvalue, $label,
            get_string('authpreventaccountcreation_help', 'admin'), $el);
        $mform->setType($key, PARAM_BOOL);

        $key = 'auth_instructions';
        $label = get_string('instructions', 'auth');
        $el = $mform->createElement('editor', $key, $label, ['rows' => 3], ['maxfiles' => 0]);
        $defaultvalue = config::get_config_default('core', $key) ?: get_string('emptysettingvalue', 'admin');
        $this->add_auth_element($key, $defaultvalue, $label,
            get_string('authinstructions', 'auth'), $el);
        $mform->setType($key, PARAM_RAW);

        $key = 'allowemailaddresses';
        $label = get_string('allowemailaddresses', 'admin');
        $el = $mform->createElement('text', $key, $label);
        $defaultvalue = config::get_config_default('core', $key) ?: get_string('emptysettingvalue', 'admin');
        $this->add_auth_element($key, $defaultvalue, $label,
            get_string('configallowemailaddresses', 'admin'), $el);
        $mform->setType($key, PARAM_TEXT);

        $key = 'denyemailaddresses';
        $label = get_string('denyemailaddresses', 'admin');
        $el = $mform->createElement('text', $key, $label);
        $defaultvalue = config::get_config_default('core', $key) ?: get_string('emptysettingvalue', 'admin');
        $this->add_auth_element($key, $defaultvalue, $label,
            get_string('configdenyemailaddresses', 'admin'), $el);
        $mform->setType($key, PARAM_TEXT);

        $key = 'verifychangedemail';
        $label = get_string('verifychangedemail', 'admin');
        $el = $mform->createElement('advcheckbox', $key, '', $label,
            ['class' => 'text-left justify-content-start']);
        $defaultvalue = config::get_config_default('core', $key) ? get_string('yes') : get_string('no');
        $this->add_auth_element($key, $defaultvalue, $label,
            get_string('configverifychangedemail', 'admin'), $el);
        $mform->setType($key, PARAM_BOOL);

        $key = 'alternateloginurl';
        $label = get_string('alternateloginurl', 'auth');
        $el = $mform->createElement('text', $key, $label, ['cols' => 50]);
        $defaultvalue = config::get_config_default('core', $key) ?: get_string('emptysettingvalue', 'admin');
        $this->add_auth_element($key, $defaultvalue, $label,
            get_string('alternatelogin', 'auth', htmlspecialchars(get_login_url())), $el);
        $mform->setType($key, PARAM_URL);
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
        permission::can_edit_tenant_auth_settings($tenantid);
    }

    /**
     * Process form submission
     *
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        unset($data->submitbutton);
        $data->auth_instructions = $data->auth_instructions['text'];

        foreach ($this->get_all_settings() as $key) {
            $value = !empty($data->{$key.'_custom'}) ? '' . ($data->$key ?? null) : null;
            config::set_config_tenant_override($data->tenantid, $key, $value);
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
            foreach ($this->get_all_settings() as $key) {
                $value = config::get_config_tenant_override($tenantid, 'core', $key);
                $formdata->$key = $value;
                if ($key === 'auth_instructions') {
                    $formdata->$key = ['text' => $formdata->$key ?? '', 'format' => FORMAT_HTML];
                }
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

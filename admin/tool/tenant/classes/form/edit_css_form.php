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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

/**
 * Class edit_css_form
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use core_form\dynamic_form;
use html_writer;
use MoodleQuickForm;
use stdClass;
use tool_tenant\config;
use tool_tenant\helper;
use tool_tenant\local\config\admin;
use tool_tenant\permission;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/tenant/classes/form/colourpicker.php');

// TODO WP-367 Perhaps move this to the colourpicker file.
\MoodleQuickForm::registerElementType('tenant_colourpicker',
    $CFG->dirroot . '/' . $CFG->admin . '/tool/tenant/classes/form/colourpicker.php',
        \tool_tenant\form\moodlequickform_tool_tenant_colourpicker::class);

/**
 * Class edit_css_form
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_css_form extends dynamic_form {

    /** @var array */
    protected $allsettings = null;
    /** @var string Use default value. */
    public const CONFIG_DEFAULT = 0;
    /** @var string Override default value. */
    public const CONFIG_OVERRIDE = 1;

    /**
     * List of settings that can be overridden
     *
     * @return array
     */
    protected function get_all_settings(): array {
        if ($this->allsettings === null) {
            $this->allsettings = [];
            foreach (array_keys(admin::overridable_admin_settings()) as $key) {
                if (!config::is_default_config_forced('core', $key)) {
                    $this->allsettings[] = $key;
                }
            }
        }
        return $this->allsettings;
    }

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;
        $mform->setDisableShortforms();

        $mform->addElement('hidden', 'tenantid');
        $mform->setType('tenantid', PARAM_INT);

        $mform->addElement('header', 'images', get_string('images', 'tool_tenant'));

        $mform->addElement('filemanager', 'headerlogo', get_string('headerlogo', 'tool_tenant'), '',
            $this->get_filemanager_options());
        $mform->addHelpButton('headerlogo', 'headerlogo', 'tool_tenant');
        $mform->addElement('filemanager', 'loginlogo', get_string('loginlogo', 'tool_tenant'), '',
            $this->get_filemanager_options());
        $mform->addHelpButton('loginlogo', 'loginlogo', 'tool_tenant');
        $mform->addElement('filemanager', 'tenantselectorlogo', get_string('tenantselectorlogo', 'tool_tenant'), '',
            $this->get_filemanager_options());
        $mform->addHelpButton('tenantselectorlogo', 'tenantselectorlogo', 'tool_tenant');
        $mform->addElement('filemanager', 'loginbackground', get_string('loginbackground', 'tool_tenant'), '',
                $this->get_filemanager_options());
        $options = $this->get_filemanager_options();
        $options['accepted_types'] = 'png, ico';
        $mform->addElement('filemanager', 'favicon', get_string('favicon', 'tool_tenant'), '',
                $options);

        $mform->addElement('header', 'colours', get_string('colours', 'tool_tenant'));
        $mform->setExpanded('colours');

        $mform->addElement('tenant_colourpicker', 'brand', get_string('brand', 'tool_tenant'));
        $mform->setType('brand', PARAM_RAW_TRIMMED); // Need to validate that this is a valid colour.
        $mform->setDefault('brand', '');
        $mform->addHelpButton('brand', 'brand', 'tool_tenant');

        $mform->addElement('advcheckbox', 'brandgraytones', '', get_string('brandgraytones', 'tool_tenant'));
        $mform->setType('brandgraytones', PARAM_INT);
        $mform->setDefault('brandgraytones', 0);
        $mform->addHelpButton('brandgraytones', 'brandgraytones', 'tool_tenant');

        $mform->addElement('header', 'advanced', get_string('advanced', 'tool_tenant'));
        $mform->setExpanded('advanced');

        $mform->addElement('textarea', 'footertext', get_string('footertext', 'tool_tenant'));
        $mform->setType('footertext', PARAM_RAW);

        $this->add_contact_support_form_elements($mform);

        $callbacks = get_plugins_with_function('extend_tenant_edit_css_form');
        foreach ($callbacks as $type => $plugins) {
            foreach ($plugins as $plugin => $pluginfunction) {
                $pluginfunction($this, $mform, $this->_ajaxformdata);
            }
        }

        if (permission::can_edit_tenant_theme_advanced()) {
            $mform->addElement(
                'static',
                'warning',
                '',
                html_writer::div(get_string('advancedbrandingwarning', 'tool_tenant'), 'alert alert-warning'),
            );
            $mform->setAdvanced('warning', true);

            $mform->addElement('tenant_colourpicker', 'navbar', get_string('navbarcolour', 'tool_tenant'));
            $mform->setType('navbar', PARAM_RAW_TRIMMED); // Need to validate that this is a valid colour.
            $mform->setDefault('navbar', '');
            $mform->addHelpButton('navbar', 'navbarcolour', 'tool_tenant');
            $mform->setAdvanced('navbar', true);

            $mform->addElement('tenant_colourpicker', 'button', get_string('buttoncolour', 'tool_tenant'));
            $mform->setType('button', PARAM_RAW_TRIMMED); // Need to validate that this is a valid colour.
            $mform->setDefault('button', '');
            $mform->addHelpButton('button', 'buttoncolour', 'tool_tenant');
            $mform->setAdvanced('button', true);

            $mform->addElement('textarea', 'customcss', get_string('customcss', 'tool_tenant'));
            $mform->setType('customcss', PARAM_RAW);
            $mform->setAdvanced('customcss', true);

            $buttonattrs = [
                'type' => 'button',
                'class' => 'btn btn-outline-secondary',
                'data-action' => 'resetappearance',
                'data-tenantid' => $this->_ajaxformdata['tenantid'],
            ];
            $buttonhtml = \html_writer::tag('button', get_string('resettenantappearance', 'tool_tenant'), $buttonattrs);
            $deschtml = \html_writer::div(get_string('resettenantappearancedesc', 'tool_tenant'), 'mt-3');
            $html = \html_writer::div($buttonhtml . $deschtml);
            $mform->addElement('static', 'resetappearance', get_string('resetappearance', 'tool_tenant'), $html);
            $mform->setAdvanced('resetappearance', true);
        }

        // Add the buttons just in case we ever use this form not inside a modal.
        $this->add_action_buttons(false);
    }

    /**
     * Colour value elements for CSS config.
     * @return array A list of colour elements to configure.
     */
    public static function get_colour_values() : array {
        return [
            'brand',
            'navbar',
            'button',
        ];
    }

    /**
     * Validation of form elements.
     *
     * @param  array $cssdata The css data.
     * @param  array $files Files related to the css.
     * @return array An array of errors if the validation fails.
     */
    public function validation($cssdata, $files) {
        // Validate that we have colour values.
        $err = [];

        foreach ($this->get_colour_values() as $value) {
            if (isset($cssdata[$value]) && strlen($cssdata[$value]) && !helper::is_valid_colour($cssdata[$value])) {
                $err[$value] = get_string('invalidcolour', 'tool_tenant');
            }
        }

        $this->validate_contact_support_form_elements($cssdata, $err);

        // Extend validation for any form extensions from plugins.
        $pluginsfunction = get_plugins_with_function('validate_tenant_edit_css_form');
        $errors = array();
        foreach ($pluginsfunction as $plugintype => $plugins) {
            foreach ($plugins as $pluginfunction) {
                $pluginerrors = $pluginfunction($cssdata, $this->_ajaxformdata);
                $errors = array_merge($errors, $pluginerrors);
            }
        }

        // TODO WP-367 validation of css and footer text.

        // Detect invalid CSS code in Custom SCSS field.
        // Since there was no validation on the field, the invalid
        // CSS code was affecting the rest of the CSS when minified.
        if (isset($cssdata['customcss'])) {
            $scss = new \core_scss();
            try {
                $scss->compile($cssdata['customcss']);
            } catch (\ScssPhp\ScssPhp\Exception\ParserException $e) {
                $err['customcss'] = get_string('scssinvalid', 'admin', $e->getMessage());
            } catch (\ScssPhp\ScssPhp\Exception\CompilerException $e) { // phpcs:ignore
                // Silently ignore this - it could be a scss variable defined from somewhere
                // else which we are not examining here.
            }
        }

        $err = array_merge($err, $errors);

        return $err;
    }

    /**
     * Check access
     */
    public function check_access_for_dynamic_submission(): void {
        $tenantid = $this->optional_param('tenantid', 0, PARAM_INT);
        permission::require_can_edit_tenant_theme($tenantid);
    }

    /**
     * Process form submission
     *
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $manager = new \tool_tenant\manager();
        unset($data->submitbutton);

        $this->process_contact_support_form_elements($data);

        $pluginsfunction = get_plugins_with_function('process_tenant_edit_css_requests');
        foreach ($pluginsfunction as $plugintype => $plugins) {
            foreach ($plugins as $pluginfunction) {
                $pluginfunction($data);
            }
        }

        $manager->save_css_config($data);
    }

    /**
     * Returns the filemanager options for this form.
     *
     * @return array filemanager options.
     */
    public static function get_filemanager_options() : array {
        return [
            'accepted_types' => ['web_image'], // Add other types as required.
            'maxbytes' => 0,
            'subdirs' => 0,
            'maxfiles' => 1
        ];
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        $data = (object) $this->_ajaxformdata;
        if (isset($data->tenantid)) {
            // Get the config from the tenant.
            $manager = new \tool_tenant\manager();
            $this->set_data($manager->get_css_config($data->tenantid, $this->get_filemanager_options()));
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

    /**
     * Add contact site support related configurations to the form (supportname/supportemail/supportpage/supportavailability).
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    private function add_contact_support_form_elements(MoodleQuickForm &$mform): void {
        // Support name.
        if (in_array('supportname', $this->get_all_settings())) {
            $elements = [
                $mform->createElement('radio', 'supportnameoverride', '',
                    get_string('configusedefault', 'tool_tenant'), self::CONFIG_DEFAULT),
                $mform->createElement('radio', 'supportnameoverride', '',
                    get_string('configoverride', 'tool_tenant'), self::CONFIG_OVERRIDE),
                $mform->createElement('text', 'supportnamevalue', ''),
                $mform->createElement('static', 'supportnamedescription', '',
                    html_writer::div(get_string('supportnamedescription', 'tool_tenant'), 'w-100')),
            ];
            $mform->addGroup($elements, 'supportnameformgroup', get_string('supportname', 'tool_tenant'),
                html_writer::div('', 'w-100'), false);
            $mform->setDefault('supportnameoverride', self::CONFIG_DEFAULT);
            $mform->hideIf('supportnamevalue', 'supportnameoverride', 'eq', self::CONFIG_DEFAULT);
            $mform->setType('supportnamevalue', PARAM_TEXT);
        }

        // Support Email.
        if (in_array('supportemail', $this->get_all_settings())) {
            $elements = [
                $mform->createElement('radio', 'supportemailoverride', '',
                    get_string('configusedefault', 'tool_tenant'), self::CONFIG_DEFAULT),
                $mform->createElement('radio', 'supportemailoverride', '',
                    get_string('configoverride', 'tool_tenant'), self::CONFIG_OVERRIDE),
                $mform->createElement('text', 'supportemailvalue', ''),
                $mform->createElement('static', 'supportemaildescription', '',
                    html_writer::div(get_string('supportemaildescription', 'tool_tenant'), 'w-100')),
            ];
            $mform->addGroup($elements, 'supportemailformgroup', get_string('supportemail', 'tool_tenant'),
                html_writer::div('', 'w-100'), false);
            $mform->setDefault('supportemailoverride', self::CONFIG_DEFAULT);
            $mform->hideIf('supportemailvalue', 'supportemailoverride', 'eq', self::CONFIG_DEFAULT);
            $mform->setType('supportemailvalue', PARAM_EMAIL);
        }

        // Support Page.
        if (in_array('supportpage', $this->get_all_settings())) {
            $elements = [
                $mform->createElement('radio', 'supportpageoverride', '',
                    get_string('configusedefault', 'tool_tenant'), self::CONFIG_DEFAULT),
                $mform->createElement('radio', 'supportpageoverride', '',
                    get_string('configoverride', 'tool_tenant'), self::CONFIG_OVERRIDE),
                $mform->createElement('text', 'supportpagevalue', ''),
                $mform->createElement('static', 'supportpagedescription', '',
                    html_writer::div(get_string('supportpagedescription', 'tool_tenant'), 'w-100')),
            ];
            $mform->addGroup($elements, 'supportpageformgroup', get_string('supportpage', 'tool_tenant'),
                html_writer::div('', 'w-100'), false);
            $mform->setDefault('supportpageoverride', self::CONFIG_DEFAULT);
            $mform->hideIf('supportpagevalue', 'supportpageoverride', 'eq', self::CONFIG_DEFAULT);
            $mform->setType('supportpagevalue', PARAM_URL);
        }

        // Support Availability.
        if (in_array('supportavailability', $this->get_all_settings())) {
            $options = [
                CONTACT_SUPPORT_ANYONE => get_string('availabletoanyone', 'admin'),
                CONTACT_SUPPORT_AUTHENTICATED => get_string('availabletoauthenticated', 'admin'),
                CONTACT_SUPPORT_DISABLED => get_string('disabled', 'admin'),
            ];
            $elements = [
                $mform->createElement('radio', 'supportavailabilityoverride', '',
                    get_string('configusedefault', 'tool_tenant'), self::CONFIG_DEFAULT),
                $mform->createElement('radio', 'supportavailabilityoverride', '',
                    get_string('configoverride', 'tool_tenant'), self::CONFIG_OVERRIDE),
                $mform->createElement('select', 'supportavailabilityvalue', '', $options),
                $mform->createElement('static', 'supportavailabilitydescription', '',
                    html_writer::div(get_string('supportavailabilitydescription', 'tool_tenant'), 'w-100')),
            ];
            $mform->addGroup($elements, 'supportavailabilityformgroup', get_string('supportavailability', 'tool_tenant'),
                html_writer::div('', 'w-100'), false);
            $mform->setDefault('supportavailabilityoverride', self::CONFIG_DEFAULT);
            $mform->setDefault('supportavailabilityvalue', CONTACT_SUPPORT_AUTHENTICATED);
            $mform->hideIf('supportavailabilityvalue', 'supportavailabilityoverride', 'eq', self::CONFIG_DEFAULT);
            $mform->setType('supportavailabilityvalue', PARAM_INT);
        }
    }

    /**
     * Validate contact site support configurations (supportname/supportemail/supportpage/supportavailability).
     *
     * @param array $cssdata
     * @param array $err
     */
    private function validate_contact_support_form_elements(array $cssdata, array &$err): void {
        // Validate support name.
        if (in_array('supportname', $this->get_all_settings()) &&
                ((int) $cssdata['supportnameoverride'] === self::CONFIG_OVERRIDE && empty($cssdata['supportnamevalue']))) {
            $err['supportnameformgroup'] = get_string('required');
        }
        // Validate support email.
        if (in_array('supportemail', $this->get_all_settings()) &&
                ((int) $cssdata['supportemailoverride'] === self::CONFIG_OVERRIDE &&
                !validate_email($cssdata['supportemailvalue']))) {
            $err['supportemailformgroup'] = get_string('invalidemail');
        }
        // Validate support page.
        if (in_array('supportpage', $this->get_all_settings()) &&
                ((int) $cssdata['supportpageoverride'] === self::CONFIG_OVERRIDE &&
                !filter_var($cssdata['supportpagevalue'], FILTER_VALIDATE_URL))) {
            $err['supportpageformgroup'] = get_string('invalidurl', 'error');
        }
    }

    /**
     * Save contact site support related configurations (supportname/supportemail/supportpage/supportavailability).
     *
     * @param stdClass $data submitted data
     * @return void
     */
    private function process_contact_support_form_elements(stdClass $data): void {
        foreach ($this->get_all_settings() as $supportconfig) {
            if ((int) $data->{$supportconfig . 'override'} === self::CONFIG_OVERRIDE) {
                config::set_config_tenant_override($data->tenantid, $supportconfig, $data->{$supportconfig . 'value'});
            } else {
                config::set_config_tenant_override($data->tenantid, $supportconfig, null);
                unset($data->{$supportconfig . 'value'});
            }
        }
    }
}

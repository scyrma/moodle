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
use tool_tenant\helper;
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

        $mform->addElement('header', 'advanced', get_string('advanced', 'tool_tenant'));
        $mform->setExpanded('advanced');

        $mform->addElement('textarea', 'footertext', get_string('footertext', 'tool_tenant'));
        $mform->setType('footertext', PARAM_RAW);

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

        // Extend validation for any form extensions from plugins.
        $pluginsfunction = get_plugins_with_function('validate_tenant_edit_css_form');
        $errors = array();
        foreach ($pluginsfunction as $plugintype => $plugins) {
            foreach ($plugins as $pluginfunction) {
                $pluginerrors = $pluginfunction($cssdata, $this->_ajaxformdata);
                $errors = array_merge($errors, $pluginerrors);
            }
        }

        $err = array_merge($err, $errors);

        // TODO WP-367 validation of css and footer text.

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
}

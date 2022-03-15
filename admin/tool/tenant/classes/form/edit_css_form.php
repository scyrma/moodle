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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class edit_css_form
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use tool_tenant\manager;
use tool_tenant\permission;
use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/tenant/classes/form/colourpicker.php');

// TODO SP-367 Perhaps move this to the colourpicker file.
\MoodleQuickForm::registerElementType('tenant_colourpicker',
    $CFG->dirroot . '/' . $CFG->admin . '/tool/tenant/classes/form/colourpicker.php',
        \tool_tenant\form\moodlequickform_tool_tenant_colourpicker::class);

/**
 * Class edit_css_form
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_css_form extends modal_form {

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'tenantid');
        $mform->setType('tenantid', PARAM_INT);

        $mform->addElement('header', 'images', get_string('images', 'tool_tenant'));

        $mform->addElement('filemanager', 'headerlogo', get_string('headerlogo', 'tool_tenant'), '',
                $this->get_filemanager_options());
        $mform->addElement('filemanager', 'loginlogo', get_string('loginlogo', 'tool_tenant'), '',
                $this->get_filemanager_options());
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
        foreach ($this->get_colour_values() as $value) {
            $mform->addElement('tenant_colourpicker', $value, get_string($value, 'tool_tenant'));
            $mform->setType($value, PARAM_RAW_TRIMMED); // Need to validate that this is a valid colour.
            $mform->setDefault($value, '');
            $mform->addHelpButton($value, $value, 'tool_tenant');
        }

        $mform->addElement('header', 'advanced', get_string('advanced', 'tool_tenant'));
        $mform->setExpanded('advanced');
        $mform->addElement('textarea', 'customcss', get_string('customcss', 'tool_tenant'));
        $mform->setType('customcss', PARAM_RAW);
        $mform->addElement('textarea', 'footertext', get_string('footertext', 'tool_tenant'));
        $mform->setType('footertext', PARAM_RAW);

        $callbacks = get_plugins_with_function('extend_tenant_edit_css_form');
        foreach ($callbacks as $type => $plugins) {
            foreach ($plugins as $plugin => $pluginfunction) {
                $pluginfunction($this, $mform, $this->_ajaxformdata);
            }
        }

        $mform->addElement('header', 'reset', get_string('reset'));
        $mform->setExpanded('reset');
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

        // Add the buttons just in case we ever use this form not inside a modal.
        $this->add_action_buttons(false);
    }

    /**
     * Colour value elements for CSS config.
     * @return array A list of colour elements to configure.
     */
    public static function get_colour_values() : array {
        return [
            'primary',
            'brand',
            'button',
            'drawer',
            'footer',
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
        $pattern = "/^#(?=[abcdefABCDEF0-9]*$)(?:..{5}|.{3})$/";
        $err = [];

        foreach ($this->get_colour_values() as $value) {
            if (strlen($cssdata[$value]) && !preg_match($pattern, $cssdata[$value])) {
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

        // TODO SP-367 validation of css and footer text.

        return $err;
    }

    /**
     * Check access
     */
    public function require_access() {
        $tenantid = $this->optional_param('tenantid', 0, PARAM_INT);
        permission::require_can_edit_tenant_theme($tenantid);
    }

    /**
     * Process form submission
     *
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
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
    public function set_data_for_modal() {
        $data = (object) $this->_ajaxformdata;
        if (isset($data->tenantid)) {
            // Get the config from the tenant.
            $manager = new \tool_tenant\manager();
            $this->set_data($manager->get_css_config($data->tenantid, $this->get_filemanager_options()));
        }
    }
}

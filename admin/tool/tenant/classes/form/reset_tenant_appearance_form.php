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
 * Class reset_tenant_appearance_form
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín <mikel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use tool_tenant\permission;
use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class reset_tenant_appearance_form
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín <mikel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reset_tenant_appearance_form extends modal_form {
    /** @var int */
    protected $tenantid;

    /**
     * Tenant being edited
     *
     * @return int
     */
    protected function get_tenant_id(): int {
        if ($this->tenantid === null) {
            $this->tenantid = (int)$this->_ajaxformdata['id'];
        }
        return $this->tenantid;
    }

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'tenantid');
        $mform->setType('tenantid', PARAM_INT);

        $mform->addElement('html', \html_writer::div(get_string('resettenantappearanceformintro', 'tool_tenant'), 'mb-3'));

        $elements = [];
        $elements[] = $mform->createElement('advcheckbox', 'resetimages',
            get_string('resettenantappearanceimages', 'tool_tenant'));
        $mform->setDefault('resetimages', 1);
        $elements[] = $mform->createElement('advcheckbox', 'resetcolours',
            get_string('resettenantappearancecolours', 'tool_tenant'));
        $mform->setDefault('resetcolours', 1);
        $elements[] = $mform->createElement('advcheckbox', 'resetcss',
            get_string('resettenantappearancecss', 'tool_tenant'));
        $mform->setDefault('resetcss', 1);
        $elements[] = $mform->createElement('advcheckbox', 'resetfooter',
            get_string('resettenantappearancefooter', 'tool_tenant'));
        $mform->setDefault('resetfooter', 1);
        $mform->addGroup($elements, 'exporters', '', \html_writer::div('', 'w-100 p-1'), false);

        $mform->addElement('html', \html_writer::div(get_string('resettenantappearanceformend', 'tool_tenant')));
    }

    /**
     * Check access
     */
    public function require_access() {
        permission::require_can_edit_tenant_theme($this->get_tenant_id());
    }

    /**
     * Validation of form elements.
     *
     * @param  array $data The new user details.
     * @param  array $files Files related to the user.
     * @return array An array of errors if the validation fails.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['resetimages']) && empty($data['resetcolours']) && empty($data['resetcss']) &&
            empty($data['resetfooter'])) {
            $errors['exporters'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Process form submission
     *
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
        $manager = new \tool_tenant\manager();
        $manager->reset_css_config($data);
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_modal() {
        $data = new \stdClass();
        $data->tenantid = $this->get_tenant_id();
        $this->set_data($data);
    }
}

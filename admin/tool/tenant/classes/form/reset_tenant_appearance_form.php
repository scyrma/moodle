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
 * Class reset_tenant_appearance_form
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use core_form\dynamic_form;
use tool_tenant\permission;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class reset_tenant_appearance_form
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reset_tenant_appearance_form extends dynamic_form {

    /**
     * Tenant being edited
     *
     * @return int
     */
    protected function get_tenant_id(): int {
        return $this->optional_param('id', 0, PARAM_INT);
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
    public function check_access_for_dynamic_submission(): void {
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
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $manager = new \tool_tenant\manager();
        $manager->reset_css_config($data);
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        $data = new \stdClass();
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
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'id' => $this->get_tenant_id() ?: tenancy::get_tenant_id(),
        ]);
    }
}

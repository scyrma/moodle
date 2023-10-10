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
 * Class switch_tenant_form
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use core_form\dynamic_form;
use tool_tenant\permission;
use tool_tenant\tenancy;

/**
 * Class switch_tenant_form
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahujas
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class switch_tenant_form extends dynamic_form {

    /**
     * Form definition for switch_tenant_form
     *
     */
    protected function definition() {
        $mform = $this->_form;
        // Add a advanced select element to select a tenant to switch to.
        $options = [
            'ajax' => 'tool_tenant/form-potential-tenant-selector',
            'multiple' => false,
            'valuehtmlcallback' => function ($tenantid) {
                return tenancy::get_tenant_name_from_id($tenantid);
            }
        ];
        $mform->addElement('autocomplete', 'selecttenant', get_string('selecttenant', 'tool_tenant'), [], $options);
        $mform->addRule('selecttenant', null, 'required', null, 'client');
    }

    /**
     * Require access to form
     *
     */
    public function check_access_for_dynamic_submission(): void {
        permission::require_can_switch_tenant();
    }

    /**
     * Process form data
     *
     * @return string
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        if ($data->selecttenant) {
            $url = \tool_tenant\tenancy::get_tenantswitch_url($data->selecttenant);
        }
        return $url->out_as_local_url(false);
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data($this->_ajaxformdata);
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
        ]);
    }
}

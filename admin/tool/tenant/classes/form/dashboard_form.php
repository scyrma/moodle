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
 * Class dashboard_form
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;

use core_form\dynamic_form;
use tool_tenant\manager;
use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_tenant\tenant;

/**
 * Class dashboard_form
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class dashboard_form extends dynamic_form {

    /** @var tenant */
    protected $tenant;

    /**
     * Form definition
     */
    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $mform->setDisableShortforms();
        // Add empty header for consistency.
        $mform->addElement('header', 'hdr', '');

        $tenant = $this->get_tenant();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $iscurrenttenant = $tenant->get('id') == tenancy::get_tenant_id();
        $context = [
            'id' => $iscurrenttenant ? '' : $tenant->get('id'),
            'linked' => $tenant->get('dashboardlinked'),
            'sitedashboardurl' => new \moodle_url('/my/indexsys.php'),
            'tenantdashboardurl' => manager::get_dashboard_url($iscurrenttenant ? 0 : $tenant->get('id')),
            'caneditsitedashboard' => permission::can_edit_site_dashboard()
        ];
        $dashboardconfig = $OUTPUT->render_from_template('tool_tenant/dashboard_config', $context);
        $mform->addElement('static', 'dashboardconfig', get_string('defaultdashboardconfiguration', 'tool_tenant'),
            \html_writer::div($dashboardconfig, 'dashboard-config'));
    }

    /**
     * Tenant being edited
     *
     * @return tenant
     */
    protected function get_tenant(): tenant {
        if ($this->tenant === null) {
            $id = $this->optional_param('tenantid', 0, PARAM_INT);
            $manager = new manager();
            $this->tenant = $manager->get_tenant($id);
        }
        return $this->tenant;
    }

    /**
     * Check access
     */
    public function check_access_for_dynamic_submission(): void {
        permission::require_can_see_tenant_dashboard_tab($this->get_tenant()->get('id'));
    }

    /**
     * Process form submission
     */
    public function process_dynamic_submission() {
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        $tenant = $this->get_tenant();
        $this->set_data($tenant->to_record());
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
            'tenantid' => $this->get_tenant()->get('id'),
        ]);
    }
}

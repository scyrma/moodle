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
 * Form for auth plugin tenant availability
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\local\auth;

use core_form\dynamic_form;
use tool_tenant\permission;
use tool_tenant\tenancy;

/**
 * Form for auth plugin tenant availability
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenant_availability_form extends dynamic_form {

    /** @var string Element for selected tenants */
    public const AVAILABILITY_TYPE = 'tenant_availability_type';

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {

        $mform = $this->_form;
        $mform->setDisableShortforms();
        // Add empty header for consistency.
        $mform->addElement('header', 'hdr', '');

        $data = $this->_ajaxformdata;

        $tenantavailabilitystr = get_string('tenantavailability', 'tool_tenant');
        $alltenantsstr = get_string($data['auth'] . '_alltenants', 'tool_tenant');
        $selectedtenantsstr = get_string($data['auth'] . '_onlyfollowingtenants', 'tool_tenant') . '...';
        $notselectedtenantsstr = get_string($data['auth'] . '_exceptfollowingtenants', 'tool_tenant') . '...';

        // Options for tenants autocomplete elements.
        $tenantautocompleteoptions = [
            'ajax' => 'tool_tenant/form-potential-tenant-selector',
            'multiple' => true,
            'valuehtmlcallback' => static function($tenantid) {
                return tenancy::get_tenant_name_from_id($tenantid);
            }
        ];

        $mform->addElement('radio', self::AVAILABILITY_TYPE, $tenantavailabilitystr, $alltenantsstr,
            issuer_helper::ISSUER_AVAILABLE_ALWAYS);

        $mform->addElement('radio', self::AVAILABILITY_TYPE, null,
            $selectedtenantsstr, issuer_helper::ISSUER_AVAILABLE_SELECTED);
        $mform->addElement('autocomplete', 'manual_select_tenants',
            get_string('selecttenants', 'tool_tenant'), [], $tenantautocompleteoptions)
            ->setHiddenLabel(true);
        $mform->hideIf('manual_select_tenants', self::AVAILABILITY_TYPE, 'ne',
            issuer_helper::ISSUER_AVAILABLE_SELECTED);

        $mform->addElement('radio', self::AVAILABILITY_TYPE, null, $notselectedtenantsstr,
            issuer_helper::ISSUER_AVAILABLE_EXCEPT);
        $mform->addElement('autocomplete', 'manual_exclude_tenants',
            get_string('selecttenants', 'tool_tenant'), [], $tenantautocompleteoptions)
            ->setHiddenLabel(true);
        $mform->hideIf('manual_exclude_tenants', self::AVAILABILITY_TYPE, 'ne',
            issuer_helper::ISSUER_AVAILABLE_EXCEPT);

        $mform->setType(self::AVAILABILITY_TYPE, PARAM_INT);
        $mform->addHelpButton('formgroup', 'tenantavailability', 'tool_tenant');
        $mform->setDefault(self::AVAILABILITY_TYPE, issuer_helper::ISSUER_AVAILABLE_ALWAYS);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_ALPHANUM);

        $mform->addElement('hidden', 'auth');
        $mform->setType('auth', PARAM_COMPONENT);
    }

    /**
     * Check access
     */
    protected function check_access_for_dynamic_submission(): void {
        permission::require_can_switch_tenant();
    }

    /**
     * Process form submission
     *
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $issuerid = $this->optional_param('id', '', PARAM_ALPHANUM);
        $auth = $this->optional_param('auth', null, PARAM_COMPONENT);
        $tenantids = [];
        $avtype = $data->{self::AVAILABILITY_TYPE};
        if ($avtype == issuer_helper::ISSUER_AVAILABLE_SELECTED) {
            $tenantids = $data->manual_select_tenants;
        } else if ($avtype == issuer_helper::ISSUER_AVAILABLE_EXCEPT) {
            $tenantids = $data->manual_exclude_tenants;
        }
        issuer_helper::save_issuer_availability($issuerid, $avtype, $tenantids, $auth);
    }

    /**
     * Set data when the form is shown for the first time
     */
    public function set_data_for_dynamic_submission(): void {
        $issuerid = $this->optional_param('id', '', PARAM_ALPHANUM);
        $auth = $this->optional_param('auth', null, PARAM_COMPONENT);
        [$avtype, $tenantids] = issuer_helper::get_issuer_availability($issuerid, $auth);
        $data = [
            'id' => $issuerid,
            'auth' => $auth,
            self::AVAILABILITY_TYPE => $avtype,
        ];
        if ($avtype == issuer_helper::ISSUER_AVAILABLE_SELECTED) {
            $data['manual_select_tenants'] = $tenantids;
        } else if ($avtype == issuer_helper::ISSUER_AVAILABLE_EXCEPT) {
            $data['manual_exclude_tenants'] = $tenantids;
        }
        $this->set_data($data);
    }

    /**
     * Perform some extra moodle validation
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files): array {
        $errors = [];
        if ($data[self::AVAILABILITY_TYPE] == issuer_helper::ISSUER_AVAILABLE_SELECTED
                && empty($data['manual_select_tenants'])) {
            $errors['formgroup'] = get_string('required');
        } else if ($data[self::AVAILABILITY_TYPE] == issuer_helper::ISSUER_AVAILABLE_EXCEPT
                && empty($data['manual_exclude_tenants'])) {
            $errors['formgroup'] = get_string('required');
        }
        return $errors;
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
        $id = $this->optional_param('id', '', PARAM_ALPHANUM);
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'id' => $id,
        ]);
    }
}

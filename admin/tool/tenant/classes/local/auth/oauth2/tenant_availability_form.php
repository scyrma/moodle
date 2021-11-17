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
 * Form for oauth2 tenant availability
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\local\auth\oauth2;

defined('MOODLE_INTERNAL') || die();

use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_wp\modal_form;

/**
 * Form for oauth2 tenant availability
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenant_availability_form extends modal_form {

    /** @var string Element for selected tenants */
    public const AVAILABILITY_TYPE = 'tenant_availability_type';

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {

        $mform = $this->_form;

        $tenantavailabilitystr = get_string('tenantavailability', 'tool_tenant');
        $alltenantsstr = get_string('oauth2_alltenants', 'tool_tenant');
        $selectedtenantsstr = get_string('oauth2_onlyfollowingtenants', 'tool_tenant') . '...';
        $notselectedtenantsstr = get_string('oauth2_exceptfollowingtenants', 'tool_tenant') . '...';

        // Options for tenants autocomplete elements.
        $tenantautocompleteoptions = [
            'ajax' => 'tool_tenant/form-potential-tenant-selector',
            'multiple' => true,
            'valuehtmlcallback' => static function($tenantid) {
                return tenancy::get_tenant_name_from_id($tenantid);
            }
        ];

        $mform->addElement('radio', self::AVAILABILITY_TYPE, $tenantavailabilitystr, $alltenantsstr,
            manager::ISSUER_AVAILABLE_ALWAYS);

        $mform->addElement('radio', self::AVAILABILITY_TYPE, null,
            $selectedtenantsstr, manager::ISSUER_AVAILABLE_SELECTED);
        $mform->addElement('autocomplete', 'manual_select_tenants',
            get_string('selecttenants', 'tool_tenant'), [], $tenantautocompleteoptions)
            ->setHiddenLabel(true);
        $mform->hideIf('manual_select_tenants', self::AVAILABILITY_TYPE, 'ne',
            manager::ISSUER_AVAILABLE_SELECTED);

        $mform->addElement('radio', self::AVAILABILITY_TYPE, null, $notselectedtenantsstr,
            manager::ISSUER_AVAILABLE_EXCEPT);
        $mform->addElement('autocomplete', 'manual_exclude_tenants',
            get_string('selecttenants', 'tool_tenant'), [], $tenantautocompleteoptions)
            ->setHiddenLabel(true);
        $mform->hideIf('manual_exclude_tenants', self::AVAILABILITY_TYPE, 'ne',
            manager::ISSUER_AVAILABLE_EXCEPT);

        $mform->setType(self::AVAILABILITY_TYPE, PARAM_INT);
        $mform->addHelpButton('formgroup', 'tenantavailability', 'tool_tenant');
        $mform->setDefault(self::AVAILABILITY_TYPE, manager::ISSUER_AVAILABLE_ALWAYS);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
    }

    /**
     * Check access
     */
    public function require_access() {
        permission::require_can_switch_tenant();
    }

    /**
     * Process form submission
     *
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
        $issuerid = $this->optional_param('id', 0, PARAM_INT);
        $tenantids = [];
        $avtype = $data->{self::AVAILABILITY_TYPE};
        if ($avtype == manager::ISSUER_AVAILABLE_SELECTED) {
            $tenantids = $data->manual_select_tenants;
        } else if ($avtype == manager::ISSUER_AVAILABLE_EXCEPT) {
            $tenantids = $data->manual_exclude_tenants;
        }
        manager::save_issuer_availability($issuerid, $avtype, $tenantids);
    }

    /**
     * Set data when the form is shown for the first time
     */
    public function set_data_for_modal() {
        $issuerid = $this->optional_param('id', 0, PARAM_INT);
        [$avtype, $tenantids] = manager::get_issuer_availability($issuerid);
        $data = [
            'id' => $issuerid,
            self::AVAILABILITY_TYPE => $avtype,
        ];
        if ($avtype == manager::ISSUER_AVAILABLE_SELECTED) {
            $data['manual_select_tenants'] = $tenantids;
        } else if ($avtype == manager::ISSUER_AVAILABLE_EXCEPT) {
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
        if ($data[self::AVAILABILITY_TYPE] == manager::ISSUER_AVAILABLE_SELECTED && empty($data['manual_select_tenants'])) {
            $errors['formgroup'] = get_string('required');
        } else if ($data[self::AVAILABILITY_TYPE] == manager::ISSUER_AVAILABLE_EXCEPT && empty($data['manual_exclude_tenants'])) {
            $errors['formgroup'] = get_string('required');
        }
        return $errors;
    }
}

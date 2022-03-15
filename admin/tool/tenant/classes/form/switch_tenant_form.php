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
 * Class switch_tenant_form
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\form;
use tool_tenant\manager;
use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_tenant\tenant;
use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class switch_tenant_form
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahujas
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class switch_tenant_form extends modal_form {
    /**
     * @var
     */
    private $tenant;

    /**
     * Form definition for switch_tenant_form
     *
     */
    protected function definition() {
        global $CFG, $OUTPUT;
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
    public function require_access() {
        permission::require_can_switch_tenant();
    }

    /**
     * Process form data
     *
     * @param \stdClass $data
     * @return string
     */
    public function process(\stdClass $data) {
        if ($data->selecttenant) {
            $url = \tool_tenant\tenancy::get_tenantswitch_url($data->selecttenant);
        }
        return $url->out_as_local_url(false);
    }
}

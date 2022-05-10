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
 * This file contains the backend class for tenant allocation outcome.
 *
 * @package    tool_tenant
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Sumit Negi
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\tool_dynamicrule\outcome;

use MoodleQuickForm;
use tool_tenant\manager;
use tool_tenant\permission;
use tool_tenant\tenant;
use tool_tenant\tenancy;

/**
 * The backend class for tenant allocation outcome
 *
 * @package    tool_tenant
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Sumit Negi
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class allocation extends \tool_dynamicrule\outcome_base {

    /**
     * Validates the configform of the outcome
     *
     * @param array $data
     *
     * @return array
     */
    public function validate_config_form(array $data): array {
        $errors = [];
        $tenant = new tenant($data['tenantid']);
        if (!permission::can_edit_dynamicrule_outcome($tenant)) {
            // We need to check permission here as listed tenant might be viewable to user,
            // but user does not have capability to allocate users.
            $errors['tenantid'] = get_string('errornopermissionallocateusers', 'tool_tenant');
        }

        return $errors;
    }

    /**
     * If the current user is able to add this outcome.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::can_add_dynamicrule_outcome();
    }

    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     *
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $tenant = new tenant($this->get_tenantid());
        return permission::can_edit_dynamicrule_outcome($tenant);
    }

    /**
     * Check if tenant still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        $tenantid = $this->get_tenantid();
        return tenant::record_exists_select('id = :id AND archived = :archived', ['id' => $tenantid, 'archived' => 0]);
    }

    /**
     * Return the tenant object from configured tenantid
     *
     * @return tenant
     */
    protected function get_tenant(): tenant {
        $tenantid = $this->get_tenantid();
        return new tenant($tenantid);
    }

    /**
     * Get tenant id
     *
     * @return int
     */
    protected function get_tenantid(): int {
        return $this->get_configdata()['tenantid'] ?? 0;
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        // Tenant select (autocomplete) field.
        $selecttenantstr = get_string('selecttenantoutcome', 'tool_tenant');
        $missingtenantstr = get_string('missingtenant', 'tool_tenant');
        $options = [
            'ajax'     => 'tool_tenant/form-potential-tenant-selector',
            'multiple' => false,
            'class'    => 'select_tenant'
        ];
        if ($this->is_configuration_valid()) {
            $options['valuehtmlcallback'] = function () {
                return tenancy::get_tenant_name_from_id($this->get_tenantid());
            };
        }
        $mform->addElement('autocomplete', 'tenantid', $selecttenantstr, [], $options);
        $mform->addRule('tenantid', $missingtenantstr, 'required', null, 'client');
        $mform->setType('tenantid', PARAM_INT);
    }

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomeallocation', 'tool_tenant');
    }

    /**
     * Apply this outcome on a given user
     *
     * @param stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $manager = new manager();
        $tenantid = $this->get_tenantid();
        $manager->allocate_user($user->id, $tenantid, 'tool_tenant', 'dynamicrule');
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        $tenant = $this->get_tenant();
        $fullname = $tenant->get_formatted_name();
        $description = get_string('outcomeallocationdescription', 'tool_tenant', $fullname);
        return $description;
    }

    /**
     * Return the broken description for the outcome.
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_broken_description(): string {
        $tenantid = $this->get_tenantid();
        if (!tenant::get_record(['id' => $tenantid])) {
            return get_string('errortenantarchived', 'tool_tenant');
        } else if (!tenant::record_exists_select('id = :id AND archived = :archived', ['id' => $tenantid, 'archived' => 0])) {
            return get_string('errortenantnotfound', 'tool_tenant');
        }
        return parent::get_broken_description();
    }

}

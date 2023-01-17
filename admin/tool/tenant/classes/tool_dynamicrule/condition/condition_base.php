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

declare(strict_types=1);

namespace tool_tenant\tool_dynamicrule\condition;

use MoodleQuickForm;
use tool_dynamicrule\condition_sql;
use tool_dynamicrule\rule;
use tool_tenant\event\tenant_user_created;
use tool_tenant\event\tenant_user_updated;
use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_tenant\tenant;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * The base class for tenant conditions.
 *
 * @package    tool_tenant
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class condition_base extends condition_sql {

    /**
     * If the current user is able to add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::can_view_tenants_list();
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return permission::can_move_users_between_tenants();
    }

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];
        if (!permission::can_view_tenants_list()) {
            // We need to check that tenant list is visible to user.
            $errors['tenantid'] = get_string('errornopermissionaddcondition', 'tool_tenant');
        }

        return $errors;
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
     * Check if tenant still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        $tenantid = $this->get_tenantid();
        return array_key_exists($tenantid, tenancy::get_tenants());
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
     * Add tenantid condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('tool_tenant', $this->get_tenantid());
    }

    /**
     * Get tenantid condition field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['tenantid'] = $importer->get_mapping('tool_tenant', $this->get_tenantid(),
            IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }

    /**
     * Immediately evaluate the rule when tenant_user_created and tenant_user_updated are triggered.
     *
     * @return string[]
     */
    public function get_event_subscription() {
        return [tenant_user_created::class, tenant_user_updated::class];
    }

    /**
     * Execute as a scheduled task
     *
     * @return bool
     */
    public function is_scheduled_task(): bool {
        // TODO WP-3988 remove this function when we can properly listen to event tenant_user_updated.
        return true;
    }

    /**
     * Which rule types this condition supports.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return rule::TYPE_SHARED;
    }
}

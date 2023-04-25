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

namespace tool_tenant\tool_custompage\audience;

use MoodleQuickForm;
use core_reportbuilder\local\helpers\database;
use tool_custompage\local\audience\base;
use tool_tenant\permission;
use tool_tenant\tenancy;

/**
 * Tenant users audience type
 *
 * @package     tool_tenant
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenantusers extends base {

    /** @var int */
    public const TENANT_ALL = 0;
    /** @var int */
    public const TENANT_ONLY = 1;
    /** @var int */
    public const TENANT_EXCEPT = 2;

    /**
     * Adds audience's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {

        // We need to retrieve all tenants except the shared space.
        $options = [
            'ajax' => 'tool_tenant/form-potential-tenant-selector',
            'multiple' => true,
            'valuehtmlcallback' => function ($tenantid) {
                return tenancy::get_tenant_name_from_id((int)$tenantid);
            }
        ];

        $mform->addElement('radio', 'criteria', null,
            get_string('alltenantsselected', 'tool_tenant'),
            self::TENANT_ALL);

        $mform->addElement('radio', 'criteria', null,
            get_string('tenantsselected', 'tool_tenant') . '...',
            self::TENANT_ONLY);

        $mform->addElement('autocomplete', 'onlytenants',
            get_string('tenantsselected', 'tool_tenant'), [], $options)
            ->setHiddenLabel(true);

        $mform->hideIf('onlytenants', 'criteria', 'ne', self::TENANT_ONLY);

        $mform->addElement('radio', 'criteria', null,
            get_string('tenantsexceptselected', 'tool_tenant') . '...',
            self::TENANT_EXCEPT);

        $mform->addElement('autocomplete', 'excepttenants',
            get_string('tenantsexceptselected', 'tool_tenant'), [], $options)
            ->setHiddenLabel(true);

        $mform->hideIf('excepttenants', 'criteria', 'ne', self::TENANT_EXCEPT);

        $mform->setDefault('criteria', self::TENANT_ALL);
    }

    /**
     * Validates the configform of the condition.
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];

        if ($data['criteria'] == self::TENANT_ONLY && empty($data['onlytenants'])) {
            $errors['onlytenants'] = get_string('required');
            return $errors;
        }

        if ($data['criteria'] == self::TENANT_EXCEPT && empty($data['excepttenants'])) {
            $errors['excepttenants'] = get_string('required');
            return $errors;
        }

        return $errors;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current audience
     *
     * @param string $usertablealias
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(string $usertablealias): array {
        $tenantcriteria = (int)$this->get_configdata()['criteria'];

        // If 'all tenant' was stored as criteria, we retrieve all users.
        if ($tenantcriteria === self::TENANT_ALL) {
            return ['', '1=1', []];
        }

        // Based on criteria operator, we set the tenant ids and condition to use .
        if ($tenantcriteria === self::TENANT_ONLY) {
            $tenantcondition = 'OR';
            $tenantids = array_map('intval', $this->get_configdata()['onlytenants']);
        } else {
            $tenantcondition = 'AND';
            $tenantids = array_map('intval', $this->get_configdata()['excepttenants']);
        }

        $tenantuserselects = [];
        foreach ($tenantids as $tenantid) {
            $tenantuserselect = tenancy::get_users_subquery(false, false, "{$usertablealias}.id", $tenantid);
            if ($tenantcriteria === self::TENANT_EXCEPT) {
                $tenantuserselect = "NOT ({$tenantuserselect})";
            }

            $tenantuserselects[] = $tenantuserselect;
        }

        $tenantuserselectwhere = '(' . implode(") {$tenantcondition} (", $tenantuserselects) . ')';

        return ['', $tenantuserselectwhere, []];
    }

    /**
     * Return user friendly name of this audience type
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tenantusers', 'tool_tenant');
    }

    /**
     * Return the description for the audience.
     *
     * @return string
     */
    public function get_description(): string {
        $tenantcriteria = (int)$this->get_configdata()['criteria'];

        if ($tenantcriteria === self::TENANT_ALL) {
            return get_string('alltenantsselected', 'tool_tenant');
        }

        // Based on criteria operator, we set the tenant ids and lang string to use .
        if ($tenantcriteria === self::TENANT_ONLY) {
            $tenantcriteriastr = 'tenantsselecteddesc';
            $tenantids = array_map('intval', $this->get_configdata()['onlytenants']);
        } else {
            $tenantcriteriastr = 'tenantsexceptselecteddesc';
            $tenantids = array_map('intval', $this->get_configdata()['excepttenants']);
        }

        $tenantnames = implode(', ', array_map(static function(int $tenantid): string {
            return (string) tenancy::get_tenant_name_from_id($tenantid);
        }, $tenantids));

        return get_string($tenantcriteriastr, 'tool_tenant', $tenantnames);
    }

    /**
     * If the current user is able to add this audience.
     *
     * @param bool $global True if current page is global, otherwise false
     * @return bool
     */
    public function user_can_add(bool $global = false): bool {
        // Check if user is able to switch tenant.
        return $global && permission::can_switch_tenant() && tenancy::is_site_multi_tenant();
    }

    /**
     * If the current user is able to edit this audience.
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        // Check if user is able to switch tenant.
        return permission::can_switch_tenant();
    }
}

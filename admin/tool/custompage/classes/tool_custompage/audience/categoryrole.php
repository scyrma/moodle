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

namespace tool_custompage\tool_custompage\audience;

use core_course_category;
use MoodleQuickForm;
use core_reportbuilder\local\helpers\database;
use tool_custompage\local\audience\base;
use tool_tenant\manager;
use tool_tenant\tenancy;

/**
 * Category role audience type
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class categoryrole extends base {

    /**
     * Adds audience's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $roles = $this->get_category_assignable_roles();
        $mform->addElement('autocomplete', 'roles', get_string('selectrole', 'role'), $roles, ['multiple' => true]);
    }

    /**
     * Helps to build SQL to retrieve users that matches the current audience
     *
     * @param string $usertablealias
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(string $usertablealias): array {
        global $DB;

        $roles = $this->get_configdata()['roles'];
        $prefix = database::generate_param_name() . '_';
        [$insql, $inparams] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, $prefix);

        $ra = database::generate_alias();
        $ctx = database::generate_alias();

        $tenantid = $this->get_persistent()->get_page()->get('tenantid');
        $tenantcategory = $this->get_tenant_category($tenantid);

        // If a tenant with valid category was previously added and for some reason change it
        // we need to check that category is valid.
        if ($tenantcategory === null) {
            return ['', '1=0', []];
        }

        // We need to retrieve all child categories where any user possibly has a role assigned.
        $categorycontext = $tenantcategory->get_context();
        $contextids = array_merge([$categorycontext->id], array_keys($categorycontext->get_child_contexts()));

        $prefixcat = database::generate_param_name() . '_';
        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, $prefixcat);

        $join = "
            JOIN {role_assignments} {$ra} ON {$ra}.userid = {$usertablealias}.id
            JOIN {context} {$ctx} ON {$ctx}.id = {$ra}.contextid AND {$ctx}.contextlevel = " . CONTEXT_COURSECAT;

        $where = "{$ra}.contextid {$contextsql} AND {$ra}.roleid {$insql}";

        return [$join, $where, $inparams + $contextparams];
    }

    /**
     * Return user friendly name of this audience type
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('categoryrole', 'tool_custompage');
    }

    /**
     * Return the description for the audience.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;

        $rolesids = $this->get_configdata()['roles'];
        $roles = $DB->get_records_list('role', 'id', $rolesids, 'sortorder');
        $tenantid = $this->get_persistent()->get_page()->get('tenantid');
        $tenantcategorycontext = $this->get_tenant_category($tenantid);
        $rolesfixed = role_fix_names($roles, $tenantcategorycontext->get_context(), ROLENAME_ALIAS, true);

        return $this->format_description_for_multiselect($rolesfixed);
    }

    /**
     * If the current user is able to add this audience.
     *
     * @param bool $global True if current page is global, otherwise false
     * @return bool
     */
    public function user_can_add(bool $global = false): bool {
        // Only can be added in tenant pages.
        if ($global) {
            return false;
        }

        // Check if user is able to assign any role or tenant have no category associated.
        $tenantcategory = $this->get_tenant_category();
        if ($tenantcategory === null || !has_capability('moodle/role:assign', $tenantcategory->get_context())) {
            return false;
        }

        return true;
    }

    /**
     * If the current user is able to edit this audience.
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        global $DB;

        // Check if user can assign all saved role types on this audience instance.
        $roleids = $this->get_configdata()['roles'];
        $tenantid = $this->get_persistent()->get_page()->get('tenantid');
        $roles = $this->get_category_assignable_roles($tenantid);

        // Check that all saved roles still exist.
        [$insql, $inparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select('role', "id $insql", $inparams);
        if (empty($roles) || !empty(array_diff(array_keys($records), array_keys($roles)))) {
            return false;
        }

        return true;
    }

    /**
     * List of roles assignable in context of tenant category.
     *
     * @param int $tenantid
     * @return array
     */
    private function get_category_assignable_roles(int $tenantid = 0): array {
        $tenantcategory = $this->get_tenant_category($tenantid);
        if ($tenantcategory === null) {
            return [];
        }
        return get_assignable_roles($tenantcategory->get_context(), ROLENAME_ALIAS);
    }

    /**
     * Retrieve tenant category.
     *
     * @param int $tenantid
     * @return null|core_course_category
     */
    private function get_tenant_category(int $tenantid = 0): ?core_course_category {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        return (new manager)->get_tenant($tenantid)->get_category();
    }
}

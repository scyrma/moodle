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
 * Class users_list
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\output;

use renderer_base;
use tool_tenant\manager;
use tool_tenant\permission;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;

/**
 * Class users_list
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class users_list implements \renderable , \templatable {

    /** @var \tool_tenant\users_report $userreport The system report of tenant users. */
    protected $userreport;

    /** @var int $tenantid The ID of the tenant. */
    protected $tenantid;

    /**
     * users_list constructor.
     *
     * @param \tool_tenant\users_report $userreport
     * @param int $tenantid The ID of the tenant we are viewing users for (or 0 in case we are on the "All users" page)
     */
    public function __construct(\tool_tenant\users_report $userreport, int $tenantid) {
        $this->userreport = $userreport;
        $this->tenantid = $tenantid;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return \stdClass|array
     */
    public function export_for_template(renderer_base $output) {

        // If we are viewing "All users" we still need to determine the current tenant in order to perform permission checks.
        // Typically that is the current tenant, except when we are in the "Shared space" in which case we use the tenant of
        // the current user.
        if ($this->tenantid === 0) {
            $tenantid = tenancy::get_tenant_id();
            if (sharedspace::is_shared_space($tenantid)) {
                $tenantid = tenancy::get_actual_tenant_id();
            }
        } else {
            $tenantid = $this->tenantid;
        }

        $params = [];
        $context = \context_system::instance();

        $bulkactions = [];
        if (permission::can_suspend_users($tenantid)) {
            $bulkactions['actions'] = [
                get_string('actions') => [
                    'suspendusers' => get_string('suspendusers', 'tool_tenant'),
                    'unsuspendusers' => get_string('unsuspendusers', 'tool_tenant'),
                ]
            ];
        }
        if (permission::can_delete_users($tenantid)) {
            $actions = get_string('actions');
            $bulkactions['actions'][$actions]['deleteusers'] = get_string('deleteusers', 'tool_tenant');
        }
        if (permission::can_confirm_anybody($tenantid)) {
            $actions = get_string('actions');
            $bulkactions['actions'][$actions]['confirm'] = get_string('confirmusers', 'tool_tenant');
        }
        if (permission::can_resend_email_users($tenantid)) {
            $actions = get_string('actions');
            $bulkactions['actions'][$actions]['resend'] = get_string('emailsconfirmationresend', 'tool_tenant');
        }
        if (permission::can_assign_tenant_admin($tenantid)) {
            $bulkactions['admin'] = [
                get_string('tenantadministration', 'tool_tenant') => [
                    'assigntenantadmin' => get_string('assigntenantadmins', 'tool_tenant'),
                    'unassigntenantadmin' => get_string('unassigntenantadmins', 'tool_tenant')
                ]
            ];
        }
        // Show only 'Allocate to programs' action to users with permission to allocate to programs.
        if ($this->can_allocate_to_programs()) {
            $actions = get_string('actions');
            $bulkactions['actions'][$actions]['allocatetoprogram'] = get_string('allocatetoprogram', 'tool_program');
        }
        // Show only 'Allocate to certifications' action to users with permission to allocate to certifications.
        if ($this->can_allocate_to_certifications()) {
            $actions = get_string('actions');
            $bulkactions['actions'][$actions]['allocatetocertification'] =
                get_string('allocatetocertification', 'tool_certification');
        }
        if (permission::can_move_users_between_tenants()) {
            // Add a tenant selector.
            $manager = new manager();
            $tenants = array_map(function (\tool_tenant\tenant $t) {
                return $t->get_formatted_name();
            }, $manager->get_tenants_without_shared());
            unset($tenants[$this->tenantid]);
            if ($tenants) {
                $bulkactions['tenants'] = [get_string('movebetweentenants', 'tool_tenant') => $tenants];
            }
        }

        $select = new \single_select(new \moodle_url('#'), 'bulkactions', $bulkactions);
        $select->set_label(get_string('withselectedusers'));
        $params['bulkactionsselect'] = $select->export_for_template($output);;

        if (permission::can_create_users($tenantid)) {
            $params['adduser'] = true;
            $params['addbutton'] = true;
            $params['addbuttontitle'] = get_string('adduser', 'tool_tenant');
            $params['addbuttonicon'] = true;
            $params['systemcontextid'] = $context->id; // TODO not needed?
        }
        $params['tenantid'] = $this->tenantid;
        $params['userslist'] = $this->userreport->output();

        return $params;
    }

    /**
     * Current user has permission to allocate users to programs
     *
     * @return bool
     */
    private function can_allocate_to_programs(): bool {
        if (!class_exists('\tool_program\permission')) {
            return false;
        }
        return \tool_program\permission::can_allocate_anybody_as_organisation_manager() ||
            \tool_program\permission::has_allocateuser_capability();
    }

    /**
     * Current user has permission to allocate users to certifications
     *
     * @return bool
     */
    private function can_allocate_to_certifications(): bool {
        if (!class_exists('\tool_certification\permission')) {
            return false;
        }
        return \tool_certification\permission::can_allocate_anybody_as_organisation_manager() ||
            \tool_certification\permission::has_allocateuser_capability();
    }
}

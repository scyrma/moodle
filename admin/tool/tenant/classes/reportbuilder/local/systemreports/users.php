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

namespace tool_tenant\reportbuilder\local\systemreports;

use context_system;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use core_user\fields;
use html_writer;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;
use tool_tenant\config;
use tool_tenant\manager;
use tool_tenant\permission;
use tool_tenant\reportbuilder\local\entities\tenant;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * Users system report implementation
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @author      2022 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class users extends system_report {

    /** @var int The given tenant ID */
    protected $tenantid;
    /** @var bool Check whether the report needs to show all tenant entities */
    private $showtenantentities;
    /** @var bool If showall is selected, show users from all tenants that are visible to the current user */
    private $showall;
    /** @var bool Check whether current user has permission to view inactive users */
    private $showinactiveusers;

    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        global $CFG;

        // Normalise arguments. If showall is selected, show users from all tenants that are visible to the current user.
        // If showall is not selected, show users from current tenant only.
        $this->showall = $this->get_parameter('showall', false, PARAM_BOOL) && permission::can_switch_tenant();
        $this->tenantid = $this->get_parameter('id', 0, PARAM_INT);
        if (!$this->tenantid) {
            if ($this->showall) {
                $this->tenantid = permission::can_view_users_in_all_tenants() ? 0 : tenancy::get_actual_tenant_id();
            } else {
                $this->tenantid = tenancy::get_tenant_id();
            }
        }

        // Check if report needs to show all tenant entities. This permission needs to be checked here because, even though
        // the entity checks the same permission, we are adding a base field after adding the entity.
        $this->showtenantentities = $this->showall && permission::can_show_tenant_report_column();

        // Check whether current user has permission to view inactive users, either for the current tenant or across the site
        // (in the case we are showing all users on the site).
        $this->showinactiveusers = $this->tenantid ? permission::can_view_inactive_users($this->tenantid) :
            permission::can_view_all_inactive_users();

        // This report is not typical. It can be generated for a tenant who is not the current tenant.
        // We need to make sure that we take the identity fields for the correct tenant.
        config::push_for_tenant($this->tenantid);

        // Add user entity.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');

        $this->set_main_table('user', $useralias);

        if (!$this->showinactiveusers) {
            $this->add_base_condition_sql("{$useralias}.suspended = 0 AND {$useralias}.confirmed = 1");
        }

        // Add tenant name column if needed.
        if ($this->showtenantentities) {
            $tenantentity = new tenant();
            $tenantentity->add_joins($tenantentity->get_user_tenant_joins("{$useralias}.id"));
            $tenantalias = $tenantentity->get_table_alias('tool_tenant');
            $this->add_entity($tenantentity);

            // Add "tenantid" field to the base fields so we can use it in the actions.
            $this->add_base_fields("{$tenantalias}.id AS tenantid");
        }

        // If tenantid is provided, show users from tenant and sub-tentants.
        // If user can switch tenant, show users from all tenants the user can switch to.
        if (!$this->tenantid) {
            $guest = database::generate_param_name();
            // Show users from all tenants (except for deleted and guest).
            $this->add_base_condition_sql("{$useralias}.deleted = 0 and {$useralias}.id <> :{$guest}",
                [$guest => $CFG->siteguest]);
        } else {
            $where = tenancy::get_users_subquery(false, false, "{$useralias}.id", $this->tenantid, $this->showall);
            $where .= " AND {$useralias}.deleted = 0";
            $this->add_base_condition_sql($where);
        }

        $this->add_base_fields("{$useralias}.id, {$useralias}.confirmed, {$useralias}.suspended,
        '' as fullusername " . fields::for_name()->get_sql($useralias)->selects); // Necessary for actions.
        $this->add_actions();
        $this->set_downloadable(true);

        // The same instance of this system report can be shown in 'context' of different tenants.
        // Different tenants may have different custom profile fields. Make sure that when user entity is initialised
        // we add columns and filters for all tenants and define availability for a particular tenant if needed.
        config::push_for_tenant(0);
        $this->add_entity($userentity);
        config::pop();

        $this->add_columns($userentity);
        $this->add_filters($userentity);
        config::pop();
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return $this->showall ? permission::can_browse_all_users() : permission::can_browse_users($this->tenantid);
    }

    /**
     * Adds the columns we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     *
     * @param user $userentity
     */
    public function add_columns(user $userentity): void {

        $useralias = $userentity->get_table_alias('user');

        $showcheckboxes =
            permission::can_suspend_users($this->tenantid) ||
            permission::can_delete_users($this->tenantid) ||
            permission::can_move_users_between_tenants();

        if ($showcheckboxes) {
            $movecolumn = (new column(
                'check',
                new lang_string('select'),
                'user'
            ))
                ->add_fields("{$useralias}.id" . fields::for_name()->get_sql($useralias)->selects)
                ->add_attributes(['class' => 'sr-only-header', 'data-togglegroup-name' => 'tenant-users'])
                ->add_callback([$this, 'col_checkbox']);
            $this->add_column($movecolumn);
        }

        // Add full name with picture and link column.
        $this->add_column_from_entity('user:fullnamewithpicturelink');

        // Add all identity field columns (Includes all user profile fields set as identity fields).
        $identityfields = fields::for_identity($this->get_context(), true)->get_required_fields();
        foreach ($identityfields as $identityfield) {
            $column = $userentity->get_identity_column($identityfield);
            $this->add_column($column);
        }

        // Add last access column.
        $this->add_column_from_entity('user:lastaccess');

        // Add tenant name column if tenant entity has been added to the report.
        if ($this->showtenantentities) {
            $this->add_column_from_entity('tenant:name');
        }

        // Append "Tenant administrator" badge if needed.
        if ($column = $this->get_column('user:fullnamewithpicturelink')) {
            $comp = database::generate_param_name();
            $roleid = database::generate_param_name();
            $adminsql = "(SELECT 1
                            FROM {role_assignments} ra
                           WHERE ra.userid = u.id
                             AND ra.component = :{$comp}
                             AND ra.roleid = :{$roleid})";
            $adminparams = [$comp => 'tool_tenant', $roleid => manager::get_tenant_admin_role()];

            $column
                ->add_field($adminsql, 'tenantadmin', $adminparams)
                ->add_callback(static function(string $value, stdClass $row): string {
                    if ($row->tenantadmin > 0) {
                        $value .= ' ' . html_writer::span(get_string('tenantadmin', 'tool_tenant'),
                            'badge badge-pill badge-secondary');
                    }
                    return $value;
                });
        }

        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
    }

    /**
     * Takes a column and creates a checkbox element with it.
     *
     * @param  string $value
     * @param  stdClass $row
     * @return string The checkbox element.
     */
    public function col_checkbox(string $value, stdClass $row): string {
        $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
        $userfullname = fullname($row, $viewfullnames);
        $id = 'selectuser' . $value;
        $checkbox = html_writer::checkbox('users[' . $value . ']', $value, false, null,
            [
                'id' => $id,
                'data-bulkuserid' => $value,
                'data-action' => 'toggle',
                'data-toggle' => 'slave',
                'data-fullname' => $userfullname,
                'data-togglegroup' => 'tenant-users',
            ]);
        $label = get_string('selectuser', 'tool_tenant', $userfullname);
        return $checkbox . html_writer::tag('label', $label,
                ['for' => $id, 'class' => 'accesshide']);
    }

    /**
     * Helper method for action callbacks. Each of them require the correct tenant for the permission checks
     *
     * Most of the time that will be the current tenant, but when we are viewing "All users" $this->tenantid = 0
     *
     * Note also we can't pass NULL to the permission methods because they call {@see tenancy::get_tenant_id()} which returns
     * the current tenant when called for current user, not necessarily the tenant of that user, and for the shared space this
     * means the permission methods will return false
     *
     * @param stdClass $row
     * @return int
     */
    private function get_action_tenantid(stdClass $row): int {
        if (empty($row->tenantid)) {
            $row->tenantid = $this->tenantid ?: tenancy::get_actual_tenant_id((int) $row->id);
        }
        return (int) $row->tenantid;
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     *
     * @param user $userentity
     */
    protected function add_filters(user $userentity): void {

        // Add full name filter.
        $this->add_filter_from_entity('user:fullname');

        // Add all identity field filters (Includes all user profile fields set as identity fields).
        $identityfields = fields::for_identity($this->get_context(), true)->get_required_fields();
        foreach ($identityfields as $identityfield) {
            $filter = $userentity->get_identity_filter($identityfield);
            $this->add_filter($filter);
        }

        // Add last access filter.
        $this->add_filter_from_entity('user:lastaccess');
        // Add suspended filter.
        $this->add_filter_from_entity('user:suspended')->set_is_available($this->showinactiveusers);
        // Add tenant name filter if tenant entity has been added to the report.
        if ($this->showtenantentities) {
            $this->add_filter_from_entity('tenant:name');
        }
        // Add authentication method filter.
        $this->add_filter_from_entity('user:auth');
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     *
     * Note the use of ":id" placeholder which will be substituted according to actual values in the row
     */
    protected function add_actions(): void {

        // Action to edit user account.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/settings', '', 'core'),
            [
                'data-action' => 'edit',
                'data-id' => ':id',
                'data-fullusername' => ':fullusername',
            ],
            false,
            new lang_string('edituser', 'tool_tenant')
        ))->add_callback(function(stdClass $row): bool {
            $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
            $row->fullusername = fullname($row, $viewfullnames);
            return permission::can_update_user($row, $this->get_action_tenantid($row));
        }));

        // Action to suspend user account.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/show', '', 'core'),
            [
                'data-action' => 'suspend',
                'data-id' => ':id',
            ],
            false,
            new lang_string('suspenduser', 'tool_tenant')
        ))->add_callback(function(stdClass $row): bool {
            return permission::can_suspend_user($row, $this->get_action_tenantid($row));
        }));

        // Action to unsuspend user account.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/hide', '', 'core'),
            [
                'data-action' => 'unsuspend',
                'data-id' => ':id',
            ],
            false,
            new lang_string('unsuspenduser', 'tool_tenant')
        ))->add_callback(function(stdClass $row): bool {
            return permission::can_unsuspend_user($row, $this->get_action_tenantid($row));
        }));

        // Action to confirm user account.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('e/tick', '', 'core'),
            [
                'data-action' => 'confirm',
                'data-id' => ':id',
                'data-fullusername' => ':fullusername',
            ],
            false,
            new lang_string('confirmuser', 'tool_tenant')
        ))->add_callback(function(stdClass $row): bool {
            $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());
            $row->fullusername = fullname($row, $viewfullnames);
            return permission::can_confirm_user($row, $this->get_action_tenantid($row));
        }));

        // Action to resend email to user.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('paper-plane-o', '', 'tool_wp'),
            [
                'data-action' => 'resendemail',
                'data-id' => ':id',
            ],
            false,
            new lang_string('resendemailuser', 'tool_tenant')
        ))->add_callback(function(stdClass $row): bool {
            return permission::can_resend_email_user($row, $this->get_action_tenantid($row));
        }));

        // Action to delete user.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/delete', '', 'core'),
            [
                'data-action' => 'delete',
                'data-id' => ':id',
            ],
            false,
            new lang_string('deleteuser', 'tool_tenant')
        ))->add_callback(function(stdClass $row): bool {
            return permission::can_delete_user($row, $this->get_action_tenantid($row));
        }));
    }

    /**
     * Row class
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        return ($row->suspended || $row->confirmed == 0) ? 'text-muted' : '';
    }

    /**
     * Return list of column names that will be excluded when table is downloading.
     *
     * @return array
     */
    public function get_exclude_columns_for_download(): array {
        return ['user:check', 'actions'];
    }
}

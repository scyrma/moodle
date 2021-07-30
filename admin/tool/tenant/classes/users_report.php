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
 * Class for the users report
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_wp\db;

/**
 * Class for the users report
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class users_report extends \tool_reportbuilder\system_report {

    /** @var int */
    protected $tenantid;
    /**
     * @var bool
     */
    private $showtenantentities;
    /**
     * @var array
     */
    private $localuserfields;
    /**
     * @var array
     */
    private $identityfields;
    /**
     * @var array
     */
    private $customuserfields;
    /**
     * @var
     */
    private $showall;

    /**
     * Initialise the report.
     */
    public function initialise() {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');

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
        // This report is not typical. It can be generated for a tenant who is not the current tenant.
        // We need to make sure that we take the identity fields for the correct tenant.
        config::push_for_tenant($this->tenantid);

        $this->set_default_pagesize(30);

        $this->showtenantentities = $this->showall && tenancy::is_site_multi_tenant();

        // Get all the fields that can potentially be identity fields. Add all of them them as defaults but only identity
        // fields as available. Visible identity fields may be different for different users and report definition should
        // not be updated because the current user has changed.
        $this->customuserfields = profile_manager::profile_get_all_user_fields();
        $this->localuserfields = array('email', 'username', 'idnumber', 'phone1',
            'phone2', 'department', 'institution', 'city', 'country');

        // Now the 'identityfields' are the fields that are actually identity fields for the tenant this report is generated for
        // that are visible to the current user.
        $this->identityfields = \core_user\fields::for_identity(\context_system::instance(), true)->get_required_fields();

        $this->set_main_table('user', 'u');
        $this->set_downloadable(true);
        // If tenantid is provided , show users from tenant and sub-tentants.
        // If user can switch tenant, show users from all tenants the user can switch to.
        if (!$this->tenantid) {
            $guest = db::generate_param_name();
            // Show users from all tenants (except for deleted and guest).
            $this->add_base_condition_sql('u.deleted = 0 and u.id <> :' . $guest, [$guest => $CFG->siteguest]);
        } else {
            $where = tenancy::get_users_subquery(false, false, 'u.id', $this->tenantid, $this->showall);
            $where .= " AND u.deleted = 0";
            $this->add_base_condition_sql($where);
        }
        $this->add_base_fields('u.id, u.confirmed, u.suspended, u.firstname as fullusername ' .
            \core_user\fields::for_name()->get_sql('u')->selects); // Necessary for actions.
        $this->add_actions();

        // The same instance of this system report can be shown in 'context' of different tenants.
        // Different tenants may have different custom profile fields. Make sure that when user entity is initialised
        // we add columns and filters for all tenants and define availability for a particular tenant if needed.
        config::push_for_tenant(0);
        $userentity = (new user_entity())->set_allow_tenant_columns(true);
        $this->add_entity($userentity);
        config::pop();

        $this->set_columns($userentity);
        $this->set_filters($userentity);
        config::pop();
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return $this->showall ? permission::can_browse_all_users() : permission::can_browse_users($this->tenantid);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('userscount', 'tool_tenant');
    }

    /**
     * Set the columns for the report.
     *
     * @param user_entity $userentity
     */
    protected function set_columns(user_entity $userentity) {
        $showcheckboxes =
            permission::can_suspend_users($this->tenantid) ||
            permission::can_delete_users($this->tenantid) ||
            permission::can_move_users_between_tenants();

        if ($showcheckboxes) {
            $movecolumn = (new report_column(
                'check',
                new \lang_string('select'),
                'user'
            ))
                ->add_fields('u.id,'.user_entity::get_all_user_name_fields(true, 'u'))
                ->add_attributes(['class' => 'sr-only-header', 'data-togglegroup-name' => 'tenant-users'])
                ->set_is_default(true, 0)
                ->add_callback([$this, 'col_checkbox']);
            $this->add_column($movecolumn);
        }

        $adminsql = '(SELECT 1
                       FROM {role_assignments} ra
                      WHERE ra.userid = u.id
                        AND ra.component = :comp
                        AND ra.roleid = :roleid)';
        $adminparams = ['comp' => 'tool_tenant', 'roleid' => manager::get_tenant_admin_role()];

        $this->get_column('user:fullnamewithpicturelink')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true, 1)
            ->add_field($adminsql, 'tenantadmin', $adminparams)
            ->set_visiblename(new \lang_string('fullname'))
            ->add_callback([$this, 'append_admin_label']);

        $order = 2;
        foreach ($this->localuserfields as $userfield) {
            if ($column = $this->get_column('user:' . $userentity->resolve_column_name($userfield))) {
                $column->set_is_default(true, $order++)
                    ->set_is_available(in_array($userfield, $this->identityfields));
            }
        }
        foreach ($this->customuserfields as $profilefield) {
            $userfield = 'profile_field_' . $profilefield->field->shortname;
            if ($column = $this->get_column('user:' . $userentity->resolve_column_name($userfield))) {
                $column->set_is_default(true, $order++)
                    ->set_is_available(in_array($userfield, $this->identityfields));
            }
        }

        $this->get_column('user:lastaccess')
            ->set_is_default(true, $order++)
            ->set_is_sortable(true, true, 1);

        // Tenant column.
        $this->get_column('user:tenant')
            ->set_is_default(true, $order++)
            ->set_is_sortable(true, true)
            ->set_is_available($this->showtenantentities);

        if ($this->showtenantentities) {
            // Add "tenantid" field to the base fields so we can use it in the actions.
            $this->add_base_fields($userentity->get_table_alias('tool_tenant').'.id AS tenantid');
        }
    }

    /**
     * Set the filters for the report.
     *
     * @param user_entity $userentity
     */
    protected function set_filters(user_entity $userentity) {
        $filters = $this->get_filters();
        $filters['user:fullname']->set_is_default(true);

        // Add filters for all fields from the user table (country, city, etc) that are identify fields.
        foreach ($this->localuserfields as $userfield) {
            if ($filter = $this->get_filter('user:' . $userfield)) {
                $filter->set_is_default(true)
                    ->set_is_available(in_array($userfield, $this->identityfields));
            }
        }
        if ($filter = $this->get_filter('user:lastaccess')) {
            $filter->set_is_default(true);
        }
        if ($filter = $this->get_filter('user:suspended')) {
            $filter->set_is_default(true);
        }
        // Tenant filter.
        if ($filter = $this->get_filter('user:tenant')) {
            $filter->set_is_default(true)
                ->set_is_available($this->showtenantentities);
        }
        // Add filters for all custom user profile fields filter, regardless whether they are identity fields or not.
        // (We probably need to show only identity fields here but we have introduced this before 3.11 when it became
        // possible to specify profile fields as identity fields, so if we change it now users will see it as a
        // regression).
        foreach ($this->customuserfields as $profilefield) {
            $name = $userentity->resolve_filter_name('profile_field_'.$profilefield->field->shortname);
            if ($filter = $this->get_filter('user:' . $name)) {
                $filter->set_is_default(true);
                $filter->set_is_available(profile_manager::is_field_object_visible($profilefield->field));
            }
        }
        // Auth method filter.
        if ($filter = $this->get_filter('user:auth')) {
            $filter->set_is_default(true);
        }
    }

    /**
     * Takes a column and creates a checkbox element with it.
     *
     * @param  int $value
     * @param  \stdClass $row
     * @return string The checkbox element.
     */
    public function col_checkbox(int $value, \stdClass $row) : string {
        $userfullname = format::fullname($value, $row);
        $id = 'selectuser' . $value;
        $checkbox = \html_writer::checkbox('users[' . $value . ']', $value, false, null,
            ['id' => $id,
             'data-bulkuserid' => $value,
             'data-action' => 'toggle',
             'data-toggle' => 'slave',
             'data-fullname' => $userfullname,
             'data-togglegroup' => 'tenant-users',
            ]);
        $label = get_string('selectuser', 'tool_tenant', $userfullname);
        return $checkbox . \html_writer::tag('label', $label,
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
     * @param \stdClass $row
     * @return int
     */
    private function get_action_tenantid(\stdClass $row): int {
        if (empty($row->tenantid)) {
            $row->tenantid = $this->tenantid ?: tenancy::get_actual_tenant_id($row->id);
        }
        return $row->tenantid;
    }

    /**
     * Set the actions icons of the report.
     *
     * Note that each of the action callbacks needs to be performed on the tenant of the row user. In most cases this is the
     * current tenant, except when we are viewing "All users" from any tenant (including shared space)
     */
    private function add_actions() {
        $blankurl = new \moodle_url('#');

        $icon = new \pix_icon('i/settings', get_string('edituser', 'tool_tenant'), 'core');
        $action = (new \tool_reportbuilder\report_action($blankurl, $icon, [
            'data-action' => 'edit',
            'data-id' => ':id',
            'data-fullusername' => ':fullusername',
        ]))
            ->add_callback(function(\stdClass $row): bool {
                $row->fullusername = format::fullname('', $row);
                return permission::can_update_user($row, $this->get_action_tenantid($row));
            });
        $this->add_action($action);

        $icon = new \pix_icon('t/hide', get_string('suspenduser', 'tool_tenant'), 'core');
        $action = (new \tool_reportbuilder\report_action($blankurl, $icon, [
            'data-action' => 'suspend',
            'data-id' => ':id',
        ]))
            ->add_callback(function(\stdClass $row): bool {
                return permission::can_suspend_user($row, $this->get_action_tenantid($row));
            });
        $this->add_action($action);

        $icon = new \pix_icon('e/tick', get_string('confirmuser', 'tool_tenant'), 'core');
        $action = (new \tool_reportbuilder\report_action($blankurl, $icon, [
            'data-action' => 'confirm',
            'data-id' => ':id',
            'data-fullusername' => ':fullusername'
        ]))
            ->add_callback(function(\stdClass $row): bool {
                $row->fullusername = format::fullname('', $row);
                return permission::can_confirm_user($row, $this->get_action_tenantid($row));
            });
        $this->add_action($action);

        $icon = new \pix_icon('paper-plane-o', get_string('resendemailuser', 'tool_tenant'), 'tool_wp');
        $action = (new \tool_reportbuilder\report_action($blankurl, $icon, [
            'data-action' => 'resendemail',
            'data-id' => ':id',
        ]))
            ->add_callback(function(\stdClass $row): bool {
                return permission::can_resend_email_user($row, $this->get_action_tenantid($row));
            });
        $this->add_action($action);

        $icon = new \pix_icon('t/show', get_string('unsuspenduser', 'tool_tenant'), 'core');
        $action = (new \tool_reportbuilder\report_action($blankurl, $icon, [
            'data-action' => 'unsuspend',
            'data-id' => ':id',
        ]))
            ->add_callback(function(\stdClass $row): bool {
                return permission::can_unsuspend_user($row, $this->get_action_tenantid($row));
            });
        $this->add_action($action);

        $icon = new \pix_icon('t/delete', get_string('deleteuser', 'tool_tenant'), 'core');
        $action = (new \tool_reportbuilder\report_action($blankurl, $icon, [
            'data-action' => 'delete',
            'data-id' => ':id',
        ]))
            ->add_callback(function(\stdClass $row): bool {
                return permission::can_delete_user($row, $this->get_action_tenantid($row));
            });
        $this->add_action($action);
    }

    /**
     * Row class
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        return ($row->suspended || $row->confirmed == 0) ? 'dimmed_text' : '';
    }

    /**
     * Append the 'Tenant administrator' label/tag after user fullname when needed.
     *
     * @param string $value
     * @param \stdClass $row
     * @return string
     */
    public function append_admin_label($value, \stdClass $row): string {
        if ($row->tenantadmin > 0) {
            $value .= \html_writer::tag('span', ' ' . get_string('tenantadmin', 'tool_tenant'),
                ['class' => 'badge badge-secondary ml-2']);
        }
        return $value;
    }

    /**
     * Return list of column names that will be excluded when table is downloading.
     *
     * @return array
     */
    public function get_exclude_columns_for_download(): array {
        return ['check', 'actions'];
    }
}

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
 * Tool tenant external functions and service definitions.
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'tool_tenant_change_sortorder' => [
        'classname' => tool_tenant_external::class,
        'methodname' => 'change_sortorder',
        'description' => 'change sortorder of tenants',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_get_tenants' => [
        'classname' => tool_tenant_external::class,
        'methodname' => 'get_tenants',
        'description' => 'get all tenants',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_tenant_allocate_users' => [
        'classname' => tool_tenant_external::class,
        'methodname' => 'allocate_users',
        'description' => 'allocate user to tenant',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_confirm_users' => [
        'classname' => tool_tenant\external\confirm_users::class,
        'methodname' => 'execute',
        'description' => 'confirm users',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_resend_email_users' => [
        'classname' => tool_tenant\external\resend_confirmation_email::class,
        'methodname' => 'execute',
        'description' => 'resend email to users',
        'type' => 'write',
        'ajax' => true,
    ],

    'tool_tenant_suspend_users' => [
        'classname' => tool_tenant_external::class,
        'methodname' => 'suspend_users',
        'description' => 'Suspend users',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_unsuspend_users' => [
        'classname' => tool_tenant_external::class,
        'methodname' => 'unsuspend_users',
        'description' => 'Unsuspend users',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_delete_users' => [
        'classname' => tool_tenant_external::class,
        'methodname' => 'delete_users',
        'description' => 'Delete users',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_assign_tenant_admin_roles' => [
        'classname' => tool_tenant_external::class,
        'methodname' => 'assign_tenant_admin_roles',
        'description' => 'Assigns Tenant admin role to users',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_unassign_tenant_admin_roles' => [
        'classname' => tool_tenant_external::class,
        'methodname' => 'unassign_tenant_admin_roles',
        'description' => 'Unassigns Tenant admin role from users',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_potential_tenant_selector' => [
        'classname' => tool_tenant\external\potential_tenant_selector::class,
        'methodname' => 'execute',
        'description' => 'get list of tenants',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_tenant_enable_shared_space' => [
        'classname' => tool_tenant\external\enable_shared_space::class,
        'methodname' => 'execute',
        'description' => 'enable shared space',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_shared_space_disable_reminder' => [
        'classname' => tool_tenant\external\shared_space_disable_reminder::class,
        'methodname' => 'execute',
        'description' => 'disable shared space reminder',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_check_user_limit' => [
        'classname' => tool_tenant\external\check_user_limit::class,
        'methodname' => 'execute',
        'description' => 'see if user limit has reached',
        'type' => 'read',
        'ajax' => true,
    ],
    'tool_tenant_get_login_selector_tenants' => [
        'classname' => tool_tenant\external\get_login_selector_tenants::class,
        'methodname' => 'execute',
        'description' => 'Gets login tenant selector tenant list',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false,
    ],
    'tool_tenant_get_tenant_login_info' => [
        'classname' => tool_tenant\external\get_tenant_login_info::class,
        'description' => 'Get tenant login info',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_tenant_update_dashboardlinked' => [
        'classname' => tool_tenant\external\dashboard\update_dashboardlinked::class,
        'description' => 'Update dashboard linked value',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_reset_tenant_dashboard' => [
        'classname' => tool_tenant\external\dashboard\reset_tenant_dashboard::class,
        'description' => 'Reset dashboard for all users in a tenant',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_reset_all_linked_dashboards' => [
        'classname' => tool_tenant\external\dashboard\reset_all_linked_dashboards::class,
        'description' => 'Reset dashboard for all users in linked tenants',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_create_tenant' => [
        'classname' => tool_tenant\external\create_tenant::class,
        'description' => 'Create tenant',
        'type' => 'write'
    ],
    'tool_tenant_update_tenant' => [
        'classname' => tool_tenant\external\update_tenant::class,
        'description' => 'Update tenant',
        'type' => 'write'
    ],
    'tool_tenant_archive_tenant' => [
        'classname' => tool_tenant\external\archive_tenant::class,
        'description' => 'Archive a tenant. All users allocated to this tenant will be assumed to be in the default
                          tenant until this tenant is restored',
        'type' => 'write',
        'ajax' => true,
    ],
    'tool_tenant_restore_tenant' => [
        'classname' => tool_tenant\external\restore_tenant::class,
        'description' => 'Restore a tenant that was previously archived',
        'type' => 'write'
    ],
];

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
 * Tool tenant external functions and service definitions.
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
    ]
];

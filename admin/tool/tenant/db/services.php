<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Tool tenant external functions and service definitions.
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
];

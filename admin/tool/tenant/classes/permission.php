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
 * Class permission
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

defined('MOODLE_INTERNAL') || die();

/**
 * Class permission
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission {

    /**
     * can_manage_tenants
     *
     * @return bool
     */
    protected static function can_manage_tenants(): bool {
        return has_capability('tool/tenant:manage', \context_system::instance());
    }

    /**
     * can_view_tenants_list
     *
     * @return bool
     */
    public static function can_view_tenants_list(): bool {
        return has_any_capability(['tool/tenant:manage', 'tool/tenant:allocate'], \context_system::instance());
    }

    /**
     * require_can_view_tenants_list
     *
     * @throws \moodle_exception
     */
    public static function require_can_view_tenants_list(): void {
        if (!self::can_view_tenants_list()) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * can_view_archived_tenants_list
     *
     * @return bool
     */
    public static function can_view_archived_tenants_list(): bool {
        return self::can_manage_tenants();
    }

    /**
     * can_edit_tenant
     *
     * @param int $tenantid
     * @return bool
     */
    public static function can_edit_tenant(int $tenantid): bool {
        return self::is_tenant_active($tenantid) &&
            self::can_manage_tenants();
    }

    /**
     * require_can_edit_tenant
     *
     * @param int $tenantid
     * @throws \moodle_exception
     */
    public static function require_can_edit_tenant(int $tenantid): void {
        if (!self::can_edit_tenant($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * can_create_tenant
     *
     * @param bool $ignorelimit Ignore tenant limit, only check capability
     * @return bool
     */
    public static function can_create_tenant(bool $ignorelimit = false): bool {
        global $CFG;
        if (!self::can_manage_tenants()) {
            return false;
        }
        if (!$ignorelimit && $CFG->tool_tenant_tenantlimitenabled == 1) {
            $archived = (new manager())->get_archived_tenants();
            return count(tenancy::get_tenants()) + count($archived) < $CFG->tool_tenant_tenantlimit;
        }
        return true;
    }

    /**
     * require_can_create_tenant
     *
     * @throws \moodle_exception
     */
    public static function require_can_create_tenant(): void {
        if (!self::can_create_tenant()) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * Can this user create users in the supplied tenant.
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True is this user can create users in the tenant.
     */
    public static function can_create_users(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid)) {
            return false;
        }

        $systemcontext = \context_system::instance();
        if (has_capability('moodle/user:create', $systemcontext)) {
            // User has capability to create users anywhere.
            return true;
        }

        // User belongs to the same tenant and has capability to manage users.
        return (tenancy::get_tenant_id() == $tenantid
            && has_capability('tool/tenant:manageusers', \context_system::instance()));
    }

    /**
     * require_can_create_users
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @throws \moodle_exception
     */
    public static function require_can_create_users(int $tenantid = 0) {
        if (!self::can_create_users($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/user:create', 'nopermissions', 'error');
        }
    }

    /**
     * Can this user update users in the supplied tenant.
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_update_users(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid)) {
            return false;
        }
        $systemcontext = \context_system::instance();
        if (has_capability('moodle/user:update', $systemcontext)) {
            // User has capability to update users anywhere.
            return true;
        }

        // User belongs to the same tenant and has capability to manage users.
        return (tenancy::get_tenant_id() == $tenantid
            && has_capability('tool/tenant:manageusers', \context_system::instance()));
    }

    /**
     * require_can_update_users
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @throws \moodle_exception
     */
    public static function require_can_update_users(int $tenantid = 0): void {
        if (!self::can_update_users($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/user:update', 'nopermissions', 'error');
        }
    }

    /**
     * Can update the given user
     *
     * @param \stdClass $user
     * @param int $usertenantid user tenant id if known, pass "null" if tenant is not known
     * @return bool
     */
    public static function can_update_user(\stdClass $user, ?int $usertenantid = null): bool {
        if ($usertenantid === null) {
            $usertenantid = tenancy::get_tenant_id($user->id);
        }
        return self::can_update_users($usertenantid);
    }

    /**
     * require_can_update_user
     *
     * @param \stdClass $user
     * @param int|null $usertenantid
     * @throws \moodle_exception
     */
    public static function require_can_update_user(\stdClass $user, ?int $usertenantid = null): void {
        if (!self::can_update_user($user, $usertenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/user:update', 'nopermissions', 'error');
        }
    }

    /**
     * Checks if given tenant exists and is active (not archived)
     *
     * Note that both is_tenant_active() and is_tenant_archived() would return false for non-existing tenant
     *
     * @param int $tenantid
     * @return bool
     */
    protected static function is_tenant_active(int $tenantid): bool {
        return array_key_exists($tenantid, tenancy::get_tenants());
    }

    /**
     * Checks if given tenant exists and is archived
     *
     * Note that both is_tenant_active() and is_tenant_archived() would return false for non-existing tenant
     *
     * @param int $tenantid
     * @return bool
     */
    protected static function is_tenant_archived(int $tenantid): bool {
        return array_key_exists($tenantid, (new manager())->get_archived_tenants());
    }

    /**
     * Can this user browse users in tenants. Will first check to see if the user can browse users in all tenants
     * and if not then check if they have the capability to browser users in the specified tenant (will use the default
     * tenant by default).
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True is this user can browse users.
     */
    public static function can_browse_users(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid)) {
            return false;
        }

        // Check for general permission.
        if (has_capability('tool/tenant:manage', \context_system::instance())
            || self::can_move_users_between_tenants()) {
            return true;
        }

        // Check for permission specific to this tenant.
        if (self::can_create_users($tenantid) || self::can_update_users($tenantid)) {
            return true;
        }
        if (tenancy::get_tenant_id() == $tenantid
            && has_capability('tool/tenant:browseusers', \context_system::instance())) {
            return true;
        }
        return false;
    }

    /**
     * Can this user delete users in the supplied tenant.
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_delete_users(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid)) {
            return false;
        }

        $systemcontext = \context_system::instance();
        if (has_capability('moodle/user:delete', $systemcontext)) {
            // User has capability to delete users anywhere.
            return true;
        }

        // User belongs to the same tenant and has capability to manage users.
        return (tenancy::get_tenant_id() == $tenantid
            && has_capability('tool/tenant:manageusers', $systemcontext));
    }

    /**
     * require_can_delete_users
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @throws \moodle_exception
     */
    public static function require_can_delete_users(int $tenantid = 0): void {
        if (!self::can_delete_users($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/user:delete', 'nopermissions', 'error');
        }
    }

    /**
     * Can delete the given user
     *
     * @param \stdClass $user
     * @param int $usertenantid user tenant id if known, pass "null" if tenant is not known
     * @return bool
     */
    public static function can_delete_user(\stdClass $user, ?int $usertenantid = null): bool {
        global $USER;
        if ($user->id == $USER->id || isguestuser($user) || is_siteadmin($user)) {
            return false;
        }

        if ($usertenantid === null) {
            $usertenantid = tenancy::get_tenant_id($user->id);
        }
        return self::can_delete_users($usertenantid);
    }

    /**
     * require_can_delete_user
     *
     * @param \stdClass $user
     * @param int $usertenantid user tenant id if known, pass "null" if tenant is not known
     * @throws \moodle_exception
     */
    public static function require_can_delete_user(\stdClass $user, ?int $usertenantid = null): void {
        if (!self::can_delete_user($user, $usertenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/user:delete', 'nopermissions', 'error');
        }
    }

    /**
     * Can this user suspend users in the supplied tenant (loose check)
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_suspend_users(int $tenantid = 0) : bool {
        return self::can_update_users($tenantid);
    }

    /**
     * Can suspend the given user
     *
     * @param \stdClass $user
     * @param int $usertenantid user tenant id if known, pass "null" if tenant is not known
     * @return bool
     */
    public static function can_suspend_user(\stdClass $user, ?int $usertenantid = null): bool {
        global $USER;
        if ($user->id == $USER->id || $user->suspended || isguestuser($user) || is_siteadmin($user)) {
            return false;
        }

        if ($usertenantid === null) {
            $usertenantid = tenancy::get_tenant_id($user->id);
        }
        return self::can_suspend_users($usertenantid);
    }

    /**
     * require_can_suspend_user
     *
     * @param \stdClass $user
     * @param int $usertenantid user tenant id if known, pass "null" if tenant is not known
     * @throws \moodle_exception
     */
    public static function require_can_suspend_user(\stdClass $user, ?int $usertenantid = null): void {
        if (!self::can_suspend_user($user, $usertenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/user:update', 'nopermissions', 'error');
        }
    }

    /**
     * Can unsuspend the given user
     *
     * @param \stdClass $user
     * @param int $usertenantid user tenant id if known, pass "null" if tenant is not known
     * @return bool
     */
    public static function can_unsuspend_user(\stdClass $user, ?int $usertenantid = null): bool {
        if (!$user->suspended || isguestuser($user)) {
            return false;
        }

        if ($usertenantid === null) {
            $usertenantid = tenancy::get_tenant_id($user->id);
        }
        return self::can_suspend_users($usertenantid);
    }

    /**
     * require_can_unsuspend_user
     *
     * @param \stdClass $user
     * @param int $usertenantid user tenant id if known, pass "null" if tenant is not known
     * @throws \moodle_exception
     */
    public static function require_can_unsuspend_user(\stdClass $user, ?int $usertenantid = null): void {
        if (!self::can_unsuspend_user($user, $usertenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/user:update', 'nopermissions', 'error');
        }
    }

    /**
     * Can user view "Roles" tab for the given tenant id
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool
     */
    public static function can_see_roles_tab(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid)) {
            return false;
        }
        $tenant = tenancy::get_tenants()[$tenantid];

        if (!has_capability('moodle/role:assign', \context_system::instance())) {
            $categorycontext = $tenant->categoryid ?
                \context_coursecat::instance($tenant->categoryid, IGNORE_MISSING) :
                null;
            if (!$categorycontext || !has_capability('moodle/role:assign', $categorycontext)) {
                return false;
            }
        }

        return self::can_browse_users($tenantid);
    }

    /**
     * Can the user move other users between different tenants.
     *
     * @return bool True if the user has permission to move users between tenants.
     */
    public static function can_move_users_between_tenants() : bool {
        return has_capability('tool/tenant:allocate', \context_system::instance());
    }

    /**
     * require_can_move_users_between_tenants
     *
     * @throws \moodle_exception
     */
    public static function require_can_move_users_between_tenants(): void {
        if (!self::can_move_users_between_tenants()) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:allocate', 'nopermissions', 'error');
        }
    }

    /**
     * Can the user edit the theme for tenants.
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True if the user has permission to edit the themes of tenants, else false.
     */
    public static function can_edit_tenant_theme(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid)) {
            return false;
        }

        // Check for general permission.
        if (has_capability('tool/tenant:manage', \context_system::instance())) {
            return true;
        }

        return ($tenantid == tenancy::get_tenant_id()) &&
            has_capability('tool/tenant:managetheme', \context_system::instance());
    }

    /**
     * require_can_edit_tenant_theme
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @throws \moodle_exception
     */
    public static function require_can_edit_tenant_theme(int $tenantid = 0): void {
        if (!self::can_edit_tenant_theme($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:managetheme', 'nopermissions', 'error');
        }
    }

    /**
     * can_view_tenant_details
     *
     * @param int $tenantid Tenant id
     * @return bool
     */
    public static function can_view_tenant_details(int $tenantid): bool {
        return self::is_tenant_active($tenantid) &&
            (self::can_browse_users($tenantid) || self::can_edit_tenant_theme($tenantid));

    }

    /**
     * can_edit_tenant_details
     *
     * @param int $tenantid Tenant id
     * @return bool
     */
    public static function can_edit_tenant_details(int $tenantid): bool {
        return self::is_tenant_active($tenantid) &&
            self::can_manage_tenants();

    }

    /**
     * can_move_tenant
     *
     * @param int $tenantid
     * @return bool
     */
    public static function can_move_tenant(int $tenantid): bool {
        return self::is_tenant_active($tenantid) &&
            self::can_manage_tenants();
    }

    /**
     * require_can_move_tenant
     *
     * @param int $tenantid
     * @throws \moodle_exception
     */
    public static function require_can_move_tenant(int $tenantid): void {
        if (!self::can_move_tenant($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * can_archive_tenant
     *
     * @param int $tenantid
     * @return bool
     */
    public static function can_archive_tenant(int $tenantid): bool {
        return self::can_manage_tenants() &&
            self::is_tenant_active($tenantid) &&
            $tenantid != tenancy::get_default_tenant_id();
    }

    /**
     * require_can_archive_tenant
     *
     * @param int $tenantid
     * @throws \moodle_exception
     */
    public static function require_can_archive_tenant(int $tenantid): void {
        if (!self::can_archive_tenant($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * can_delete_tenant
     *
     * @param int $tenantid
     * @return bool
     */
    public static function can_delete_tenant(int $tenantid): bool {
        return self::is_tenant_archived($tenantid) &&
            self::can_manage_tenants();
    }

    /**
     * require_can_delete_tenant
     *
     * @param int $tenantid
     * @throws \moodle_exception
     */
    public static function require_can_delete_tenant(int $tenantid): void {
        if (!self::can_delete_tenant($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * can_restore_tenant
     *
     * @param int $tenantid
     * @return bool
     */
    public static function can_restore_tenant(int $tenantid): bool {
        return self::is_tenant_archived($tenantid) &&
            self::can_manage_tenants();
    }

    /**
     * require_can_restore_tenant
     *
     * @param int $tenantid
     * @throws \moodle_exception
     */
    public static function require_can_restore_tenant(int $tenantid): void {
        if (!self::can_restore_tenant($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * can_assign_tenant_admin
     *
     * @param int $tenantid
     * @return bool
     */
    public static function can_assign_tenant_admin(int $tenantid): bool {
        return self::can_manage_tenants();
    }

    /**
     * require_can_assign_tenant_admin
     *
     * @param int $tenantid
     * @throws \moodle_exception
     */
    public static function require_can_assign_tenant_admin(int $tenantid): void {
        if (!self::can_assign_tenant_admin($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * Return true if user can switch tenant, false otherwise.
     *
     * @return bool
     */
    public static function can_switch_tenant(): bool {
        $context = \context_system::instance();
        return (isloggedin() && !isguestuser() && !is_major_upgrade_required() && has_capability('tool/tenant:manage', $context));
    }

    /**
     * require_can_switch_tenant
     *
     * @throws \moodle_exception
     */
    public static function require_can_switch_tenant(): void {
        if (!self::can_switch_tenant()) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * Is current user allowed to view users in all tenants
     *
     * @return bool
     */
    public static function can_view_users_in_all_tenants(): bool {
        return has_any_capability(['moodle/site:viewparticipants', 'tool/tenant:manage', 'tool/tenant:allocate'],
            \context_system::instance());
    }
}

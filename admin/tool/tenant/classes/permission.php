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
 * Class permission
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use moodle_exception;

/**
 * Class permission
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
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
     * Whether current user is able to export tenants
     *
     * @return bool
     */
    public static function can_export_tenants(): bool {
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
            !sharedspace::is_shared_space($tenantid) &&
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
            // TODO should we exclude "Shared space"?
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
     * require_check_quotas_to_add_users
     *
     * @param int $tenantid
     * @param int $numberofusers
     * @throws \moodle_exception
     */
    public static function require_check_quotas_to_add_users(int $tenantid = 0, int $numberofusers = 0) {
        if (!self::check_quotas_to_add_users($tenantid, $numberofusers)) {
            throw new \moodle_exception('userslimitreached', 'tool_tenant');
        }
    }
    /**
     * Checks quotas if it is possible to create or unsuspend given number of users in a particular tenant
     *
     * @param int $tenantid
     * @param int $numberofusers
     * @return bool true if quota available, false if not.
     * @throws \dml_exception
     */
    public static function check_quotas_to_add_users(int $tenantid, int $numberofusers) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        global $DB;
        if ($numberofusers <= 0) {
            return true;
        }

        $userlimitenabled = (int)get_config('core', 'userlimitenabled') ?: 0;
        $tenantlimitenabled = (int)get_config('core', 'tool_tenant_userlimitenabled') ?: 0;

        $userlimitconfigvalue = $userlimitenabled ? ((int)get_config('core', 'userlimit') ?: 0) : 0;
        $tenantuserlimitconfigvalue = $tenantlimitenabled ? (get_config('core', 'tool_tenant_userlimit') ?: 0) : 0;

        if ($userlimitenabled && $userlimitconfigvalue > 0) {
            $sitwidewithoutsuspended = get_users(false, '', false, null,
                'firstname ASC', '', '', '', '',
                '*', 'suspended = :suspended', ['suspended' => 0]);
            if ($numberofusers > $userlimitconfigvalue - $sitwidewithoutsuspended) {
                return false;
            }
        }

        if ($tenantlimitenabled && $tenantuserlimitconfigvalue > 0) {
            $sql = tenancy::get_users_subquery(false, true, 'id', $tenantid, false);
            $tenantusercount = $DB->count_records_select('user', "{$sql} deleted = 0 AND suspended = 0");
            if ($numberofusers > $tenantuserlimitconfigvalue - $tenantusercount) {
                return false;
            }
        }

        return true;
    }

    /**
     * Can this user create users in the supplied tenant.
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True is this user can create users in the tenant.
     */
    public static function can_create_users(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid) || sharedspace::is_shared_space($tenantid)) {
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
     * @return bool
     */
    public static function can_update_users(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid) || sharedspace::is_shared_space($tenantid)) {
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
     * @throws \required_capability_exception
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
     * Can the current user edit the custom profile fields of another user
     *
     * @param int $userid another user id, if 0 check if we can edit custom profile field when creating user
     * @return bool
     */
    public static function can_edit_custom_profile_field(int $userid) {
        if ($userid > 0 && self::can_update_users(tenancy::get_tenant_id($userid))) {
            return true;
        }
        if (self::can_create_users()) {
            return true;
        }
        return false;
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
        return array_key_exists($tenantid, tenancy::get_tenants()) || sharedspace::is_shared_space($tenantid);
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
     * Can this user browse users in the specified tenant. Will first check to see if the user can browse users in all tenants
     * and if not then check if they have the capability to browser users in the specified tenant (will use the default
     * tenant by default).
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True is this user can browse users.
     */
    public static function can_browse_users(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid) || sharedspace::is_shared_space($tenantid)) {
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
     * Current user is able to browse all users from all tenants.
     *
     * @return bool
     */
    public static function can_browse_all_users(): bool {
        return self::can_browse_users_anywhere() && self::can_switch_tenant();
    }
    /**
     * Current user is able to browse users at least in their own tenant
     *
     * @return bool
     */
    public static function can_browse_users_anywhere() : bool {
        return has_any_capability(['tool/tenant:manage', 'tool/tenant:browseusers', 'tool/tenant:manageusers',
            'moodle/user:create', 'moodle/user:update'], \context_system::instance()) || self::can_move_users_between_tenants();
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
            $usertenantid = tenancy::get_actual_tenant_id($user->id);
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
     * Can this user confirm users in the supplied tenant (loose check)
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_confirm_anybody(int $tenantid = 0) : bool {
        return self::can_update_users($tenantid);
    }
    /**
     * require_can_confirm_anybody
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @throws \moodle_exception
     */
    public static function require_can_confirm_anybody(int $tenantid = 0) : void {
        if (!self::can_confirm_anybody($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/user:update', 'nopermissions', 'error');
        }
    }

    /**
     * Can confirm the given user
     *
     * @param \stdClass $user
     * @param int $usertenantid user tenant id if known, pass "null" if tenant is not known
     * @return bool
     */
    public static function can_confirm_user(\stdClass $user, ?int $usertenantid = null): bool {
        global $USER;
        if ($user->id == $USER->id || $user->confirmed || isguestuser($user) || is_siteadmin($user)) {
            return false;
        }

        if ($usertenantid === null) {
            $usertenantid = tenancy::get_actual_tenant_id($user->id);
        }
        return self::can_confirm_anybody($usertenantid);
    }

    /**
     * require_can_confirm_user
     *
     * @param \stdClass $user
     * @param int|null $usertenantid
     * @return void
     * @throws \required_capability_exception
     */
    public static function require_can_confirm_user(\stdClass $user, ?int $usertenantid = null) : void {
        if (!self::can_confirm_user($user, $usertenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/user:update', 'nopermissions', 'error');
        }
    }

    /**
     * Can this user resend confirmation email to users in the supplied tenant (loose check)
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True is this user can edit users in the tenant.
     */
    public static function can_resend_email_users(int $tenantid = 0) : bool {
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
            $usertenantid = tenancy::get_actual_tenant_id($user->id);
        }
        return self::can_suspend_users($usertenantid);
    }

    /**
     * Can resend confirmation email to the given user
     *
     * @param \stdClass $user
     * @param int $usertenantid user tenant id if known, pass "null" if tenant is not known
     * @return bool
     */
    public static function can_resend_email_user(\stdClass $user, ?int $usertenantid = null): bool {
        global $USER;
        if ($user->id == $USER->id || $user->confirmed || isguestuser($user) || is_siteadmin($user)) {
            return false;
        }

        if ($usertenantid === null) {
            $usertenantid = tenancy::get_actual_tenant_id($user->id);
        }
        return self::can_resend_email_users($usertenantid);
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
     * require_can_resend_email_user
     *
     * @param \stdClass $user
     * @param int $usertenantid user tenant id if known, pass "null" if tenant is not known
     */
    public static function require_can_resend_email_user(\stdClass $user, ?int $usertenantid = null): void {
        if (!self::can_resend_email_user($user, $usertenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'moodle/user:update', 'nopermissions', 'error');
        }
    }

    /**
     * Can unsuspend the given user
     *
     * @param \stdClass $user
     * @param int|null $usertenantid user tenant id if known, pass "null" if tenant is not known
     * @return bool
     */
    public static function can_unsuspend_user(\stdClass $user, ?int $usertenantid = null): bool {
        if (!$user->suspended || isguestuser($user)) {
            return false;
        }

        if ($usertenantid === null) {
            $usertenantid = tenancy::get_actual_tenant_id($user->id);
        }
        return self::can_suspend_users($usertenantid);
    }

    /**
     * require_can_unsuspend_user
     *
     * @param \stdClass $user
     * @param int|null $usertenantid
     * @throws \required_capability_exception
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
        if (sharedspace::is_shared_space($tenantid)) {
            return false;
        }
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
     * Determine whether current user can move given user to tenant
     *
     * Note that currently this only checks capabilities and ensures we aren't trying to allocate to the shared space. To be
     * re-factored when we have tenants hierarchy
     *
     * @param int $userid
     * @param int $tenantid
     * @return bool
     */
    public static function can_move_user_to_tenant(int $userid, int $tenantid): bool {
        return self::can_move_users_between_tenants() && !sharedspace::is_shared_space($tenantid);
    }

    /**
     * Require current user can move given user to tenant
     *
     * @param int $userid
     * @param int $tenantid
     * @throws moodle_exception
     */
    public static function require_can_move_user_to_tenant(int $userid, int $tenantid): void {
        if (!self::can_move_user_to_tenant($userid, $tenantid)) {
            throw new moodle_exception('cannotallocateusertotenant', 'tool_tenant');
        }
    }

    /**
     * Can this user edit tenant theme anywhere, at least in their own tenant
     *
     * @return bool
     */
    public static function can_edit_tenant_theme_anywhere() : bool {
        return has_any_capability(['tool/tenant:manage', 'tool/tenant:managetheme'], \context_system::instance());
    }

    /**
     * Can the user edit the theme for the specified tenant
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True if the user has permission to edit the themes of tenants, else false.
     */
    public static function can_edit_tenant_theme(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid) || sharedspace::is_shared_space($tenantid)) {
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
     * Can this user manage tenant dashbaords anywhere
     *
     * @return bool
     */
    public static function can_edit_all_tenant_dashboards() : bool {
        return (self::can_switch_tenant() && has_capability('tool/tenant:managedashboard', \context_system::instance()));
    }

    /**
     * require_can_edit_all_tenant_dashboards
     *
     * @throws \moodle_exception
     */
    public static function require_can_edit_all_tenant_dashboards(): void {
        if (!self::can_edit_all_tenant_dashboards()) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:managedashboard', 'nopermissions', 'error');
        }
    }


    /**
     * Can this user manage tenant dashbaord blocks anywhere
     *
     * @return bool
     */
    public static function can_edit_tenant_dashboard_blocks_anywhere(): bool {
        return has_capability('tool/tenant:managedashboard', \context_system::instance());
    }

    /**
     * Can the user manage the dashboard blocks for the specified tenant
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True if the user has permission to manage the blocks in the dashboard of tenants, else false.
     */
    public static function can_edit_tenant_dashboard_blocks(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        $tenant = new tenant($tenantid);
        return (self::can_see_tenant_dashboard_tab($tenantid) && !$tenant->get('dashboardlinked'));
    }

    /**
     * require_can_edit_tenant_dashboard_blocks
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @throws \moodle_exception
     */
    public static function require_can_edit_tenant_dashboard_blocks(int $tenantid = 0): void {
        if (!self::can_edit_tenant_dashboard_blocks($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:managedashboard', 'nopermissions', 'error');
        }
    }

    /**
     * Can view tenants "Dashboard" tab
     * This method is also used to check if can link/un-link dashboards, and to check if can reset user dashboards.
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool
     */
    public static function can_see_tenant_dashboard_tab(int $tenantid = 0) : bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        if (!self::is_tenant_active($tenantid) || sharedspace::is_shared_space($tenantid)) {
            return false;
        }
        return self::can_access_tenant($tenantid) && has_capability('tool/tenant:managedashboard', \context_system::instance());
    }

    /**
     * require_can_see_tenant_dashboard_tab
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @throws \moodle_exception
     */
    public static function require_can_see_tenant_dashboard_tab(int $tenantid = 0): void {
        if (!self::can_see_tenant_dashboard_tab($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:managedashboard', 'nopermissions', 'error');
        }
    }

    /**
     * Can the user edit the site dashboard
     *
     * @return bool True if the user has permission to edit the default site dashboard, else false.
     */
    public static function can_edit_site_dashboard(): bool {
        return has_capability('moodle/my:configsyspages', \context_system::instance());
    }

    /**
     * Can view "Details" tab
     *
     * @param int $tenantid Tenant id
     * @return bool
     */
    public static function can_view_tenant_details(int $tenantid): bool {
        return self::is_tenant_active($tenantid) && !sharedspace::is_shared_space($tenantid) &&
            (self::can_browse_users($tenantid) || self::can_edit_tenant_theme($tenantid));

    }

    /**
     * Can view "Details" tab
     *
     * @param int $tenantid Tenant id
     */
    public static function require_can_view_tenant_details(int $tenantid) {
        if (!self::can_view_tenant_details($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * can_edit_tenant_details
     *
     * @param int $tenantid Tenant id
     * @return bool
     */
    public static function can_edit_tenant_details(int $tenantid): bool {
        return self::is_tenant_active($tenantid) && !sharedspace::is_shared_space($tenantid) &&
            self::can_manage_tenants();

    }

    /**
     * can_move_tenant
     *
     * @param int $tenantid
     * @return bool
     */
    public static function can_move_tenant(int $tenantid): bool {
        return self::is_tenant_active($tenantid) && !sharedspace::is_shared_space($tenantid) &&
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
            self::is_tenant_active($tenantid) && !sharedspace::is_shared_space($tenantid) &&
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
        return self::is_tenant_archived($tenantid) && !sharedspace::is_shared_space($tenantid) &&
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
        return self::can_manage_tenants() && !sharedspace::is_shared_space($tenantid);
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
     * Return true if can change category, false otherwise.
     *
     * @param int $categoryid CategoryID
     * @return bool
     */
    public static function can_change_category_parent($categoryid): bool {
        return is_null(tenancy::find_tenant_by_category_id($categoryid));
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
     * @param int|null $currentuserid
     * @return bool
     */
    public static function can_view_users_in_all_tenants(?int $currentuserid = null): bool {
        return has_any_capability(['moodle/site:viewparticipants', 'tool/tenant:manage', 'tool/tenant:allocate'],
            \context_system::instance(), $currentuserid ?: null);
    }

    /**
     * User can access entities in the given tenant
     *
     * Currently user can access either their own tenant or all but when we introduce tenants hierarchy
     * this may be more complicated
     *
     * @param int $tenantid
     * @return bool
     */
    public static function can_access_tenant(int $tenantid): bool {
        return ($tenantid == tenancy::get_tenant_id()) ||
            self::can_switch_tenant();
    }

    /**
     * User can access the given tenant
     *
     * @param int $tenantid
     */
    public static function require_can_access_tenant(int $tenantid): void {
        if (!self::can_access_tenant($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:manage', 'nopermissions', 'error');
        }
    }

    /**
     * See if the user can change authentication method.
     * @param int $userid
     * @return bool
     */
    public static function can_change_user_auth_method(int $userid): bool {
        if ($userid > 0) {
            return has_capability('moodle/user:update', \context_system::instance());
        } else {
            return has_capability('moodle/user:create', \context_system::instance());
        }
    }

    /**
     * User should have permission to access/switch tenant
     * @return bool
     */
    public static function can_add_dynamicrule_outcome() {
        return self::can_switch_tenant();
    }

    /**
     * Checks if user can edit dynamic rule outcome
     *
     * User should be able to allocate users to given tenant.
     *
     * @param tenant $tenant
     * @return bool
     */
    public static function can_edit_dynamicrule_outcome(tenant $tenant): bool {
        return self::can_create_users($tenant->get('id'));
    }

    /**
     * Can user edit tenant authentication settings
     *
     * @param int $tenantid
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function can_edit_tenant_auth_settings(int $tenantid = 0): bool {
        return has_capability('tool/tenant:authconfig', \context_system::instance()) &&
            !sharedspace::is_shared_space($tenantid) &&
            self::can_access_tenant($tenantid ?: tenancy::get_tenant_id());
    }

    /**
     * require_can_edit_tenant_auth_settings
     *
     * @param int $tenantid
     * @throws \required_capability_exception
     */
    public static function require_can_edit_tenant_auth_settings(int $tenantid = 0): void {
        if (!self::can_edit_tenant_auth_settings($tenantid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/tenant:authconfig', 'nopermissions', 'error');
        }
    }

    /**
     * Can this user view suspended/non-confirmed users in the specified tenant.
     *
     * @param int $tenantid Tenant id (if empty the current tenant id will be used)
     * @return bool True if is user can view inactive users in the specified tenant.
     */
    public static function can_view_inactive_users(int $tenantid = 0): bool {
        return self::can_browse_users($tenantid);
    }

    /**
     * Can this user view suspended/non-confirmed users in all tenants.
     *
     * @return bool True if is user can view inactive users in all tenants.
     */
    public static function can_view_all_inactive_users(): bool {
        return self::can_browse_all_users();
    }
}

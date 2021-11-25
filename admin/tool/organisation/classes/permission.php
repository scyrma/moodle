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
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use tool_organisation\output\user_with_jobs;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class permission
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission {

    /**
     * has_manage_positions_capability
     *
     * @return bool
     */
    public static function has_manage_positions_capability(): bool {
        return has_capability('tool/organisation:managepositions', \context_system::instance());
    }

    /**
     * is_editable
     *
     * @param hierarchy $hierarchy
     * @param int $tenantid
     * @return bool
     */
    protected static function is_editable(hierarchy $hierarchy, int $tenantid = 0): bool {
        $tenantid = $tenantid ?: tenancy::get_tenant_id();
        return !$hierarchy->get('archived') && self::can_access_entity($hierarchy) &&
            $hierarchy->get('tenantid') == $tenantid;
    }

    /**
     * can_create_position
     *
     * @param null|position $parentposition
     * @return bool
     */
    public static function can_create_position(?position $parentposition = null): bool {
        return self::has_manage_positions_capability() &&
            (!$parentposition || self::is_editable($parentposition));
    }

    /**
     * can_edit_position
     *
     * @param position $position
     * @return bool
     */
    public static function can_edit_position(position $position): bool {
        return self::has_manage_positions_capability() &&
            self::is_editable($position);
    }

    /**
     * Checks if user can edit the current position, if the position is from another tenant checks if user can switch
     * to that another tenant.
     *
     * @param position $position
     * @return bool
     */
    public static function can_edit_position_in_its_tenant(position $position): bool {
        return self::has_manage_positions_capability() &&
            !$position->get('archived') &&
            \tool_tenant\permission::can_access_tenant($position->get('tenantid'));
    }

    /**
     * can_view_positions
     *
     * @return bool
     */
    public static function can_view_positions(): bool {
        return self::has_manage_positions_capability();
    }

    /**
     * has_manage_departments_capability
     *
     * @return bool
     */
    public static function has_manage_departments_capability(): bool {
        return has_capability('tool/organisation:managedepartments', \context_system::instance());
    }

    /**
     * can_create_department
     *
     * @param null|department $parentdepartment
     * @return bool
     */
    public static function can_create_department(?department $parentdepartment = null): bool {
        return self::has_manage_departments_capability() &&
            (!$parentdepartment || self::is_editable($parentdepartment));
    }

    /**
     * can_edit_department
     *
     * @param department $department
     * @return bool
     */
    public static function can_edit_department(department $department): bool {
        return self::has_manage_departments_capability() &&
            self::is_editable($department);
    }

    /**
     * Checks if user can edit the current department, if the department is from another tenant checks if user can switch
     * to that another tenant.
     *
     * @param department $department
     * @return bool
     */
    public static function can_edit_department_in_its_tenant(department $department): bool {
        return self::has_manage_departments_capability() &&
            !$department->get('archived') &&
            \tool_tenant\permission::can_access_tenant($department->get('tenantid'));
    }

    /**
     * can_view_departments
     *
     * @return bool
     */
    public static function can_view_departments(): bool {
        return self::has_manage_departments_capability();
    }

    /**
     * has_assign_jobs_capability
     *
     * @return bool
     */
    public static function has_assign_jobs_capability(): bool {
        return has_capability('tool/organisation:assignjobs', \context_system::instance());
    }

    /**
     * can_assign_job_to_anybody
     *
     * @return bool
     */
    public static function can_assign_job_to_anybody(): bool {
        if (sharedspace::is_shared_space()) {
            return false;
        }
        return self::has_assign_jobs_capability();
    }

    /**
     * can_assign_job_to_user
     *
     * @param int $userid
     * @return bool
     */
    public static function can_assign_job_to_user(int $userid): bool {
        return self::has_assign_jobs_capability() &&
            \tool_tenant\permission::can_access_tenant(tenancy::get_tenant_id($userid));
    }

    /**
     * can_edit_job
     *
     * @param job $job
     * @return bool
     */
    public static function can_edit_job(job $job): bool {
        return self::has_assign_jobs_capability() &&
            \tool_tenant\permission::can_access_tenant($job->get('tenantid')) &&
            \tool_tenant\permission::can_access_tenant(tenancy::get_tenant_id($job->get('userid')));
    }

    /**
     * can_view_jobs
     *
     * @return bool
     */
    public static function can_view_jobs(): bool {
        if (sharedspace::is_shared_space()) {
            return false;
        }
        return self::has_assign_jobs_capability();
    }

    /**
     * Can view jobs of another user
     *
     * @param \stdClass $user
     * @return bool
     */
    public static function can_view_user_jobs(\stdClass $user): bool {
        global $USER;

        return ($user->id == $USER->id) ||
            (self::has_assign_jobs_capability() &&
                \tool_tenant\permission::can_access_tenant(tenancy::get_tenant_id($user->id))) ||
            (($userorg = \tool_organisation\organisation::get_user_with_jobs()) &&
                $userorg->is_manager_over_user($user->id));
    }

    /**
     * can_view_index
     *
     * @return bool
     */
    public static function can_view_index(): bool {
        return self::can_view_jobs() ||
            self::can_view_departments() ||
            self::can_view_positions();
    }

    /**
     * require_can_edit_department
     *
     * @param department $department
     * @throws \moodle_exception
     */
    public static function require_can_edit_department(department $department): void {
        if (!self::can_edit_department($department)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/organisation:managedepartments', 'nopermissions', '');
        }
    }

    /**
     * require_can_view_department
     *
     * @param department $department
     * @throws \moodle_exception
     */
    public static function require_can_view_department(department $department): void {
        if (!self::can_view_departments() || !self::can_access_entity($department)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/organisation:managedepartments', 'nopermissions', '');
        }
    }

    /**
     * require_can_create_department
     *
     * @param null|department $parent
     * @throws \moodle_exception
     */
    public static function require_can_create_department(?department $parent = null): void {
        if (!self::can_create_department($parent)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/organisation:managedepartments', 'nopermissions', '');
        }
    }

    /**
     * require_can_edit_position
     *
     * @param position $position
     * @throws \moodle_exception
     */
    public static function require_can_edit_position(position $position): void {
        if (!self::can_edit_position($position)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/organisation:managepositions', 'nopermissions', '');
        }
    }

    /**
     * require_can_view_position
     *
     * @param position $position
     * @throws \moodle_exception
     */
    public static function require_can_view_position(position $position): void {
        if (!self::can_view_positions() || !self::can_access_entity($position)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/organisation:managepositions', 'nopermissions', '');
        }
    }

    /**
     * require_can_create_position
     *
     * @param null|position $parent
     * @throws \moodle_exception
     */
    public static function require_can_create_position(?position $parent = null): void {
        if (!self::can_create_position($parent)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/organisation:managepositions', 'nopermissions', '');
        }
    }

    /**
     * require_can_assign_job_to_anybody
     *
     * @throws \moodle_exception
     */
    public static function require_can_assign_job_to_anybody(): void {
        if (!self::can_assign_job_to_anybody()) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/organisation:assignjobs', 'nopermissions', '');
        }
    }

    /**
     * require_can_edit_job
     *
     * @param job $job
     * @throws \moodle_exception
     */
    public static function require_can_edit_job(job $job): void {
        if (!self::can_edit_job($job)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/organisation:assignjobs', 'nopermissions', '');
        }
    }

    /**
     * require_can_assign_job_to_user
     *
     * @param int $userid
     * @throws \moodle_exception
     */
    public static function require_can_assign_job_to_user(int $userid): void {
        if (!self::can_assign_job_to_user($userid)) {
            throw new \required_capability_exception(\context_system::instance(),
                'tool/organisation:assignjobs', 'nopermissions', '');
        }
    }

    /**
     * Has program allocate user capability
     *
     * @return bool
     */
    public static function has_allocateuser_capability(): bool {
        return has_capability('tool/program:allocateuser', \context_system::instance());
    }

    /**
     * Checks if user can view program overdue report
     *
     * @param user_with_jobs $user
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function can_view_user_programs_overdue(user_with_jobs $user): bool {
        $permissions = organisation::PERM_ALLOCATE_PROGRAMS + organisation::PERM_VIEW_REPORTS;
        return $user->is_manager($permissions) || self::has_allocateuser_capability();
    }

    /**
     * Checks if position/department can be accessed by the current user based on the position/department tenant
     *
     * This function does not check if position/department is archived or not
     *
     * @param hierarchy $hierarchy
     * @param int|null $userid
     * @return bool
     */
    public static function can_access_entity(hierarchy $hierarchy, int $userid = null): bool {
        return \tool_tenant\hierarchy::is_own_or_parent_shared_entity(
            $hierarchy->get('tenantid'),
            $hierarchy->get('shared'),
            $userid ? tenancy::get_tenant_id($userid) : tenancy::get_tenant_id()
        );
    }

    /**
     * Checks if user has department/global manager permission
     *
     * @return bool
     */
    public static function user_is_manager(): bool {
        $user = organisation::get_user_with_jobs();
        return $user && $user->is_manager();
    }
}

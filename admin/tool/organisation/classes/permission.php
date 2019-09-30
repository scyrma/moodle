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
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation;

use tool_organisation\output\user_with_jobs;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class permission
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * @return bool
     */
    protected static function is_editable(hierarchy $hierarchy): bool {
        return !$hierarchy->get('archived') && $hierarchy->get('tenantid') == tenancy::get_tenant_id();
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
            tenancy::get_tenant_id($userid) == tenancy::get_tenant_id();
    }

    /**
     * can_edit_job
     *
     * @param job $job
     * @return bool
     */
    public static function can_edit_job(job $job): bool {
        return self::has_assign_jobs_capability() &&
            $job->get('tenantid') == tenancy::get_tenant_id() &&
            tenancy::get_tenant_id($job->get('userid')) == tenancy::get_tenant_id();
    }

    /**
     * can_view_jobs
     *
     * @return bool
     */
    public static function can_view_jobs(): bool {
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
                tenancy::get_tenant_id() == tenancy::get_tenant_id($user->id)) ||
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
}

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
 * Permission class for tool_program.
 *
 * @package   tool_program
 * @copyright 2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program;

use context;
use context_system;
use moodle_exception;
use stdClass;
use tool_certification\api as certificationapi;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_tenant\tenancy;
use tool_organisation\organisation;

defined('MOODLE_INTERNAL') || die();

/**
 * Class permission. Contains permission check methods to allow/disallow actions related to programs.
 *
 * @package   tool_program
 * @copyright 2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission {

    /**
     * Check edit capability.
     *
     * @param context $context
     * @return bool
     */
    public static function has_edit_capability(?context $context = null): bool {
        $context = $context ?: context_system::instance();
        return has_capability('tool/program:edit', $context);
    }

    /**
     * Check allocateuser capability.
     *
     * @param context $context
     * @return bool
     */
    public static function has_allocateuser_capability(?context $context = null): bool {
        $context = $context ?: context_system::instance();
        return has_capability('tool/program:allocateuser', $context);
    }

    /**
     * Check configurecustomfields capability.
     *
     * @return bool
     */
    public static function has_configurecustomfields_capability(): bool {
        return has_capability('tool/program:configurecustomfields', context_system::instance());
    }

    /**
     * User can access the program contents and view it on the dashboard (my programs overview).
     *
     * @param program $program
     * @param int $userid
     * @return bool
     */
    public static function can_view_program(program $program, int $userid): bool {
        // TODO this is never called for user other than current.
        global $USER;

        if ($program->is_archived()) {
            return false;
        }

        if ($program->is_hidden()) {
            return false;
        }

        if (!api::is_active_allocation((int) $program->get('id'), $userid)) {
            return false;
        }

        if (tenancy::get_tenant_id($USER->id) !== (int) $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * Checks if current user can allocate users within the given program.
     *
     * @param program $program
     * @return bool
     */
    public static function can_allocate_anybody(program $program): bool {
        if (!$program->get('id')) {
            return false;
        }

        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        if (!$canallocate && !self::has_allocateuser_capability($program->get_context())) {
            return false;
        }

        if ($program->is_archived()) {
            return false;
        }

        if (!api::is_allocation_window_open($program)) {
            return false;
        }

        if (tenancy::get_tenant_id() !== (int) $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * Check if current user can edit a given program.
     *
     * @param program $program
     * @return bool
     */
    public static function can_edit_details(program $program): bool {

        if (!$program->get('id')) {
            return false;
        }

        if (!self::has_edit_capability($program->get_context())) {
            return false;
        }

        if ($program->is_archived()) {
            return false;
        }

        if (tenancy::get_tenant_id() != $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * Check if current user can view details of the program (can edit/can allocate/can view allocations)
     *
     * @param program $program
     * @return bool
     */
    public static function can_view_details(program $program): bool {
        return self::can_edit_details($program)
                || self::can_view_allocated_users($program);
    }

    /**
     * Check if current user can view program progress report
     *
     * @param program $program
     * @return bool
     */
    public static function can_view_users_progress(program $program): bool {
        return $program->get('id') &&
            tenancy::get_tenant_id() == $program->get('tenantid') &&
            (self::has_edit_capability($program->get_context())
                || self::has_allocateuser_capability($program->get_context())
                || self::can_allocate_anybody_as_organisation_manager());
    }

    /**
     * User can view programs list.
     *
     * @param context $context
     * @return bool
     */
    public static function can_view_list(?context $context = null): bool {
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        return (self::has_edit_capability($context) || self::has_allocateuser_capability($context) || $canallocate);
    }

    /**
     * User can create a program.
     *
     * @param context $context
     */
    public static function require_can_view_list(?context $context = null): void {
        if (!self::can_view_list($context)) {
            throw new moodle_exception('errornopermissionviewprograms', 'tool_program');
        }
    }

    /**
     * Checks if a given user allocation can be created in a given program.
     *
     * @param program $program
     * @param int $userid
     * @return bool
     */
    public static function can_allocate_user(program $program, int $userid): bool {
        if (!$program->get('id')) {
            return false;
        }

        // Check if user has allocateuser capability OR allocate permission from Organisation over this user.
        if (!self::has_allocateuser_capability($program->get_context()) &&
                !self::can_allocate_as_organisation_manager($userid)) {
            return false;
        }

        if ($program->is_archived()) {
            return false;
        }

        if (!api::is_allocation_window_open($program)) {
            return false;
        }

        if (tenancy::get_tenant_id() !== (int) $program->get('tenantid')) {
            return false;
        }

        $select = 'programid = ? AND userid = ? AND certificationid = ?';
        if (program_user::record_exists_select($select, [$program->get('id'), $userid, 0])) {
            // This allocation already exists and duplicates are not allowed!
            return false;
        }

        return true;
    }

    /**
     * Check if current user is able to view allocated users.
     *
     * @param program $program
     * @return bool
     */
    public static function can_view_allocated_users(program $program): bool {
        return self::can_edit_details($program) || self::can_allocate_anybody($program);
    }

    /**
     * Check if current user can delete the given program.
     *
     * @param program $program
     * @return bool
     */
    public static function can_delete(program $program): bool {
        if (!$program->get('id')) {
            return false;
        }

        if (!self::has_edit_capability($program->get_context())) {
            return false;
        }

        if (!$program->is_archived()) {
            return false;
        }

        if (tenancy::get_tenant_id() !== (int) $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * User can duplicate a program
     *
     * @param program $program
     * @return bool
     */
    public static function can_duplicate(program $program) : bool {
        return self::can_view_details($program) && self::can_create();
    }

    /**
     * User can create a program.
     *
     * @param context $context
     * @return bool
     */
    public static function can_create(?context $context = null): bool {
        return self::has_edit_capability($context);
    }

    /**
     * Check if current user can unarchive the given program.
     *
     * @param program $program
     * @return bool
     */
    public static function can_restore(program $program): bool {

        if (!self::has_edit_capability($program->get_context())) {
            return false;
        }

        if (!$program->is_archived()) {
            return false;
        }

        if (tenancy::get_tenant_id() !== (int) $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * Check current user can archive given program.
     *
     * @param program $program
     * @return bool
     */
    public static function can_archive(program $program): bool {
        // TODO WP-946 WP-966 performs DB queries.
        if (!$program->get('id')) {
            return false;
        }

        if (!self::has_edit_capability($program->get_context())) {
            return false;
        }

        if ($program->is_archived()) {
            return false;
        }

        if (tenancy::get_tenant_id() !== (int) $program->get('tenantid')) {
            return false;
        }

        if (api::belongs_to_non_archived_certification($program)) {
            return false;
        }

        return true;
    }

    /**
     * Can view list of archived programs
     *
     * @param context $context
     * @return bool
     */
    public static function can_view_archived_list(?context $context = null): bool {
        return self::has_edit_capability($context);
    }

    /**
     * Check if current user can edit the allocation of the given program user.
     *
     * @param program_user $programuser
     * @return bool
     */
    public static function can_edit_user_allocation(program_user $programuser): bool {
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        if (!$canallocate && !self::has_allocateuser_capability($programuser->get_program()->get_context())) {
            return false;
        }

        // If it is a certification allocation, the allocation manager is not able to change dates or edit/delete allocation.
        if ($programuser->is_certification_allocation()) {
            return false;
        }

        if ($programuser->get_program()->is_archived()) {
            return false;
        }

        if (tenancy::get_tenant_id() != tenancy::get_tenant_id($programuser->get('userid'))) {
            return false;
        }

        if (tenancy::get_tenant_id() != $programuser->get_program()->get('tenantid')) {
            return false;
        }
        return true;
    }

    /**
     * Check if current user can delete the allocation of the given program user.
     *
     * @param program_user $programuser
     * @return bool
     */
    public static function can_delete_user_allocation(program_user $programuser): bool {
        // Allocation window is valid.
        $program = $programuser->get_program();
        if (!api::is_allocation_window_open($program)) {
            return false;
        }
        // Can edit user allocation.
        if (!self::can_edit_user_allocation($programuser)) {
            return false;
        }
        // If allocation is from dynamic rules cannot delete user allocation.
        if (constants::ALLOCATION_MANUAL !== (int)$programuser->get('allocationtype')) {
            return false;
        }
        return true;
    }

    /**
     * Check if current user can view a given program.
     *
     * @param program $program
     * @param context $context
     * @param int $userid
     */
    public static function require_can_view_program(program $program, ?context $context, int $userid): void {
        // TODO this method is not used.
        if (!self::can_view_program($program, $userid)) {
            throw new moodle_exception('errornopermissionviewprograms', 'tool_program');
        }
    }

    /**
     * Require that current user is able to duplicate a given program.
     *
     * @param program $program
     */
    public static function require_can_duplicate(program $program): void {
        if (!self::can_duplicate($program)) {
            throw new moodle_exception('errornopermissionmanageprograms', 'tool_program');
        }
    }

    /**
     * Require that current user is able to archive a given program.
     *
     * @param program $program
     */
    public static function require_can_archive(program $program): void {
        if (!self::can_archive($program)) {
            throw new moodle_exception('errornopermissionmanageprograms', 'tool_program');
        }
    }

    /**
     * Require that current user is able to archive a given program.
     *
     * @param program $program
     */
    public static function require_can_restore(program $program): void {
        if (!self::can_restore($program)) {
            throw new moodle_exception('errornopermissionmanageprograms', 'tool_program');
        }
    }

    /**
     * Check if current user can create a program.
     *
     * @param context $context
     */
    public static function require_can_create(?context $context = null): void {
        if (!self::can_create($context)) {
            throw new moodle_exception('errornopermissionmanageprograms', 'tool_program');
        }
    }

    /**
     * Require that current user is able to allocate users to the given program.
     *
     * @param program $program
     */
    public static function require_can_allocate_anybody(program $program): void {
        if (!self::can_allocate_anybody($program)) {
            throw new moodle_exception('errorcantallocateusers', 'tool_program');
        }
    }

    /**
     * Require that current user is able to manage a given program.
     *
     * @param program $program
     */
    public static function require_can_edit_details(program $program): void {
        if (!self::can_edit_details($program)) {
            throw new moodle_exception('errornopermissionmanageprograms', 'tool_program');
        }
    }

    /**
     * Check if current user can view a given program.
     *
     * @param program $program
     */
    public static function require_can_view_details(program $program): void {
        if (!self::can_view_details($program)) {
            throw new moodle_exception('errornopermissionviewprograms', 'tool_program');
        }
    }

    /**
     * Require that current user is able to completely delete a given program.
     *
     * @param program $program
     */
    public static function require_can_delete(program $program): void {
        if (!self::can_delete($program)) {
            throw new moodle_exception('errornopermissionmanageprograms', 'tool_program');
        }
    }

    /**
     * Require that current user can self enrol to a given course within a program.
     *
     * @param int $courseid
     * @param program $program
     */
    public static function require_can_self_enrol_to_course(int $courseid, program $program): void {
        global $USER;

        $userid = (int) $USER->id;
        $programid = (int) $program->get('id');
        // Check if user is allocated into the program.
        if (!$programuser = program_user::get_record(['userid' => $userid, 'programid' => $programid])) {
            throw new moodle_exception('errorcantselfenrol', 'tool_program');
        }

        // Check if course is in the program.
        if (!api::is_course_in_program($program, $courseid)) {
            throw new moodle_exception('errorcantselfenrol', 'tool_program');
        }

        if (tenancy::get_tenant_id($userid) !== (int) $program->get('tenantid')) {
            throw new moodle_exception('errorcantselfenrol', 'tool_program');
        }

        // Check if course not locked for this program and user.
        $unlockedcoursesids = api::get_unlocked_courses_ids($program, $userid);
        if (!in_array($courseid, $unlockedcoursesids, true)) {
            throw new moodle_exception('errorcantselfenrol', 'tool_program');
        }
    }

    /**
     * Require that current user can be allocated to the given program.
     *
     * @param program $program
     * @param int $userid
     */
    public static function require_can_allocate_user(program $program, int $userid): void {
        if (!self::can_allocate_user($program, $userid)) {
            throw new moodle_exception('errorusercantbeallocated', 'tool_program');
        }
    }

    /**
     * Require that current user can manage allocations.
     *
     * @param program_user $programuser
     */
    public static function require_can_edit_user_allocation(?program_user $programuser): void {
        if (!$programuser || !self::can_edit_user_allocation($programuser)) {
            throw new moodle_exception('errornopermissionmanageusers', 'tool_program');
        }
    }

    /**
     * Can manage user list.
     *
     * @param program $program
     */
    public static function require_can_view_allocated_users(program $program): void {
        if (!self::can_view_allocated_users($program)) {
            throw new moodle_exception('errornopermissionmanageusers', 'tool_program');
        }
    }

    /**
     * Can view report on users progress for a particular program
     *
     * @param program $program
     */
    public static function require_can_view_users_progress(program $program): void {
        if (!self::can_view_users_progress($program)) {
            throw new moodle_exception('errornopermissionviewreports', 'tool_program');
        }
    }

    /**
     * Checks if we can show Programs link into site admin.
     *
     * @return bool
     */
    public static function check_access(): bool {
        return self::can_view_list();
    }

    /**
     * Checks if current user can view programs progress of the given user.
     *
     * User can see its own reports OR manager can see them if user is a subordinate.
     * User who can allocate to all programs also can see these reports.
     *
     * @param int $userid
     * @param program $program if specified will check if the progress for the given program can be viewed
     * @return bool
     */
    public static function can_view_user_programs_progress(int $userid, ?program $program = null): bool {
        global $USER;
        if ($userid != $USER->id) {
            $context = $program ? $program->get_context() : null;
            if (!self::has_allocateuser_capability($context) &&
                    !self::can_view_user_programs_progress_as_organisation_manager($userid)) {
                return false;
            }

            if (tenancy::get_tenant_id($userid) != tenancy::get_tenant_id()) {
                return false;
            }
        }

        if ($program) {
            // If program is specified check if it is available.
            // Archived programs are still available for this report.
            if ($program->get('tenantid') != tenancy::get_tenant_id()) {
                return false;
            }

            if ($program->is_hidden()) {
                // Only users who can manage programs can see hidden programs.
                if (!self::has_edit_capability($program->get_context())) {
                    return false;
                }
            }

            // Check here if user is actually allocated to this program.
            // Suspended/completed/expired allocations are all valid for the reporting purposes.
            if (!api::has_any_allocations($program->get('id'), $userid)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Require current user can view programs progress of the given userid.
     *
     * @param int $userid
     * @param program $program if specified will check if the progress for the given program can be viewed
     */
    public static function require_can_view_user_programs_progress(int $userid, ?program $program = null): void {
        if (!self::can_view_user_programs_progress($userid, $program)) {
            throw new moodle_exception('errornopermissionviewreports', 'tool_program');
        }
    }

    /**
     * Checks if current user can view reports for userid as organisation manager
     *
     * @param int $userid
     * @return bool
     */
    public static function can_view_user_programs_progress_as_organisation_manager(int $userid): bool {
        // Check if can view reports if is subordinate.
        if (class_exists('\\tool_organisation\\organisation')) {
            if ($userorg = \tool_organisation\organisation::get_user_with_jobs()) {
                return $userorg->is_manager_over_user($userid, \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS)
                    || $userorg->is_manager_over_user($userid, \tool_organisation\organisation::PERM_VIEW_REPORTS);
            }
        }
        return false;
    }

    /**
     * Checks if current user can view the programs overdue report
     *
     * @return bool
     * @throws moodle_exception
     */
    public static function can_view_programs_overdue() {
        $orgpermission = false;
        // Check if can view reports if is subordinate.
        if (class_exists('\\tool_organisation\\organisation')) {
            if ($userorg = \tool_organisation\organisation::get_user_with_jobs()) {
                $permissions = organisation::PERM_ALLOCATE_PROGRAMS + organisation::PERM_VIEW_REPORTS;
                $orgpermission = $userorg->is_manager($permissions);
            }
        }
        if (!$orgpermission && !self::has_allocateuser_capability()) {
            return false;
        }
        return true;
    }

    /**
     * Require check if current user can view the programs overdue report
     *
     * @throws moodle_exception
     */
    public static function require_can_view_programs_overdue(): void {
        if (!self::can_view_programs_overdue()) {
            throw new moodle_exception('errornopermissionviewreports', 'tool_program');
        }
    }

    /**
     * Checks if current user can allocate at least anybody as organisation manager
     *
     * @return bool
     */
    public static function can_allocate_anybody_as_organisation_manager() : bool {
        if (class_exists('\\tool_organisation\\organisation')) {
            $user = \tool_organisation\organisation::get_user_with_jobs();
            return $user && $user->is_manager(\tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS);
        }

        return false;
    }

    /**
     * Checks if current user can allocate given user as organisation manager
     *
     * @param int $userid
     * @return bool
     */
    protected static function can_allocate_as_organisation_manager(int $userid) : bool {
        if (class_exists('\\tool_organisation\\organisation')) {
            $user = \tool_organisation\organisation::get_user_with_jobs();
            return $user && $user->is_manager_over_user($userid, \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS);
        }

        return false;
    }

    /**
     * Checks if current user can reset a given program.
     *
     * @param program_user $programuser
     * @return bool
     */
    public static function can_reset_progress(program_user $programuser): bool {
        $hascapability = has_capability('tool/program:coursereset', context_system::instance());
        return $hascapability && self::can_edit_user_allocation($programuser);
    }

    /**
     * Require current user can reset a given program.
     *
     * @param program_user $programuser
     */
    public static function require_can_reset_progress(program_user $programuser): void {
        if (!self::can_reset_progress($programuser)) {
            throw new moodle_exception('errorcannotresetprogram', 'tool_program');
        }
    }
}

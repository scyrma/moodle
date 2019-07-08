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
    public static function has_edit_capability(context $context): bool {
        return has_capability('tool/program:edit', $context);
    }

    /**
     * Check allocateuser capability.
     *
     * @param context $context
     * @return bool
     */
    public static function has_allocateuser_capability(context $context): bool {
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
     * @param context $context
     * @return bool
     */
    public static function can_view_program(program $program, int $userid, context $context): bool {
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
     * @param context $context
     * @return bool
     */
    public static function can_allocate(program $program, context $context): bool {
        global $USER;
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        if (!$canallocate && !self::has_allocateuser_capability($context)) {
            return false;
        }

        if ($program->is_archived()) {
            return false;
        }

        if (!api::is_allocation_window_open($program)) {
            return false;
        }

        if (tenancy::get_tenant_id($USER->id) !== (int) $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * Check if current user can edit a given program.
     *
     * @param program $program
     * @param context $context
     * @return bool
     */
    public static function can_edit_details(program $program, context $context): bool {
        global $USER;

        if (!self::has_edit_capability($context)) {
            return false;
        }

        if ($program->is_archived()) {
            return false;
        }

        if (tenancy::get_tenant_id($USER->id) !== (int) $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * User can view certifications list.
     *
     * @param context $context
     * @return bool
     */
    public static function can_view_list(context $context): bool {
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        return (self::has_edit_capability($context) || self::has_allocateuser_capability($context) || $canallocate);
    }

    /**
     * User can create a program.
     *
     * @param context $context
     */
    public static function require_can_view_list(context $context): void {
        if (!self::can_view_list($context)) {
            throw new moodle_exception('errornopermissionviewprograms', 'tool_program');
        }
    }

    /**
     * Checks if a given user allocation can be created in a given program.
     *
     * @param program $program
     * @param int $userid
     * @param int $certificationid
     * @return bool
     */
    public static function can_be_allocated(program $program, int $userid, int $certificationid): bool {
        global $USER;

        $select = 'programid = ? AND userid = ? AND certificationid = ?';
        if (program_user::record_exists_select($select, [$program->get('id'), $userid, $certificationid])) {
            // This allocation already exists and duplicates are not allowed!
            return false;
        }

        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_as_organisation_manager($userid);
        if (!$canallocate && !self::has_allocateuser_capability(context_system::instance())) {
            return false;
        }

        if ($certificationid === 0 && !api::is_allocation_window_open($program)) {
            return false;
        }

        if ($certificationid !== 0 && !certificationapi::user_can_be_allocated($certificationid)) {
            return false;
        }

        if (tenancy::get_tenant_id($USER->id) !== (int) $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * Check if current user is able to view allocated users.
     *
     * @param program $program
     * @param context $context
     * @return bool
     */
    public static function can_view_allocated_users(program $program, context $context): bool {
        return self::can_edit_details($program, $context) || self::can_allocate($program, $context);
    }

    /**
     * Check if current user can delete the given program.
     *
     * @param program $program
     * @param context $context
     * @return bool
     */
    public static function can_delete(program $program, context $context): bool {
        global $USER;

        if (!self::has_edit_capability($context)) {
            return false;
        }

        if (!$program->is_archived()) {
            return false;
        }

        if (tenancy::get_tenant_id($USER->id) !== (int) $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * User can create a certification.
     *
     * @param context $context
     * @return bool
     */
    public static function can_create(context $context): bool {
        return self::has_edit_capability($context);
    }

    /**
     * Check if current user can unarchive the given program.
     *
     * @param program $program
     * @param context $context
     * @return bool
     */
    public static function can_restore(program $program, context $context): bool {
        global $USER;

        if (!self::has_edit_capability($context)) {
            return false;
        }

        if (!$program->is_archived()) {
            return false;
        }

        if (tenancy::get_tenant_id($USER->id) !== (int) $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * Check current user can archive given program.
     *
     * @param program $program
     * @param context $context
     * @return bool
     */
    public static function can_archive(program $program, context $context): bool {

        if (!self::has_edit_capability($context)) {
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
     * Check if current user can manage the allocation of the given program user.
     *
     * @param program_user $programuser
     * @param context $context
     * @return bool
     */
    public static function can_manage_user_allocation(program_user $programuser, context $context): bool {
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        if (!$canallocate && !self::has_allocateuser_capability($context)) {
            return false;
        }

        // If it is a certification allocation, the allocation manager is not able to change dates or edit/delete allocation.
        if ($programuser->is_certification_allocation()) {
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
    public static function require_can_view_program(program $program, context $context, int $userid): void {
        if (!self::can_view_program($program, $userid, $context)) {
            throw new moodle_exception('errornopermissionviewprograms', 'tool_program');
        }
    }

    /**
     * Require that current user is able to archive a given program.
     *
     * @param program $program
     * @param context $context
     */
    public static function require_can_archive(program $program, context $context): void {
        if (!self::can_archive($program, $context)) {
            throw new moodle_exception('errornopermissionmanageprograms', 'tool_program');
        }
    }

    /**
     * Require that current user is able to archive a given program.
     *
     * @param program $program
     * @param context $context
     */
    public static function require_can_restore(program $program, context $context): void {
        if (!self::can_restore($program, $context)) {
            throw new moodle_exception('errornopermissionmanageprograms', 'tool_program');
        }
    }

    /**
     * Check if current user can create a program.
     *
     * @param context $context
     */
    public static function require_can_create(context $context): void {
        if (!self::can_create($context)) {
            throw new moodle_exception('errornopermissionmanageprograms', 'tool_program');
        }
    }

    /**
     * Require that current user is able to allocate users to the given program.
     *
     * @param program $program
     * @param context $context
     */
    public static function require_can_allocate(program $program, context $context): void {
        if (!self::can_allocate($program, $context)) {
            throw new moodle_exception('errorcantallocateusers', 'tool_program');
        }
    }

    /**
     * Require that current user is able to manage a given program.
     *
     * @param program $program
     * @param context $context
     */
    public static function require_can_edit_details(program $program, context $context): void {
        if (!self::can_edit_details($program, $context)) {
            throw new moodle_exception('errornopermissionmanageprograms', 'tool_program');
        }
    }

    /**
     * Require that current user is able to completely delete a given program.
     *
     * @param program $program
     * @param context $context
     */
    public static function require_can_delete(program $program, context $context): void {
        if (!self::can_delete($program, $context)) {
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
     * @param int $certificationid
     */
    public static function require_can_be_allocated(program $program, int $userid, int $certificationid): void {
        if (!self::can_be_allocated($program, $userid, $certificationid)) {
            throw new moodle_exception('errorusercantbeallocated', 'tool_program');
        }
    }

    /**
     * Require that current user can manage allocations.
     *
     * @param program_user $programuser
     * @param context $context
     */
    public static function require_can_manage_user_allocation(program_user $programuser, context $context): void {
        if (!self::can_manage_user_allocation($programuser, $context)) {
            throw new moodle_exception('errornopermissionmanageusers', 'tool_program');
        }
    }

    /**
     * Can manage user list.
     *
     * @param program $program
     * @param context $context
     */
    public static function require_can_manage_users_list(program $program, context $context): void {
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        $caneditdetails = self::can_edit_details($program, $context);
        if (!$caneditdetails && !$canallocate && !self::has_allocateuser_capability($context)) {
            throw new moodle_exception('errornopermissionmanageusers', 'tool_program');
        }
    }

    /**
     * Checks if we can show Programs link into site admin.
     *
     * @return bool
     */
    public static function check_access(): bool {
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        $context = context_system::instance();
        if (!$canallocate && !self::has_edit_capability($context) && !self::has_allocateuser_capability($context)) {
            return false;
        }
        return true;
    }

    /**
     * Checks if user can deallocate users.
     *
     * @param program $program
     * @param context $context
     * @param int $userid
     * @return bool
     */
    public static function can_deallocate(program $program, context $context, int $userid): bool {
        global $USER;
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_as_organisation_manager($userid);
        if (!$canallocate && !self::has_allocateuser_capability($context)) {
            return false;
        }
        // Program is not archived.
        if ($program->is_archived()) {
            return false;
        }
        // Belongs to same tenant.
        if (tenancy::get_tenant_id($USER->id) !== (int) $program->get('tenantid')) {
            return false;
        }
        return true;
    }

    /**
     * Check if current user can archive a given program.
     *
     * @param stdClass $row
     * @return bool
     */
    public static function can_view_archive_icon(stdClass $row): bool {
        $program = new program($row->id);
        if (api::belongs_to_non_archived_certification($program)) {
            return false;
        }
        return true;
    }

    /**
     * Check if current user can edit a given program.
     *
     * @param stdClass $row
     * @return bool
     */
    public static function can_view_restore_icon(stdClass $row): bool {
        $program = new program($row->id);
        return self::can_restore($program, context_system::instance());
    }

    /**
     * Check if current user can edit a given program.
     *
     * @param stdClass $row
     * @return bool
     */
    public static function can_view_delete_icon(stdClass $row): bool {
        $program = new program($row->id);
        return self::can_delete($program, context_system::instance());
    }

    /**
     * Checks if current user can view icon to allocate users to a program.
     *
     * @param stdClass $row
     * @return bool
     */
    public static function can_view_allocate_icon(stdClass $row): bool {
        $program = new program($row->id);
        return api::is_allocation_window_open($program);
    }

    /**
     * Checks if current user can view reset completion action icon in program users report.
     *
     * @param stdClass $row
     * @return bool
     */
    public static function can_view_reset_completion_icon(stdClass $row): bool {
        // If it comes from certification cannot deallocate user.
        if (0 !== (int) $row->certificationid) {
            return false;
        }
        $program = new program($row->programid);
        return self::can_reset_progress($program, context_system::instance());
    }

    /**
     * Checks if current user can view reports for userid.
     * User can see its own reports OR manager can see them if user is a subordinate.
     *
     * @param int $userid
     * @return bool
     */
    public static function can_view_reports(int $userid): bool {
        global $USER;
        return $userid === (int) $USER->id || self::can_view_reports_as_organisation_manager($userid);
    }

    /**
     * Require current user can view reports of the given userid.
     *
     * @param int $userid
     */
    public static function require_can_view_reports(int $userid): void {
        if (!self::can_view_reports($userid)) {
            throw new moodle_exception('errornopermissionviewreports', 'tool_program');
        }
    }

    /**
     * Checks if current user can view reports for userid as organisation manager
     *
     * @param int $userid
     * @return bool
     */
    public static function can_view_reports_as_organisation_manager(int $userid): bool {
        // Check if can view reports if is subordinate.
        if (class_exists('\\tool_organisation\\organisation')) {
            $userorg = \tool_organisation\organisation::get_user_with_jobs();
            return $userorg && $userorg->is_manager_over_user($userid, \tool_organisation\organisation::PERM_VIEW_REPORTS);
        }
        return false;
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
     * @param program $program
     * @param context $context
     * @return bool
     */
    public static function can_reset_progress(program $program, context $context): bool {
        global $USER;

        if (!self::has_edit_capability($context)) {
            return false;
        }

        if ($program->is_archived()) {
            return false;
        }

        if (tenancy::get_tenant_id($USER->id) !== (int) $program->get('tenantid')) {
            return false;
        }

        return true;
    }

    /**
     * Require current user can reset a given program.
     *
     * @param program $program
     * @param context $context
     */
    public static function require_can_reset_progress(program $program, context $context): void {
        if (!self::can_reset_progress($program, $context)) {
            throw new moodle_exception('errorcannotresetprogram', 'tool_program');
        }
    }
}

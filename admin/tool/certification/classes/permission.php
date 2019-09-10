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
 * Permission class for tool_certification.
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;
use context;
use moodle_exception;
use tool_tenant\tenancy;
use tool_organisation\organisation;
use context_system;
use stdClass;

defined('MOODLE_INTERNAL') || die();
/**
 * Class permission to perform permission checks.
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission {

    /**
     * Check edit capability.
     *
     * @param context $context
     * @return bool
     * @throws \coding_exception
     */
    public static function has_edit_capability(?context $context = null): bool {
        $context = $context ?: context_system::instance();
        return has_capability('tool/certification:edit', $context);
    }

    /**
     * Check allocateuser capability.
     *
     * @param context $context
     * @return bool
     * @throws \coding_exception
     */
    public static function has_allocateuser_capability(?context $context = null): bool {
        $context = $context ?: context_system::instance();
        return has_capability('tool/certification:allocateuser', $context);
    }

    /**
     * Check if certification belongs to the same tenant.
     *
     * @param certification $certification
     * @return bool
     * @throws \coding_exception
     */
    public static function check_belongs_same_tenant(certification $certification): bool {
        // Belongs to same tenant.
        $tenantcert = $certification->get('tenantid');
        return ((int) $tenantcert === tenancy::get_tenant_id());
    }

    /**
     * User can create a certification.
     *
     * @param context $context
     * @return bool
     * @throws \coding_exception
     */
    public static function can_create(?context $context = null): bool {
        return self::has_edit_capability($context);
    }

    /**
     * User can edit certification details.
     *
     * @param certification $certification
     * @return bool
     * @throws \coding_exception
     */
    public static function can_edit_details(certification $certification): bool {
        if (!$certification->get('id')) {
            return false;
        }
        if (!self::has_edit_capability($certification->get_context())) {
            return false;
        }
        // Certification is not archived.
        if ($certification->is_archived()) {
            return false;
        }
        // Belongs to same tenant.
        if (!self::check_belongs_same_tenant($certification)) {
            return false;
        }
        return true;
    }

    /**
     * User can duplicate certification
     *
     * @param certification $certification
     * @return bool
     */
    public static function can_duplicate(certification $certification): bool {
        return self::can_view_details($certification) && self::can_create();
    }

    /**
     * User can allocate users.
     *
     * @param certification $certification
     * @param int $userid
     * @return bool
     * @throws \coding_exception
     */
    protected static function can_allocate(certification $certification, int $userid = null): bool {
        if (!$certification->get('id')) {
            return false;
        }
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        if ($userid) {
            $canallocate = self::can_allocate_as_organisation_manager($userid);
        } else {
            $canallocate = self::can_allocate_anybody_as_organisation_manager();
        }

        if (!$canallocate && !self::has_allocateuser_capability($certification->get_context())) {
            return false;
        }
        // Certification is not archived.
        if ($certification->is_archived()) {
            return false;
        }
        // Allocation window is valid.
        if (!api::is_certification_allocation_open($certification)) {
            return false;
        }
        // Belongs to same tenant.
        if (!self::check_belongs_same_tenant($certification)) {
            return false;
        }
        // TODO check user is not allocated yet?
        return true;
    }

    /**
     * User can allocate at least some users to the certification
     *
     * @param certification $certification
     * @return bool
     */
    public static function can_allocate_anybody(certification $certification) {
        return self::can_allocate($certification);
    }

    /**
     * Current user can allocate a given user to the certification
     *
     * @param certification $certification
     * @param int $userid
     * @return bool
     */
    public static function can_allocate_user(certification $certification, int $userid) {
        return self::can_allocate($certification, $userid);
    }

    /**
     * User can archive certification.
     *
     * @param certification $certification
     * @return bool
     * @throws \coding_exception
     */
    public static function can_archive(certification $certification): bool {
        if (!$certification->get('id')) {
            return false;
        }
        // Capability to manage certifications.
        if (!self::has_edit_capability($certification->get_context())) {
            return false;
        }
        // Certification is not archived.
        if ($certification->is_archived()) {
            return false;
        }
        // Belongs to same tenant.
        if (!self::check_belongs_same_tenant($certification)) {
            return false;
        }
        return true;
    }

    /**
     * User can restore a certification.
     *
     * @param certification $certification
     * @return bool
     * @throws \coding_exception
     */
    public static function can_restore(certification $certification): bool {
        if (!$certification->get('id')) {
            return false;
        }
        // Capability to manage certifications.
        if (!self::has_edit_capability($certification->get_context())) {
            return false;
        }
        // Certification is not archived.
        if (!$certification->is_archived()) {
            return false;
        }
        // Belongs to same tenant.
        if (!self::check_belongs_same_tenant($certification)) {
            return false;
        }
        return true;
    }

    /**
     * User can delete a certification.
     *
     * @param certification $certification
     * @return bool
     * @throws \coding_exception
     */
    public static function can_delete(certification $certification): bool {
        if (!$certification->get('id')) {
            return false;
        }
        // Capability to manage certifications.
        if (!self::has_edit_capability($certification->get_context())) {
            return false;
        }
        // Certification is archived.
        if (!$certification->is_archived()) {
            return false;
        }
        // Belongs to same tenant.
        if (!self::check_belongs_same_tenant($certification)) {
            return false;
        }
        return true;
    }

    /**
     * User can view certifications list.
     *
     * @param context $context
     * @return bool
     * @throws \coding_exception
     */
    public static function can_view_list(?context $context = null): bool {
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        return (self::has_edit_capability($context) || self::has_allocateuser_capability($context) || $canallocate);
    }

    /**
     * User can view archived certifications list.
     *
     * @param context $context
     * @return bool
     * @throws \coding_exception
     */
    public static function can_view_archived_list(?context $context = null): bool {
        return self::has_edit_capability($context);
    }

    /**
     * Check if current user is able to view allocated users.
     *
     * @param certification $certification
     * @return bool
     */
    public static function can_view_allocated_users(certification $certification): bool {
        $sametenanant = tenancy::get_tenant_id() == $certification->get('tenantid');
        return $certification->get('id') &&
            $sametenanant &&
            (self::has_edit_capability($certification->get_context())
                || self::has_allocateuser_capability($certification->get_context())
                || self::can_allocate_anybody_as_organisation_manager());
    }

    /**
     * Check if current user can view details of the program (can edit/can allocate/can view allocations)
     *
     * @param certification $certification
     * @return bool
     */
    public static function can_view_details(certification $certification): bool {
        return self::can_edit_details($certification)
            || self::can_view_allocated_users($certification);
    }

    /**
     * Check if current user can view certification progress report on all or some users
     *
     * @param certification $certification
     * @return bool
     */
    public static function can_view_users_progress(certification $certification): bool {
        return self::can_view_allocated_users($certification);
    }

    /**
     * Can view list of allocated users
     *
     * @param certification $certification
     * @throws moodle_exception
     */
    public static function require_can_view_allocated_users(certification $certification): void {
        // Capability to manage certifications.
        if (!self::can_view_allocated_users($certification)) {
            throw new moodle_exception('errorcantmanageusers', 'tool_certification');
        }
    }

    /**
     * User can create a certification.
     *
     * @param context $context
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_create(?context $context = null): void {
        if (!self::can_create($context)) {
            throw new moodle_exception('errornopermissionmanagecertifications', 'tool_certification');
        }
    }

    /**
     * User can create a certification.
     *
     * @param context $context
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_view_list(?context $context = null): void {
        if (!self::can_view_list($context)) {
            throw new moodle_exception('errornopermissionmanagecertifications', 'tool_certification');
        }
    }

    /**
     * Check if certification belongs to the same tenant with exception.
     *
     * @param certification $certification
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_check_belongs_same_tenant(certification $certification): void {
        // Belongs to same tenant.
        if (!self::check_belongs_same_tenant($certification)) {
            throw new moodle_exception('errorusernotinsametenant', 'tool_certification');
        }
    }

    /**
     * User can edit certification details.
     *
     * @param certification $certification
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_edit_details(certification $certification): void {
        if (!self::can_edit_details($certification)) {
            throw new moodle_exception('errornopermissionmanagecertifications', 'tool_certification');
        }
    }

    /**
     * User can view certification details.
     *
     * @param certification $certification
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_view_details(certification $certification): void {
        if (!self::can_view_details($certification)) {
            throw new moodle_exception('errornopermissionmanagecertifications', 'tool_certification');
        }
    }

    /**
     * User can archive a certification.
     *
     * @param certification $certification
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_archive(certification $certification): void {
        if (!self::can_archive($certification)) {
            throw new moodle_exception('errornopermissionmanagecertifications', 'tool_certification');
        }
    }

    /**
     * User can restore a certification.
     *
     * @param certification $certification
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_restore(certification $certification): void {
        if (!self::can_restore($certification)) {
            throw new moodle_exception('errorcantrestorecertification', 'tool_certification');
        }
    }

    /**
     * User can delete a certification.
     *
     * @param certification $certification
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_delete(certification $certification): void {
        // Capability to manage certifications.
        if (!self::can_delete($certification)) {
            throw new moodle_exception('errorcantdeletecertification', 'tool_certification');
        }
    }

    /**
     * Checks if we can allocate users.
     *
     * @param certification $certification
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_allocate_anybody(certification $certification): void {
        // Capability to manage certifications.
        if (!self::can_allocate_anybody($certification)) {
            throw new moodle_exception('errorcantmanageusers', 'tool_certification');
        }
    }

    /**
     * Checks if user can edit allocation of an individual user.
     *
     * @param certification_user $certificationuser
     * @return bool
     */
    public static function can_edit_user_allocation(certification_user $certificationuser): bool {
        $userid = $certificationuser->get('userid');
        if (!$certificationuser->get('id') || !$userid || !$certificationuser->get('certificationid')) {
            return false;
        }
        $certification = $certificationuser->get_certification();
        if (tenancy::get_tenant_id() != tenancy::get_tenant_id($userid)) {
            return false;
        }
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_as_organisation_manager($userid);
        if (!$canallocate && !self::has_allocateuser_capability($certification->get_context())) {
            return false;
        }
        // Certification is not archived.
        if ($certification->is_archived()) {
            return false;
        }
        // Belongs to same tenant.
        if (!self::check_belongs_same_tenant($certification)) {
            return false;
        }
        return true;
    }

    /**
     * Checks if user can delete allocation of an individual user.
     *
     * @param certification_user $certificationuser
     * @return bool
     */
    public static function can_delete_user_allocation(certification_user $certificationuser): bool {
        // Allocation window is valid.
        $certification = $certificationuser->get_certification();
        if (!api::is_certification_allocation_open($certification)) {
            return false;
        }
        // Can edit user allocation.
        if (!self::can_edit_user_allocation($certificationuser)) {
            return false;
        }
        // If allocation is from dynamic rules cannot delete user allocation.
        if (constants::ALLOCATION_MANUAL !== (int)$certificationuser->get('allocationtype')) {
            return false;
        }
        return true;
    }

    /**
     * Checks if user can edit user allocation.
     *
     * @param certification_user $certificationuser
     * @throws moodle_exception
     */
    public static function require_can_edit_user_allocation(?certification_user $certificationuser): void {
        // Capability to manage certifications.
        if (!$certificationuser || !self::can_edit_user_allocation($certificationuser)) {
            throw new moodle_exception('errorcantmanageusers', 'tool_certification');
        }
    }

    /**
     * Can view report on users progress for a particular certification
     *
     * @param certification $certification
     */
    public static function require_can_view_users_progress(certification $certification): void {
        if (!self::can_view_users_progress($certification)) {
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
            $userorg = organisation::get_user_with_jobs();
            return $userorg && $userorg->is_manager_over_user($userid, organisation::PERM_VIEW_REPORTS);
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
            $user = organisation::get_user_with_jobs();
            return $user && $user->is_manager(organisation::PERM_ALLOCATE_PROGRAMS);
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
        if (tenancy::get_tenant_id($userid) != tenancy::get_tenant_id()) {
            return false;
        }
        if (class_exists('\\tool_organisation\\organisation')) {
            $user = organisation::get_user_with_jobs();
            return $user && $user->is_manager_over_user($userid, organisation::PERM_ALLOCATE_PROGRAMS);
        }

        return false;
    }

    /**
     * Checks if we can show Certifications link into site admin.
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function check_access(): bool {
        return self::can_view_list();
    }

    /**
     * Checks if current user can view reports for userid.
     *
     * @param int $userid
     * @return bool
     */
    public static function can_view_user_progress(int $userid): bool {
        global $USER;
        return $userid == $USER->id ||
            self::can_allocate_as_organisation_manager($userid) ||
            self::can_view_reports_as_organisation_manager($userid) ||
            (self::has_allocateuser_capability() &&
                tenancy::get_tenant_id() == tenancy::get_tenant_id($userid));
    }

    /**
     * Require that current user can view reports for userid.
     *
     * @param int $userid
     * @throws moodle_exception
     */
    public static function require_can_view_user_progress(int $userid) {
        if (!self::can_view_user_progress($userid)) {
            throw new moodle_exception('errornopermissionviewreports', 'tool_certification');
        }
    }

    /**
     * Check if current user can certify a given user.
     *
     * @param certification_user $certificationuser
     * @param bool $certificationcompleted user has completed the certification
     * @return bool
     */
    public static function can_certify_user(certification_user $certificationuser,
                                                      bool $certificationcompleted): bool {
        return self::can_edit_user_allocation($certificationuser) &&
            !$certificationcompleted;
    }

    /**
     * Check if current user can revoke the certification from a given user.
     *
     * @param certification_user $certificationuser
     * @param bool $certificationcompleted user has completed the certification
     * @return bool
     */
    public static function can_revoke_user_certification(certification_user $certificationuser,
                                                     bool $certificationcompleted): bool {
        // Check user is certified and program is not completed. Otherwise cannot revoke.
        $certification = $certificationuser->get_certification();
        return self::can_edit_user_allocation($certificationuser) &&
            $certificationcompleted &&
            !api::is_program_completed($certification->get('program'), $certificationuser->get('userid'));
    }
}
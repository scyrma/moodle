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
    public static function has_edit_capability(context $context): bool {
        return has_capability('tool/certification:edit', $context);
    }

    /**
     * Check allocateuser capability.
     *
     * @param context $context
     * @return bool
     * @throws \coding_exception
     */
    public static function has_allocateuser_capability(context $context): bool {
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
    public static function can_create(context $context): bool {
        return self::has_edit_capability($context);
    }

    /**
     * User can edit certification details.
     *
     * @param certification $certification
     * @param context $context
     * @return bool
     * @throws \coding_exception
     */
    public static function can_edit_details(certification $certification, context $context): bool {
        if (!self::has_edit_capability($context)) {
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
     * User can allocate users.
     *
     * @param certification $certification
     * @param context $context
     * @param int $userid
     * @return bool
     * @throws \coding_exception
     */
    public static function can_allocate(certification $certification, context $context, int $userid = null): bool {
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        if ($userid !== null) {
            $canallocate = self::can_allocate_as_organisation_manager($userid);
        } else {
            $canallocate = self::can_allocate_anybody_as_organisation_manager();
        }

        if (!$canallocate && !self::has_allocateuser_capability($context)) {
            return false;
        }
        // Certification is not archived.
        if ($certification->is_archived()) {
            return false;
        }
        // Allocation window is valid.
        if (!api::user_can_be_allocated($certification->get('id'))) {
            return false;
        }
        // Belongs to same tenant.
        if (!self::check_belongs_same_tenant($certification)) {
            return false;
        }
        return true;
    }

    /**
     * User can archive certification.
     *
     * @param certification $certification
     * @param context $context
     * @return bool
     * @throws \coding_exception
     */
    public static function can_archive(certification $certification, context $context): bool {
        // Capability to manage certifications.
        if (!self::has_edit_capability($context)) {
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
     * @param context $context
     * @return bool
     * @throws \coding_exception
     */
    public static function can_restore(certification $certification, context $context): bool {
        // Capability to manage certifications.
        if (!self::has_edit_capability($context)) {
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
     * @param context $context
     * @return bool
     * @throws \coding_exception
     */
    public static function can_delete(certification $certification, context $context): bool {
        // Capability to manage certifications.
        if (!self::has_edit_capability($context)) {
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
    public static function can_view_list(context $context): bool {
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        return (self::has_edit_capability($context) || self::has_allocateuser_capability($context) || $canallocate);
    }

    /**
     * Can manage user list.
     *
     * @param certification $certification
     * @param context $context
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_manage_users_list(certification $certification, context $context): void {
        // Capability to manage certifications.
        if (!self::can_edit_details($certification, $context) && !self::can_manage_user_allocation($context)) {
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
    public static function require_can_create(context $context): void {
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
    public static function require_can_view_list(context $context): void {
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
     * @param context $context
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_edit_details(certification $certification, context $context): void {
        if (!self::can_edit_details($certification, $context)) {
            throw new moodle_exception('errornopermissionmanagecertifications', 'tool_certification');
        }
    }

    /**
     * User can archive a certification.
     *
     * @param certification $certification
     * @param context $context
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_archive(certification $certification, context $context): void {
        if (!self::can_archive($certification, $context)) {
            throw new moodle_exception('errornopermissionmanagecertifications', 'tool_certification');
        }
    }

    /**
     * User can restore a certification.
     *
     * @param certification $certification
     * @param context $context
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_restore(certification $certification, context $context): void {
        if (!self::can_restore($certification, $context)) {
            throw new moodle_exception('errorcantrestorecertification', 'tool_certification');
        }
    }

    /**
     * User can delete a certification.
     *
     * @param certification $certification
     * @param context $context
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_delete(certification $certification, context $context): void {
        // Capability to manage certifications.
        if (!self::can_delete($certification, $context)) {
            throw new moodle_exception('errorcantdeletecertification', 'tool_certification');
        }
    }

    /**
     * Checks if we can allocate users.
     *
     * @param certification $certification
     * @param context $context
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_allocate(certification $certification, context $context): void {
        // Capability to manage certifications.
        if (!self::can_allocate($certification, $context)) {
            throw new moodle_exception('errorcantmanageusers', 'tool_certification');
        }
    }

    /**
     * Checks if user can deallocate users.
     *
     * @param certification $certification
     * @param context $context
     * @param int $userid
     * @return bool
     * @throws \coding_exception
     */
    public static function can_deallocate(certification $certification, context $context, int $userid): bool {
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_as_organisation_manager($userid);
        if (!$canallocate && !self::has_allocateuser_capability($context)) {
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
     * Checks if user can deallocate users.
     *
     * @param certification $certification
     * @param context $context
     * @param int $userid
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function require_can_deallocate(certification $certification, context $context, int $userid): void {
        // Capability to manage certifications.
        if (!self::can_deallocate($certification, $context, $userid)) {
            throw new moodle_exception('errorcantmanageusers', 'tool_certification');
        }
    }

    /**
     * Check if current user can manage the allocation of users.
     *
     * @param context $context
     * @return bool
     */
    public static function can_manage_user_allocation(context $context): bool {
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        return !(!$canallocate && !self::has_allocateuser_capability($context));
    }

    /**
     * Check if current user can deallocate a user in a given certification.
     *
     * @param stdClass $row
     * @return bool
     */
    public static function can_view_deallocate_icon(stdClass $row): bool {
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_as_organisation_manager($row->userid);
        return !(!$canallocate && !self::has_allocateuser_capability(context_system::instance()));
    }

    /**
     * Checks if current user can view icon to allocate users to a certification.
     *
     * @param stdClass $row
     * @return bool
     */
    public static function can_view_allocate_icon(stdClass $row): bool {
        // Allocation window is valid.
        return api::user_can_be_allocated($row->id);
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
        $context = context_system::instance();
        $canallocate = self::can_allocate_anybody_as_organisation_manager();
        return !(!$canallocate && !self::has_edit_capability($context) && !self::has_allocateuser_capability($context));
    }

    /**
     * Checks if current user can view reports for userid.
     *
     * @param int $userid
     * @return bool
     */
    public static function can_view_reports(int $userid): bool {
        global $USER;
        return $userid === (int) $USER->id || self::can_view_reports_as_organisation_manager($userid);
    }

    /**
     * Check if current user can certify a given user.
     *
     * @param stdClass $row
     * @return bool
     */
    public static function can_view_certify_user_icon(stdClass $row): bool {
        return !api::is_user_certified($row->userid, $row->certificationid);
    }

    /**
     * Check if current user can revoke the certification from a given user.
     *
     * @param stdClass $row
     * @return bool
     */
    public static function can_view_revoke_user_icon(stdClass $row): bool {
        $certification = new certification($row->certificationid);

        // User is certified.
        $isusercertified = api::is_user_certified($row->userid, $row->certificationid);

        // Program is completed. Otherwise cannot revoke.
        $programid = $certification->get('program');
        $programcompletion = api::is_program_completed($programid, $row->userid);

        return ($isusercertified && !$programcompletion);
    }
}
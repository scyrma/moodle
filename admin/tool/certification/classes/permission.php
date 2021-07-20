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
 * Permission class for tool_certification.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;
use context;
use moodle_exception;
use tool_dynamicrule\rule;
use tool_tenant\hierarchy;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_organisation\organisation;
use context_system;

defined('MOODLE_INTERNAL') || die();
/**
 * Class permission to perform permission checks.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
     * Checks if user can edit the current certification, if the certification is from another tenant checks if user can switch
     * to that another tenant.
     *
     * @param certification $certification
     * @return bool
     */
    public static function can_edit_details_in_certification_tenant(certification $certification): bool {
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

        if (!self::is_certification_visible_in_list($certification) ||
            !\tool_tenant\permission::can_access_tenant($certification->get('tenantid'))) {
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
     * @param int|null $userid
     * @param bool $checkallocationwindow Check if allocation window is open for this certification
     * @param bool $checkcertificationisvisible If certification is 'visible' to the current user based on the certification tenant
     * @return bool
     * @throws \coding_exception
     */
    public static function can_allocate(certification $certification, int $userid = null,
                                           bool $checkallocationwindow = true, bool $checkcertificationisvisible = true): bool {
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
        if ($checkallocationwindow && !api::is_certification_allocation_open($certification)) {
            return false;
        }
        if ($checkcertificationisvisible && !self::is_certification_visible_in_list($certification)) {
            return false;
        }
        if (certification_user::get_record(['userid' => $userid, 'certificationid' => $certification->get('id')])) {
            return false;
        }
        return true;
    }

    /**
     * User can allocate at least some users to the certification
     *
     * @param certification $certification
     * @param bool $checkallocationwindow Check if allocation window is open for this certification
     * @return bool
     */
    public static function can_allocate_anybody(certification $certification, bool $checkallocationwindow = true) {
        return self::can_allocate($certification, null, $checkallocationwindow);
    }

    /**
     * Current user can allocate a given user to the certification
     *
     * @param certification $certification
     * @param int $userid
     * @param bool $checkallocationwindow Check if allocation window is open for this certification
     * @param bool $checkcertificationisvisible If certification is 'visible' to the current user based on the certification tenant
     * @return bool
     */
    public static function can_allocate_user(certification $certification, int $userid,  bool $checkallocationwindow = true,
                                             bool $checkcertificationisvisible = true) {
        return self::can_allocate($certification, $userid, $checkallocationwindow, $checkcertificationisvisible);
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
        return $certification->get('id') &&
            self::is_certification_visible_in_list($certification) &&
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
        return self::can_edit_details_in_certification_tenant($certification)
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
        // Check if user has allocateuser capability OR allocate permission from Organisation.
        $canallocate = self::can_allocate_as_organisation_manager($userid);
        if (!$canallocate && !self::has_allocateuser_capability($certification->get_context())) {
            return false;
        }
        // Certification is not archived.
        if ($certification->is_archived()) {
            return false;
        }
        // Tenant check.
        if (!self::is_certification_visible_in_list($certification)) {
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
            (!$certificationcompleted || ($certificationcompleted && $certificationuser->get('currentprogramid')));
    }

    /**
     * Check if current user can revoke the certification from a given user.
     *
     * @param certification_user $certificationuser
     * @param bool $certificationcompleted user has completed the certification
     * @param int $programid program where user is allocated
     * @return bool
     */
    public static function can_revoke_user_certification(certification_user $certificationuser,
                                                     bool $certificationcompleted, ?int $programid): bool {
        // Check user is certified and program is not completed. Otherwise cannot revoke.
        // We get current program where user is allocated because certification program can be changed any time.
        // TODO WP-1336 Avoid extra db calls.
        if (!$programid) {
            // Find CURRENT programuser allocation.
            $certificationid = $certificationuser->get('certificationid');
            $userid = $certificationuser->get('userid');
            $programuser = api::get_latest_programuser_allocation($userid, $certificationid);
            $programid = $programuser->get('programid');
        }
        return self::can_edit_user_allocation($certificationuser) && $certificationcompleted &&
            !api::is_program_completed($programid, $certificationuser->get('userid'));
    }

    /**
     * User can duplicate certification
     *
     * @param certification $certification
     * @throws moodle_exception
     */
    public static function require_can_duplicate(certification $certification): void {
        if (!self::can_duplicate($certification)) {
            throw new moodle_exception('errornopermissionmanagecertifications', 'tool_certification');
        }
    }

    /**
     * Checks if user can add dynamic rule condition
     *
     * User should be able to view all allocated users in at least one certification.
     * Since certifications (currently) are only possible in system context, we can
     * only check capabilities in system context.
     *
     * Organisation managers who can only allocate/view members of their team should not be able to
     * add DR conditions.
     *
     * @return bool
     */
    public static function can_add_dynamicrule_condition(): bool {
        // We can not call can_view_list() or can_view_allocated_users() here because we need to check capability
        // to view ALL allocated users.
        return self::has_edit_capability()
            || self::has_allocateuser_capability();
    }

    /**
     * Checks if user can edit dynamic rule condition
     *
     * User should be able to view all allocated users in the given certification.
     *
     * @param certification $certification
     * @param rule $rule
     * @return bool
     */
    public static function can_edit_dynamicrule_condition(certification $certification, rule $rule): bool {
        // We can not call can_view_allocated_users() here because we need to check capability
        // to view ALL allocated users.
        return (self::has_edit_capability($certification->get_context())
            || self::has_allocateuser_capability($certification->get_context())) &&
            hierarchy::is_own_or_parent_shared_entity(
                $certification->get('tenantid'), $certification->get('shared'), $rule->get('tenantid'));
    }

    /**
     * Checks if user can add dynamic rule outcome
     *
     * User should be able to allocate users to certifications.
     * Since certifications (currently) are only possible in system context, we can
     * only check capabilities in system context.
     *
     * Organisation managers who can only allocate members of their team should not be able to
     * add DR outcomes.
     *
     * @return bool
     */
    public static function can_add_dynamicrule_outcome(): bool {
        // We check using has_allocateuser_capability() here rather than can_allocate_anybody()
        // to prevent case when organisation manager may allocate someone who does not
        // belong to her team.
        return self::has_allocateuser_capability();
    }

    /**
     * Checks if user can edit dynamic rule outcome
     *
     * User should be able to allocate users to given certification.
     *
     * @param certification $certification
     * @param rule $rule
     * @return bool
     */
    public static function can_edit_dynamicrule_outcome(certification $certification, rule $rule): bool {
        // We check using has_allocateuser_capability() here rather than can_allocate_anybody()
        // to prevent case when organisation manager may allocate someone who does not
        // belong to her team.
        return self::has_allocateuser_capability($certification->get_context()) &&
            hierarchy::is_own_or_parent_shared_entity($certification->get('tenantid'), $certification->get('shared'),
                $rule->get('tenantid'));
    }

    /**
     * Checks if certification is 'visible' to the current user based on the certification tenant
     *
     * This function does not check if certification is archived or not
     *
     * @param certification $certification
     * @return bool
     */
    protected static function is_certification_visible_in_list(certification $certification): bool {
        return tenancy::get_tenant_id() == $certification->get('tenantid') ||
            ($certification->get('shared') && in_array($certification->get('tenantid'), hierarchy::get_parent_tenants_ids()));
    }
}

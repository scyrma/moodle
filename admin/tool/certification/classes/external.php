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
 * External functions for Certifications.
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

use external_api;
use core_tag;
use moodle_url;
use context_system;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use tool_tenant\tenancy;

/**
 * Class external
 *
 * @package tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {
    /**
     * Parameters for archive_certification.
     * @return external_function_parameters
     */
    public static function archive_certification_parameters(): external_function_parameters {
        return new external_function_parameters([
            'certificationid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
        ]);
    }

    /**
     * Archives a certification.
     * @param int $certificationid
     * @return array
     */
    public static function archive_certification(int $certificationid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::archive_certification_parameters(), [
            'certificationid' => $certificationid,
        ]);
        $certificationid = $params['certificationid'];
        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);
        $certification = new certification($certificationid);
        // Check if current user has permission to archive.
        permission::require_can_archive($certification);

        $result = api::archive_certification($certificationid);

        return [
            'result' => $result,
        ];
    }

    /**
     * Return for archive_certification.
     * @return external_single_structure
     */
    public static function archive_certification_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, '', VALUE_REQUIRED),
        ]);
    }

    /**
     * Parameters for restore_certification.
     * @return external_function_parameters
     */
    public static function restore_certification_parameters(): external_function_parameters {
        return new external_function_parameters([
            'certificationid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
        ]);
    }

    /**
     * Archives or restores a certification.
     * @param int $certificationid
     * @return array
     */
    public static function restore_certification(int $certificationid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::restore_certification_parameters(), [
            'certificationid' => $certificationid,
        ]);
        $certificationid = $params['certificationid'];
        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);
        $certification = new certification($certificationid);
        // Check if current user has permission to archive.
        permission::require_can_restore($certification);

        $result = api::restore_certification($certificationid);

        return [
            'result' => $result,
        ];
    }

    /**
     * Return for restore_certification.
     * @return external_single_structure
     */
    public static function restore_certification_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, '', VALUE_REQUIRED),
        ]);
    }

    /**
     * Parameters for delete_certification.
     * @return external_function_parameters
     */
    public static function delete_certification_parameters(): external_function_parameters {
        return new external_function_parameters([
            'certificationid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
        ]);
    }

    /**
     * Deletes a certification.
     * @param int $certificationid
     * @return array
     */
    public static function delete_certification(int $certificationid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::delete_certification_parameters(), [
            'certificationid' => $certificationid,
        ]);
        $certificationid = $params['certificationid'];

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);

        $certification = new certification($certificationid);
        // Check if current user has permission.
        permission::require_can_delete($certification);

        $result = api::delete_certification($certification);

        return [
            'result' => $result,
        ];
    }

    /**
     * Return for delete_certification.
     * @return external_single_structure
     */
    public static function delete_certification_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, '', VALUE_REQUIRED),
        ]);
    }

    /**
     * Parameters for the program selector WS.
     * @return external_function_parameters
     */
    public static function potential_program_selector_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_NOTAGS, 'Search string', VALUE_REQUIRED),
        ]);
    }

    /**
     * Program selector.
     * @param string $search
     * @return array
     */
    public static function potential_program_selector(string $search): array {
        $params = self::validate_parameters(self::potential_program_selector_parameters(),
            ['search' => $search]);
        $search = $params['search'];

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);

        return api::get_potential_programs($search);
    }

    /**
     * Return for program selector.
     * @return external_multiple_structure
     */
    public static function potential_program_selector_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'ID of the program'),
            'fullname' => new external_value(PARAM_TEXT, 'The fullname of the program'),
        ]));
    }

    /**
     * Parameters for the certification selector WS.
     * @return external_function_parameters
     */
    public static function potential_certification_selector_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_NOTAGS, 'Search string', VALUE_REQUIRED),
        ]);
    }

    /**
     * Certification selector.
     * @param string $search
     * @return array
     */
    public static function potential_certification_selector(string $search): array {
        $params = self::validate_parameters(self::potential_certification_selector_parameters(),
            ['search' => $search]);
        $search = $params['search'];

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);

        return api::get_potential_certifications($search);
    }

    /**
     * Return for certification selector.
     * @return external_multiple_structure
     */
    public static function potential_certification_selector_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'ID of the certification'),
            'fullname' => new external_value(PARAM_TEXT, 'The fullname of the certification'),
        ]));
    }

    /**
     * Parameters for deallocate_user.
     * @return external_function_parameters
     */
    public static function deallocate_user_parameters(): external_function_parameters {
        return new external_function_parameters([
            'certificationid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
            'userid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
        ]);
    }

    /**
     * Deallocates user from a certification.
     * @param int $certificationid
     * @param int $userid
     */
    public static function deallocate_user(int $certificationid, int $userid) {
        // Parameter validation.
        $params = self::validate_parameters(self::deallocate_user_parameters(), [
            'certificationid' => $certificationid,
            'userid' => $userid,
        ]);
        $certificationid = $params['certificationid'];
        $userid = $params['userid'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        // Check permissions to allocate users.
        /** @var certification_user $certificationuser */
        $certificationuser = certification_user::get_record($params);
        permission::require_can_edit_user_allocation($certificationuser ?: null);

        api::deallocate_user($certificationid, $userid);
    }

    /**
     * Return for deallocate_user.
     */
    public static function deallocate_user_returns() {
    }

    /**
     * Parameters for certify a user.
     *
     * @return external_function_parameters
     */
    public static function certify_user_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User id'),
            'certificationid' => new external_value(PARAM_INT, 'ID of the certification'),
            'suspendprogallocation' => new external_value(PARAM_BOOL, 'Flag to suspend program allocation'),
            'expirydate' => new external_value(PARAM_INT, 'Expiry date timestamp', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Certify a user.
     *
     * @param int $userid
     * @param int $certificationid
     * @param bool $suspendprogallocation
     * @param int $expirydate
     * @return array
     */
    public static function certify_user(int $userid, int $certificationid, bool $suspendprogallocation,
                                        int $expirydate = null): array {
        // Parameter validation.
        $params = self::validate_parameters(self::certify_user_parameters(), [
            'userid' => $userid,
            'certificationid' => $certificationid,
            'suspendprogallocation' => $suspendprogallocation,
            'expirydate' => $expirydate,
        ]);
        $userid = $params['userid'];
        $certificationid = $params['certificationid'];
        $suspendprogallocation = $params['suspendprogallocation'];
        $expirydate = $params['expirydate'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        api::set_user_as_certified($userid, $certificationid, $suspendprogallocation, $expirydate);
        return [
            'result' => true,
        ];
    }

    /**
     * Return for certify a user.
     *
     * @return external_single_structure
     */
    public static function certify_user_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, '', VALUE_REQUIRED),
        ]);
    }

    /**
     * Parameters for revoke certification.
     *
     * @return external_function_parameters
     */
    public static function revoke_certification_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User id'),
            'certificationid' => new external_value(PARAM_INT, 'ID of the certification'),
        ]);
    }

    /**
     * Revokes a certification from a user.
     *
     * @param int $userid
     * @param int $certificationid
     * @return array
     */
    public static function revoke_certification(int $userid, int $certificationid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::revoke_certification_parameters(), [
            'userid' => $userid,
            'certificationid' => $certificationid,
        ]);
        $userid = $params['userid'];
        $certificationid = $params['certificationid'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        api::revoke_certification_from_user($userid, $certificationid);
        return [
            'result' => true,
        ];
    }

    /**
     * Return for revoke certification from a user.
     *
     * @return external_single_structure
     */
    public static function revoke_certification_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, '', VALUE_REQUIRED),
        ]);
    }
}

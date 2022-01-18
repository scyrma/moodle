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
 * External functions for Certifications.
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

use external_api;
use core_tag;
use context_system;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * Class external
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
        // Check if current user has permission to archive the certification.
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
        // Check if current user has permission to restore the certification.
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
        // Check if current user has permission to delete the certification.
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

        // Check if current user has permission to view the list of certifications.
        permission::require_can_view_list();

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
            'expirydate' => new external_value(PARAM_INT, 'Expiry date timestamp', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Certify a user.
     *
     * @param int $userid
     * @param int $certificationid
     * @param int|null $expirydate
     * @return array
     */
    public static function certify_user(int $userid, int $certificationid, int $expirydate = null): array {
        // Parameter validation.
        $params = self::validate_parameters(self::certify_user_parameters(), [
            'userid' => $userid,
            'certificationid' => $certificationid,
            'expirydate' => $expirydate,
        ]);
        $userid = $params['userid'];
        $certificationid = $params['certificationid'];
        $expirydate = $params['expirydate'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        // Check if current user has permission to certify the given user.
        $certificationuser = certification_user::get_record([
            'userid' => $userid,
            'certificationid' => $certificationid,
        ]);
        $iscertified = api::is_user_certified($userid, $certificationid);
        permission::require_can_certify_user_deep($certificationuser, $iscertified);

        api::set_user_as_certified($userid, $certificationid, $expirydate);
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

        // Check if current user has permission to revoke the certification from the given user.
        $certificationuser = certification_user::get_record([
            'userid' => $userid,
            'certificationid' => $certificationid,
        ]);
        $iscertified = api::is_user_certified($userid, $certificationid);
        permission::require_can_revoke_user_certification($certificationuser, $iscertified, null);

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

    /**
     * Parameters for get_certification_user_log
     * @return external_function_parameters
     */
    public static function get_certification_user_log_parameters(): external_function_parameters {
        return new external_function_parameters([
            'certificationid' => new external_value(PARAM_INT, 'ID of the certification', VALUE_REQUIRED),
            'userid' => new external_value(PARAM_INT, 'ID of the user', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get certification user log
     * @param int $certificationid
     * @param int $userid
     * @return array
     */
    public static function get_certification_user_log(int $certificationid, int $userid): array {
        global $OUTPUT;

        $params = self::validate_parameters(self::get_certification_user_log_parameters(),
            ['certificationid' => $certificationid, 'userid' => $userid]);

        $context = context_system::instance();
        self::validate_context($context);

        // Check if current user has permission to view the progress from the given user.
        permission::require_can_view_user_progress($userid);

        $userlog = new user_log($params['userid'], $params['certificationid']);
        $log = $userlog->get_user_log();
        $downloadform = $OUTPUT->download_dataformat_selector(
            get_string('downloadas', 'table'),
            'certification_user_log_export.php',
            'dataformat',
            array('certificationid' => $params['certificationid'], 'userid' => $params['userid'])
        );

        return [
            'log' => $log['log'],
            'downloadform' => $downloadform,
            'lastallocationdate' => $log['lastallocationdate'],
        ];
    }

    /**
     * Return for get_certification_user_log
     * @return external_single_structure
     */
    public static function get_certification_user_log_returns(): external_single_structure {
        return new external_single_structure([
            'log' => new external_multiple_structure(new external_single_structure([
                'user' => new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'ID of the user'),
                    'picture' => new external_value( PARAM_RAW, 'Picture of the user (html)'),
                    'fullname' => new external_value(PARAM_TEXT, 'Fullname of the user'),
                    'profileurl' => new external_value(PARAM_RAW, 'URL of the user profile')
                ]),
                'event' => new external_value(PARAM_TEXT, 'Description of the event'),
                'date' => new external_value(PARAM_INT, 'Date timestamp of the event'),
            ])),
            'lastallocationdate' => new external_value(PARAM_INT, 'Date timestamp of the last allocation'),
            'downloadform' => new external_value( PARAM_RAW, 'Download table form (html)')
        ]);
    }

    /**
     * Parameters for deallocate_user
     *
     * @return external_function_parameters
     */
    public static function bulk_deallocate_user_parameters(): external_function_parameters {
        return new external_function_parameters([
            'certificationuserids' => new external_multiple_structure(new external_value(PARAM_INT)),
        ]);
    }

    /**
     * Deallocates a user from a certification.
     *
     * @param array $certificationuserids
     * @return array
     */
    public static function bulk_deallocate_user(array $certificationuserids): array {
        // Parameter validation.
        $params = self::validate_parameters(self::bulk_deallocate_user_parameters(), [
            'certificationuserids' => $certificationuserids,
        ]);
        $certificationuserids = $params['certificationuserids'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        $successcount = 0;
        $skippedcount = 0;

        foreach ($certificationuserids as $certificationuserid) {
            $certificationuser = new certification_user($certificationuserid);
            if (!$certificationuser || !permission::can_edit_user_allocation($certificationuser)) {
                $skippedcount++;
            } else {
                api::deallocate_user($certificationuser->get('certificationid'), $certificationuser->get('userid'));
                $successcount++;
            }
        }

        return [
            'successcount' => $successcount,
            'skippedcount' => $skippedcount,
        ];
    }

    /**
     * Return for deallocate_user.
     *
     * @return external_function_parameters
     */
    public static function bulk_deallocate_user_returns(): external_single_structure {
        return new external_single_structure([
            'successcount' => new external_value(PARAM_INT),
            'skippedcount' => new external_value(PARAM_INT),
        ]);
    }
}

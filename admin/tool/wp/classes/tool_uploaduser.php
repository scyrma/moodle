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
 * Class for tool_uploaduser
 *
 * @package     tool_wp
 * @copyright   2019 Daniel Neis Araujo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

use tool_tenant\manager;
use tool_tenant\tenancy;
use uu_progress_tracker;
use stdClass;

/**
 * Class tool_uploaduser
 *
 * This implements methods to be use on uploading users CSV files.
 *
 * @package     tool_wp
 * @copyright   2019 Daniel Neis Araujo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploaduser {

    /**
     * Callback for tool_uploaduser that validates a line of user upload.
     *
     * @param string $columname Name of the column being validated
     * @return bool
     */
    public static function validate_column($columname) {
        if ($columname == 'tenant') {
            return has_capability('tool/tenant:allocate', \context_system::instance());
        } else {
            $jobfields = 'jobposition|jobdepartment|jobstartdate|jobenddate';
            $programfields = 'program|programstartdate|programenddate|programduedate';
            $certificationfields = 'certification|certificationstartdate|'.
               'certificationenddate|certificationduedate|certificationexpirydate';
            return preg_match("/^({$jobfields}|{$programfields}|{$certificationfields})\d+$/", $columname);
        }
    }

    /**
     * Callback for tool_uploaduser that process a newly created user.
     *
     * @uses \tool_tenant\tool_uploaduser::process_new_user()
     * @uses \tool_organisation\tool_uploaduser::process_new_user()
     * @uses \tool_program\tool_uploaduser::process_new_user()
     * @uses \tool_certification\tool_uploaduser::process_new_user()
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     */
    public static function process_new_user($user, $filecolumns, $upt) {
        component_class_callback('tool_tenant\tool_uploaduser', 'process_new_user', [$user, $filecolumns, $upt]);
        component_class_callback('tool_organisation\tool_uploaduser', 'process_new_user', [$user, $filecolumns, $upt]);
        component_class_callback('tool_program\tool_uploaduser', 'process_new_user', [$user, $filecolumns, $upt]);
        component_class_callback('tool_certification\tool_uploaduser', 'process_new_user', [$user, $filecolumns, $upt]);
    }

    /**
     * Callback for tool_uploaduser that process updated user.
     *
     * @uses \tool_tenant\tool_uploaduser::process_updated_user()
     * @uses \tool_organisation\tool_uploaduser::process_updated_user()
     * @uses \tool_program\tool_uploaduser::process_updated_user()
     * @uses \tool_certification\tool_uploaduser::process_updated_user()
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     */
    public static function process_updated_user($user, $filecolumns, $upt) {
        component_class_callback('tool_tenant\tool_uploaduser', 'process_updated_user', [$user, $filecolumns, $upt]);
        component_class_callback('tool_organisation\tool_uploaduser', 'process_updated_user', [$user, $filecolumns, $upt]);
        component_class_callback('tool_program\tool_uploaduser', 'process_updated_user', [$user, $filecolumns, $upt]);
        component_class_callback('tool_certification\tool_uploaduser', 'process_updated_user', [$user, $filecolumns, $upt]);
    }

    /**
     * Callback for tool_uploaduser that validates if a user can be created.
     *
     * @uses \tool_tenant\tool_uploaduser::can_create_user()
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     * @return bool
     */
    public static function can_create_user($user, $filecolumns, $upt) {
        return component_class_callback('tool_tenant\tool_uploaduser', 'can_create_user', [$user, $filecolumns, $upt]);
    }

    /**
     * Callback for tool_uploaduser that validates if a user can be updated (only {user} table and custom profile fields)
     *
     * @uses \tool_tenant\tool_uploaduser::can_update_user()
     *
     * @param stdClass $user
     * @param array $filecolumns
     * @param uu_progress_tracker $upt
     */
    public static function can_update_user($user, $filecolumns, $upt) {
        return component_class_callback('tool_tenant\tool_uploaduser', 'can_update_user', [$user, $filecolumns, $upt]);
    }
}

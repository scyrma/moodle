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
 * Observer for tool_program.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

use core\event\course_completed;
use tool_program\api;
use tool_program\constants;
use tool_program\event\program_completed;
use tool_program\event\user_allocation_deleted;
use core\event\course_deleted;
use core\event\user_deleted;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;

/**
 * Class tool_program_observer
 *
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_observer {
    /**
     * Course completed observer
     *
     * @param course_completed $event
     */
    public static function on_course_completed(course_completed $event): void {
        // Recalculate program progress by course and user and create missing program enrolments.
        api::recalculate_program_progress_by_courseid_and_userid($event->courseid, $event->relateduserid);
    }

    /**
     * Program completed observer
     *
     * @param program_completed $event
     */
    public static function on_program_completed(program_completed $event): void {
        // Send notification to user.
        api::send_program_completed_notification($event->relateduserid, $event->other['programid']);

        // Program is completed. Delete Due date calendar event for this user and program.
        $data = (object) [
            'userid' => $event->relateduserid,
            'programid' => $event->other['programid'],
        ];
        api::delete_calendar_events($data, constants::CALENDAR_EVENT_DUE_DATE);
    }

    /**
     * User deallocated observer
     *
     * @param user_allocation_deleted $event
     */
    public static function user_allocation_deleted(user_allocation_deleted $event): void {
        // Send notification if is a direct program allocation, otherwise tool_certification will send it.
        if ((int)$event->other['certificationid'] === 0) {
            api::send_program_user_deallocated_notification($event->relateduserid, $event->other['programid']);
        }
    }

    /**
     * Course deleted observer
     *
     * @param course_deleted $event
     */
    public static function course_deleted(course_deleted $event): void {
        api::remove_deleted_course_from_programs($event->courseid);
    }

    /**
     * User deleted observer
     *
     * @param user_deleted $event
     */
    public static function user_deleted(user_deleted $event): void {
        api::remove_deleted_user_from_programs($event->objectid);
    }

    /**
     * Tenant deleted event
     *
     * @param \tool_tenant\event\tenant_deleted $event
     * @return void
     */
    public static function tenant_deleted(\tool_tenant\event\tenant_deleted $event) {
        // Delete all programs that belong to this tenant.
        $programs = \tool_program\persistent\program::get_records(['tenantid' => $event->objectid]);
        foreach ($programs as $program) {
            api::archive_program($program);
            api::delete_program($program);
        }
    }

    /**
     * Tenant archived/restored event
     *
     * @param \tool_tenant\event\tenant_updated $event
     * @return void
     */
    public static function tenant_updated(\tool_tenant\event\tenant_updated $event) {
        if (!empty($event->other['isarchived'])) {
            // Suspend all program course enrolments that belong to this tenant.
            $programs = \tool_program\persistent\program::get_records(['tenantid' => $event->objectid, 'archived' => 0]);
            foreach ($programs as $program) {
                api::suspend_all_allocated_users_enrolments($program);
            }
        }
        if (!empty($event->other['isrestored'])) {
            // Restore all program course enrolments that belong to this tenant.
            $programs = \tool_program\persistent\program::get_records(['tenantid' => $event->objectid, 'archived' => 0]);
            foreach ($programs as $program) {
                api::restore_all_allocated_users_enrolments($program);
            }
        }
    }

    /**
     * User moved between tenants event
     *
     * @param \tool_tenant\event\tenant_user_updated $event
     * @return void
     */
    public static function tenant_user_updated(\tool_tenant\event\tenant_user_updated $event) {

        // Find all program direct allocations.
        /** @var \tool_program\persistent\program_user[] $programusers */
        $programusers = \tool_program\persistent\program_user::get_records_select(
            'userid = :userid AND certificationid = 0', ['userid' => $event->relateduserid]);

        foreach ($programusers as $programuser) {
            $program = $programuser->get_program();
            // For each allocation decide if the user should still remain in this program (when this is a shared
            // program that is available to both old and new user tenant).
            $keepprogramallocation = hierarchy::is_own_or_parent_shared_entity(
                $program->get('tenantid'), $program->get('shared'), $event->other['tenantid']);
            if (!$keepprogramallocation) {
                // Program is no longer available - remove the allocation.
                api::deallocate_user($program->get('id'), $event->relateduserid);
            } else {
                // Program is shared - remove from old program groups and add to the new ones.
                api::remove_user_from_program_course_groups($programuser);
                api::restore_user_in_program_course_groups($programuser);
            }
        }

        // Remove user from component-less tenant groups.
        api::remove_user_from_previous_tenant_groups($event->relateduserid);
    }
}

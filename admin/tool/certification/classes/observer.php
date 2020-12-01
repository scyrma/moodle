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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class observer for tool_certification.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

use tool_certification\constants;
use tool_certification\event\certification_completion_created;
use tool_certification\event\user_allocation_created;
use tool_certification\event\user_allocation_deleted;
use tool_program\event\program_completed;
use tool_certification\api;
use core\event\user_deleted;
use tool_program\persistent\program_user;

/**
 * Class tool_certification_observer
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_observer {
    /**
     * Program completed observer
     *
     * @param program_completed $event
     */
    public static function on_program_completed(program_completed $event): void {
        api::set_certification_completed_by_user_and_program($event->relateduserid, $event->other['programid']);
    }

    /**
     * Certification completed observer
     *
     * @param certification_completion_created $event
     */
    public static function on_certification_completed(certification_completion_created $event): void {
        // Send notification to user.
        api::send_certification_completed_notification($event->relateduserid, $event->other['certificationid']);
    }

    /**
     * User allocated observer
     *
     * @param user_allocation_created $event
     */
    public static function user_allocation_created(user_allocation_created $event): void {
        api::send_certification_user_allocated_notification($event->relateduserid, $event->other['certificationid']);
    }

    /**
     * User deallocated observer
     *
     * @param user_allocation_deleted $event
     */
    public static function user_allocation_deleted(user_allocation_deleted $event): void {
        api::send_certification_user_deallocated_notification($event->relateduserid, $event->other['certificationid']);
    }

    /**
     * User deleted observer
     *
     * @param user_deleted $event
     */
    public static function user_deleted(user_deleted $event): void {
        api::remove_deleted_user_from_certifications($event->objectid);
    }

    /**
     * Tenant deleted event
     *
     * @param \tool_tenant\event\tenant_deleted $event
     * @return void
     */
    public static function tenant_deleted(\tool_tenant\event\tenant_deleted $event) {
        // Delete all certifications that belong to this tenant.
        $certifications = \tool_certification\certification::get_records(['tenantid' => $event->objectid]);
        foreach ($certifications as $certification) {
            api::archive_certification($certification->get('id'));
            $certification = new \tool_certification\certification($certification->get('id'));
            api::delete_certification($certification);
        }
    }

    /**
     * Tenant archived/restored event
     *
     * @param \tool_tenant\event\tenant_updated $event
     * @return void
     */
    public static function tenant_updated(\tool_tenant\event\tenant_updated $event) {
        if (!empty($event->other['isarchived']) AND $event->other['isarchived'] == true) {
            // Suspend all program course enrolments for this certification that belong to this tenant.
            $certifications = \tool_certification\certification::get_records(['tenantid' => $event->objectid, 'archived' => 0]);
            foreach ($certifications as $certification) {
                $programuserallocations = program_user::get_records(['certificationid' => $certification->get('id')]);
                foreach ($programuserallocations as $programuser) {
                    // Suspend program course enrolments for this user and remove from course groups.
                    \tool_program\api::suspend_allocated_user_enrolments($programuser);
                }
            }
        }
        if (!empty($event->other['isrestored']) AND $event->other['isrestored'] == true) {
            // Get all active user allocations and activate the course enrolments if there is at least one active allocation.
            $certifications = \tool_certification\certification::get_records(['tenantid' => $event->objectid, 'archived' => 0]);
            foreach ($certifications as $certification) {
                $params = ['certificationid' => $certification->get('id'), 'status' => constants::STATUS_OVERRIDE_DEFAULT];
                $programuserallocations = program_user::get_records($params);
                foreach ($programuserallocations as $programuser) {
                    \tool_program\api::reactivate_allocated_user_program_enrolments($programuser);
                }
            }
        }
    }

    /**
     * Tenant user updated event
     *
     * @param \tool_tenant\event\tenant_user_updated $event
     * @return void
     */
    public static function tenant_user_updated(\tool_tenant\event\tenant_user_updated $event) {
        global $DB;

        // Deallocate user from previous tenant certification allocations.
        $sql = "
            SELECT tcu.*
            FROM {tool_certification_users} tcu
            JOIN {tool_certification} tc
            ON tcu.certificationid = tc.id
            WHERE tc.tenantid = :tenantid AND tcu.userid = :userid
        ";
        $params = [
            'userid' => $event->relateduserid,
            'tenantid' => $event->other['oldtenantid'],
        ];
        $allocations = $DB->get_records_sql($sql, $params);

        foreach ($allocations as $allocation) {
            api::deallocate_user($allocation->certificationid, $allocation->userid);
        }
    }
}

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
 * Observer for tool_program.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

use core\event\course_completed;
use tool_program\api;
use tool_program\event\program_completed;
use tool_program\event\user_allocation_created;
use tool_program\event\user_allocation_deleted;
use core\event\course_deleted;
use core\event\user_deleted;

/**
 * Class tool_program_observer
 *
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_observer {
    /**
     * Course completed observer
     *
     * @param course_completed $event
     */
    public static function on_course_completed(course_completed $event): void {
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
    }

    /**
     * User allocated observer
     *
     * @param user_allocation_created $event
     */
    public static function user_allocation_created(user_allocation_created $event): void {
        api::send_program_user_allocated_notification($event->relateduserid, $event->other['programid']);
    }

    /**
     * User deallocated observer
     *
     * @param user_allocation_deleted $event
     */
    public static function user_allocation_deleted(user_allocation_deleted $event): void {
        api::send_program_user_deallocated_notification($event->relateduserid, $event->other['programid']);
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
}

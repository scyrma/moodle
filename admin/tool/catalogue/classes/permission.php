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

declare(strict_types=1);

namespace tool_catalogue;

use context_course;
use context_coursecat;
use core_user;
use moodle_exception;
use stdClass;
use tool_program\api;
use tool_program\persistent\program;
use tool_program\persistent\program_user;

/**
 * Permission class for tool_catalogue
 *
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission {

    /**
     * Check if user is allowed to view the program cover and program content pages
     *
     * @param int $userid
     * @param program $program
     * @return bool
     */
    public static function can_view_program(int $userid, program $program): bool {
        global $USER;

        $canview = \tool_program\permission::can_view_program($program, $userid);
        if ((int) $USER->id !== $userid) {
            return $canview && \tool_program\permission::can_view_list();
        }

        return $canview;
    }

    /**
     * Require to check if user is allowed to view the program cover and program content pages
     *
     * @param int $userid
     * @param program $program
     */
    public static function require_can_view_program(int $userid, program $program): void {
        if (!self::can_view_program($userid, $program)) {
            throw new moodle_exception('errornopermissionviewprogram', 'tool_catalogue');
        }
    }

    /**
     * Check if user is allowed to view the course cover page
     *
     * This method checks if the user is enroled to the course OR if the user can browse the list of courses in the
     * course category OR if is allocated to a program containing this course.
     * Also, in case is not the current user, that the current user can view course participants or details from the other user.
     *
     * @param int $userid
     * @param stdClass $course
     * @return bool
     */
    public static function can_view_course_cover(int $userid, stdClass $course): bool {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/user/lib.php');

        // If viewing details of another user, then we must be able to view participants as well as profile of that user.
        $user = core_user::get_user($userid, '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        if ($userid !== (int) $USER->id && (!course_can_view_participants($context) || !user_can_view_profile($user, $course))) {
            return false;
        }

        if (is_enrolled(context_course::instance($course->id), $userid, '', true)) {
            return true;
        }

        if (has_capability('moodle/category:viewcourselist', context_coursecat::instance($course->category), $userid)) {
            return true;
        }

        $programs = api::get_linked_programs_to_a_user_course((int) $course->id);
        if (empty($programs)) {
            return false;
        }

        $programids = array_map(static function(program $program) {
            return $program->get('id');
        }, $programs);
        [$sql, $params] = $DB->get_in_or_equal($programids, SQL_PARAMS_NAMED);
        $allocations = program_user::get_records_select("userid = :userid AND programid $sql", $params + ['userid' => $userid]);

        return !empty($allocations);
    }

    /**
     * Require to check if user is allowed to view the course cover page
     *
     * @param int $userid
     * @param stdClass $course
     * @return void
     */
    public static function require_can_view_course_cover(int $userid, stdClass $course): void {
        if (!self::can_view_course_cover($userid, $course)) {
            throw new moodle_exception('errornopermissionviewcoursecover', 'tool_catalogue');
        }
    }

    /**
     * Require to get user catalogue
     *
     * @param int $userid
     * @return void
     */
    public static function require_can_get_user_catalogue(int $userid): void {
        global $USER;

        $user = core_user::get_user($userid, '*', MUST_EXIST);
        core_user::require_active_user($user, true);
        if ($userid !== (int) $USER->id && !\tool_program\permission::can_view_list()) {
            throw new moodle_exception('errornopermissionviewprograms', 'tool_program');
        }
    }
}

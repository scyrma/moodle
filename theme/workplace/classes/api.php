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
 * File for API for theme workplace.
 *
 * @package   theme_workplace
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace theme_workplace;

defined('MOODLE_INTERNAL') || die();

/**
 * Class API for theme workplace.
 *
 * @package   theme_workplace
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class api {

    /**
     * Returns the list of enrolled courses for the current user.
     *
     * Course list is ordered by enddate, which is either course end date or enrolment end date,
     * whichever is lower. If more than one enrolment methods used for the same user, the one with
     * the latest date is used.
     *
     * @return array
     * @throws \dml_exception
     * @deprecated
     */
    public static function get_enrolled_courses_for_current_user_by_lowest_enddate(): array {
        global $DB, $USER;
        debugging('This function is deprecated.', DEBUG_DEVELOPER);

        $params = [];

        // Finds all active enrolments per course and the maximum enrolment end date.
        $enrolsql = "SELECT e.courseid, MAX(CASE WHEN ue.timeend = 0 THEN 9999999999 ELSE ue.timeend END) AS timeend
                  FROM {enrol} e
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :userid
                 WHERE ue.status = :active
                   AND e.status = :enabled
                   AND ue.timestart < :now1
                   AND (ue.timeend = 0 OR ue.timeend > :now2)
              GROUP BY e.courseid";

        $params['userid'] = $USER->id;
        $params['active'] = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;
        $params['now1'] = round(time(), -2);
        $params['now2'] = $params['now1'];

        // Finds all courses where user is enrolled with the enddate = closest of course enddate and enrolments timeend.
        // Show courses with enddate first.
        $contextsql = \context_helper::get_preload_record_columns_sql('ctx');
        $sql = "SELECT course.id, course.fullname, course.shortname, course.category, course.summary,
                course.summaryformat, course.idnumber, course.startdate, course.visible, course.sortorder,
                (CASE WHEN course.enddate > 0 AND course.enddate < enrol.timeend THEN course.enddate
                      WHEN enrol.timeend = 9999999999 THEN 0
                      ELSE enrol.timeend END) AS enddate,
                $contextsql
                FROM ( $enrolsql ) enrol
                JOIN {course} course ON course.id = enrol.courseid
                JOIN {context} ctx ON ctx.contextlevel = :coursecontextlevel AND ctx.instanceid = course.id
            ORDER BY
          (CASE WHEN course.enddate > 0 AND course.enddate < enrol.timeend
                THEN course.enddate
                ELSE enrol.timeend END), course.sortorder, course.id";

        $params['coursecontextlevel'] = CONTEXT_COURSE;

        $courses = $DB->get_records_sql($sql, $params, 0, COURSE_DB_QUERY_LIMIT);

        // Filter out courses that user can't view.
        foreach ($courses as $course) {
            if (!$course->visible) {
                \context_helper::preload_from_record($course);
                $context = \context_course::instance($course->id);
                if (!has_capability('moodle/course:viewhiddencourses', $context)) {
                    unset($courses[$course->id]);
                    continue;
                }
            }
        }

        return $courses;
    }
}

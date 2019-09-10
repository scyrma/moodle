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
 * File for API for theme workplace.
 *
 * @package   theme_workplace
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_workplace;

defined('MOODLE_INTERNAL') || die();

/**
 * Class API for theme workplace.
 *
 * @package   theme_workplace
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Returns the list of enrolled courses for the current user.
     * If user is enrolled in same course several times returns only one with minimum of
     * end date of user course enrolment OR course end date.
     *
     * @return array
     * @throws \dml_exception
     */
    public static function get_enrolled_courses_for_current_user_by_lowest_enddate(): array {
        global $DB, $USER;

        $maxenrol = 2524608000;

        // Finds all active enrolments per course and the maximum enrolment end date.
        $enrolsql = "SELECT e.courseid, MAX(CASE WHEN ue.timeend = 0 THEN $maxenrol ELSE ue.timeend END) AS timeend
                  FROM {enrol} e
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :userid
                 WHERE ue.status = :active
                   AND e.status = :enabled
                   AND ue.timestart < :now1
                   AND (ue.timeend = 0 OR ue.timeend > :now2)
              GROUP BY e.courseid";

        // Finds all courses where user is enrolled with the enddate = closest of course enddate and enrolments timeend.
        // Show courses with enddate first.
        $contextsql = \context_helper::get_preload_record_columns_sql('ctx');
        $sql = "SELECT courses.id, courses.fullname, courses.shortname, courses.category, courses.summary,
                courses.summaryformat, courses.idnumber, courses.startdate,
                (CASE WHEN courses.enddate > 0 AND courses.enddate < enrol.timeend THEN courses.enddate
                      WHEN enrol.timeend >= $maxenrol THEN 0
                      ELSE enrol.timeend END) AS enddate,
                $contextsql
                FROM ( $enrolsql ) enrol
                JOIN {course} courses ON courses.id = enrol.courseid
                JOIN {context} ctx ON ctx.contextlevel = :coursecontextlevel AND ctx.instanceid = courses.id
                ORDER BY
                  (CASE WHEN courses.enddate > 0 AND courses.enddate < enrol.timeend THEN courses.enddate
                      ELSE enrol.timeend END),
                  courses.id";

        $params['userid'] = $USER->id;
        $params['active'] = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;
        $params['now1'] = round(time(), -2);
        $params['now2'] = $params['now1'];
        $params['coursecontextlevel'] = CONTEXT_COURSE;

        $courses = $DB->get_records_sql($sql, $params, 0, COURSE_DB_QUERY_LIMIT);

        return $courses;
    }
}
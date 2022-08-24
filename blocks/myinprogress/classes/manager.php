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

namespace block_myinprogress;

/**
 * Manager class for block_myinprogress
 *
 * @package    block_myinprogress
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {

    /** @var int */
    const SHOWCOURSES_WITHCOMPLETION = 0;
    /** @var int */
    const SHOWCOURSES_ALL = 1;

    /**
     * Returns inprogress courses for a user (courses with at least one access that have not been completed by the user).
     *
     * @param int|null $userid
     * @param bool $onlywithcompletion to return only curses with completion enabled
     * @return array
     */
    public static function get_inprogress_courses(int $userid = null, bool $onlywithcompletion = true): array {
        global $DB, $USER, $CFG;

        // Check global completion first.
        if ($onlywithcompletion && empty($CFG->enablecompletion)) {
            return [];
        }

        $userid = $userid ?? $USER->id;

        [$sql, $params] = self::get_inprogress_courses_sql((int) $userid, $onlywithcompletion);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Return user inprogress courses sql ordered by last access.
     * In progress course is defined as a visible course where user is enrolled, with at least one access,
     * and not completed.
     *
     * @param int $userid
     * @param bool $onlywithcompletion to return couses only with completion enabled
     * @return array
     */
    private static function get_inprogress_courses_sql(int $userid, bool $onlywithcompletion): array {
        [$enrolmentssql, $enrolmentsparams] = self::get_user_enrolments_sql($userid);

        $completionenabledsql = $onlywithcompletion ? "AND c.enablecompletion = 1" : "";

        $sql = "SELECT c.id, c.fullname, c.summary, c.startdate, c.visible, c.format, ul.timeaccess
                  FROM {course} c
                  JOIN {user_lastaccess} ul
                       ON ul.courseid = c.id
             LEFT JOIN {course_completions} cc
                       ON cc.userid = ul.userid
                       AND cc.course = c.id
                  JOIN ($enrolmentssql) en
                       ON (en.courseid = c.id)
                 WHERE ul.userid = :uluserid
                   AND c.visible = :visible
                   AND cc.timecompleted IS NULL
                   $completionenabledsql
              ORDER BY ul.timeaccess DESC";

        $params = [
                'uluserid' => $userid,
                'visible' => 1,
            ] + $enrolmentsparams;

        return [$sql, $params];
    }

    /**
     * Return courses with enabled and active enrolments for a user sql.
     *
     * @param int $userid
     * @return array
     */
    private static function get_user_enrolments_sql(int $userid): array {
        $sql = "SELECT DISTINCT e.courseid
                           FROM {enrol} e
                           JOIN {user_enrolments} ue
                                ON ue.enrolid = e.id
                                AND ue.userid = :ueuserid
                          WHERE ue.status = :active
                            AND e.status = :enabled
                            AND ue.timestart < :now1
                            AND (ue.timeend = 0 OR ue.timeend > :now2)";

        $now = time();
        $params = [
            'ueuserid' => $userid,
            'enabled' => ENROL_INSTANCE_ENABLED,
            'active' => ENROL_USER_ACTIVE,
            'now1' => $now,
            'now2' => $now
        ];
        return [$sql, $params];
    }
}

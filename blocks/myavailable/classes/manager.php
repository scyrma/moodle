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

namespace block_myavailable;

use context_course;
use stdClass;
use tool_program\api;

/**
 * Manager class for block_myavailable
 *
 * @package    block_myavailable
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Odei Alba <odei.alba@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {

    /**
     * Returns available courses for current user.
     *
     * @param int|null $userid
     * @return array
     */
    public static function get_available_courses(int $userid = null): array {
        global $DB, $USER;

        $userid = !empty($userid) ? $userid : (int) $USER->id;

        [$sql, $params] = self::get_available_courses_sql($userid);

        $records = $DB->get_records_sql($sql, $params);

        return array_filter($records, static function(stdClass $record) {
            return $record->visible || has_capability('moodle/course:viewhiddencourses', context_course::instance($record->id));
        });
    }

    /**
     * Return user available courses sql ordered by course fullname.
     * Available course is defined as a visible course, where user is enrolled or it is accesible through a program,
     * and user has never accessed.
     *
     * @param int $userid
     * @return array
     */
    private static function get_available_courses_sql(int $userid): array {
        global $DB;

        $accesiblecoursesids = self::get_accessible_courses_ids($userid);
        [$insql, $inparams] = $DB->get_in_or_equal($accesiblecoursesids, SQL_PARAMS_NAMED, 'acid', true, 0);

        $sql = "SELECT c.*
                  FROM {course} c
                 WHERE c.id $insql
        AND NOT EXISTS (SELECT ul.id FROM {user_lastaccess} ul WHERE ul.userid = :userid AND ul.courseid = c.id)
              ORDER BY c.fullname";

        $params = ['userid' => $userid] + $inparams;

        return [$sql, $params];
    }

    /**
     * Returns the accessible courses ids.
     * Accessible courses are defined as courses where the user is enroled, or courses inside a program
     * that are unlocked for the user.
     *
     * @param int $userid
     * @return array
     */
    private static function get_accessible_courses_ids(int $userid): array {
        // Get all course ids where user is enrolled.
        $enrolledcoursesids = array_keys(enrol_get_all_users_courses($userid, true, '*', 'fullname'));

        // Get all accessible courses within a program.
        $programcoursesids = [];
        $programs = api::get_user_accessible_programs($userid);
        foreach ($programs as $program) {
            $programcoursesids = array_unique(array_merge($programcoursesids, api::get_unlocked_courses_ids($program, $userid)));
        }

        // Return all accesible courses removing duplicates.
        return array_unique(array_merge($enrolledcoursesids, $programcoursesids), SORT_REGULAR);
    }
}

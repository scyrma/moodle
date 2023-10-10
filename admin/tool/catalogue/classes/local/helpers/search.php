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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_catalogue\local\helpers;

use tool_catalogue\configuration;

/**
 * Class search
 *
 * @package     tool_catalogue
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class search {

    /**
     * Returns list of courses that match the search criteria.
     *
     * @param string $searchstring
     * @return array Keys represent course ids and values are priorities.
     */
    public static function search_courses(string $searchstring = ''): array {
        $coursecatcache = \cache::make('core', 'coursecat');
        $searchterms = preg_split('|\s+|', trim($searchstring), 0, PREG_SPLIT_NO_EMPTY);
        $cachekey = 'w-'.serialize($searchterms);
        $priorities = $coursecatcache->get($cachekey);
        if ($priorities === false) {
            // Courses should be ordered by "priority DESC, sortorder ASC" when displayed.
            $records = get_courses_search($searchterms, 'c.sortorder', 0, 9999999, $totalcount);
            $priorities = self::calculate_search_priorities($records, $searchterms);
            arsort($priorities, SORT_NUMERIC);
            $coursecatcache->set($cachekey, $priorities);
        }
        return $priorities;
    }

    /**
     * Returns list of courses in category and subcategories that user is allowed to see.
     *
     * @param int $categoryid
     * @return array Keys represent course ids and values are priorities.
     */
    public static function get_courses_by_category(int $categoryid = 0): array {
        if ($categoryid === 0) {
            $category = \core_course_category::user_top();
        } else {
            $category = \core_course_category::get($categoryid, IGNORE_MISSING);
        }

        if (!$category || !\core_course_category::can_view_category($category)) {
            // No permission or invalid category id.
            return [];
        }
        // Use cachekey that get_courses uses internally.
        $coursecatcache = \cache::make('core', 'coursecat');
        $cachekey = 'l-'. $categoryid. '-r-'. serialize(['sortorder' => 1]);
        $ids = $coursecatcache->get($cachekey);
        if ($ids === false) {
            // Function get_courses() will set the cache for the next time.
            // It also validates permission to view courses and sub-categories.
            $ids = $category->get_courses(['recursive' => true, 'idonly' => true]);
        }
        return array_fill_keys($ids, 0);
    }

    /**
     * Calculate search priorities for courses
     *
     * @param array $records
     * @param array $searchterms
     * @return array with keys being course ids and values being priorities (the higher the better)
     */
    protected static function calculate_search_priorities(array &$records, array $searchterms): array {
        $words = [];
        foreach ($searchterms as $searchterm) {
            if ($searchterm[0] !== '-') {
                // Exclude search term that starts with - and strip + control signs.
                $words[] = preg_quote(preg_replace('/^\+/', '', $searchterm), '/');
            }
        }
        $regex = '/('.implode('|', $words).')/i';
        $priorities = [];
        foreach ($records as $record) {
            // If the course has any of the search terms in the fullname or shortname, set priority to 1,
            // otherwise set it to 0 (e.g. where search term is in summary).
            $priorities[$record->id] =
                count($words) && preg_match_all($regex, $record->fullname.' '.$record->shortname) ? 1 : 0;
        }
        return $priorities;
    }

    /**
     * Get courses by category or search string
     *
     * By default returns id, category, fullname, shortname fields.
     *
     * @param filters $filters
     * @param string[] $extracoursefields list of additional course fields (i.e. summary, startdate, enddate)
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public static function get_courses(filters $filters,
            array $extracoursefields = [], int $offset = 0, int $limit = 20): array {
        global $DB;

        $searchstring = $filters->get_search_string();
        $categoryid = $filters->get_categoryid();
        if ($searchstring === null) {
            $courseids = self::get_courses_by_category($categoryid);
        } else if ($searchstring !== null && empty($categoryid)) {
            $courseids = self::search_courses($searchstring);
        } else {
            throw new \coding_exception('Invalid parameters');
        }

        if (empty($courseids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($courseids), SQL_PARAMS_NAMED);
        $idbypriority = [];
        foreach ($courseids as $courseid => $priority) {
            if ($priority) {
                $idbypriority[$priority][] = $courseid;
            }
        }

        $prioritysql = 0;
        if (!empty($idbypriority)) {
            $queries = [];
            foreach ($idbypriority as $priority => $ids) {
                [$insqlpriority, $paramspriority] = $DB->get_in_or_equal(array_values($ids), SQL_PARAMS_NAMED);
                $queries[] = 'WHEN id '.$insqlpriority.' THEN '.$priority;
                $params = array_merge($params, $paramspriority);
            }
            $prioritysql = 'CASE '.implode(' ', $queries).' ELSE 0 END';
        }

        $fields = 'id, category, fullname, shortname';
        if ($extracoursefields) {
            $fields .= ', ' . join(', ', $extracoursefields);
        }
        $sql = "SELECT $fields, ($prioritysql) AS priority FROM {course} WHERE id $insql ORDER BY priority DESC, sortorder ASC";
        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Get courses count by category or search string
     *
     * @param filters|null $filters
     * @return int
     */
    public static function count_courses(?filters $filters): int {
        if ($filters->get_search_string() === null) {
            $courseids = self::get_courses_by_category($filters->get_categoryid());
        } else if ($filters->get_search_string() !== null && empty($filters->get_categoryid())) {
            $courseids = self::search_courses($filters->get_search_string());
        } else {
            throw new \coding_exception('Invalid parameters');
        }

        return count($courseids);
    }
}

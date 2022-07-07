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

namespace tool_program\persistent;

use Closure;
use core\persistent;
use stdClass;

/**
 * Program set
 *
 * @package tool_program
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_set extends persistent {
    /**
     * Database table.
     */
    public const TABLE = 'tool_program_sets';
    /**
     * Completion all in order
     */
    public const COMPLETION_ALL_IN_ORDER = 0;
    /**
     * Completion all in any order
     */
    public const COMPLETION_ALL_IN_ANY_ORDER = 1;
    /**
     * Completion at least
     */
    public const COMPLETION_AT_LEAST = 2;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'programid' => [
                'type' => PARAM_INT,
            ],
            'parent' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'completioncriteria' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => self::COMPLETION_ALL_IN_ORDER,
            ],
            'completionatleast' => [
                'type' => PARAM_INT,
                'optional' => true,
                'default' => 1,
            ],
        ];
    }

    /**
     * Get courses ids within the set (shallow, not recursive).
     *
     * @return int[]
     */
    public function get_courses_ids(): array {
        global $DB;

        return $DB->get_fieldset_select(program_course::TABLE, 'courseid', 'setid = :setid', ['setid' => $this->get('id')]);
    }

    /**
     * Get program courses ids (shallow, not recursive).
     *
     * @return int[]
     */
    public function get_program_courses_ids(): array {
        global $DB;

        return $DB->get_fieldset_select(program_course::TABLE, 'id', 'setid = :setid', ['setid' => $this->get('id')]);
    }

    /**
     * Get courses count within the set (shallow, not recursive).
     *
     * @return int
     */
    public function get_courses_count(): int {
        return count($this->get_courses_ids());
    }

    /**
     * Get courses within the set (shallow, not recursive).
     *
     * @return stdClass[]
     */
    public function get_courses(): array {
        global $DB;

        $courses = [];

        if ($coursesids = $this->get_courses_ids()) {
            [$sql, $params] = $DB->get_in_or_equal($coursesids, SQL_PARAMS_NAMED, 'id');
            $where = 'WHERE id ' . $sql;
            $courses = $DB->get_records_sql('SELECT * FROM {course} ' . $where . ' ORDER BY sortorder DESC', $params);
        }

        return $courses;
    }

    /**
     * Get program courses (shallow, not recursive).
     *
     * @return program_course[]
     */
    public function get_program_courses(): array {
        global $DB;

        $programcourses = [];
        $records = $DB->get_records(program_course::TABLE, ['setid' => $this->get('id')]);
        foreach ($records as $record) {
            $programcourses[] = new program_course(0, $record);
        }

        return $programcourses;
    }

    /**
     * Get program
     *
     * @return program|false
     */
    public function get_program() {
        return program::get_record(['id' => $this->get('programid')]);
    }

    /**
     * Returns array with ids from subsets.
     *
     * @return array
     */
    public function get_subsets_ids(): array {
        global $DB;
        return $DB->get_fieldset_select(self::TABLE, 'id', 'parent = :setid', ['setid' => $this->get('id')]);
    }

    /**
     * Returns array with subsets (shallow, not recursive).
     *
     * @return program_set[]
     */
    public function get_subsets(): array {
        global $DB;

        $subsets = [];
        if ($subsetsids = $this->get_subsets_ids()) {
            [$insql, $params] = $DB->get_in_or_equal($subsetsids, SQL_PARAMS_NAMED, 'id');
            $sqlquery = 'SELECT * FROM {' . self::TABLE . '} WHERE id ' . $insql . ' ORDER BY sortorder DESC';
            $subsetrecords = $DB->get_records_sql($sqlquery, $params);
            foreach ($subsetrecords as $subsetrecord) {
                $subsets[] = new self(0, $subsetrecord);
            }
        }

        return $subsets;
    }

    /**
     * Get the immediate children of this set (shallow, not recursive) sorted by sortorder.
     *
     * @return program_set[]|program_course[]
     */
    public function get_sorted_children(): array {
        $childrensets = self::get_records(['parent' => $this->get('id')]);
        $childrencourses = program_course::get_records(['setid' => $this->get('id')]);
        $children = array_merge($childrensets, $childrencourses);
        usort($children, $this->get_persistents_sorter_by_sortorder());

        return $children;
    }

    /**
     * Get last child sortorder
     *
     * @return int
     */
    private function get_last_child_sortorder(): int {
        global $DB;

        $sqlquery = 'SELECT COALESCE(MAX(u.sortorder), 0)
                       FROM (SELECT pse.parent, pse.sortorder
                               FROM {' . self::TABLE . '} pse
                              WHERE pse.parent = :parent
                          UNION ALL
                             SELECT pco.setid AS parent, pco.sortorder
                               FROM {' . program_course::TABLE . '} pco
                              WHERE pco.setid = :setid) u ';

        $setid = $this->get('id');

        return (int) $DB->get_field_sql($sqlquery, ['parent' => $setid, 'setid' => $setid]);
    }

    /**
     * Get next child sortorder
     *
     * @return int
     */
    public function get_next_child_sortorder(): int {
        return 1 + $this->get_last_child_sortorder();
    }

    /**
     * Get sorter for persistents by their sortorder field value.
     *
     * @return Closure
     */
    private function get_persistents_sorter_by_sortorder(): Closure {
        return static function(persistent $child1, persistent $child2) {
            return (int) $child1->get('sortorder') <=> (int) $child2->get('sortorder');
        };
    }
}

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

namespace tool_program\reportbuilder\local\filters;

use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\database;

/**
 * Contains course custom filter
 *
 * @package   tool_program
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class contains_course extends text {

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values): array {
        if (!$values) {
            return ['', []];
        }

        $coursealias = database::generate_alias();

        $this->filter->set_field_sql("{$coursealias}.fullname");

        [$res, $params] = parent::get_sql_filter($values);

        if (empty($res)) {
            return [$res, $params];
        }

        $programcourse = database::generate_alias();
        $programset = database::generate_alias();
        $program = database::generate_alias();

        $sql = "
        EXISTS (
            SELECT 1 FROM {course} {$coursealias}
            WHERE {$res}
            AND {$coursealias}.id IN
                (SELECT {$programcourse}.courseid FROM {tool_program_courses} {$programcourse}
                JOIN {tool_program_sets} {$programset} ON {$programcourse}.setid = {$programset}.id
                JOIN {tool_program} {$program} ON {$program}.id = {$programset}.programid
                WHERE {$program}.id = tp.id)
        )";

        return [$sql, $params];
    }
}

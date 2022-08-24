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

/**
 * Behat plugin generator
 *
 * @package    tool_program
 * @category   test
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_program_generator extends behat_generator_base {

    /**
     * Get a list of the entities that can be created for this component
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'programs' => [
                'datagenerator' => 'program',
                'required' => [
                    'fullname'
                ],
                'switchids' => [
                    'tenant' => 'tenantid',
                ],
            ],
            'program_users' => [
                'datagenerator' => 'program_user',
                'required' => [
                    'user',
                    'program',
                ],
                'switchids' => [
                    'user' => 'userid',
                    'program' => 'programid',
                ],
            ],
            "program_courses" => [
                'datagenerator' => 'program_course',
                'required' => [
                    'course',
                    'program',
                ],
                'switchids' => [
                    'course' => 'courseid',
                    'program' => 'programid',
                    'set' => 'setid'
                ],
            ],
            "program_completions" => [
                'datagenerator' => 'program_completion',
                'required' => [
                    'user',
                    'program',
                ],
                'switchids' => [
                    'user' => 'userid',
                    'program' => 'programid',
                ],
            ],
            "program_sets" => [
                'singular' => 'program_set',
                'datagenerator' => 'program_set',
                'required' => [
                    'program',
                ],
                'switchids' => [
                    'program' => 'programid',
                    'set' => 'parent', // Use 'set' in behat generator to define the parent set.
                ],
            ],
        ];
    }

    /**
     * Get program id
     *
     * @param string $fullname
     * @return int
     */
    protected function get_program_id(string $fullname): int {
        global $DB;
        return $DB->get_field('tool_program', 'id', ['fullname' => $fullname], MUST_EXIST);
    }

    /**
     * Gets the tenant id.
     *
     * @param string $tenantname
     * @return int
     */
    protected function get_tenant_id(string $tenantname): int {
        global $DB;
        return $DB->get_field('tool_tenant', 'id', ['name' => $tenantname], MUST_EXIST);
    }

    /**
     * Get parent set id
     *
     * @param string $name
     * @return int
     */
    protected function get_set_id(string $name): int {
        global $DB;
        return $DB->get_field('tool_program_sets', 'id', ['name' => $name], MUST_EXIST);
    }
}

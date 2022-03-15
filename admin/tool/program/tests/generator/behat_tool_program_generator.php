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
 * Behat plugin generator
 *
 * @package    tool_program
 * @category   test
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Behat plugin generator
 *
 * @package    tool_program
 * @category   test
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
}

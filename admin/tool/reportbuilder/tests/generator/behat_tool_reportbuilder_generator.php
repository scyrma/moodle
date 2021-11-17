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
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Behat plugin generator
 *
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_reportbuilder_generator extends behat_generator_base {

    /**
     * Get a list of the entities that can be created for this component
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'audiences' => [
                'datagenerator' => 'audience',
                'required' => ['report'],
                'switchids' => [
                    'report' => 'reportid',
                    'position' => 'positionid',
                    'department' => 'departmentid',
                ]
            ],
            'reports' => [
                'datagenerator' => 'report',
                'required' => ['tenant'],
                'switchids' => ['tenant' => 'tenantid'],
            ],
            'schedules' => [
                'datagenerator' => 'schedule',
                'required' => ['report'],
                'switchids' => [
                    'report' => 'reportid',
                    'position' => 'positionid',
                    'department' => 'departmentid',
                ],
            ],
        ];
    }

    /**
     * Looks up report id from it's name
     *
     * @param string $name
     * @return int
     */
    protected function get_report_id(string $name): int {
        global $DB;

        return $DB->get_field('tool_reportbuilder', 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * Looks up tenant id from it's name
     *
     * @param string $name
     * @return int
     */
    protected function get_tenant_id(string $name): int {
        global $DB;

        return $DB->get_field('tool_tenant', 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * Looks up position id from it's name or idnumber
     *
     * @param string $nameoridnumber
     * @return int
     */
    protected function get_position_id(string $nameoridnumber): int {
        /** @var tool_organisation_generator $organisationgenerator */
        $organisationgenerator = behat_util::get_data_generator()->get_plugin_generator('tool_organisation');

        return $organisationgenerator->lookup_position($nameoridnumber);
    }

    /**
     * Looks up department id from it's name or idnumber
     *
     * @param string $nameoridnumber
     * @return int
     */
    protected function get_department_id(string $nameoridnumber): int {
        /** @var tool_organisation_generator $organisationgenerator */
        $organisationgenerator = behat_util::get_data_generator()->get_plugin_generator('tool_organisation');

        return $organisationgenerator->lookup_department($nameoridnumber);
    }
}

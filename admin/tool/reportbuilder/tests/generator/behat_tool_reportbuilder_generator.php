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
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Behat plugin generator
 *
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Alberto Lara Hernández <albertolara@moodle.com>
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
}

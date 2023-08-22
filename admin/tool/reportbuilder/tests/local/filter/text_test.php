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

namespace tool_reportbuilder\local\filter;

use tool_reportbuilder_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Tests for conversion of text filter
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @category  test
 * @covers    \tool_reportbuilder\local\filter\text
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class text_test extends \core_reportbuilder_testcase {

    /**
     * Load required classes
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/reportbuilder/tests/fixtures/testable_report_exporter.php");
    }

    /**
     * Get Report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    public function get_plugin_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Provider for text conversion test
     *
     * @return array[]
     */
    public function convert_provider(): array {
        return [
            'is any value' => [0, '', ['Admin', 'A', 'B']],
            'contains' => [0, 'A', ['Admin', 'A']],
            'doesn\'t contain' => [1, 'A', ['B']],
            'is equal to' => [2, 'A', ['A']],
            'starts with' => [3, 'A', ['Admin', 'A']],
            'ends with' => [4, 'A', ['A']],
            'is empty' => [5, 'A', []],
            'is not empty' => [6, 'A', ['Admin', 'A', 'B']],
        ];
    }

    /**
     * Test for conversion of text condition
     *
     * @dataProvider convert_provider
     * @param int $operator
     * @param string $firstname
     * @param array $expected
     * @return void
     */
    public function test_convert(int $operator, string $firstname, array $expected): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->get_plugin_generator();
        $this->getDataGenerator()->create_user(['firstname' => 'A']);
        $this->getDataGenerator()->create_user(['firstname' => 'B']);
        $report = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
                'adddefault' => 0,
            ]);
        $reportid = $report->get_id();
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $generator->add_column($report, 'user:firstname');
        $generator->add_condition($report, 'user:firstname');
        $DB->set_field('tool_reportbuilder', 'conditions',
            json_encode(['user:firstname_op' => $operator, 'user:firstname' => $firstname]),
            ['id' => $reportid]);

        $exporter = new \testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing($expected, array_column($rows, 0));

        $report = \tool_reportbuilder\manager::get_report($reportid);
        $newreportid = $report->convert();

        $newrows = $this->get_custom_report_content($newreportid);
        $this->assertEqualsCanonicalizing($expected, array_column($newrows, 'c0_firstname'));
    }
}

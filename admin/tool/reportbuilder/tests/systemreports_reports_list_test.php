<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * File containing tests for reports lists.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the system report reports_list
 *
 * @package   tool_reportbuilder
 * @covers    \tool_reportbuilder\local\systemreports\reports_list
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_systemreports_reports_list_testcase extends advanced_testcase {

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Basic test for the reports list class.
     */
    public function test_reports_list() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Generate one report.
        $mockreport = $this->get_generator()->create_report(
            ['source' => \tool_reportbuilder\test\mock_report::class]
        );

        // Retrieve and execute the "reports_list" system report. It should contain one report.
        $report = \tool_reportbuilder\system_report_factory::create(
            \tool_reportbuilder\local\systemreports\reports_list::class);

        $exporter = new testable_report_exporter($report->get_id());
        $rows = $exporter->get_table_rows();
        $this->assertEquals(1, count($rows));
    }
}
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
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\report_base;
use tool_reportbuilder\system_report_factory;
use tool_reportbuilder\local\systemreports\reports_list;
use tool_reportbuilder\test\mock_report;

global $CFG;
require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

/**
 * Tests for the system report reports_list
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @category  test
 * @covers    \tool_reportbuilder\local\systemreports\reports_list
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_systemreports_reports_list_testcase extends advanced_testcase {

    /**
     * Test setup
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Basic test for the reports list class.
     *
     * @return void
     */
    public function test_reports_list() {
        $this->setAdminUser();

        $this->create_report(mock_report::class);

        $exporter = $this->get_report_exporter();
        $rows = $exporter->get_table_rows();
        $this->assertCount(1, $rows);
    }

    /**
     * Test reports list is empty for a normal user without any audience records configured
     *
     * @return void
     */
    public function test_reports_list_no_access() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->create_report(mock_report::class);

        $exporter = $this->get_report_exporter();
        $this->assertEmpty($exporter->get_table_rows());
    }

    /**
     * Test reports list for all jobs
     *
     * @return void
     */
    public function test_reports_list_all_jobs() {
        list($position, $department) = $this->create_position_and_department();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->get_organisation_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
        ]);

        $exporter = $this->get_report_exporter();
        $this->assertCount(1, $exporter->get_table_rows());
    }

    /**
     * Test reports list for user in same position as report audience
     *
     * @return void
     */
    public function test_reports_list_same_position() {
        list($position, $department) = $this->create_position_and_department();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->get_organisation_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'positionid' => $position->id,
        ]);

        $exporter = $this->get_report_exporter();
        $this->assertCount(1, $exporter->get_table_rows());
    }

    /**
     * Test reports list for user in sub position of report audience
     *
     * @return void
     */
    public function test_reports_list_sub_position() {
        list($position, $department) = $this->create_position_and_department();
        $subposition = $this->get_organisation_generator()->create_position(['parentid' => $position->id]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->get_organisation_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $subposition->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'positionid' => $position->id,
            'subpositions' => 1,
        ]);

        $exporter = $this->get_report_exporter();
        $this->assertCount(1, $exporter->get_table_rows());
    }

    /**
     * Test reports list for user in same department as report audience
     *
     * @return void
     */
    public function test_reports_list_same_department() {
        list($position, $department) = $this->create_position_and_department();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->get_organisation_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'departmentid' => $department->id,
        ]);

        $exporter = $this->get_report_exporter();
        $this->assertCount(1, $exporter->get_table_rows());
    }

    /**
     * Test reports list for user in sub department of report audience
     *
     * @return void
     */
    public function test_reports_list_sub_department() {
        list($position, $department) = $this->create_position_and_department();
        $subdepartment = $this->get_organisation_generator()->create_department(['parentid' => $department->id]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->get_organisation_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $position->id,
            'departmentid' => $subdepartment->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'departmentid' => $department->id,
            'subdepartments' => 1,
        ]);

        $exporter = $this->get_report_exporter();
        $this->assertCount(1, $exporter->get_table_rows());
    }

    /**
     * Return reports_list exporter
     *
     * @return testable_report_exporter
     */
    protected function get_report_exporter() : testable_report_exporter {
        $reportslist = system_report_factory::create(reports_list::class);

        return new testable_report_exporter($reportslist->get_id());
    }

    /**
     * Create report from source class
     *
     * @param string $source
     * @return report_base
     */
    protected function create_report(string $source) : report_base {
        return $this->get_plugin_generator()->create_report(['source' => $source]);
    }

    /**
     * Generate a position and department
     *
     * @return array [$position, $department]
     */
    protected function create_position_and_department() : array {
        return $this->get_organisation_generator()->create_position_and_department();
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_plugin_generator() : tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Get organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_organisation_generator() : tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }
}
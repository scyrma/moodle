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
 * File containing tests for report access list class
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\report_base;
use tool_reportbuilder\local\helpers\audience;
use tool_reportbuilder\test\mock_report;

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\local\helpers\audience
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_helper_audience_testcase extends advanced_testcase {

    /** @var stdClass $user */
    protected $user;

    /**
     * Test setup
     */
    public function setUp() {
        $this->resetAfterTest();

        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);
    }

    /**
     * Test reports list is empty for a normal user without any audience records configured
     *
     * @return void
     */
    public function test_reports_list_no_access() {
        $reports = audience::user_reports_list();
        $this->assertEmpty($reports);
    }

    /**
     * Test reports list for all jobs
     *
     * @return void
     */
    public function test_reports_list_all_jobs() {
        list($position, $department) = $this->create_position_and_department();

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
        ]);

        $reports = audience::user_reports_list();
        $this->assertEquals([$report->get_id()], $reports);
    }

    /**
     * Test reports list for user in same position and department as report audience
     *
     * @return void
     */
    public function test_reports_list_same_position_and_department() {
        list($position, $department) = $this->create_position_and_department();

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $reports = audience::user_reports_list();
        $this->assertEquals([$report->get_id()], $reports);
    }

    /**
     * Test reports list for user in different position and department as report audience
     *
     * @return void
     */
    public function test_reports_list_different_position_and_department() {
        list($position, $department) = $this->create_position_and_department();

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        // Report with no audience.
        $this->create_report(mock_report::class);

        // Report with different audience.
        list($position2, $department2) = $this->create_position_and_department();
        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'positionid' => $position2->id,
            'departmentid' => $department2->id,
        ]);

        $reports = audience::user_reports_list();
        $this->assertEmpty($reports);
    }

    /**
     * Test reports list for user in same position as report audience
     *
     * @return void
     */
    public function test_reports_list_same_position() {
        list($position, $department) = $this->create_position_and_department();

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'positionid' => $position->id,
        ]);

        $reports = audience::user_reports_list();
        $this->assertEquals([$report->get_id()], $reports);
    }

    /**
     * Test reports list for user in sub position of report audience
     *
     * @return void
     */
    public function test_reports_list_sub_position() {
        list($position, $department) = $this->create_position_and_department();
        $subposition = $this->get_organisation_generator()->create_position(['parentid' => $position->id]);

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $subposition->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'positionid' => $position->id,
            'subpositions' => 1,
        ]);

        $reports = audience::user_reports_list();
        $this->assertEquals([$report->get_id()], $reports);
    }

    /**
     * Test reports list for user in sub position of report audience where sub positions are disabled
     *
     * @return void
     */
    public function test_reports_list_disable_sub_position() {
        list($position, $department) = $this->create_position_and_department();
        $subposition = $this->get_organisation_generator()->create_position(['parentid' => $position->id]);

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $subposition->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'positionid' => $position->id,
        ]);

        $reports = audience::user_reports_list();
        $this->assertEmpty($reports);
    }

    /**
     * Test reports list for user in same position and sub position of report audience
     *
     * @return void
     */
    public function test_reports_list_same_position_and_sub_position() {
        list($position, $department) = $this->create_position_and_department();
        $subposition = $this->get_organisation_generator()->create_position(['parentid' => $position->id]);

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $report1 = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report1->get_id(),
            'positionid' => $position->id,
        ]);

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $subposition->id,
            'departmentid' => $department->id,
        ]);

        $report2 = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report2->get_id(),
            'positionid' => $subposition->id,
        ]);

        $reports = audience::user_reports_list();
        $this->assertEqualsCanonicalizing([$report1->get_id(), $report2->get_id()], $reports);
    }

    /**
     * Test reports list for user in same department as report audience
     *
     * @return void
     */
    public function test_reports_list_same_department() {
        list($position, $department) = $this->create_position_and_department();

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'departmentid' => $department->id,
        ]);

        $reports = audience::user_reports_list();
        $this->assertEquals([$report->get_id()], $reports);
    }

    /**
     * Test reports list for user in sub department of report audience
     *
     * @return void
     */
    public function test_reports_list_sub_department() {
        list($position, $department) = $this->create_position_and_department();
        $subdepartment = $this->get_organisation_generator()->create_department(['parentid' => $department->id]);

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $position->id,
            'departmentid' => $subdepartment->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'departmentid' => $department->id,
            'subdepartments' => 1,
        ]);

        $reports = audience::user_reports_list();
        $this->assertEquals([$report->get_id()], $reports);
    }

    /**
     * Test reports list for user in sub department of report audience where sub departments are disabled
     *
     * @return void
     */
    public function test_reports_list_disable_sub_department() {
        list($position, $department) = $this->create_position_and_department();
        $subdepartment = $this->get_organisation_generator()->create_department(['parentid' => $department->id]);

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $position->id,
            'departmentid' => $subdepartment->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'departmentid' => $department->id,
        ]);

        $reports = audience::user_reports_list();
        $this->assertEmpty($reports);
    }

    /**
     * Test reports list for user in same department and sub department of report audience
     *
     * @return void
     */
    public function test_reports_list_same_department_and_sub_department() {
        list($position, $department) = $this->create_position_and_department();
        $subdepartment = $this->get_organisation_generator()->create_department(['parentid' => $department->id]);

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $report1 = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report1->get_id(),
            'departmentid' => $department->id,
        ]);

        $this->get_organisation_generator()->assign_job([
            'userid' => $this->user->id,
            'positionid' => $position->id,
            'departmentid' => $subdepartment->id,
        ]);

        $report2 = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report2->get_id(),
            'departmentid' => $subdepartment->id,
        ]);

        $reports = audience::user_reports_list();
        $this->assertEqualsCanonicalizing([$report1->get_id(), $report2->get_id()], $reports);
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
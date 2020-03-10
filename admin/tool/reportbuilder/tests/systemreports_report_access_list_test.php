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
use tool_reportbuilder\system_report_factory;
use tool_reportbuilder\local\systemreports\report_access_list;
use tool_reportbuilder\test\mock_report;

global $CFG;
require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\local\systemreports\report_access_list
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_systemreports_report_access_list_testcase extends advanced_testcase {

    /**
     * Test setup
     */
    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test access list where no audience has been configured
     *
     * @return void
     */
    public function test_report_no_access() {
        $report = $this->create_report(mock_report::class);

        $exporter = $this->get_report_exporter($report);
        $this->assertEmpty($exporter->get_table_rows());
    }

    /**
     * Test access list for all jobs
     *
     * @return void
     */
    public function test_report_all_jobs() {
        list($position, $department) = $this->create_position_and_department();

        $user = $this->getDataGenerator()->create_user();
        $this->get_organisation_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
        ]);

        $rows = $this->get_report_exporter($report)->get_table_rows();
        $this->assertCount(1, $rows);
        $this->assertContains(fullname($user), $rows[0][0]);
    }

    /**
     * Test access list for user in same position as report audience
     *
     * @return void
     */
    public function test_report_same_position() {
        list($position, $department) = $this->create_position_and_department();

        $user = $this->getDataGenerator()->create_user();
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

        $rows = $this->get_report_exporter($report)->get_table_rows();
        $this->assertCount(1, $rows);
        $this->assertContains(fullname($user), $rows[0][0]);
    }

    /**
     * Test access list for user in sub position of report audience
     *
     * @return void
     */
    public function test_report_sub_position() {
        list($position, $department) = $this->create_position_and_department();
        $subposition = $this->get_organisation_generator()->create_position(['parentid' => $position->id]);

        $user = $this->getDataGenerator()->create_user();
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

        $rows = $this->get_report_exporter($report)->get_table_rows();
        $this->assertCount(1, $rows);
        $this->assertContains(fullname($user), $rows[0][0]);
    }

    /**
     * Test access list for user in same department as report audience
     *
     * @return void
     */
    public function test_report_same_department() {
        list($position, $department) = $this->create_position_and_department();

        $user = $this->getDataGenerator()->create_user();
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

        $rows = $this->get_report_exporter($report)->get_table_rows();
        $this->assertCount(1, $rows);
        $this->assertContains(fullname($user), $rows[0][0]);
    }

    /**
     * Test access list for user in sub department of report audience
     *
     * @return void
     */
    public function test_report_sub_department() {
        list($position, $department) = $this->create_position_and_department();
        $subdepartment = $this->get_organisation_generator()->create_department(['parentid' => $department->id]);

        $user = $this->getDataGenerator()->create_user();
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

        $rows = $this->get_report_exporter($report)->get_table_rows();
        $this->assertCount(1, $rows);
        $this->assertContains(fullname($user), $rows[0][0]);
    }

    /**
     * Test access list for users in same position or department as report audience
     *
     * @return void
     */
    public function test_report_same_position_or_department() {
        // First user, position, department.
        list($position1, $department1) = $this->create_position_and_department();

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Alice']);
        $this->get_organisation_generator()->assign_job([
            'userid' => $user1->id,
            'positionid' => $position1->id,
            'departmentid' => $department1->id,
        ]);

        // Second user, position, department.
        list($position2, $department2) = $this->create_position_and_department();

        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Bob']);
        $this->get_organisation_generator()->assign_job([
            'userid' => $user2->id,
            'positionid' => $position2->id,
            'departmentid' => $department2->id,
        ]);

        $report = $this->create_report(mock_report::class);

        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'positionid' => $position1->id,
        ]);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'departmentid' => $department2->id,
        ]);

        $rows = $this->get_report_exporter($report)->get_table_rows();
        $this->assertCount(2, $rows);
        $this->assertContains(fullname($user1), $rows[0][0]);
        $this->assertContains(fullname($user2), $rows[1][0]);
    }

    /**
     * Return report_access_list exporter, containing reference to passed report
     *
     * @param report_base $report
     * @return testable_report_exporter
     */
    protected function get_report_exporter(report_base $report) : testable_report_exporter {
        $reportaccesslist = system_report_factory::create(report_access_list::class);

        return new testable_report_exporter($reportaccesslist->get_id(), true, 0, [
            'id' => $report->get_id(),
        ]);
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
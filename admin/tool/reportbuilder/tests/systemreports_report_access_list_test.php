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
 * File containing tests for report access list class
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_reportbuilder\report_base;
use tool_reportbuilder\system_report_factory;
use tool_reportbuilder\local\systemreports\report_access_list;
use tool_reportbuilder\test\mock_report;

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
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/reportbuilder/tests/fixtures/testable_report_exporter.php");
    }

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test access list where no audience has been configured
     */
    public function test_report_no_access(): void {
        $report = $this->create_report(mock_report::class);

        $exporter = $this->get_report_exporter($report);
        $rows = $exporter->get_table_rows();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString(fullname(get_admin()), $rows[0][0]);
    }

    /**
     * Test access list for all jobs
     */
    public function test_report_all_jobs(): void {
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
        $this->assertCount(2, $rows);

        // Check user.
        $expected = array_values(array_filter($rows, function(array $row) {
            return !empty($row[1]);
        }));
        $this->assertStringContainsString(fullname($user), $expected[0][0]);

        // Check admin.
        $admin = array_values(array_filter($rows, function(array $row) {
            return empty($row[1]);
        }));
        $this->assertStringContainsString(fullname(get_admin()), $admin[0][0]);
    }

    /**
     * Test access list for user in same position as report audience
     */
    public function test_report_same_position(): void {
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
        $this->assertCount(2, $rows);

        // Check user.
        $expected = array_values(array_filter($rows, function(array $row) {
            return !empty($row[1]);
        }));
        $this->assertStringContainsString(fullname($user), $expected[0][0]);

        // Check admin.
        $admin = array_values(array_filter($rows, function(array $row) {
            return empty($row[1]);
        }));
        $this->assertStringContainsString(fullname(get_admin()), $admin[0][0]);
    }

    /**
     * Test access list for user in sub position of report audience
     */
    public function test_report_sub_position(): void {
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
        $this->assertCount(2, $rows);

        // Check user.
        $expected = array_values(array_filter($rows, function(array $row) {
            return !empty($row[1]);
        }));
        $this->assertStringContainsString(fullname($user), $expected[0][0]);

        // Check admin.
        $admin = array_values(array_filter($rows, function(array $row) {
            return empty($row[1]);
        }));
        $this->assertStringContainsString(fullname(get_admin()), $admin[0][0]);
    }

    /**
     * Test access list for user in same department as report audience
     */
    public function test_report_same_department(): void {
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
        $this->assertCount(2, $rows);

        // Check user.
        $expected = array_values(array_filter($rows, function(array $row) {
            return !empty($row[1]);
        }));
        $this->assertStringContainsString(fullname($user), $expected[0][0]);

        // Check admin.
        $admin = array_values(array_filter($rows, function(array $row) {
            return empty($row[1]);
        }));
        $this->assertStringContainsString(fullname(get_admin()), $admin[0][0]);
    }

    /**
     * Test access list for user in sub department of report audience
     */
    public function test_report_sub_department(): void {
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
        $this->assertCount(2, $rows);

        // Check user.
        $expected = array_values(array_filter($rows, function(array $row) {
            return !empty($row[1]);
        }));
        $this->assertStringContainsString(fullname($user), $expected[0][0]);

        // Check admin.
        $admin = array_values(array_filter($rows, function(array $row) {
            return empty($row[1]);
        }));
        $this->assertStringContainsString(fullname(get_admin()), $admin[0][0]);
    }

    /**
     * Test access list for users in same position or department as report audience
     */
    public function test_report_same_position_or_department(): void {
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
        $this->assertCount(3, $rows);
        $this->assertStringContainsString(fullname(get_admin()), $rows[0][0]);
        $this->assertStringContainsString(fullname($user1), $rows[1][0]);
        $this->assertStringContainsString(fullname($user2), $rows[2][0]);
    }

    /**
     * Test access list doesn't duplicate rows when a job assignment exists in a department with multiple sub-departments
     */
    public function test_report_duplicate_users_multiple_sub_departments(): void {
        list($position, $department) = $this->create_position_and_department();

        $user = $this->getDataGenerator()->create_user(['firstname' => 'Alice']);
        $this->get_organisation_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        // Create multiple sub-departments (simulates WP-1498).
        $this->get_organisation_generator()->create_department(['parentid' => $department->id]);
        $this->get_organisation_generator()->create_department(['parentid' => $department->id]);

        // Audience should be that of the original position/department (not including sub-position/department).
        $report = $this->create_report(mock_report::class);

        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $rows = $this->get_report_exporter($report)->get_table_rows();
        $this->assertCount(2, $rows);

        // Check user.
        $expected = array_values(array_filter($rows, function(array $row) {
            return !empty($row[1]);
        }));
        $this->assertStringContainsString(fullname($user), $expected[0][0]);

        // Check admin.
        $admin = array_values(array_filter($rows, function(array $row) {
            return empty($row[1]);
        }));
        $this->assertStringContainsString(fullname(get_admin()), $admin[0][0]);
    }

    /**
     * Test access list for users with RB capabilities who are not in a job
     */
    public function test_report_users_with_capabilities(): void {
        list($position, $department) = $this->create_position_and_department();

        $user = $this->getDataGenerator()->create_user();
        $this->get_organisation_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $user2 = $this->getDataGenerator()->create_user();
        $this->get_plugin_generator()->assign_edit_capability($user2->id);

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
        ]);

        $rows = $this->get_report_exporter($report)->get_table_rows();
        $this->assertCount(3, $rows);

        // Check user with job.
        $userwithjob = array_filter($rows, function(array $row) {
            return !empty($row[1]);
        });
        $userwithjob = reset($userwithjob);
        $this->assertStringContainsString(fullname($user), $userwithjob[0]);

        // Check user with capability.
        $userwithcap = array_values(array_filter($rows, function(array $row) {
            return empty($row[1]) && (strpos($row[0], 'Admin User') === false);
        }));
        $this->assertStringContainsString(fullname($user2), $userwithcap[0][0]);

        // Check admin.
        $admin = array_values(array_filter($rows, function(array $row) {
            return empty($row[1]) && (strpos($row[0], 'Admin User') !== false);
        }));
        $this->assertStringContainsString(fullname(get_admin()), $admin[0][0]);
    }

    /**
     * Test getting users list that are set as audience for a report.
     */
    public function test_get_users_by_audience_sql(): void {
        global $DB;
        $alias = \tool_wp\db::generate_alias();
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $report = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);
        [$basejoin, $basecondition, $params] = report_access_list::get_users_by_audience_sql($report->get_id(),
            'u', $alias, false);
        // Assert no users have been set in audience.
        $sql = "SELECT * FROM {user} u $basejoin WHERE $basecondition";
        $records = $DB->get_records_sql($sql, $params);
        $this->assertEmpty($records);

        $tenantid = $tenantgenerator->create_tenant()->id;
        $report = $this->get_plugin_generator()->create_report(['source' => mock_report::class, 'tenantid' => $tenantid]);
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Alice']);
        $tenantgenerator->allocate_user($user->id, $tenantid);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Lionel']);
        $tenantgenerator->allocate_user($user2->id, $tenantid);
        $user3 = $this->getDataGenerator()->create_user(['firstname' => 'Lulu']);
        $tenantgenerator->allocate_user($user3->id, $tenantid);
        $position = $this->get_organisation_generator()->create_position(['tenantid' => $tenantid]);
        $department = $this->get_organisation_generator()->create_department(['tenantid' => $tenantid]);

        $this->setUser($user);

        $this->get_organisation_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $this->get_organisation_generator()->assign_job([
            'userid' => $user2->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        [$basejoin, $basecondition, $params] = report_access_list::get_users_by_audience_sql($report->get_id(),
            'u', $alias, false);
        // Assert 2 organisation users are found in the report.
        $sql = "SELECT * FROM {user} u $basejoin WHERE $basecondition";
        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(2, $records);
        $this->assertEquals($user->id, $records[$user->id]->id);
        $this->assertEquals($user->username, $records[$user->id]->username);
        $this->assertEquals($user2->id, $records[$user2->id]->id);
        $this->assertEquals($user2->username, $records[$user2->id]->username);
    }

    /**
     * Test getting users list that have RB capabilities as audience for a report.
     */
    public function test_get_users_by_capabilities_sql(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Yoda']);

        [$cannotmatchanyrows, $basejoin, $basecondition, $params] = report_access_list::get_users_by_capabilities_sql(false);
        $sql = "SELECT * FROM {user} u $basejoin WHERE 1=0 $basecondition";
        $this->assertFalse($cannotmatchanyrows);
        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(0, $records);

        // Assign tool/reportbuilder:edit capability to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('tool/reportbuilder:edit', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $user1->id, \context_system::instance()->id);

        [$cannotmatchanyrows, $basejoin, $basecondition, $params] = report_access_list::get_users_by_capabilities_sql(false);
        $sql = "SELECT * FROM {user} u $basejoin WHERE 1=0 $basecondition";
        $this->assertFalse($cannotmatchanyrows);
        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $records);
        $record = reset($records);
        $this->assertEquals($user1->username, $record->username);
    }

    /**
     * Return report_access_list exporter, containing reference to passed report
     *
     * @param report_base $report
     * @return testable_report_exporter
     */
    protected function get_report_exporter(report_base $report): testable_report_exporter {
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
    protected function create_report(string $source): report_base {
        return $this->get_plugin_generator()->create_report(['source' => $source]);
    }

    /**
     * Generate a position and department
     *
     * @return stdClass[]
     */
    protected function create_position_and_department(): array {
        return $this->get_organisation_generator()->create_position_and_department();
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_plugin_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Get organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_organisation_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }
}

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
 * File containing tests for report access list class
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\systemreports;

use advanced_testcase;
use testable_report_exporter;
use tool_reportbuilder_generator;
use tool_reportbuilder\report_base;
use tool_reportbuilder\system_report_factory;
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
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_access_list_test extends advanced_testcase {

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
     *
     * Note that admin does not show up on access list report.
     */
    public function test_report_no_access(): void {
        $report = $this->create_report(mock_report::class);

        $exporter = $this->get_report_exporter($report);
        $rows = $exporter->get_table_rows();
        $this->assertEmpty($rows);
    }

    /**
     * Test access list for users with RB capabilities who are not in an audience type
     *
     * Note that admin does not show up on access list report.
     */
    public function test_report_users_with_capabilities(): void {
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->get_plugin_generator()->assign_edit_capability($user2->id);

        $report = $this->create_report(mock_report::class);

        $rows = $this->get_report_exporter($report)->get_table_rows();
        // Should contain $user2, which has the edit report capability, but no $user.
        $this->assertCount(1, $rows);
        $this->assertStringContainsString(fullname($user2), $rows[0][0]);
    }

    /**
     * Test access list for users that belong to an audience type
     */
    public function test_report_users_with_audience_type(): void {
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $report = $this->create_report(mock_report::class);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'classname' => \tool_reportbuilder\tool_reportbuilder\audiences\manual::class,
            'configdata' => ['users' => [$user2->id]],
        ]);

        $rows = $this->get_report_exporter($report)->get_table_rows();
        // Should contain $user2, which belongs to an audience type, but no $user.
        $this->assertCount(1, $rows);
        $this->assertStringContainsString(fullname($user2), $rows[0][0]);
    }

    /**
     * Test getting users list that are set as audience for a report.
     */
    public function test_get_users_by_audience_sql(): void {
        global $DB;

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        // Pass zero as report ID.
        [$wheres, $params] = report_access_list::get_users_by_audience_sql(0);
        $this->assertEmpty($wheres);
        $this->assertEmpty($params);

        $report = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);
        [$wheres, $params] = report_access_list::get_users_by_audience_sql($report->get_id());

        // Assert no users have been set in audience.
        $this->assertEmpty($wheres);
        $this->assertEmpty($params);

        $tenantid = $tenantgenerator->create_tenant()->id;
        $report = $this->get_plugin_generator()->create_report(['source' => mock_report::class, 'tenantid' => $tenantid]);
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Alice']);
        $tenantgenerator->allocate_user($user->id, $tenantid);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Lionel']);
        $tenantgenerator->allocate_user($user2->id, $tenantid);
        $user3 = $this->getDataGenerator()->create_user(['firstname' => 'Lulu']);
        $tenantgenerator->allocate_user($user3->id, $tenantid);

        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'classname' => \tool_reportbuilder\tool_reportbuilder\audiences\manual::class,
            'configdata' => ['users' => [$user->id, $user3->id]],
        ]);

        [$basecondition, $params] = report_access_list::get_users_by_audience_sql($report->get_id());

        // Assert 2 organisation users are found in the report.
        $sql = "SELECT * FROM {user} u WHERE " .'(' . implode(') OR (', $basecondition) . ')';
        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(2, $records);
        $this->assertEqualsCanonicalizing(['Lulu', 'Alice'], array_column($records, 'firstname'));
    }

    /**
     * Test getting users list that have RB capabilities as audience for a report.
     */
    public function test_get_users_by_capabilities_sql(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Yoda']);

        [$cannotmatchanyrows, $basejoin, $basecondition, $params] = report_access_list::get_users_by_capabilities_sql(false);
        $sql = "SELECT * FROM {user} u $basejoin WHERE 1=0 AND $basecondition";
        $this->assertFalse($cannotmatchanyrows);
        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(0, $records);

        // Assign tool/reportbuilder:edit capability to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('tool/reportbuilder:edit', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $user1->id, \context_system::instance()->id);

        [$cannotmatchanyrows, $basejoin, $basecondition, $params] = report_access_list::get_users_by_capabilities_sql(false);
        $sql = "SELECT * FROM {user} u $basejoin WHERE $basecondition";
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
        $reportaccesslist = system_report_factory::create(report_access_list::class, ['id' => $report->get_id()]);

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
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_plugin_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }
}

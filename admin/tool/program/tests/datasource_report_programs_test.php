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
 * File containing tests for report_programs datasource
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\api;
use tool_program\tool_reportbuilder\datasources\report_programs;
use tool_reportbuilder\local\helpers\aggregation;
use tool_reportbuilder\local\helpers\columns;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the datasource report_programs
 *
 * @package     tool_program
 * @covers      \tool_program\tool_reportbuilder\datasources\report_programs
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_datasource_report_programs_testcase extends advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_reportbuilder_generator */
    protected $rbgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->rbgenerator = self::getDataGenerator()->get_plugin_generator('tool_reportbuilder');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Set up for the test
     */
    protected function set_up_for_report(): array {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

        $users = [];

        // Create one user and one program and allocate them to the default tenant.
        $user1 = self::getDataGenerator()->create_user()->id;
        $defaulttenantid = tenancy::get_default_tenant_id();
        $this->tenantgenerator->allocate_user($user1, $defaulttenantid);
        $programparams = (object) ['tenantid' => $defaulttenantid];
        $this->generator->generate_program($programparams);
        $users[] = $user1;

        // Create one more user and five more programs and allocate them to the new tenant.
        $othertenantid = $this->tenantgenerator->create_tenant()->id;
        $programparams = (object) ['tenantid' => $othertenantid];
        for ($i = 0; $i < 5; $i++) {
            $this->generator->generate_program($programparams)->get('id');
        }
        $user2 = self::getDataGenerator()->create_user()->id;
        $this->tenantgenerator->allocate_user($user2, $othertenantid);
        $users[] = $user2;

        // First user should have capability to edit reports.
        $this->rbgenerator->assign_edit_capability($users[0]);

        return $users;
    }

    /**
     * System capabilities and manager permissions
     */
    public function test_permissions(): void {
        $users = $this->set_up_for_report();

        // Create a report from the report_programs datasource with default columns/conditions.
        $reportid = $this->rbgenerator->create_report(['source' => report_programs::class])->get_id();

        // Execute report for different users.
        // User 0 can see the only program in the default tenant (but can not see programs in other tenants).
        self::setUser($users[0]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertCount(1, $rows);

        // User 1 can see all five programs in its tenant (but can not see programs in other tenants).
        self::setUser($users[1]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertCount(5, $rows);
    }

    /**
     * Convert tags column HTML content into array of plaintext values
     *
     * @param string $tags
     * @return string[]
     */
    private static function extract_tags(string $tags) : array {
        preg_match_all('/<span[^>]*>([^<]*)<\/span>/', $tags, $matches);

        return $matches[1];
    }

    /**
     * Test program tag aggregation
     *
     * @return void
     */
    public function test_tag_groupconcat_aggregation() : void {
        global $DB;

        // Not available in MSSQL.
        if (strcasecmp($DB->get_dbfamily(), 'mssql') === 0) {
            $this->markTestSkipped('MSSQL cannot aggregate columns containing sub-queries');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        api::create_program((object)['fullname' => 'Program 1', 'program_tags' => ['Hello', 'moon']]);
        api::create_program((object)['fullname' => 'Program 2', 'program_tags' => ['Hello', 'world']]);

        // Set up the report, containing a single column (tags).
        $report = $this->rbgenerator->create_report([
            'source' => report_programs::class,
            'adddefault' => 0,
        ]);

        $column = columns::add_column_from_key($report, 'tool_program:tags');

        // Setting aggregation method to groupconcat should return a single row containing all tags.
        aggregation::set_aggregation($column->get('id'), 'groupconcat');

        $rows = (new testable_report_exporter($report->get_id()))->get_table_rows();
        $this->assertCount(1, $rows);

        $tags = self::extract_tags(reset($rows)[0]);
        $this->assertEqualsCanonicalizing(['Hello', 'moon', 'Hello', 'world'], $tags);
    }

    /**
     * Test program distinct tag aggregation
     *
     * @return void
     */
    public function test_tag_groupconcatdistinct_aggregation() : void {
        global $DB;

        // Not available in MSSQL.
        if (strcasecmp($DB->get_dbfamily(), 'mssql') === 0) {
            $this->markTestSkipped('MSSQL cannot aggregate columns containing sub-queries');
        } else if (!\tool_reportbuilder\local\aggregate\groupconcatdistinct::is_compatible(null)) {
            $this->markTestSkipped($DB->get_dbfamily() . ' does not currently support group concat distinct');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        api::create_program((object)['fullname' => 'Program 1', 'program_tags' => ['Hello', 'moon']]);
        api::create_program((object)['fullname' => 'Program 2', 'program_tags' => ['Hello', 'world']]);

        // Set up the report, containing a single column (tags).
        $report = $this->rbgenerator->create_report([
            'source' => report_programs::class,
            'adddefault' => 0,
        ]);

        $column = columns::add_column_from_key($report, 'tool_program:tags');

        // Setting aggregation method to groupconcatdistinct should return a single row containing unique tags.
        aggregation::set_aggregation($column->get('id'), 'groupconcatdistinct');

        $rows = (new testable_report_exporter($report->get_id()))->get_table_rows();
        $this->assertCount(1, $rows);

        $tags = self::extract_tags(reset($rows)[0]);
        $this->assertEqualsCanonicalizing(['Hello', 'moon', 'world'], $tags);
    }

    /**
     * Create a report
     *
     * @param int $tenantid
     * @param bool $adddefault
     * @return int
     */
    protected function create_report(int $tenantid, bool $adddefault): int {
        return $this->rbgenerator->create_report([
            'source' => report_programs::class,
            'tenantid' => $tenantid,
            'adddefault' => (int) $adddefault
        ])->get_id();
    }

    /**
     * Stress testing - add all available columns, try all possible aggregation methods.
     */
    public function test_stress_aggregation(): void {
        $users = $this->set_up_for_report();
        self::setUser($users[0]);

        // Create a report from the report_programs datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $this->rbgenerator->add_all_available_columns_to_report($reportid);
        $this->rbgenerator->datasource_stress_test_aggregation($reportid, $this);
    }

    /**
     * Stress testing - add all available conditions.
     */
    public function test_stress_conditions(): void {
        $users = $this->set_up_for_report();
        self::setUser($users[0]);

        // Create a report from the report_programs datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $this->rbgenerator->add_all_available_columns_to_report($reportid);
        $this->rbgenerator->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing - add all available filters.
     */
    public function test_stress_filters(): void {
        $users = $this->set_up_for_report();
        self::setUser($users[0]);

        // Create a report from the report_programs datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $this->rbgenerator->add_all_available_columns_to_report($reportid);
        $this->rbgenerator->datasource_stress_test_filters($reportid, $this);
    }

    /**
     * Shared programs only display users from the current tenant and below
     */
    public function test_shared_programs() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $this->resetAfterTest();

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $program = $this->generator->generate_program((object)['tenantid' => $sharedspaceid, 'fullname' => 'Sharedprogram']);
        $program2 = $this->generator->generate_program((object)['tenantid' => $sharedspaceid, 'fullname' => 'NOTvisible!']);
        $DB->update_record('tool_program', ['shared' => 0, 'id' => $program2->get('id')]);
        $this->generator->allocate_users_to_program($program->get('id'),
            array_column(array_merge($users1, $users2), 'id'));
        $this->rbgenerator->assign_edit_capability($users1[0]->id);

        // Create a report inside a tenant. User from this tenant will be able to see one shared program with only their users.
        $this->setUser($users1[0]);
        $reportid = $this->create_report(tenancy::get_tenant_id($users1[0]->id), false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->rbgenerator->add_column($report, 'tool_program:fullname');
        $this->rbgenerator->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column($users1, 'username'), array_column($rows, 1));
        $this->assertEquals(['Sharedprogram'], array_unique(array_column($rows, 0)));

        // Create a report in shared space. Admin will be able to see both programs and all users.
        $this->setAdminUser();
        tenancy::set_switched_tenant_id($sharedspaceid);
        $reportid = $this->create_report($sharedspaceid, false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->rbgenerator->add_column($report, 'tool_program:fullname');
        $this->rbgenerator->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column(array_merge($users1, $users2), 'username'),
            array_filter(array_column($rows, 1)));
        $this->assertEqualsCanonicalizing(['NOTvisible!', 'Sharedprogram'], array_unique(array_column($rows, 0)));
    }
}

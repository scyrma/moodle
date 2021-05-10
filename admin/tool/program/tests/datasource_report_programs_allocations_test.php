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
 * File containing tests for report_programs_allocation_completion datasource
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\tool_reportbuilder\datasources\report_programs_allocation_completion;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the datasource report_programs_allocation_completion
 *
 * @package     tool_program
 * @covers      \tool_program\tool_reportbuilder\datasources\report_programs_allocation_completion
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_datasource_report_programs_allocations_testcase extends advanced_testcase {

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

        // Create one user and one allocation to a program in the default tenant.
        $user = self::getDataGenerator()->create_user()->id;
        $defaulttenantid = tenancy::get_default_tenant_id();
        $this->tenantgenerator->allocate_user($user, $defaulttenantid);
        $program = $this->generator->generate_program((object) ['tenantid' => $defaulttenantid]);
        $this->generator->allocate_user_to_program($program->get('id'), $user);

        // Create five more users and five more allocations to a program in a new tenant.
        $othertenantid = $this->tenantgenerator->create_tenant()->id;
        $programparams = (object) ['tenantid' => $othertenantid];
        $otherprogram = $this->generator->generate_program($programparams);
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = self::getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->tenantgenerator->allocate_user($users[$i], $othertenantid);
            $this->generator->allocate_user_to_program($otherprogram->get('id'), $users[$i]);
        }

        // First user should have capability to edit reports.
        $this->rbgenerator->assign_edit_capability($users[0]);
        // Second user should have capability to view reports.
        $this->rbgenerator->assign_read_capability($users[1]);

        return $users;
    }

    /**
     * System capabilities and manager permissions
     */
    public function test_permissions(): void {
        $users = $this->set_up_for_report();

        // Create a report from the report_programs datasource with default columns/conditions.
        $reportid = $this->rbgenerator->create_report(['source' => report_programs_allocation_completion::class])->get_id();

        // Execute report for different users.
        // Users 0 and 1 can see all five allocations in their tenant (but can not see allocations in other tenants).
        self::setUser($users[0]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertCount(5, $rows);

        self::setUser($users[1]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertCount(5, $rows);
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
            'source' => report_programs_allocation_completion::class,
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
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $this->resetAfterTest();

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $program = $this->generator->generate_program((object)['tenantid' => $sharedspaceid, 'fullname' => 'Sharedprogram']);
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
            array_column($rows, 1));
        $this->assertEquals(['Sharedprogram'], array_unique(array_column($rows, 0)));
    }

}

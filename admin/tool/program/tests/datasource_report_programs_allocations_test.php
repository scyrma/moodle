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
 * File containing tests for report_programs_allocation_completion datasource
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 */
class tool_program_datasource_report_programs_allocations_testcase extends advanced_testcase {

    /**
     * Get report builder generator
     *
     * @return tool_program_generator|component_generator_base
     * @throws coding_exception
     */
    protected function get_generator(): tool_program_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_program');
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator|component_generator_base
     * @throws coding_exception
     */
    protected function get_reportbuilder_generator(): tool_reportbuilder_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Returns the tenant generator
     *
     * @return tool_tenant_generator|component_generator_base
     * @throws coding_exception
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Set up for the test
     */
    protected function set_up_for_report(): array {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $this->setUp();
        $this->resetAfterTest();

        // Create one user and one allocation to a program in the default tenant.
        $user = self::getDataGenerator()->create_user()->id;
        $defaulttenantid = tenancy::get_default_tenant_id();
        $this->get_tenant_generator()->allocate_user($user, $defaulttenantid);
        $program = $this->get_generator()->generate_program_with_base_set((object) ['tenantid' => $defaulttenantid]);
        $this->get_generator()->allocate_user_to_program($program->get('id'), $user);

        // Create five more users and five more allocations to a program in a new tenant.
        $othertenantid = $this->get_tenant_generator()->create_tenant()->id;
        $programparams = (object) ['tenantid' => $othertenantid];
        $otherprogram = $this->get_generator()->generate_program_with_base_set($programparams);
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = self::getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], $othertenantid);
            $this->get_generator()->allocate_user_to_program($otherprogram->get('id'), $users[$i]);
        }

        // First user should have capability to edit reports.
        $this->get_reportbuilder_generator()->assign_edit_capability($users[0]);
        // Second user should have capability to view reports.
        $this->get_reportbuilder_generator()->assign_read_capability($users[1]);
        // Third user is a manager that has "view reports" permission over users four and five.
        $this->get_reportbuilder_generator()->assign_job_with_report_permissions($users[2], [$users[3], $users[4]]);

        return $users;
    }

    /**
     * System capabilities and manager permissions
     */
    public function test_permissions(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the report_programs datasource with default columns/conditions.
        $reportid = $generator->create_report(['source' => report_programs_allocation_completion::class])->get_id();

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

        // User 2 can see only his team allocations.
        self::setUser($users[2]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertCount(2, $rows);
    }

    /**
     * Create a report
     *
     * @param int $tenantid
     * @param bool $adddefault
     * @return int
     */
    protected function create_report(int $tenantid, bool $adddefault): int {
        return $this->get_reportbuilder_generator()->create_report([
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
        $generator = $this->get_reportbuilder_generator();
        self::setUser($users[0]);

        // Create a report from the report_programs datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_aggregation($reportid, $this);
    }

    /**
     * Stress testing - add all available conditions.
     */
    public function test_stress_conditions(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();
        self::setUser($users[0]);

        // Create a report from the report_programs datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing - add all available filters.
     */
    public function test_stress_filters(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();
        self::setUser($users[0]);

        // Create a report from the report_programs datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_filters($reportid, $this);
    }
}

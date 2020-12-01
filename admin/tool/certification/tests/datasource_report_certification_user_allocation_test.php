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
 * File containing tests for report_certification_user_allocation datasource
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\tool_reportbuilder\datasources\report_certification_user_allocation;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the datasource report_certification_user_allocation
 *
 * @package    tool_certification
 * @covers     \tool_certification\tool_reportbuilder\datasources\report_certification_user_allocation
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_datasource_report_certification_user_allocation_testcase extends advanced_testcase {

    /**
     * Get report builder generator
     *
     * @return tool_certification_generator|component_generator_base
     * @throws coding_exception
     */
    protected function get_generator(): tool_certification_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_certification');
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
     * Get program generator
     *
     * @return tool_program_generator|component_generator_base
     */
    public function get_program_generator(): tool_program_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_program');
    }

    /**
     * Set up for the test
     */
    protected function set_up_for_report(): array {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $this->setUp();
        $this->resetAfterTest();

        // Create one user and one allocation to a certification in the default tenant.
        $user = self::getDataGenerator()->create_user()->id;
        $defaulttenantid = tenancy::get_default_tenant_id();
        $this->get_tenant_generator()->allocate_user($user, $defaulttenantid);
        $certification = $this->get_generator()->generate_certification(['tenantid' => $defaulttenantid]);
        $this->get_generator()->allocate_user($user, $certification->get('id'));

        // Create five more users and five more allocations to a certification in a new tenant.
        $othertenantid = $this->get_tenant_generator()->create_tenant()->id;
        $othercertification = $this->get_generator()->generate_certification(['tenantid' => $othertenantid]);
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = self::getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], $othertenantid);
            $this->get_generator()->allocate_user($users[$i], $othercertification->get('id'));
        }

        // First user should have capability to edit reports.
        $this->get_reportbuilder_generator()->assign_edit_capability($users[0]);
        // Second user should have capability to view reports.
        $this->get_reportbuilder_generator()->assign_read_capability($users[1]);

        return $users;
    }

    /**
     * System capabilities and manager permissions
     */
    public function test_permissions(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the report_certification_user_allocation datasource with default columns/conditions.
        $reportid = $generator->create_report(['source' => report_certification_user_allocation::class])->get_id();

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
        return $this->get_reportbuilder_generator()->create_report([
            'source' => report_certification_user_allocation::class,
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

        // Create a report from the report_certifications datasource without default columns/conditions.
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

        // Create a report from the report_certifications datasource without default columns/conditions.
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

        // Create a report from the report_certification_user_allocation datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_filters($reportid, $this);
    }

    /**
     * Shared certifications only display users from the current tenant and below
     */
    public function test_shared_certifications() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $this->resetAfterTest();

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->get_tenant_generator()->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->get_tenant_generator()->create_tenant_and_users(2);
        $program = $this->get_program_generator()->generate_program((object)[
            'tenantid' => $sharedspaceid, 'fullname' => 'Sharedprogram']);
        $certification = $this->get_generator()->generate_certification([
            'tenantid' => $sharedspaceid, 'fullname' => 'Sharedcertification', 'program' => $program->get('id')]);

        $this->get_generator()->allocate_users_to_certification($certification->get('id'),
            array_column(array_merge($users1, $users2), 'id'));
        $this->get_reportbuilder_generator()->assign_edit_capability($users1[0]->id);

        // Create a report inside a tenant. User from this tenant will be able to see one shared certification with
        // only their users.
        $this->setUser($users1[0]);
        $reportid = $this->create_report(tenancy::get_tenant_id($users1[0]->id), false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->get_reportbuilder_generator()->add_column($report, 'tool_certification:fullname');
        $this->get_reportbuilder_generator()->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column($users1, 'username'), array_column($rows, 1));
        $this->assertEquals(['Sharedcertification'], array_unique(array_column($rows, 0)));

        // Create a report in shared space. Admin will be able to see all users.
        $this->setAdminUser();
        tenancy::set_switched_tenant_id($sharedspaceid);
        $reportid = $this->create_report($sharedspaceid, false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->get_reportbuilder_generator()->add_column($report, 'tool_certification:fullname');
        $this->get_reportbuilder_generator()->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column(array_merge($users1, $users2), 'username'),
            array_filter(array_column($rows, 1)));
        $this->assertEqualsCanonicalizing(['Sharedcertification'], array_unique(array_column($rows, 0)));
    }

    /**
     * Shared certifications only display users from the current tenant and below
     */
    public function test_certifications_on_shared_programs() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $this->resetAfterTest();

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->get_tenant_generator()->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->get_tenant_generator()->create_tenant_and_users(2);
        $program = $this->get_program_generator()->generate_program((object)[
            'tenantid' => $sharedspaceid, 'fullname' => 'Sharedprogram']);
        $certification = $this->get_generator()->generate_certification([
            'tenantid' => $tenant1->id, 'fullname' => 'Normalcertification', 'program' => $program->get('id')]);

        $this->get_generator()->allocate_users_to_certification($certification->get('id'),
            array_column($users1, 'id'));
        $this->get_reportbuilder_generator()->assign_edit_capability($users1[0]->id);

        // Create a report inside a tenant. User from this tenant will be able to see one shared certification.
        $this->setUser($users1[0]);
        $reportid = $this->create_report(tenancy::get_tenant_id($users1[0]->id), false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->get_reportbuilder_generator()->add_column($report, 'tool_certification:fullname');
        $this->get_reportbuilder_generator()->add_column($report, 'tool_program:fullname');
        $this->get_reportbuilder_generator()->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column($users1, 'username'), array_column($rows, 2));
        $this->assertEquals(['Normalcertification'], array_unique(array_column($rows, 0)));
        $this->assertEquals(['Sharedprogram'], array_unique(array_column($rows, 1)));
    }
}

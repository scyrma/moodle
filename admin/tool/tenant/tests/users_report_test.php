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
 * File containing tests for user_tenants_test
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_reportbuilder\system_report;
use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_tenant\users_report;
use tool_reportbuilder\test\mock_report;

/**
 * Class users_reports_test
 */
class users_report_test extends \advanced_testcase {

    /**
     * @var system_report
     */
    private $report;
    /**
     * @var component_generator_base
     */
    private $tenantgenator;

    /**
     * Setup test
     *
     */
    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->tenantgenator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

    }

    /**
     * Load required test libraries
     *
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/reportbuilder/tests/fixtures/testable_report_exporter.php");
    }

    /**
     * Test that the columns in the report match the user permissions
     *
     */
    public function test_visible_tenant_columns() {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        // Site not multi-tenant.
        $report = \tool_reportbuilder\system_report_factory::create(users_report::class);
        $testableexporter = new testable_report_exporter($report->get_id());
        // 1 row is returned (admin).
        $this->assertCount(1, $testableexporter->get_table_rows());
        // 5 columns, the tenant column is not present.
        $this->assertCount(5, $testableexporter->get_table_rows()[0]);

        // Site is multi-tenant, still only users from the specified tenant are returned.
        [$tenant1, $users1] = $this->tenantgenator->create_tenant_and_users(2);
        $testableexporter = new testable_report_exporter($report->get_id(), true, 0, ['id' => $tenant1->id]);
        // 2 rows returned (2 users in the tenant).
        $this->assertCount(2, $testableexporter->get_table_rows());
        // Tenant column is not present.
        $this->assertEquals(5, count($testableexporter->get_table_rows()[0]));

        // Show users from all tenants.
        $testableexporter = new testable_report_exporter($report->get_id(), true, 0, ['id' => 0, 'showall' => true]);
        $this->assertCount(6, $testableexporter->get_table_rows()[0]);

        // 3 rows returned (admin and 2 tenant users).
        $this->assertEquals(3, count($testableexporter->get_table_rows()));
        // Tenant column is present.
        $this->assertEquals(6, count($testableexporter->get_table_rows()[0]));
        $this->assertEqualsCanonicalizing(['New tenant 1', 'New tenant 1', 'Default tenant'],
            array_column($testableexporter->get_table_rows(), 4));

        // Someone who can switch tenant columns can see the tenant columns.
        $context = context_system::instance();
        $roleid = create_role('manage users role', 'manageusersrole', 'Role description');
        assign_capability('tool/tenant:manage', CAP_ALLOW, $roleid, $context->id);

        $this->getDataGenerator()->role_assign($roleid, $users1[0]->id);
        $this->setUser($users1[0]->id);
        $testableexporter = new testable_report_exporter($report->get_id(), true, 0, ['showall' => true]);
        // 3 rows returned (admin and 2 tenant users).
        $this->assertEquals(3, count($testableexporter->get_table_rows()));
        // 4 columns returned, there are no identity columns, tenant column is present.
        $this->assertCount(4, $testableexporter->get_table_rows()[0]);
        // Ensure that the tenant value exists.
        $this->assertEqualsCanonicalizing(['New tenant 1', 'New tenant 1', 'Default tenant'],
            array_column($testableexporter->get_table_rows(), 2));

        // Tenant admin cannot see tenant column.
        $tenantadmin = $this->getDataGenerator()->create_user();
        $manager = new \tool_tenant\manager();
        $manager->allocate_user($tenantadmin->id, $tenant1->id, 'tool_wp', 'test');
        $manager->assign_tenant_admin_roles([$tenantadmin->id], $tenant1->id);
        $this->setUser($tenantadmin->id);
        $testableexporter = new testable_report_exporter($report->get_id());
        // 3 users are returned - 2 tenant users and the tenant admin.
        $this->assertCount(3,
            $testableexporter->get_table_rows());
        $this->assertCount(5,
            $testableexporter->get_table_rows()[0]);
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
     * Test visibility of user identity columns.
     */
    public function test_visible_identity_columns() {
        global $CFG;
        $this->setAdminUser();
        $CFG->showuseridentity = 'email,username,institution';
        $report = \tool_reportbuilder\system_report_factory::create(users_report::class);
        $this->assertEquals(users_report::class, get_class($report));
        $testableexporter = new testable_report_exporter($report->get_id());
        // 1 row is returned (admin).
        $this->assertCount(1,
            $testableexporter->get_table_rows());
        // 7 columns are returned - checkbox, name, email, username, institution,last access,actions.
        $this->assertCount(7,
            $testableexporter->get_table_rows()[0]);

        // Site is multi-tenant.
        [$tenant1, $users1] = $this->tenantgenator->create_tenant_and_users(2); // Parent tenant.
        $this->assertEquals(users_report::class, get_class($report));
        $testableexporter = new testable_report_exporter($report->get_id(), true, 0, ['id' => $tenant1->id]);
        // 7 columns are returned
        // checkbox,fullname,lastaccess,email,username,institution,actions.
        $this->assertCount(7,
            $testableexporter->get_table_rows()[0]);

        // Show users from all tenants (tenant column visible).
        $this->tenantgenator->create_tenant_and_users(2); // Parent tenant.
        $testableexporter = new testable_report_exporter($report->get_id(), true, 0, ['id' => 0, 'showall' => true]);
        // 8 columns are returned
        // Checkbox,fullname,lastaccess,email,username,institution,actions.
        $this->assertCount(8, $testableexporter->get_table_rows()[0]);

        // Change $CFG->showuseridentity.
        $CFG->showuseridentity = 'email,username';
        $testableexporter = new testable_report_exporter($report->get_id(), true, 0, ['id' => $tenant1->id]);
        // 6 columns are returned.
        // Checkbox,fullname,lastaccess,email,username,actions.
        $this->assertCount(6, $testableexporter->get_table_rows()[0]);

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


    public function test_subtenant_users() {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $report = \tool_reportbuilder\system_report_factory::create(users_report::class);
        $this->assertEquals(users_report::class, get_class($report));
        $testableexporter = new testable_report_exporter($report->get_id());
        // 1 admin user is returned.
        $this->assertCount(1,
            $testableexporter->get_table_rows());

        // Site is multi-tenant. When using tenant report show only users for given tenant and subtenant.
        [$tenant1, $users1] = $this->tenantgenator->create_tenant_and_users(2); // Parent tenant.
        $this->assertEquals(users_report::class, get_class($report));
        $testableexporter = new testable_report_exporter($report->get_id(), true, 0, ['id' => $tenant1->id]);
        // 2 tenant users are returned.
        $this->assertCount(2,
            $testableexporter->get_table_rows());

        // Create a sub-tenant with "New tenant 1" as its parent.
        $subtenant = $this->tenantgenator->create_tenant_with_parent(['parentid' => $tenant1->id]);
        $this->tenantgenator->create_user(['tenantid' => $subtenant->id]);
        $this->assertEquals([$tenant1->id], \tool_tenant\hierarchy::get_parent_tenants_ids($subtenant->id));
        // 2 tenant users are returned.
        $testableexporter = new testable_report_exporter($report->get_id(), true, 0, ['id' => $tenant1->id]);
        $this->assertCount(2,
            $testableexporter->get_table_rows());

        // When looking at a sub tenant show users from only that tenant.
        $testableexporter = new testable_report_exporter($report->get_id(), true, 0, ['id' => $subtenant->id]);
        $this->assertCount(1,
            $testableexporter->get_table_rows());

        // When looking at all users report , show users from all tenants.
        $testableexporter = new testable_report_exporter($report->get_id(), true, 0, ['id' => 0, 'showall' => true]);
        // 1 admin ,2 tenant and 1 subtenant users are returned.
        $this->assertCount(4,
            $testableexporter->get_table_rows());
    }
}

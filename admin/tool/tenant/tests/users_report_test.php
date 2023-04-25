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

namespace tool_tenant;

use advanced_testcase;
use context_system;
use core_reportbuilder\testable_system_report_table;
use tool_tenant\reportbuilder\local\systemreports\users;
use tool_tenant_generator;

/**
 * Class users_reports_test, tests for the list of tenants report
 *
 * @package     tool_tenant
 * @category    test
 * @covers      \tool_tenant\reportbuilder\local\systemreports\users
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja <hittesh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class users_report_test extends advanced_testcase {

    /**
     * @var tool_tenant_generator
     */
    private $tenantgenator;

    /**
     * Setup test
     */
    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->tenantgenator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/reportbuilder/tests/fixtures/testable_system_report_table.php");
    }

    /**
     * Test visible tenant columns in a site not multi-tenant
     */
    public function test_visible_tenant_columns_no_multi_tenant(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Site not multi-tenant.
        $report = system_report_factory::create(users::class, []);
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'), []);
        // 1 row is returned (admin).
        $this->assertCount(1, $testableexporter->get_table_rows());
        // 4 columns, the tenant column is not present.
        $this->assertCount(4, $testableexporter->get_table_rows()[0]);
    }

    /**
     * Test visible tenant columns in a multi-tenant site
     */
    public function test_visible_tenant_columns_multi_tenant(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$tenant1] = $this->tenantgenator->create_tenant_and_users(2);

        // Site is multi-tenant, still only users from the specified tenant are returned.
        $report = system_report_factory::create(users::class, ['id' => $tenant1->id]);
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'),
            ['id' => $tenant1->id]);
        // 2 rows returned (2 users in the tenant, without returning admin user).
        $this->assertCount(2, $testableexporter->get_table_rows());
        // Tenant column is not present.
        $this->assertEquals(4, count($testableexporter->get_table_rows()[0]));
    }

    /**
     * Test visible tenant columns in a multi-tenant site showing users from all tenants
     */
    public function test_visible_tenant_columns_all_tenants(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->tenantgenator->create_tenant_and_users(2);

        // Show users from all tenants.
        $report = system_report_factory::create(users::class, ['id' => 0, 'showall' => true]);
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'),
            ['id' => 0, 'showall' => true]);
        $this->assertCount(5, $testableexporter->get_table_rows()[0]);

        // 3 rows returned (admin and 2 tenant users).
        $this->assertEquals(3, count($testableexporter->get_table_rows()));
        // Tenant column is present.
        $this->assertEquals(5, count($testableexporter->get_table_rows()[0]));
        $this->assertEqualsCanonicalizing(['New tenant 1', 'New tenant 1', 'Default tenant'],
            array_column($testableexporter->get_table_rows(), 'name')); // TODO tenant name?
    }

    /**
     * Test visible tenant columns in a multi-tenant site with a user who can switch tenants
     */
    public function test_visible_tenant_columns_user_can_switch_tenants(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$tenant1, $users1] = $this->tenantgenator->create_tenant_and_users(2);

        // Someone who can switch tenant columns can see the tenant columns.
        $context = context_system::instance();
        $roleid = create_role('manage users role', 'manageusersrole', 'Role description');
        assign_capability('tool/tenant:manage', CAP_ALLOW, $roleid, $context->id);

        $this->getDataGenerator()->role_assign($roleid, $users1[0]->id);
        $this->setUser($users1[0]->id);
        $report = system_report_factory::create(users::class, ['showall' => true]);
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'), ['showall' => true]);
        // 3 rows returned (admin and 2 tenant users).
        $this->assertEquals(3, count($testableexporter->get_table_rows()));
        // 4 columns returned, there are no identity columns, tenant column is present.
        $this->assertCount(3, $testableexporter->get_table_rows()[0]);
        // Ensure that the tenant value exists.
        $this->assertEqualsCanonicalizing(['New tenant 1', 'New tenant 1', 'Default tenant'],
            array_column($testableexporter->get_table_rows(), 'name'));
    }

    /**
     * Test visible tenant columns in a multi-tenant site with a tenant admin
     */
    public function test_visible_tenant_columns_tenant_admin(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('showuseridentity', 'email');
        set_config('hiddenuserfields', 'email');
        [$tenant1] = $this->tenantgenator->create_tenant_and_users(2);
        $contextsys = context_system::instance();

        // Tenant admin cannot see tenant column.
        $tenantadmin = $this->getDataGenerator()->create_user();
        $manager = new manager();
        $manager->allocate_user($tenantadmin->id, $tenant1->id, 'tool_wp', 'test');
        $manager->assign_tenant_admin_roles([$tenantadmin->id], $tenant1->id);
        assign_capability('moodle/user:viewhiddendetails', CAP_ALLOW, (int) $CFG->tool_tenant_adminrole, $contextsys);
        $this->setUser($tenantadmin->id);
        $report = system_report_factory::create(users::class, ['showall' => true]);
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'), ['showall' => true]);
        // List of columns and filters contain email.
        $this->assertContains('c3_email', array_keys($testableexporter->columns));
        $this->assertContains('user:email', array_keys($report->get_filters()));
        // 3 users are returned - 2 tenant users and the tenant admin.
        $this->assertCount(3, $testableexporter->get_table_rows());
        $this->assertCount(4, $testableexporter->get_table_rows()[0]);
    }

    /**
     * Test visibility of user identity columns.
     */
    public function test_visible_identity_columns(): void {
        $this->setAdminUser();
        set_config('showuseridentity', 'email,username,institution');
        $report = system_report_factory::create(users::class, []);

        $this->assertEquals(users::class, get_class($report));
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'), []);
        // 1 row is returned (admin).
        $this->assertCount(1, $testableexporter->get_table_rows());
        // 7 columns are returned - checkbox, name, email, username, institution,last access.
        $this->assertCount(6, $testableexporter->get_table_rows()[0]);
    }

    /**
     * Test visibility of user identity columns in a multi-tenant site.
     */
    public function test_visible_identity_columns_multi_tenant(): void {
        $this->setAdminUser();
        set_config('showuseridentity', 'email,username,institution');
        [$tenant1] = $this->tenantgenator->create_tenant_and_users(2);
        $report = system_report_factory::create(users::class, ['id' => $tenant1->id]);

        // Site is multi-tenant.
        [$tenant1] = $this->tenantgenator->create_tenant_and_users(2); // Parent tenant.
        $this->assertEquals(users::class, get_class($report));
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'),
            ['id' => $tenant1->id]);
        // 6 columns are returned: checkbox,fullname,lastaccess,email,username,institution.
        $this->assertCount(6, $testableexporter->get_table_rows()[0]);
    }

    /**
     * Test visibility of user identity columns showing all tenants.
     */
    public function test_visible_identity_columns_all_tenants(): void {
        $this->setAdminUser();
        set_config('showuseridentity', 'email,username,institution');
        $this->tenantgenator->create_tenant_and_users(2);
        $report = system_report_factory::create(users::class, ['id' => 0, 'showall' => true]);

        // Show users from all tenants (tenant column visible).
        $this->tenantgenator->create_tenant_and_users(2); // Parent tenant.
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'),
            ['id' => 0, 'showall' => true]);
        // 7 columns are returned: Checkbox,fullname,lastaccess,email,username,institution, tenant.
        $this->assertCount(7, $testableexporter->get_table_rows()[0]);
    }

    /**
     * Test visibility of user identity columns.
     */
    public function test_visible_identity_columns_tenant_user_identity(): void {
        $this->setAdminUser();
        set_config('showuseridentity', 'email,username');
        [$tenant1] = $this->tenantgenator->create_tenant_and_users(2);
        $report = system_report_factory::create(users::class, ['id' => $tenant1->id]);

        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'),
            ['id' => $tenant1->id]);
        // 5 columns are returned: Checkbox,fullname,lastaccess,email,username.
        $this->assertCount(5, $testableexporter->get_table_rows()[0]);
    }

    /**
     * Test columns with sub tenants.
     */
    public function test_subtenant_users(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$tenant1] = $this->tenantgenator->create_tenant_and_users(2); // Parent tenant.

        // Create a sub-tenant with "New tenant 1" as its parent.
        $subtenant = $this->tenantgenator->create_tenant_with_parent(['parentid' => $tenant1->id]);
        $this->tenantgenator->create_user(['tenantid' => $subtenant->id]);
        $this->assertEquals([$tenant1->id], hierarchy::get_parent_tenants_ids($subtenant->id));

        $report = system_report_factory::create(users::class, ['id' => $tenant1->id]);
        $this->assertEquals(users::class, get_class($report));
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'),
            ['id' => $tenant1->id]);

        // 2 tenant users are returned.
        $this->assertCount(2, $testableexporter->get_table_rows());
    }

    /**
     * Test columns with sub tenants with report from sub tenant.
     */
    public function test_subtenant_users_own_sub_tenant(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$tenant1] = $this->tenantgenator->create_tenant_and_users(2); // Parent tenant.

        // Create a sub-tenant with "New tenant 1" as its parent.
        $subtenant = $this->tenantgenator->create_tenant_with_parent(['parentid' => $tenant1->id]);
        $this->tenantgenator->create_user(['tenantid' => $subtenant->id]);
        $this->assertEquals([$tenant1->id], hierarchy::get_parent_tenants_ids($subtenant->id));

        $report = system_report_factory::create(users::class, ['id' => $subtenant->id]);
        $this->assertEquals(users::class, get_class($report));
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'),
            ['id' => $subtenant->id]);
        // When looking at a sub tenant show users from only that tenant.
        $this->assertCount(1, $testableexporter->get_table_rows());
    }

    /**
     * Test columns with sub tenants with report from sub tenant showing users from all tenants.
     */
    public function test_subtenant_users_own_sub_tenant_showall(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$tenant1] = $this->tenantgenator->create_tenant_and_users(2); // Parent tenant.

        // Create a sub-tenant with "New tenant 1" as its parent.
        $subtenant = $this->tenantgenator->create_tenant_with_parent(['parentid' => $tenant1->id]);
        $this->tenantgenator->create_user(['tenantid' => $subtenant->id]);
        $this->assertEquals([$tenant1->id], hierarchy::get_parent_tenants_ids($subtenant->id));

        $report = system_report_factory::create(users::class, ['id' => 0, 'showall' => true]);
        $this->assertEquals(users::class, get_class($report));

        // When looking at all users report , show users from all tenants.
        $testableexporter = testable_system_report_table::create($report->get_report_persistent()->get('id'),
            ['id' => 0, 'showall' => true]);
        // 1 admin ,2 tenant and 1 subtenant users are returned.
        $this->assertCount(4, $testableexporter->get_table_rows());
    }
}

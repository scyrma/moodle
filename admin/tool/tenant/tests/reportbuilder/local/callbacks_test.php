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

declare(strict_types=1);

namespace tool_tenant\reportbuilder\local;

use advanced_testcase;
use core_reportbuilder_generator;
use tool_tenant\sharedspace;
use tool_tenant_generator;
use tool_tenant\tenancy;
use core_reportbuilder\permission;
use core_reportbuilder\local\models\report;
use core_user\reportbuilder\datasource\users;

/**
 * Test for tenant callbacks for core reportbuilder
 *
 * @package    tool_tenant
 * @covers     \core_reportbuilder\permission
 * @covers     \tool_tenant\reportbuilder\local\callbacks
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Paul Holden <paulh@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class callbacks_test extends advanced_testcase {

    /**
     * Helper method to create a report with audience set to "All users"
     *
     * @param int|null $tenantid
     * @return report
     */
    private function create_report_with_audience(?int $tenantid = null): report {

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $report = $generator->create_report(['name' => 'My report', 'source' => users::class], $tenantid);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $generator->create_audience(['reportid' => $report->get('id'), 'configdata' => []]);

        return $report;
    }

    /**
     * Test current tenant is set for report
     */
    public function test_set_report_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        tenancy::set_switched_tenant_id($tenant->id);

        callbacks::set_report_tenant($report = (object) []);
        $this->assertEquals((object) ['component' => 'tool_tenant', 'itemid' => $tenant->id], $report);
    }

    /**
     * Test viewing report in same tenant
     */
    public function test_can_view_report_in_same_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $report = $this->create_report_with_audience();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->setUser($generator->create_user());

        $this->assertNull(callbacks::override_can_view_report($report));

        // Ensure original permission method we added our callback to also returns the same result.
        $this->assertTrue(permission::can_view_report($report));
    }

    /**
     * Test viewing report in different tenant
     */
    public function test_can_view_report_in_different_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        tenancy::set_switched_tenant_id($tenant->id);

        // Since we switched tenant, the report will be created in it.
        $report = $this->create_report_with_audience();

        // Admin can access this report.
        $this->assertTrue(callbacks::override_can_view_report($report));
        $this->assertTrue(permission::can_view_report($report));

        // Switch back to default tenant.
        tenancy::set_switched_tenant_id(tenancy::get_default_tenant_id());

        // Admin should not be able to access this report.
        $this->assertFalse(callbacks::override_can_view_report($report));
        $this->assertFalse(permission::can_view_report($report));

        // Now create a user in default tenant.
        $this->setUser($generator->create_user());

        $this->assertFalse(callbacks::override_can_view_report($report));
        $this->assertFalse(permission::can_view_report($report));
    }

    /**
     * Test editing report in same tenant
     */
    public function test_can_edit_report_in_same_tenant(): void {
        $this->resetAfterTest();

        $report = $this->create_report_with_audience();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->setUser($generator->create_user(['tenantadmin' => true]));

        $this->assertNull(callbacks::override_can_edit_report($report));

        // Ensure original permission method we added our callback to also returns the same result.
        $this->assertTrue(permission::can_edit_report($report));
    }

    /**
     * Test editing report in different tenant
     */
    public function test_can_edit_report_in_different_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        tenancy::set_switched_tenant_id($tenant->id);

        // Since we switched tenant, the report will be created in it.
        $report = $generator->create_report(['name' => 'My report', 'source' => users::class]);

        // Admin is able to edit the new report when switched to the report tenant.
        $this->assertNull(callbacks::override_can_edit_report($report));
        $this->assertTrue(permission::can_edit_report($report));

        // Switch back to default tenant.
        tenancy::set_switched_tenant_id(tenancy::get_default_tenant_id());

        // Admin can not edit the report.
        $this->assertFalse(callbacks::override_can_edit_report($report));
        $this->assertFalse(permission::can_edit_report($report));

        // Now create a user in default tenant.
        $this->setUser($generator->create_user(['tenantadmin' => true]));

        $this->assertFalse(callbacks::override_can_edit_report($report));
        $this->assertFalse(permission::can_edit_report($report));
    }

    /**
     * Test viewing and editing callbacks for the shared reports
     */
    public function test_can_view_and_edit_shared_report(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        $tenantadmin = $generator->create_user(['tenantadmin' => true, 'tenantid' => $tenant->id]);
        $sharedspaceid = sharedspace::enable_shared_space();
        $defaulttenantid = tenancy::get_default_tenant_id();

        // Create one report in tenant and two reports in shared space (one "available for all tenants" and one not).
        tenancy::set_switched_tenant_id($tenant->id);
        $report = $generator->create_report(['name' => 'Report1', 'source' => users::class]);
        tenancy::set_switched_tenant_id($sharedspaceid);
        $alltenantreport = $generator->create_report(['name' => 'Report2', 'source' => users::class, 'area' => 'shared']);
        $adminreport = $generator->create_report(['name' => 'Report3', 'source' => users::class]);

        // Admin in shared space can view and edit shared space reports.
        $this->assertNull(callbacks::override_can_edit_report($alltenantreport));
        $this->assertTrue(callbacks::override_can_view_report($alltenantreport));
        $this->assertNull(callbacks::override_can_edit_report($adminreport));
        $this->assertTrue(callbacks::override_can_view_report($adminreport));

        // Admin in shared space can not view or edit reports in other tenants.
        $this->assertFalse(callbacks::override_can_view_report($report));
        $this->assertFalse(callbacks::override_can_edit_report($report));

        // Switch to the tenant where $report is defined.
        tenancy::set_switched_tenant_id($tenant->id);

        // Admin still can view shared reports but not edit.
        $this->assertFalse(callbacks::override_can_edit_report($alltenantreport));
        $this->assertTrue(callbacks::override_can_view_report($alltenantreport));
        // Admin can not view or edit the report from shared space that is marked as not available in all tenants.
        $this->assertFalse(callbacks::override_can_edit_report($adminreport));
        $this->assertFalse(callbacks::override_can_view_report($adminreport));
        // Admin can view and edit reports from the same tenant.
        $this->assertTrue(callbacks::override_can_view_report($report));
        $this->assertNull(callbacks::override_can_edit_report($report));

        // Switch to the default tenant.
        tenancy::set_switched_tenant_id(tenancy::get_actual_tenant_id());

        // Admin can not view or edit report from another tenant.
        $this->assertFalse(callbacks::override_can_view_report($report));
        $this->assertFalse(callbacks::override_can_edit_report($report));

        // Tenant administrator.
        $this->setUser($tenantadmin);
        $this->assertEquals($tenant->id, tenancy::get_tenant_id());

        // User can view shared report that is "Available in all tenants" and can view and edit own tenant's reports.
        $this->assertFalse(callbacks::override_can_edit_report($alltenantreport));
        $this->assertTrue(callbacks::override_can_view_report($alltenantreport));
        $this->assertFalse(callbacks::override_can_edit_report($adminreport));
        $this->assertFalse(callbacks::override_can_view_report($adminreport));
        $this->assertTrue(callbacks::override_can_view_report($report));
        $this->assertNull(callbacks::override_can_edit_report($report));
    }
}

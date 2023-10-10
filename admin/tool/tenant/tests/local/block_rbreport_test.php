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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_tenant\local;

use advanced_testcase;
use core_reportbuilder\local\helpers\audience;
use core_reportbuilder\reportbuilder\audience\manual;
use core_reportbuilder\reportbuilder\audience\systemrole;
use core_user\reportbuilder\datasource\users;
use block_rbreport\manager;

/**
 * Tests for multi-tenancy support in block rbreport (core reports)
 *
 * @package     tool_tenant
 * @category    test
 * @covers      \tool_tenant\local\block_rbreport
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class block_rbreport_test extends \advanced_testcase {
    /**
     * Set up
     */
    protected function setUp(): void {
        if (!\core_component::get_component_directory('block_rbreport')) {
            $this->markTestSkipped('block_rbreport is not installed');
        }
        $this->resetAfterTest();
    }

    /**
     * Test for manager::get_report_options
     */
    public function test_get_report_options(): void {
        global $DB;

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        /** @var \tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = self::getDataGenerator()->get_plugin_generator('core_reportbuilder');

        // Create tenants and users.
        [$tenant1, [$user1]] = $tenantgenerator->create_tenant_and_users(1,
            ['dashboardlinked' => 0]);
        [$tenant2, [$user2]] = $tenantgenerator->create_tenant_and_users(1,
            ['dashboardlinked' => 0]);

        // Create an admin for tenant1.
        $manager = new \tool_tenant\manager();
        $tenantadmin1 = $this->getDataGenerator()->create_user();
        $manager->allocate_user($tenantadmin1->id, $tenant1->id, 'tool_tenant', 'testing');
        $manager->assign_tenant_admin_roles([$tenantadmin1->id], $tenant1->id);

        // Create a shared report.
        $sharedreport = $rbgenerator->create_report([
            'name' => 'Shared Report',
            'source' => users::class,
            'component' => 'tool_tenant',
            'area' => 'shared',
            'itemid' => $sharedspaceid,
        ]);
        // Create a report in each tenant.
        $report1 = $rbgenerator->create_report(['source' => users::class,
            'component' => 'tool_tenant', 'itemid' => $tenant1->id, 'name' => 'R1', ]);
        $report2 = $rbgenerator->create_report(['source' => users::class,
            'component' => 'tool_tenant', 'itemid' => $tenant1->id, 'name' => 'R2', ]);
        $report3 = $rbgenerator->create_report(['source' => users::class,
            'component' => 'tool_tenant', 'itemid' => $tenant2->id, 'name' => 'R3', ]);

        // Create audiences.
        $rbgenerator->create_audience([
            'reportid' => $report3->get('id'),
            'classname' => manual::class,
            'configdata' => ['users' => [$user2->id]],
        ]);
        $rbgenerator->create_audience([
            'reportid' => $sharedreport->get('id'),
            'classname' => systemrole::class,
            'configdata' => ['roles' => [\tool_tenant\manager::get_tenant_admin_role()]],
        ]);
        // Purge cache, to ensure allowed reports are re-calculated.
        audience::purge_caches();

        // Create 'my' pages.
        $sitedefaultpage = $DB->insert_record('my_pages', ['userid' => null, 'name' => '__default', 'private' => 1,
            'sortorder' => 0, ]);
        $tenant1page = $DB->insert_record('my_pages', ['userid' => null, 'name' => 'tenant-' . $tenant1->id, 'private' => 1,
            'sortorder' => 0, ]);
        $user1page = $DB->insert_record('my_pages', ['userid' => $user1->id, 'name' => '__default', 'private' => 1,
            'sortorder' => 0, ]);
        $user2page = $DB->insert_record('my_pages', ['userid' => $user2->id, 'name' => '__default', 'private' => 1,
            'sortorder' => 0, ]);

        $manager = new manager();

        $this->setUser($tenantadmin1);

        // System default dashboard.
        $options = $manager->get_report_options('my-index', $sitedefaultpage, new \moodle_url('/my/indexsys.php'));
        $expected = [$sharedreport->get('id') => $sharedreport->get_formatted_name()];
        $this->assertEquals($expected, $options);

        // Tenant dashboard.
        $options = $manager->get_report_options('my-index', $tenant1page, new \moodle_url('/admin/tool/tenant/editdashboard.php'));
        $expected = [
            $sharedreport->get('id') => $sharedreport->get_formatted_name(),
            $report1->get('id') => $report1->get_formatted_name(),
            $report2->get('id') => $report2->get_formatted_name(),
        ];
        $this->assertEquals($expected, $options);

        $this->setUser($user1);
        // User1 dashboard.
        $options = $manager->get_report_options('my-index', $user1page, new \moodle_url('/my/index.php'));
        $this->assertEmpty($options);

        $this->setUser($user2);
        // User2 dashboard.
        $options = $manager->get_report_options('my-index', $user2page, new \moodle_url('/my/index.php'));
        $expected = [$report3->get('id') => $report3->get_formatted_name()];
        $this->assertEquals($expected, $options);
    }

    /**
     * Test for manager::get_report_options when user has own reports
     *
     * @return void
     */
    public function test_get_report_options_own_reports(): void {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user']);
        role_change_permission($roleid, \context_system::instance(), 'moodle/reportbuilder:edit', CAP_ALLOW);

        /** @var \tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = self::getDataGenerator()->get_plugin_generator('core_reportbuilder');

        // Create tenants and users.
        [$tenant1, [$user1]] = $tenantgenerator->create_tenant_and_users(1,
            ['dashboardlinked' => 0]);
        $manager = new manager();

        // Create report as admin - it will not be visible to user.
        $this->setAdminUser();
        $report0 = $rbgenerator->create_report(['source' => users::class,
            'component' => 'tool_tenant', 'itemid' => $tenant1->id, 'name' => 'R0', ]);

        // User2 dashboard.
        $this->setUser($user1);
        $user1page = $DB->insert_record('my_pages', ['userid' => $user1->id, 'name' => '__default', 'private' => 1,
            'sortorder' => 0, ]);
        // Create report as user - it will be visible to user.
        $report1 = $rbgenerator->create_report(['source' => users::class,
            'component' => 'tool_tenant', 'itemid' => $tenant1->id, 'name' => 'R1', ]);

        $options = $manager->get_report_options('my-index', $user1page, new \moodle_url('/my/index.php'));
        $expected = [$report1->get('id') => $report1->get_formatted_name()];
        $this->assertEquals($expected, $options);
    }

    /**
     * Test for manager::get_report_options with disabled shared space
     */
    public function test_get_report_options_disabled_shared_space(): void {
        global $DB;

        /** @var \tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = self::getDataGenerator()->get_plugin_generator('core_reportbuilder');

        // Create tenants and users.
        [$tenant1, [$user1]] = $tenantgenerator->create_tenant_and_users(1,
            ['dashboardlinked' => 0]);

        // Create an admin for tenant1.
        $manager = new \tool_tenant\manager();
        $tenantadmin1 = $this->getDataGenerator()->create_user();
        $manager->allocate_user($tenantadmin1->id, $tenant1->id, 'tool_tenant', 'testing');
        $manager->assign_tenant_admin_roles([$tenantadmin1->id], $tenant1->id);

        // Create a report.
        $report1 = $rbgenerator->create_report(['source' => users::class,
            'component' => 'tool_tenant', 'itemid' => $tenant1->id, 'name' => 'R1', ]);

        // Create audience.
        $rbgenerator->create_audience([
            'reportid' => $report1->get('id'),
            'classname' => manual::class,
            'configdata' => ['users' => [$user1->id]],
        ]);

        // Create 'my' pages.
        $sitedefaultpage = $DB->insert_record('my_pages', ['userid' => null, 'name' => '__default', 'private' => 1,
            'sortorder' => 0, ]);
        $user1page = $DB->insert_record('my_pages', ['userid' => $user1->id, 'name' => '__default', 'private' => 1,
            'sortorder' => 0, ]);

        $manager = new manager();

        $this->setUser($tenantadmin1);

        // System default dashboard.
        $options = $manager->get_report_options('my-index', $sitedefaultpage, new \moodle_url('/my/indexsys.php'));
        $this->assertEmpty($options);

        $this->setUser($user1);
        // User1 dashboard.
        $options = $manager->get_report_options('my-index', $user1page, new \moodle_url('/my/index.php'));
        $expected = [$report1->get('id') => $report1->get_formatted_name()];
        $this->assertEquals($expected, $options);
    }
}

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

namespace tool_custompage;

use advanced_testcase;
use context_system;
use tool_custompage\tool_custompage\audience\manual;
use tool_custompage_generator;
use tool_tenant_generator;
use tool_tenant\tenancy;

/**
 * Unit tests for the permission class
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\permission
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission_test extends advanced_testcase {

    /**
     * Data provider for {@see test_can_view_pages_list} and {@see test_can_create_pages}
     *
     * @return array[]
     */
    public function capabilities_provider(): array {
        return [
            ['tool/custompage:edit', true],
            ['tool/custompage:editall', true],
            [null, false],
        ];
    }

    /**
     * Test whether user can view pages list
     *
     * @param string|null $capability
     * @param bool $expectsuccess
     * @dataProvider capabilities_provider
     */
    public function test_can_view_pages_list(?string $capability, bool $expectsuccess): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        if ($capability !== null) {
            $userrole = $DB->get_field('role', 'id', ['shortname' => 'user']);
            assign_capability($capability, CAP_ALLOW, $userrole, context_system::instance());
        }

        $this->assertEquals($expectsuccess, permission::can_view_pages_list());
    }

    /**
     * Test that exception is thrown when a user cannot view pages list
     */
    public function test_require_can_view_pages_list(): void {
        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot view the list of pages');
        permission::require_can_view_pages_list();
    }

    /**
     * Test whether user can create pages
     *
     * @param string|null $capability
     * @param bool $expectsuccess
     * @dataProvider capabilities_provider
     */
    public function test_can_create_page(?string $capability, bool $expectsuccess): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        if ($capability !== null) {
            $userrole = $DB->get_field('role', 'id', ['shortname' => 'user']);
            assign_capability($capability, CAP_ALLOW, $userrole, context_system::instance());
        }

        $this->assertEquals($expectsuccess, permission::can_create_page());
    }

    /**
     * Test that exception is thrown when a user cannot create pages
     */
    public function test_require_can_create_page(): void {
        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot create new pages');
        permission::require_can_create_page();
    }

    /**
     * Test whether user can create global pages
     */
    public function test_can_create_global_page(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse(permission::can_create_global_page());

        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user']);

        // Being able to edit a tenant page does not allow a user to create global pages.
        assign_capability('tool/custompage:edit', CAP_ALLOW, $userrole, context_system::instance());
        $this->assertFalse(permission::can_create_global_page());

        // Now they can.
        assign_capability('tool/custompage:editall', CAP_ALLOW, $userrole, context_system::instance());
        $this->assertTrue(permission::can_create_global_page());
    }

    /**
     * Test that exception is thrown when a user cannot create global pages
     */
    public function test_require_can_create_global_page(): void {
        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot create new pages');
        permission::require_can_create_global_page();
    }

    /**
     * Test whether user can edit pages in their own tenant
     */
    public function test_can_edit_page_tenant(): void {
        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        tenancy::set_switched_tenant_id($tenant->id);

        $user = $generator->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);
        $this->setUser($user);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'Tenant page', 'weight' => 1, 'global' => 0]);

        $this->assertTrue(permission::can_edit_page($page));
    }

    /**
     * Test whether user can edit pages in other tenants
     */
    public function test_can_edit_page_other_tenant(): void {
        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $user = $generator->create_user(['tenantadmin' => true]);

        // Set admin user, create page in other tenant.
        $this->setAdminUser();
        $tenant = $generator->create_tenant();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'Tenant page', 'weight' => 1, 'global' => 0, 'tenantid' => $tenant->id]);

        // Switch back to tenant admin.
        $this->setUser($user);
        $this->assertFalse(permission::can_edit_page($page));
    }

    /**
     * Test whether user can edit pages that are global
     */
    public function test_can_edit_page_global(): void {
        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        $user = $generator->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);
        $this->setUser($user);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'Tenant page', 'weight' => 1, 'global' => 1]);

        $this->assertFalse(permission::can_edit_page($page));

        $this->setAdminUser();
        $this->assertTrue(permission::can_edit_page($page));
    }

    /**
     * Test that exception is thrown when a user cannot edit pages
     */
    public function test_require_can_edit_page(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page((object) ['name' => 'My page', 'weight' => 0]);

        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot edit this page');
        permission::require_can_edit_page($page);
    }

    /**
     * Test whether user can preview page in their own tenant
     */
    public function test_can_preview_page_tenant(): void {
        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        tenancy::set_switched_tenant_id($tenant->id);

        $user = $generator->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);
        $this->setUser($user);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'Tenant page', 'weight' => 1, 'global' => 0]);

        $this->assertTrue(permission::can_preview_page($page));
    }

    /**
     * Test whether user can preview page in other tenants
     */
    public function test_can_preview_page_other_tenant(): void {
        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $user = $generator->create_user(['tenantadmin' => true]);

        // Set admin user, create page in other tenant.
        $this->setAdminUser();
        $tenant = $generator->create_tenant();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'Tenant page', 'weight' => 1, 'global' => 0, 'tenantid' => $tenant->id]);

        // Switch back to tenant admin.
        $this->setUser($user);
        $this->assertFalse(permission::can_preview_page($page));
    }

    /**
     * Test whether user can preview pages that are global
     */
    public function test_can_preview_page_global(): void {
        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        $tenantadmin = $generator->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);
        $tenantuser = $generator->create_user(['tenantid' => $tenant->id]);
        $this->setUser($tenantadmin);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'Tenant page', 'weight' => 1, 'global' => 1]);

        $this->assertFalse(permission::can_preview_page($page));

        // Add user to the audience.
        $generator->create_audience(['pageid' => $page->get('id'), 'configdata' => []]);
        $this->assertTrue(permission::can_preview_page($page));

        // Switch back to non tenant admin.
        $this->setUser($tenantuser);
        $this->assertFalse(permission::can_preview_page($page));

        $this->setAdminUser();
        $this->assertTrue(permission::can_preview_page($page));
    }

    /**
     * Test that exception is thrown when a user cannot preview page
     */
    public function test_require_can_preview_page(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page((object) ['name' => 'My page', 'weight' => 0]);

        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot preview this page');
        permission::require_can_preview_page($page);
    }

    /**
     * Test whether user can view page
     */
    public function test_can_view_page(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        $pageone = $generator->create_page(['name' => 'First page', 'weight' => 0]);
        $pagetwo = $generator->create_page(['name' => 'Second page', 'weight' => 1]);

        $generator->create_audience(['pageid' => $pageone->get('id'), 'configdata' => []]);

        $this->assertTrue(permission::can_view_page($pageone));
        $this->assertFalse(permission::can_view_page($pagetwo));
    }

    /**
     * Test whether user can view page as someone who can edit it
     */
    public function test_can_view_page_as_editor(): void {
        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        tenancy::set_switched_tenant_id($tenant->id);

        $user = $generator->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);
        $this->setUser($user);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'Tenant page', 'weight' => 1, 'global' => 0]);

        $this->assertTrue(permission::can_view_page($page));
    }

    /**
     * Test that exception is thrown when a user cannot view page
     */
    public function test_require_can_view_page(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page((object) ['name' => 'My page', 'weight' => 0]);

        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot view this page');
        permission::require_can_view_page($page);
    }

    /**
     * Test whether user can view specific page when it belongs to an audience
     */
    public function test_require_can_view_page_with_audience(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page((object) ['name' => 'My page', 'weight' => 0]);

        // User without permission.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $useraudience = $generator->create_audience([
            'pageid' => $page->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$user->id]],
        ]);

        // User belongs to an audience.
        permission::require_can_view_page($page);

        // Delete the audience.
        $useraudience->get_persistent()->delete();

        // User does not belong to an audience.
        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot view this page');
        permission::require_can_view_page($page);
    }

    /**
     * Test whether user can duplicate blocks without validation
     */
    public function test_can_duplicate_block_without_validation(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse(permission::can_duplicate_block_without_validation((int)$user->id));

        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user']);

        // Assign skipblockvalidation capability to allow user can duplicate blocks without validation.
        assign_capability('tool/custompage:skipblockvalidation', CAP_ALLOW, $userrole, context_system::instance());
        $this->assertTrue(permission::can_duplicate_block_without_validation((int)$user->id));
    }
}

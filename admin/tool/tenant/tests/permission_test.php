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
use tool_tenant_generator;
use moodle_exception;

/**
 * Tests for the tool_tenant\permission class methods.
 *
 * @package    tool_tenant
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission_test extends advanced_testcase {
    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test users with certain capabilities have access.
     */
    public function test_can_create_users() {
        $context = context_system::instance();
        // In the tenant with permission.
        $user1 = $this->getDataGenerator()->create_user();
        // In the tenant without permission.
        $user2 = $this->getDataGenerator()->create_user();
        // With permission but outside of the tenant.
        $user3 = $this->getDataGenerator()->create_user();
        $manager = new \tool_tenant\manager();
        $tenant1id = $this->generator->create_tenant()->id;
        $tenant2id = $this->generator->create_tenant()->id;

        $roleid = create_role('manage users role', 'manageusersrole', 'Role description');
        assign_capability('tool/tenant:manageusers', CAP_ALLOW, $roleid, $context->id);

        $this->getDataGenerator()->role_assign($roleid, $user1->id);
        $this->getDataGenerator()->role_assign($roleid, $user3->id);

        $manager->allocate_user($user1->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user2->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user3->id, $tenant2id, 'tool_tenant', 'testing');

        $this->setUser($user1);
        $this->assertTrue(permission::can_create_users($tenant1id));
        permission::require_can_create_users($tenant1id);

        $this->setUser($user2);
        $this->assertFalse(permission::can_create_users($tenant1id));
        try {
            permission::require_can_create_users($tenant1id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that (Create users).',
                $e->getMessage());
        }

        $this->setUser($user3);
        $this->assertFalse(permission::can_create_users($tenant1id));
    }

    /**
     * Test users with certain capabilities have access.
     */
    public function test_can_edit_users() {
        $context = context_system::instance();
        // In the tenant with permission.
        $user1 = $this->getDataGenerator()->create_user();
        // In the tenant without permission.
        $user2 = $this->getDataGenerator()->create_user();
        // With permission but outside of the tenant.
        $user3 = $this->getDataGenerator()->create_user();
        $manager = new \tool_tenant\manager();
        $tenant1id = $this->generator->create_tenant()->id;
        $tenant2id = $this->generator->create_tenant()->id;

        $roleid = create_role('manage users role', 'manageusersrole', 'Role description');
        assign_capability('tool/tenant:manageusers', CAP_ALLOW, $roleid, $context->id);

        $this->getDataGenerator()->role_assign($roleid, $user1->id);
        $this->getDataGenerator()->role_assign($roleid, $user3->id);

        $manager->allocate_user($user1->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user2->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user3->id, $tenant2id, 'tool_tenant', 'testing');

        $this->setUser($user1);
        $this->assertTrue(permission::can_update_users($tenant1id));
        permission::require_can_update_users($tenant1id);

        $this->setUser($user2);
        $this->assertFalse(permission::can_update_users($tenant1id));
        try {
            permission::require_can_update_users($tenant1id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that (Update user profiles).',
                $e->getMessage());
        }

        $this->setUser($user3);
        $this->assertFalse(permission::can_update_users($tenant1id));
    }

    /**
     * Test users with certain capabilities have access.
     */
    public function test_can_browse_users() {
        $context = context_system::instance();
        // User one can manage tenants (create and edit tenants).
        $user1 = $this->getDataGenerator()->create_user();
        // User two can move users from one tenant to another.
        $user2 = $this->getDataGenerator()->create_user();
        // User three can create and update users in tenant 1.
        $user3 = $this->getDataGenerator()->create_user();
        // User four can browse users in tenant 1.
        $user4 = $this->getDataGenerator()->create_user();
        // User five can create and update users in tenant 2.
        $user5 = $this->getDataGenerator()->create_user();
        // User six can browser users in tenant 2.
        $user6 = $this->getDataGenerator()->create_user();

        $manager = new \tool_tenant\manager();
        $tenant1id = $this->generator->create_tenant()->id;
        $tenant2id = $this->generator->create_tenant()->id;

        $tmroleid = create_role('tenant manager role', 'tenantmanagerrole', 'Role description');
        $toroleid = create_role('tenant organiser role', 'tenantorganiserrole', 'Role description');
        $muroleid = create_role('manage users role', 'manageusersrole', 'Role description');
        $tbroleid = create_role('browse tenants role', 'browsetenantsrole', 'Role description');
        assign_capability('tool/tenant:manage', CAP_ALLOW, $tmroleid, $context->id);
        assign_capability('tool/tenant:allocate', CAP_ALLOW, $toroleid, $context->id);
        assign_capability('tool/tenant:manageusers', CAP_ALLOW, $muroleid, $context->id);
        assign_capability('tool/tenant:browseusers', CAP_ALLOW, $tbroleid, $context->id);

        $this->getDataGenerator()->role_assign($tmroleid, $user1->id);
        $this->getDataGenerator()->role_assign($toroleid, $user2->id);
        $this->getDataGenerator()->role_assign($muroleid, $user3->id);
        $this->getDataGenerator()->role_assign($tbroleid, $user4->id);
        $this->getDataGenerator()->role_assign($muroleid, $user5->id);
        $this->getDataGenerator()->role_assign($tbroleid, $user6->id);

        $manager->allocate_user($user1->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user2->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user3->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user4->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user5->id, $tenant2id, 'tool_tenant', 'testing');
        $manager->allocate_user($user6->id, $tenant2id, 'tool_tenant', 'testing');

        $this->setUser($user1);
        $this->assertTrue(permission::can_browse_users_anywhere());
        // Browsing tenant 1 as user 1.
        $this->assertTrue(permission::can_browse_users($tenant1id));
        // Browsing tenant 2 as user 1. Should also work.
        $this->assertTrue(permission::can_browse_users($tenant2id));

        $this->setUser($user2);
        $this->assertTrue(permission::can_browse_users_anywhere());
        // Browsing tenant 1 as user 2.
        $this->assertTrue(permission::can_browse_users($tenant1id));
        // Browsing tenant 2 as user 2. Should also work.
        $this->assertTrue(permission::can_browse_users($tenant2id));

        $this->setUser($user3);
        $this->assertTrue(permission::can_browse_users_anywhere());
        // Browsing tenant 1 as user 3.
        $this->assertTrue(permission::can_browse_users($tenant1id));
        // Browsing tenant 2 as user 3.
        $this->assertFalse(permission::can_browse_users($tenant2id));

        $this->setUser($user4);
        $this->assertTrue(permission::can_browse_users_anywhere());
        // Browsing tenant 1 as user 4.
        $this->assertTrue(permission::can_browse_users($tenant1id));
        // Browsing tenant 2 as user 4.
        $this->assertFalse(permission::can_browse_users($tenant2id));

        $this->setUser($user5);
        $this->assertTrue(permission::can_browse_users_anywhere());
        // Browsing tenant 1 as user 5.
        $this->assertFalse(permission::can_browse_users($tenant1id));
        // Browsing tenant 2 as user 5. Should work.
        $this->assertTrue(permission::can_browse_users($tenant2id));

        $this->setUser($user6);
        $this->assertTrue(permission::can_browse_users_anywhere());
        // Browsing tenant 1 as user 6.
        $this->assertFalse(permission::can_browse_users($tenant1id));
        // Browsing tenant 2 as user 6. Should work.
        $this->assertTrue(permission::can_browse_users($tenant2id));
    }

    /**
     * Test users who can or not view inactive users.
     */
    public function test_can_view_inactive_users() {
        $context = context_system::instance();
        // User one can manage tenants (create and edit tenants).
        $user1 = $this->getDataGenerator()->create_user();
        // User two can move users from one tenant to another.
        $user2 = $this->getDataGenerator()->create_user();
        // User three can create and update users in tenant 1.
        $user3 = $this->getDataGenerator()->create_user();
        // User four can browse users in tenant 1.
        $user4 = $this->getDataGenerator()->create_user();
        // User five can create and update users in tenant 2.
        $user5 = $this->getDataGenerator()->create_user();
        // User six can browser users in tenant 2.
        $user6 = $this->getDataGenerator()->create_user();

        $manager = new \tool_tenant\manager();
        $tenant1id = $this->generator->create_tenant()->id;
        $tenant2id = $this->generator->create_tenant()->id;

        $tmroleid = create_role('tenant manager role', 'tenantmanagerrole', 'Role description');
        $toroleid = create_role('tenant organiser role', 'tenantorganiserrole', 'Role description');
        $muroleid = create_role('manage users role', 'manageusersrole', 'Role description');
        $tbroleid = create_role('browse tenants role', 'browsetenantsrole', 'Role description');
        assign_capability('tool/tenant:manage', CAP_ALLOW, $tmroleid, $context->id);
        assign_capability('tool/tenant:allocate', CAP_ALLOW, $toroleid, $context->id);
        assign_capability('tool/tenant:manageusers', CAP_ALLOW, $muroleid, $context->id);
        assign_capability('tool/tenant:browseusers', CAP_ALLOW, $tbroleid, $context->id);

        $this->getDataGenerator()->role_assign($tmroleid, $user1->id);
        $this->getDataGenerator()->role_assign($toroleid, $user2->id);
        $this->getDataGenerator()->role_assign($muroleid, $user3->id);
        $this->getDataGenerator()->role_assign($tbroleid, $user4->id);
        $this->getDataGenerator()->role_assign($muroleid, $user5->id);
        $this->getDataGenerator()->role_assign($tbroleid, $user6->id);

        $manager->allocate_user($user1->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user2->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user3->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user4->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user5->id, $tenant2id, 'tool_tenant', 'testing');
        $manager->allocate_user($user6->id, $tenant2id, 'tool_tenant', 'testing');

        $this->setUser($user1);
        // Viewing inactive users across the site as user 1. Should work.
        $this->assertTrue(permission::can_view_all_inactive_users());
        // Viewing inactive users in tenant 1 as user 1. Should work.
        $this->assertTrue(permission::can_view_inactive_users($tenant1id));
        // Viewing inactive users in tenant 2 as user 1. Should also work.
        $this->assertTrue(permission::can_view_inactive_users($tenant2id));

        $this->setUser($user2);
        // Viewing inactive users across the site as user 2. Should not work.
        $this->assertFalse(permission::can_view_all_inactive_users());
        // Viewing inactive users in tenant 1 as user 2. Should work.
        $this->assertTrue(permission::can_view_inactive_users($tenant1id));
        // Viewing inactive users in tenant 2 as user 2. Should also work.
        $this->assertTrue(permission::can_view_inactive_users($tenant2id));

        $this->setUser($user3);
        // Viewing inactive users across the site as user 3. Should not work.
        $this->assertFalse(permission::can_view_all_inactive_users());
        // Viewing inactive users in tenant 1 as user 3. Should work.
        $this->assertTrue(permission::can_view_inactive_users($tenant1id));
        // Viewing inactive users in tenant 2 as user 3. Should not work.
        $this->assertFalse(permission::can_view_inactive_users($tenant2id));

        $this->setUser($user4);
        // Viewing inactive users across the site as user 4. Should not work.
        $this->assertFalse(permission::can_view_all_inactive_users());
        // Viewing inactive users in tenant 1 as user 4. Should work.
        $this->assertTrue(permission::can_view_inactive_users($tenant1id));
        // Viewing inactive users in tenant 2 as user 4. Should not work.
        $this->assertFalse(permission::can_view_inactive_users($tenant2id));

        $this->setUser($user5);
        // Viewing inactive users across the site as user 5. Should not work.
        $this->assertFalse(permission::can_view_all_inactive_users());
        // Viewing inactive users in tenant 1 as user 5. Should not work.
        $this->assertFalse(permission::can_view_inactive_users($tenant1id));
        // Viewing inactive users in tenant 2 as user 5. Should work.
        $this->assertTrue(permission::can_view_inactive_users($tenant2id));

        $this->setUser($user6);
        // Viewing inactive users across the site as user 6. Should not work.
        $this->assertFalse(permission::can_view_all_inactive_users());
        // Viewing inactive users in tenant 1 as user 6. Should not work.
        $this->assertFalse(permission::can_view_inactive_users($tenant1id));
        // Viewing inactive users in tenant 2 as user 6. Should work.
        $this->assertTrue(permission::can_view_inactive_users($tenant2id));
    }

    /**
     * Test users with certain capabilities have access.
     */
    public function test_can_move_users_between_tenants() {
        $context = context_system::instance();
        // In the tenant with permission.
        $user1 = $this->getDataGenerator()->create_user();
        // In the tenant without permission.
        $user2 = $this->getDataGenerator()->create_user();
        // With permission but outside of the tenant.
        $user3 = $this->getDataGenerator()->create_user();
        $manager = new \tool_tenant\manager();
        $tenant1id = $this->generator->create_tenant()->id;
        $tenant2id = $this->generator->create_tenant()->id;

        $roleid = create_role('tenant organiser role', 'tenantorganiserrole', 'Role description');
        assign_capability('tool/tenant:allocate', CAP_ALLOW, $roleid, $context->id);

        $this->getDataGenerator()->role_assign($roleid, $user1->id);
        $this->getDataGenerator()->role_assign($roleid, $user3->id);

        $manager->allocate_user($user1->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user2->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->allocate_user($user3->id, $tenant2id, 'tool_tenant', 'testing');

        $this->setUser($user1);
        $this->assertTrue(permission::can_move_users_between_tenants());
        permission::require_can_move_users_between_tenants();

        $this->setUser($user2);
        $this->assertFalse(permission::can_move_users_between_tenants());
        try {
            permission::require_can_move_users_between_tenants();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Allocate users to all tenants).',
                $e->getMessage());
        }

        $this->setUser($user3);
        $this->assertTrue(permission::can_move_users_between_tenants());
    }

    public function test_require_messages() {
        try {
            permission::require_can_assign_tenant_admin(-1);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Manage the addition and editing of tenants).',
                $e->getMessage());
        }

        try {
            permission::require_can_edit_tenant(-1);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Manage the addition and editing of tenants).',
                $e->getMessage());
        }

        try {
            permission::require_can_edit_tenant_theme(-1);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Manage theme settings for the current tenant).',
                $e->getMessage());
        }

        try {
            permission::require_can_create_tenant();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Manage the addition and editing of tenants).',
                $e->getMessage());
        }

        try {
            permission::require_can_move_tenant(\tool_tenant\tenancy::get_default_tenant_id());
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Manage the addition and editing of tenants).',
                $e->getMessage());
        }

        try {
            permission::require_can_update_user(guest_user());
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Update user profiles).',
                $e->getMessage());
        }

        try {
            permission::require_can_view_tenants_list();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Manage the addition and editing of tenants).',
                $e->getMessage());
        }

        try {
            permission::require_can_delete_users();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Delete users).',
                $e->getMessage());
        }

        try {
            permission::require_can_delete_user(guest_user());
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Delete users).',
                $e->getMessage());
        }

        try {
            permission::require_can_suspend_user(guest_user());
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Update user profiles).',
                $e->getMessage());
        }

        try {
            permission::require_can_unsuspend_user(guest_user());
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Update user profiles).',
                $e->getMessage());
        }

        try {
            permission::require_can_archive_tenant(-1);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Manage the addition and editing of tenants).',
                $e->getMessage());
        }

        try {
            permission::require_can_delete_tenant(-1);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Manage the addition and editing of tenants).',
                $e->getMessage());
        }

        try {
            permission::require_can_restore_tenant(-1);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Sorry, but you do not currently have permissions to do that ' .
                '(Manage the addition and editing of tenants).',
                $e->getMessage());
        }
    }

    /**
     * Test whether a category can be moved around when
     * associated with a tenant.
     */
    public function test_can_change_category_parent() {
        $this->setAdminUser();
        $cat1 = $this->getDataGenerator()->create_category();
        $cat2 = $this->getDataGenerator()->create_category();
        $categorytomoveto = $this->getDataGenerator()->create_category();
        $tenant = $this->generator->create_tenant(['categoryid' => $cat1->id]);
        $this->assertEquals($cat1->id, $tenant->categoryid);
        $this->assertFalse(permission::can_change_category_parent($cat1->id));
        $this->assertFalse($cat1->can_change_parent($categorytomoveto));

        // Try the same on a category that is not associated with a tenant.
        $this->assertTrue(permission::can_change_category_parent($cat2->id));
        $this->assertTrue($cat2->can_change_parent($categorytomoveto));
    }

    /**
     * Test whether quota limit has been reached when adding users
     */
    public function test_check_quotas_to_add_users() {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$tenant1, $users1] = $this->generator->create_tenant_and_users(2);
        $this->assertTrue(permission::check_quotas_to_add_users($tenant1->id, 0));

        // Site limit settings.
        set_config('userlimitenabled', 1);
        set_config('userlimit', 7);

        $this->assertTrue(permission::check_quotas_to_add_users($tenant1->id, 1));

        set_config('userlimit', 3);
        $this->assertNotTrue(permission::check_quotas_to_add_users($tenant1->id, 1));

        set_config('userlimit', 0);
        set_config('userlimitenabled', 0);
        $this->assertTrue(permission::check_quotas_to_add_users($tenant1->id, 1));

        // Tenant limit settings.
        $this->assertTrue(permission::check_quotas_to_add_users($tenant1->id, 1));

        set_config('tool_tenant_userlimitenabled', 0);
        set_config('tool_tenant_userlimit', 0);
        $this->assertTrue(permission::check_quotas_to_add_users($tenant1->id, 1));

        set_config('tool_tenant_userlimitenabled', 1);
        set_config('tool_tenant_userlimit', 2);
        $this->assertNotTrue(permission::check_quotas_to_add_users($tenant1->id, 1));

        set_config('tool_tenant_userlimit', 3);
        $this->assertTrue(permission::check_quotas_to_add_users($tenant1->id, 1));

        set_config('userlimitenabled', 1);
        set_config('userlimit', 3);

        set_config('tool_tenant_userlimit', 7);
        $this->assertNotTrue(permission::check_quotas_to_add_users($tenant1->id, 1));
    }
}

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

/**
 * Tests for the tool_tenant\tenancy class methods.
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenancy_test extends advanced_testcase {
    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Tear down
     */
    protected function tearDown(): void {
        \tool_tenant\config::pop_all();
    }

    /**
     * Returns the tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test for funciton get_tenant_id()
     */
    public function test_single_tenant() {
        global $USER, $DB;
        $this->setAdminUser();

        $tenants = (new \tool_tenant\manager())->get_tenants();
        $tenant = reset($tenants);
        $this->assertEquals($tenant->get('id'), \tool_tenant\tenancy::get_tenant_id());
        $this->assertEquals($tenant->get('id'), \tool_tenant\tenancy::get_default_tenant_id());

        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('uu');
        $sql = 'SELECT uu.id FROM {user} uu ' . $join . ' WHERE ' . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, $params);
        $this->assertEquals([$USER->id], $users);
    }

    /**
     * Test for function get_users_sql()
     */
    public function test_get_users_sql() {
        global $DB, $USER;
        $this->setAdminUser();
        $adminuserid = $USER->id;

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenantid = $this->get_generator()->create_tenant()->id;
        // User1 is not specifically allocated (neither is admin).
        $user1 = $this->getDataGenerator()->create_user();
        // User2 is allocated to the default tenant.
        $user2 = $this->generator->create_user(['tenantid' => $defaulttenantid]);
        // User3 and user4 are allocated to the other tenant.
        $user3 = $this->generator->create_user(['tenantid' => $othertenantid]);
        $user4 = $this->generator->create_user(['tenantid' => $othertenantid]);

        // For default tenant.
        $ualias = \tool_wp\db::generate_alias();
        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql($ualias, $defaulttenantid);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} " . $join . ' WHERE ' . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, $params);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id], $users);

        // Double join produces no errors.
        list($join2, $where2, $params2) = \tool_tenant\tenancy::get_users_sql($ualias, $defaulttenantid);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} " . $join . $join2 .
                ' WHERE ' . $where . ' AND ' . $where2 . ' ORDER BY id';
        \tool_wp\db::validate_sql($sql);
        \tool_wp\db::validate_params($params + $params2);
        $users = $DB->get_fieldset_sql($sql, $params + $params2);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id], $users);

        // For other tenant.
        $ualias = \tool_wp\db::generate_alias();
        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql($ualias, $othertenantid);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} " . $join . ' WHERE ' . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, $params);
        $this->assertEquals([$user3->id, $user4->id], $users);

        // Double join produces no errors.
        list($join2, $where2, $params2) = \tool_tenant\tenancy::get_users_sql($ualias, $othertenantid);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} " . $join . $join2 .
                ' WHERE ' . $where . ' AND ' . $where2 . ' ORDER BY id';
        \tool_wp\db::validate_sql($sql);
        \tool_wp\db::validate_params($params + $params2);
        $users = $DB->get_fieldset_sql($sql, $params + $params2);
        $this->assertEquals([$user3->id, $user4->id], $users);

        // Archive one tenant and users that were allocated to it now appear in the default tenant.
        (new \tool_tenant\manager())->archive_tenant($othertenantid);
        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u', $defaulttenantid);
        $sql = 'SELECT u.id FROM {user} u ' . $join . ' WHERE ' . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, $params);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id, $user3->id, $user4->id], $users);

        // Restore it and users are back where they were.
        (new \tool_tenant\manager())->restore_tenant($othertenantid);
        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('u', $defaulttenantid);
        $sql = 'SELECT u.id FROM {user} u ' . $join . ' WHERE ' . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, $params);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id], $users);

        list($join, $where, $params) = \tool_tenant\tenancy::get_users_sql('uu', $othertenantid);
        $sql = 'SELECT uu.id FROM {user} uu ' . $join . ' WHERE ' . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, $params);
        $this->assertEquals([$user3->id, $user4->id], $users);
    }

    /**
     * Test for tenancy tenants_search_sql method
     */
    public function test_tenants_search_sql(): void {
        global $DB;

        $this->setAdminUser();

        $tenant1 = $this->get_generator()->create_tenant(['name' => 'Banana One']);
        $tenant2 = $this->get_generator()->create_tenant(['name' => 'Banana Two']);

        // Test without field alias.
        [$select, $params] = \tool_tenant\tenancy::tenants_search_sql('Banana', null);
        $tenants = $DB->get_fieldset_select('tool_tenant', 'id', $select, $params);
        $this->assertEqualsCanonicalizing([$tenant1->id, $tenant2->id], $tenants);

        // Test with field alias.
        [$select, $params] = \tool_tenant\tenancy::tenants_search_sql('Banana', 'ta');
        $tenants = $DB->get_fieldset_sql("SELECT ta.id FROM {tool_tenant} ta WHERE {$select}", $params);
        $this->assertEqualsCanonicalizing([$tenant1->id, $tenant2->id], $tenants);

        // Test search anywhere.
        [$select, $params] = \tool_tenant\tenancy::tenants_search_sql('One', null, true);
        $tenants = $DB->get_fieldset_select('tool_tenant', 'id', $select, $params);
        $this->assertEquals([$tenant1->id], $tenants);

        // Test search beginning of name.
        [$select, $params] = \tool_tenant\tenancy::tenants_search_sql('One', null, false);
        $this->assertFalse($DB->record_exists_select('tool_tenant', $select, $params));

        // Test excluding tenants.
        [$select, $params] = \tool_tenant\tenancy::tenants_search_sql('Banana', null, true, [], [$tenant1->id]);
        $tenants = $DB->get_fieldset_select('tool_tenant', 'id', $select, $params);
        $this->assertEquals([$tenant2->id], $tenants);
    }

    /**
     * Test for function get_tenant_id()
     */
    public function test_get_tenant_id() {
        global $DB, $USER;
        $this->setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenantid = $this->get_generator()->create_tenant()->id;
        // User1 is not specifically allocated (neither is admin).
        $user1 = $this->getDataGenerator()->create_user();
        // User2 is allocated to the default tenant.
        $user2 = $this->generator->create_user(['tenantid' => $defaulttenantid]);
        // User3 and user4 are allocated to the other tenant.
        $user3 = $this->generator->create_user(['tenantid' => $othertenantid]);
        $user4 = $this->generator->create_user(['tenantid' => $othertenantid]);

        // For user who is not allocated.
        $this->assertEquals($defaulttenantid, \tool_tenant\tenancy::get_tenant_id($user1->id));

        // For user who is allocated to the default tenant.
        $this->assertEquals($defaulttenantid, \tool_tenant\tenancy::get_tenant_id($user2->id));

        // For user who is allocated to the other tenant.
        $this->assertEquals($othertenantid, \tool_tenant\tenancy::get_tenant_id($user3->id));

        // Archive one tenant and users that were allocated to it now appear in the default tenant.
        (new \tool_tenant\manager())->archive_tenant($othertenantid);
        $this->assertEquals($defaulttenantid, \tool_tenant\tenancy::get_tenant_id($user3->id));
        $this->assertEquals($defaulttenantid, \tool_tenant\tenancy::get_tenant_id($user4->id));
        $this->assertEquals($defaulttenantid, \tool_tenant\tenancy::get_tenant_id($user2->id));

        // Restore it and users are back where they were.
        (new \tool_tenant\manager())->restore_tenant($othertenantid);
        $this->assertEquals($othertenantid, \tool_tenant\tenancy::get_tenant_id($user3->id));
        $this->assertEquals($othertenantid, \tool_tenant\tenancy::get_tenant_id($user4->id));
        $this->assertEquals($defaulttenantid, \tool_tenant\tenancy::get_tenant_id($user2->id));
    }

    /**
     * Test for function get_tenant_id()
     */
    public function test_get_tenant_id_bulk() {
        global $DB, $USER;
        $this->setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenantid = $this->get_generator()->create_tenant()->id;
        // User1 is not specifically allocated (neither is admin).
        $user1 = $this->getDataGenerator()->create_user();
        // User2 is allocated to the default tenant.
        $user2 = $this->generator->create_user(['tenantid' => $defaulttenantid]);
        // User3 and user4 are allocated to the other tenant.
        $user3 = $this->generator->create_user(['tenantid' => $othertenantid]);
        $user4 = $this->generator->create_user(['tenantid' => $othertenantid]);

        // Check the tenant id is correct for each user as returned by get_tenant_ids_bulk().
        $this->assertEqualsCanonicalizing([
            $user1->id => $defaulttenantid,
            $user2->id => $defaulttenantid,
            $user3->id => $othertenantid,
            $user4->id => $othertenantid,
        ],
            \tool_tenant\tenancy::get_tenant_ids_bulk([$user1->id, $user2->id, $user3->id, $user4->id, $user4->id + 1000]));

        // Archive one tenant and users that were allocated to it now appear in the default tenant.
        (new \tool_tenant\manager())->archive_tenant($othertenantid);
        $this->assertEqualsCanonicalizing([
            $user1->id => $defaulttenantid,
            $user2->id => $defaulttenantid,
            $user3->id => $defaulttenantid,
            $user4->id => $defaulttenantid,
        ],
            \tool_tenant\tenancy::get_tenant_ids_bulk([$user1->id, $user2->id, $user3->id, $user4->id, $user4->id + 1000]));

        // Restore it and users are back where they were.
        (new \tool_tenant\manager())->restore_tenant($othertenantid);
        $this->assertEqualsCanonicalizing([
            $user1->id => $defaulttenantid,
            $user2->id => $defaulttenantid,
            $user3->id => $othertenantid,
            $user4->id => $othertenantid,
        ],
            \tool_tenant\tenancy::get_tenant_ids_bulk([$user1->id, $user2->id, $user3->id, $user4->id, $user4->id + 1000]));
    }

    /**
     * Test for function get_users_sql() for the current user
     */
    public function test_get_tenant_id_self() {
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenantid = $this->get_generator()->create_tenant()->id;
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $this->assertEquals($defaulttenantid, \tool_tenant\tenancy::get_tenant_id());

        $this->get_generator()->allocate_user($user1->id, $othertenantid);
        $this->assertEquals($othertenantid, \tool_tenant\tenancy::get_tenant_id());
    }
    /**
     * Test for function get_actual_tenant() for a given user
     */
    public function test_get_actual_tenant_id() {
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $user1 = $this->getDataGenerator()->create_user();
        $this->assertEquals($defaulttenantid, \tool_tenant\tenancy::get_actual_tenant_id($user1->id));
        [$tenant1, [$tmanager1, $tenant1user1]] = $this->get_generator()->create_tenant_and_users(2);
        $tenant2 = $this->generator->create_tenant();
        $tmroleid = create_role('tenant manager role', 'tenantmanagerrole', 'Role description');
        assign_capability('tool/tenant:manage', CAP_ALLOW, $tmroleid, context_system::instance()->id);
        $this->getDataGenerator()->role_assign($tmroleid, $tmanager1->id);

        // Check the tenant name for normal tenant user and tenant manager.
        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_actual_tenant_id($tenant1user1->id));
        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_actual_tenant_id($tmanager1->id));

        // Switch tenant.
        \tool_tenant\tenancy::set_switched_tenant_id($tenant2->id);

        // Tenant id should not change even though we have switched.
        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_tenant_id($tmanager1->id));
    }
    /**
     * Test that the correct IDs are returned for the tenant admin users.
     */
    public function test_get_tenant_admins() {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $category = $this->getDataGenerator()->create_category();
        $manager = new \tool_tenant\manager();
        $tenantid = $this->generator->create_tenant((object) ['categoryid' => $category->id])->id;
        // Check that we have no admins at the moment.
        $this->assertEmpty($manager->get_tenant_admins($tenantid));
        // Assign admin roles to users 1 and 2.
        $manager->assign_tenant_admin_role($tenantid, [$user1->id, $user2->id]);
        $tenantadminids = $manager->get_tenant_admins($tenantid);
        $this->assertCount(2, $tenantadminids);
        $this->assertEquals([$user1->id => $user1->id, $user2->id => $user2->id], $tenantadminids);
        // Remove an admin and check again.
        $manager->assign_tenant_admin_role($tenantid, [$user1->id]);
        $tenantadminids = $manager->get_tenant_admins($tenantid);
        $this->assertCount(1, $tenantadminids);
        $this->assertEquals([$user1->id => $user1->id], $tenantadminids);
    }

    /**
     * Tests for checking that course is shared between tenants
     */
    public function test_is_shared_course() {
        $cat = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $cat->id]);

        // The site is not multitenant and the course is not shared.
        $this->assertFalse(\tool_tenant\tenancy::is_shared_course($course1));
        $this->assertFalse(\tool_tenant\tenancy::is_shared_course($course1, \tool_tenant\tenancy::get_default_tenant_id()));

        $tenantcat = $this->getDataGenerator()->create_category();
        $tenantid = $this->get_generator()->create_tenant(['categoryid' => $tenantcat->id])->id;

        // Now course is considered shared for any tenant.
        $this->assertTrue(\tool_tenant\tenancy::is_shared_course($course1));
        $this->assertTrue(\tool_tenant\tenancy::is_shared_course($course1, \tool_tenant\tenancy::get_default_tenant_id()));
        $this->assertTrue(\tool_tenant\tenancy::is_shared_course($course1, $tenantid));

        // Create a course inside tenant category.
        // It will not be shared for this tenant but will be considered shared for any other.
        $course2 = $this->getDataGenerator()->create_course(['category' => $tenantcat->id]);
        $this->assertTrue(\tool_tenant\tenancy::is_shared_course($course2, \tool_tenant\tenancy::get_default_tenant_id()));
        $this->assertFalse(\tool_tenant\tenancy::is_shared_course($course2, $tenantid));
    }

    /**
     * Tests for creating/retriving groups in the courses
     */
    public function test_get_course_group() {
        $course1 = $this->getDataGenerator()->create_course();
        $tenantid = $this->get_generator()->create_tenant()->id;

        // No tenant and no component -> no group.
        $gid0 = \tool_tenant\tenancy::get_course_group($course1, 'group0', null, null, null, null);
        $this->assertEquals(0, $gid0);
        $this->assertEmpty(groups_get_all_groups($course1->id));

        // Course group for a tenant.
        $gid1 = \tool_tenant\tenancy::get_course_group($course1, 'group1', $tenantid, null, null, null);
        $this->assertTrue(groups_group_exists($gid1));
        $groups = groups_get_all_groups($course1->id);
        $this->assertArrayHasKey($gid1, $groups);
        $this->assertEquals('group1', $groups[$gid1]->name);

        $gid2 = \tool_tenant\tenancy::get_course_group($course1, 'group1 new name', $tenantid, null, null, null);
        $this->assertEquals($gid1, $gid2);
        $groups = groups_get_all_groups($course1->id);
        $this->assertEquals('group1', $groups[$gid1]->name);

        // Course group for a component item.
        $gid3 = \tool_tenant\tenancy::get_course_group($course1, 'group3', null, 'tool_program', 'area', 101);
        $groups = groups_get_all_groups($course1->id);
        $this->assertArrayHasKey($gid3, $groups);
        $this->assertEquals('group3', $groups[$gid3]->name);
        $this->assertNotEquals($gid1, $gid3);

        $gid4 = \tool_tenant\tenancy::get_course_group($course1, 'group3 new name', null, 'tool_program', 'area', 101);
        $this->assertEquals($gid3, $gid4);
        $groups = groups_get_all_groups($course1->id);
        $this->assertEquals('group3', $groups[$gid3]->name);

        // Course group for tenant and component item.
        $gid5 = \tool_tenant\tenancy::get_course_group($course1, 'group5', $tenantid, 'tool_program', 'area', 101);
        $groups = groups_get_all_groups($course1->id);
        $this->assertArrayHasKey($gid5, $groups);
        $this->assertEquals('group5', $groups[$gid5]->name);
        $this->assertNotEquals($gid1, $gid5);
        $this->assertNotEquals($gid3, $gid5);

        $gid6 = \tool_tenant\tenancy::get_course_group($course1, 'group5 new name', $tenantid, 'tool_program', 'area', 101);
        $this->assertEquals($gid5, $gid6);
        $groups = groups_get_all_groups($course1->id);
        $this->assertEquals('group5', $groups[$gid5]->name);
    }

    /**
     * Make sure group associations are removed when tenant is deleted but the group stays.
     */
    public function test_delete_groups_for_tenant() {
        global $DB;
        $course1 = $this->getDataGenerator()->create_course();
        $tenantid = $this->get_generator()->create_tenant()->id;

        $this->assertEmpty($DB->get_records(\tool_tenant\tenant_group::TABLE, []));

        // Course group for a tenant.
        $gid1 = \tool_tenant\tenancy::get_course_group($course1, 'group1', $tenantid, null, null, null);
        $groups = groups_get_all_groups($course1->id);
        $this->assertArrayHasKey($gid1, $groups);
        $this->assertTrue(groups_group_exists($gid1));
        $this->assertNotEmpty($DB->get_records(\tool_tenant\tenant_group::TABLE, []));

        $manager = new \tool_tenant\manager();
        $manager->archive_tenant($tenantid);
        $manager->delete_tenant($tenantid);

        // There are no associations for the groups but the group is still present.
        $this->assertTrue(groups_group_exists($gid1));
        $this->assertEmpty($DB->get_records(\tool_tenant\tenant_group::TABLE, []));
    }

    /**
     * Test that group association is deleted when course is deleted.
     */
    public function test_delete_groups_for_course() {
        global $DB;
        $course1 = $this->getDataGenerator()->create_course();
        $tenantid = $this->get_generator()->create_tenant()->id;

        $this->assertEmpty($DB->get_records(\tool_tenant\tenant_group::TABLE, []));

        // Course group for a tenant.
        $gid1 = \tool_tenant\tenancy::get_course_group($course1, 'group1', $tenantid, null, null, null);
        $groups = groups_get_all_groups($course1->id);
        $this->assertArrayHasKey($gid1, $groups);
        $this->assertTrue(groups_group_exists($gid1));
        $this->assertNotEmpty($DB->get_records(\tool_tenant\tenant_group::TABLE, []));

        delete_course($course1->id, false);

        $this->assertEmpty($DB->get_records(\tool_tenant\tenant_group::TABLE, []));
    }

    /**
     * Test that group associations can be deleted for component
     */
    public function test_delete_groups_for_component() {
        global $DB;
        $course1 = $this->getDataGenerator()->create_course();
        $tenantid = $this->get_generator()->create_tenant()->id;

        // Course group for a component item.
        $gid3 = \tool_tenant\tenancy::get_course_group($course1, 'group3', null, 'tool_program', 'area', 101);
        $groups = groups_get_all_groups($course1->id);
        $this->assertArrayHasKey($gid3, $groups);
        $gid4 = \tool_tenant\tenancy::get_course_group($course1, 'group3', null, 'tool_program', 'area', 102);

        $groupids = $DB->get_fieldset_select(\tool_tenant\tenant_group::TABLE, 'groupid', '1=1', []);
        $this->assertEqualsCanonicalizing([$gid3, $gid4], $groupids);

        \tool_tenant\tenant_group::delete_for_component('tool_program', 'area', 101);

        $groupids = $DB->get_fieldset_select(\tool_tenant\tenant_group::TABLE, 'groupid', '1=1', []);
        $this->assertEquals([$gid4], $groupids);
    }
    /**
     * Test to check deletion of categories.
     *
     */
    public function test_event_delete_category() {
        $this->setAdminUser();
        $category0 = $this->getDataGenerator()->create_category();
        $this->assertTrue($category0->can_delete_full());
        $category1 = $this->getDataGenerator()->create_category();
        $tenant1 = $this->generator->create_tenant((object)['categoryid' => $category1->id]);
        $this->assertEquals($category1->id, $tenant1->categoryid);
        $this->assertNotTrue($category1->can_delete_full());
        $this->assertTrue($category0->can_delete_full());
    }
    /**
     * Test to check moving of category content.
     *
     */
    public function test_event_move_category() {
        $this->setAdminUser();
        $category0 = $this->getDataGenerator()->create_category();
        $category1 = $this->getDataGenerator()->create_category();
        $this->assertTrue($category0->can_move_content_to($category1->id));
        $category2 = $this->getDataGenerator()->create_category();
        $tenant1 = $this->generator->create_tenant((object)['categoryid' => $category2->id]);
        $this->assertEquals($category2->id, $tenant1->categoryid);
        $this->assertNotTrue($category2->can_move_content_to($category1->id));
        $this->assertTrue($category0->can_move_content_to($category1->id));

    }
    /**
     * Test to check that category associated with a tenant cannot be moved or deleted.
     */
    public function test_event_delete_move_category() {
        $manager = new \tool_tenant\manager();
        $category = $this->getDataGenerator()->create_category();
        $tenantid = $this->generator->create_tenant((object)['categoryid' => $category->id])->id;
        $tenant = \tool_tenant\tenancy::get_tenants()[$tenantid];
        $this->assertEquals($category->id, $tenant->categoryid);
        $this->assertNotTrue($category->can_delete_full());
        $category2 = $this->getDataGenerator()->create_category();
        $this->assertNotTrue($category->can_move_content_to($category2->id));
    }

    /**
     * Test for function get_users_sql()
     */
    public function test_get_users_subquery() {
        global $DB, $USER, $CFG;
        $this->setAdminUser();
        $adminuserid = $USER->id;

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenantid = $this->get_generator()->create_tenant()->id;
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        // User1 is not specifically allocated (neither is admin).
        // User2 is allocated to the default tenant.
        $this->get_generator()->allocate_user($user2->id, $defaulttenantid);
        // User3 and user4 are allocated to the other tenant.
        $this->get_generator()->allocate_user($user3->id, $othertenantid);
        $this->get_generator()->allocate_user($user4->id, $othertenantid);

        // For default tenant.
        $ualias = \tool_wp\db::generate_alias();
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id");
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} WHERE " . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id], $users);

        // Double usage produces no errors.
        $where2 = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id");
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} " .
            ' WHERE ' . $where . ' AND ' . $where2 . ' ORDER BY id';
        \tool_wp\db::validate_sql($sql);
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id], $users);

        // For other tenant.
        $ualias = \tool_wp\db::generate_alias();
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id", $othertenantid);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} WHERE $where ORDER BY id";
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$user3->id, $user4->id], $users);

        // Double usage produces no errors.
        $where2 = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id", $othertenantid);
        $sql = "SELECT {$ualias}.id FROM {user} {$ualias} " .
            ' WHERE ' . $where . ' AND ' . $where2 . ' ORDER BY id';
        \tool_wp\db::validate_sql($sql);
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$user3->id, $user4->id], $users);

        // Archive one tenant and users that were allocated to it now appear in the default tenant.
        (new \tool_tenant\manager())->archive_tenant($othertenantid);
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "{$ualias}.id");
        $sql = 'SELECT ' . $ualias . '.id FROM {user} ' . $ualias . ' WHERE ' . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id, $user3->id, $user4->id], $users);

        // Restore it and users are back where they were.
        (new \tool_tenant\manager())->restore_tenant($othertenantid);
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "u.id");
        $sql = 'SELECT u.id FROM {user} u WHERE ' . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id], $users);

        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "uu.id", $othertenantid);
        $sql = 'SELECT uu.id FROM {user} uu WHERE ' . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$user3->id, $user4->id], $users);

        // Admin user can "see all".
        $this->setAdminUser();
        $where = \tool_tenant\tenancy::get_users_subquery(true, false, "u.id");
        $sql = 'SELECT u.id FROM {user} u WHERE ' . $where . ' ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id, $user3->id, $user4->id], $users);

        // Testing with "andpostfix".
        $where = \tool_tenant\tenancy::get_users_subquery(true, true, "u.id");
        $sql = 'SELECT u.id FROM {user} u WHERE ' . $where . ' u.deleted=0 ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id, $user3->id, $user4->id], $users);

        $where = \tool_tenant\tenancy::get_users_subquery(false, true, "u.id");
        $sql = 'SELECT u.id FROM {user} u WHERE ' . $where . ' u.deleted=0 ORDER BY id';
        $users = $DB->get_fieldset_sql($sql, []);
        $this->assertEquals([$adminuserid, $user1->id, $user2->id], $users);
    }

    public function test_is_user_hidden_by_tenancy() {
        global $DB, $USER, $CFG;
        $this->setAdminUser();

        [$tenant1, $users1] = $this->get_generator()->create_tenant_and_users(4);
        [$tenant2, $users2] = $this->get_generator()->create_tenant_and_users(4);
        // User0 is not specifically allocated (neither is admin).
        $user0 = $this->getDataGenerator()->create_user();
        // User $manager can manage tenants.
        $manager = $this->getDataGenerator()->create_user();
        $tmroleid = create_role('tenant manager role', 'tenantmanagerrole', 'Role description');
        assign_capability('tool/tenant:manage', CAP_ALLOW, $tmroleid, context_system::instance()->id);
        $this->getDataGenerator()->role_assign($tmroleid, $manager->id);

        // User from tenant 1 can only see users from the same tenant.
        $this->setUser($users1[2]);
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($users1[3]->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($users2[3]->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user0->id));

        // User with permission to manage tenants can see everybody.
        $this->setUser($manager);
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($users1[3]->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($users2[3]->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user0->id));

        // Same but passing the second parameter.
        $this->setUser($user0);

        // User from tenant 1 can only see users from the same tenant.
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($users1[3]->id, $users1[2]->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($users2[3]->id, $users1[2]->id));
        $this->assertTrue(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user0->id, $users1[2]->id));

        // User with permission to manage tenants can see everybody.
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($users1[3]->id, $manager->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($users2[3]->id, $manager->id));
        $this->assertFalse(\tool_tenant\tenancy::is_user_hidden_by_tenancy($user0->id, $manager->id));

        // More tests in hierarchy_test.php.
    }

    /**
     * Test method for load_tenant_config_from_tenant_url
     */
    public function test_load_tenant_config_from_tenant_url() {
        global $CFG;
        $tenant1 = $this->generator->create_tenant(['idnumber' => 't1', 'useloginurlid' => 0, 'useloginurlidnumber' => 1]);
        $tenant2 = $this->generator->create_tenant(['useloginurlid' => 1, 'useloginurlidnumber' => 1]);
        $tenant3 = $this->generator->create_tenant(['useloginurlid' => 0, 'useloginurlidnumber' => 0]);

        \tool_tenant\config::set_config_tenant_override($tenant1->id, 'auth_instructions', 'Hello Tenant 1 !');
        \tool_tenant\config::set_config_tenant_override($tenant2->id, 'auth_instructions', 'Hello Tenant 2 !');
        \tool_tenant\config::set_config_tenant_override($tenant3->id, 'auth_instructions', 'Hello Tenant 3 !');

        $user = $this->getDataGenerator()->create_user();
        $this->generator->allocate_user($user->id, \tool_tenant\tenancy::get_default_tenant_id());
        self::setUser($user);

        // Sanity check.
        $this->assertEquals('', get_config('core', 'auth_instructions'));

        // Test using tenant id.
        $params = [
            'tenanturl' => $CFG->wwwroot . '/?tenantid=' . $tenant2->id,
        ];
        \tool_tenant\tenancy::load_tenant_config_from_tenant_url('tool_mobile_get_public_config', $params);
        $this->assertEquals('Hello Tenant 2 !', get_config('core', 'auth_instructions'));

        // Test using tenant idnumber.
        $params = [
            'tenanturl' => $CFG->wwwroot . '/?tenant=' . $tenant1->idnumber,
        ];
        \tool_tenant\tenancy::load_tenant_config_from_tenant_url('tool_mobile_get_public_config', $params);
        $this->assertEquals('Hello Tenant 1 !', get_config('core', 'auth_instructions'));

        // It should not be possible to use this login url from tenant3 and config would not be changed.
        $params = [
            'tenanturl' => $CFG->wwwroot . '/?tenantid=' . $tenant3->id,
        ];
        \tool_tenant\tenancy::load_tenant_config_from_tenant_url('tool_mobile_get_public_config', $params);
        $this->assertEquals('Hello Tenant 1 !', get_config('core', 'auth_instructions'));

        // Test using a wrong param name.
        $params = [
            'wrongparamname' => $CFG->wwwroot . '/?tenant=' . $tenant2->idnumber,
        ];
        \tool_tenant\tenancy::load_tenant_config_from_tenant_url('tool_mobile_get_public_config', $params);
        $this->assertEquals('Hello Tenant 1 !', get_config('core', 'auth_instructions'));
    }
}

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
 * File containing tests for tool_tenant\tenancy class.
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_tenant\tenancy class methods.
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_tenant_tenancy_testcase extends advanced_testcase {

    /**
     * Set up
     */
    protected function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Returns the tenant generator
     * @return tool_tenant_generator
     */
    protected function get_generator() : tool_tenant_generator {
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
     * Test for function get_users_sql()
     */
    public function test_get_tenant_id() {
        global $DB, $USER;
        $this->setAdminUser();

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
     * Test that the correct IDs are returned for the tenant admin users.
     */
    public function test_get_tenant_admins() {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $category = $this->getDataGenerator()->create_category();
        $manager = new \tool_tenant\manager();
        $tenant = $manager->create_tenant_quick();
        $tenant = $manager->update_tenant($tenant->get('id'), (object) ['categoryid' => $category->id]);
        // Check that we have no admins at the moment.
        $this->assertEmpty(\tool_tenant\tenancy::get_tenant_admins($tenant->get('id')));
        // Assign admin roles to users 1 and 2.
        $manager->assign_tenant_admin_role($tenant->get('id'), [$user1->id, $user2->id]);
        $tenantadminids = \tool_tenant\tenancy::get_tenant_admins($tenant->get('id'));
        $this->assertCount(2, $tenantadminids);
        $this->assertEquals([$user1->id => $user1->id, $user2->id => $user2->id], $tenantadminids);
        // Remove an admin and check again.
        $manager->assign_tenant_admin_role($tenant->get('id'), [$user1->id]);
        $tenantadminids = \tool_tenant\tenancy::get_tenant_admins($tenant->get('id'));
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
}

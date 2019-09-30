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
 * File containing tests for tool_tenant\manager class.
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_tenant\manager;

/**
 * Tests for the tool_tenant\manager class methods.
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_tenant_manager_testcase extends advanced_testcase {

    protected function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Test that tenant-related roles are created on installation
     */
    public function test_roles() {
        $allroles = get_all_roles();
        $roles = array_combine(array_keys($allroles), array_column($allroles, 'shortname'));
        $this->assertContains('tool_tenant_admin', $roles);
        $this->assertContains('tool_tenant_manager', $roles);
        $this->assertContains('tool_tenant_user', $roles);

        $fliproles = array_flip($roles);
        $user = $this->getDataGenerator()->create_user();
        $coursecat = $this->getDataGenerator()->create_category();
        $context = context_coursecat::instance($coursecat->id);
        role_assign($fliproles['tool_tenant_manager'], $user->id, $context->id);
        $this->setUser($user);
        // Tenant manager can assign coursecreator and tenant manager roles in the category context.
        $assignableroles = get_assignable_roles($context);
        $this->assertEquals([$fliproles['coursecreator'], $fliproles['tool_tenant_manager']],
            array_keys($assignableroles), '', 0, 10, true);
    }

    /**
     * Make sure by default at least one tenant is created
     */
    public function test_default_tenant() {
        global $DB;
        $manager = new \tool_tenant\manager();
        $tenants = $manager->get_tenants();
        $this->assertEquals(1, count($tenants));
        $this->assertEquals(1, $DB->count_records('tool_tenant'));
        $tenant = reset($tenants);
        $this->assertEquals(get_string('defaultname', 'tool_tenant'), $tenant->get('name'));
        $this->assertEquals(0, $tenant->get('archived'));
    }

    /**
     * Create tenant
     */
    public function test_create_tenant() {

        $manager = new \tool_tenant\manager();
        $tenants = $manager->get_tenants();
        $this->assertEquals(1, count($tenants));

        $manager->create_tenant_quick();
        $tenants = $manager->get_tenants();
        $this->assertEquals(2, count($tenants));

        $manager->create_tenant_quick();
        $tenants = $manager->get_tenants();
        $this->assertEquals(3, count($tenants));

        $tenant = reset($tenants);
        $this->assertEquals(get_string('defaultname', 'tool_tenant'), $tenant->get('name'));
        $this->assertEquals(0, $tenant->get('archived'));
        $this->assertEquals(0, $tenant->get('sortorder'));

        $tenant = next($tenants);
        $this->assertEquals(get_string('newname', 'tool_tenant', 1), $tenant->get('name'));
        $this->assertEquals(0, $tenant->get('archived'));
        $this->assertEquals(1, $tenant->get('sortorder'));

        $tenant = next($tenants);
        $this->assertEquals(get_string('newname', 'tool_tenant', 2), $tenant->get('name'));
        $this->assertEquals(0, $tenant->get('archived'));
        $this->assertEquals(2, $tenant->get('sortorder'));
    }

    /**
     * Update tenant
     */
    public function test_update_tenant() {
        $manager = new \tool_tenant\manager();
        $tenants = $manager->get_tenants();
        $tenantid = key($tenants);

        $manager->update_tenant($tenantid, (object)['name' => 'Some name']);

        $this->assertEquals('Some name', $manager->get_tenant($tenantid)->get('name'));

        // Try with new instance of manager.
        $this->assertEquals('Some name', (new \tool_tenant\manager())->get_tenant($tenantid)->get('name'));
    }

    /**
     * Archive tenant and restore tenant
     */
    public function test_archive_tenant() {
        global $DB;

        $manager = new \tool_tenant\manager();
        $this->assertEquals(1, count($manager->get_tenants()));
        $tenant = $manager->create_tenant_quick();
        $tenantid = $tenant->get('id');
        $this->assertEquals(2, count($manager->get_tenants()));
        $this->assertEquals(2, $DB->count_records('tool_tenant'));

        $archivedtenant = $manager->archive_tenant($tenantid);
        $this->assertEquals(1, $archivedtenant->get('archived'));
        $this->assertEquals(1, count($manager->get_tenants()));
        $this->assertEquals(2, $DB->count_records('tool_tenant')); // Not deleted from DB.
        $this->assertEquals(1, count((new \tool_tenant\manager())->get_tenants()));

        try {
            (new \tool_tenant\manager())->get_tenant($tenantid);
            $this->fail('Expected exception');
        } catch (moodle_exception $e) {
            $this->assertEquals('Tenant not found', $e->getMessage());
        }

        // The tenant can still be retrieved.
        $archivedtenant = new \tool_tenant\tenant($tenantid);
        $this->assertEquals(1, $archivedtenant->get('archived'));

        // The tenant can be restored.
        $manager = new \tool_tenant\manager();
        $manager->restore_tenant($tenantid);
        $this->assertEquals(2, count($manager->get_tenants()));
        $this->assertEquals(2, $DB->count_records('tool_tenant'));
        $this->assertEquals(0, $manager->get_tenant($tenantid)->get('archived'));
        // And once more to be sure, without cache.
        $manager = new \tool_tenant\manager();
        $this->assertEquals(2, count($manager->get_tenants()));
        $this->assertEquals(0, $manager->get_tenant($tenantid)->get('archived'));
    }

    /**
     * Archive tenant and delete archived tenant
     */
    public function test_delete_tenant() {
        $manager = new \tool_tenant\manager();
        $tenant = $manager->create_tenant_quick();
        $this->assertEquals(2, count($manager->get_tenants()));
        $this->assertEquals(0, count($manager->get_archived_tenants()));
        $tenantid = $tenant->get('id');

        // Can not delete tenant that is not archived.
        try {
            $manager->delete_tenant($tenantid);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('Tenant not found', $e->getMessage());
        }

        // Archive tenant and then delete it.
        $manager->archive_tenant($tenantid);
        $this->assertEquals(1, count($manager->get_tenants()));
        $this->assertEquals(1, count($manager->get_archived_tenants()));

        $manager->delete_tenant($tenantid);
        $this->assertEquals(1, count($manager->get_tenants()));
        $this->assertEquals(0, count($manager->get_archived_tenants()));
        // And once more to be sure, without cache.
        $manager = new \tool_tenant\manager();
        $this->assertEquals(1, count($manager->get_tenants()));
        $this->assertEquals(0, count($manager->get_archived_tenants()));
    }

    /**
     * Asserts that the order of the tenants is exactly in the $idlist and their sortorder
     * attributes are consequtive numbers 0,1,2,3,...
     *
     * @param array $idlist
     */
    protected function assert_sortorders(array $idlist) {
        $tenants = (new \tool_tenant\manager())->get_tenants();
        $this->assertEquals($idlist, array_keys($tenants));
        foreach ($idlist as $sortorder => $id) {
            $this->assertEquals($sortorder, $tenants[$id]->get('sortorder'));
        }
    }

    /**
     * Change sortorder
     */
    public function test_change_sortorder() {
        $manager = new \tool_tenant\manager();
        $manager->create_tenant_quick();
        $manager->create_tenant_quick();
        $manager->create_tenant_quick();
        $manager->create_tenant_quick();
        list ($t1, $t2, $t3, $t4, $t5) = array_keys($manager->get_tenants());
        $this->assert_sortorders([$t1, $t2, $t3, $t4, $t5]);

        // The first tenant is "default" and others are not.
        $this->assertEquals(1, $manager->get_tenant($t1)->get('isdefault'));
        $this->assertEquals(0, $manager->get_tenant($t2)->get('isdefault'));

        // Use new instance of manager every time.

        // Move from the middle to the middle.
        (new \tool_tenant\manager())->change_sortorder($t4, $t2);
        $this->assert_sortorders([$t1, $t4, $t2, $t3, $t5]);
        // Move from the middle to the start, the default tenant stays on top.
        (new \tool_tenant\manager())->change_sortorder($t3, $t1);
        $this->assert_sortorders([$t1, $t3, $t4, $t2, $t5]);
        // Move from the middle to the end.
        (new \tool_tenant\manager())->change_sortorder($t4, 0);
        $this->assert_sortorders([$t1, $t3, $t2, $t5, $t4]);
        // Move from the start to the middle - default tenant is not moved.
        (new \tool_tenant\manager())->change_sortorder($t1, $t2);
        $this->assert_sortorders([$t1, $t3, $t2, $t5, $t4]);
        // Move from the end to the middle.
        (new \tool_tenant\manager())->change_sortorder($t4, $t2);
        $this->assert_sortorders([$t1, $t3, $t4, $t2, $t5]);
        // Move from the end to the start.
        (new \tool_tenant\manager())->change_sortorder($t5, $t1);
        $this->assert_sortorders([$t1, $t5, $t3, $t4, $t2]);

        // Delete one tenant and move two others.
        (new \tool_tenant\manager())->archive_tenant($t3);
        // Sortorder attributes are now not consequtive but the order is correct.
        $this->assertEquals([$t1, $t5, $t4, $t2], array_keys((new \tool_tenant\manager())->get_tenants()));
        // Move one tenant, sort order should become consequtive again.
        (new \tool_tenant\manager())->change_sortorder($t4, $t5);
        $this->assert_sortorders([$t1, $t4, $t5, $t2]);
    }

    /**
     * Test for function tool_tenant_inplace_editable()
     */
    public function test_tool_tenant_inplace_editable() {
        global $PAGE;
        $this->setAdminUser(); // Method checks capabilities.
        $tenant = (new \tool_tenant\manager())->create_tenant_quick();
        $tenantid = $tenant->get('id');

        $result = component_callback('tool_tenant', 'inplace_editable',
            ['tenant_name', $tenantid, 'New name']);
        $this->assertTrue($result instanceof \core\output\inplace_editable);
        $template = $result->export_for_template($PAGE->get_renderer('core'));
        $this->assertEquals('New name', $template['value']);

        $this->assertEquals('New name', (new \tool_tenant\manager())->get_tenant($tenantid)->get('name'));
    }

    /**
     * Test that only one category can belong to one tenant.
     */
    public function test_can_change_category() {
        $manager = new \tool_tenant\manager();
        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();
        $category3 = $this->getDataGenerator()->create_category();
        // Create tenants.
        $tenant1 = (object) [
            'name' => 'tenant 1',
            'sitename' => 'Site name for tenant 1',
            'idnumber' => '001',
            'categoryid' => $category1->id
        ];
        $fulltenant1 = $manager->create_tenant($tenant1);
        $tenant2 = (object) [
            'name' => 'tenant 1',
            'sitename' => 'Site name for tenant 1',
            'idnumber' => '001',
            'categoryid' => $category2->id
        ];
        $fulltenant2 = $manager->create_tenant($tenant2);
        // Check that changing to 0 / null is fine.
        $this->assertTrue(\tool_tenant\manager::can_change_category($fulltenant1->get('id'), 0));
        // Check that changing to the second tenants category returns false.
        $this->assertFalse(\tool_tenant\manager::can_change_category($fulltenant1->get('id'), $category2->id));
        // Check that changing to an unused category is fine and returns true.
        $this->assertTrue(\tool_tenant\manager::can_change_category($fulltenant1->get('id'), $category3->id));
    }

    public function test_assign_tenant_user_role() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();
        $category3 = $this->getDataGenerator()->create_category();

        $manager = new \tool_tenant\manager();
        $tenant1 = $manager->create_tenant_quick();
        $tenant1 = $manager->update_tenant($tenant1->get('id'), (object) ['categoryid' => $category1->id]);
        $tenant2 = $manager->create_tenant_quick();
        $tenant2 = $manager->update_tenant($tenant2->get('id'), (object) ['categoryid' => $category3->id]);

        $manager->assign_tenant_user_role($user1->id, $tenant1->get('id'));
        // User should now be assigned the tenant user role.

        $roles = $DB->get_records_menu('role', [], 'sortorder', 'shortname, id');

        $data = $DB->get_records('role_assignments');
        $record = current($data);
        // Current role assignment check.
        $context = \context::instance_by_id($record->contextid);
        $this->assertEquals($roles['tool_tenant_user'], $record->roleid);
        $this->assertEquals($category1->id, $context->instanceid);
        $this->assertEquals($user1->id, $record->userid);

        // Change the category of the tenant to a different one.
        $tenant1 = $manager->update_tenant($tenant1->get('id'), (object) ['categoryid' => $category2->id]);
        $manager->assign_tenant_user_role($user1->id, $tenant1->get('id'), $category2->id);

        $data = $DB->get_records('role_assignments');
        $record = current($data);
        // Check that the user now has the role with the new context.
        $context = \context::instance_by_id($record->contextid);
        $this->assertEquals($roles['tool_tenant_user'], $record->roleid);
        $this->assertEquals($category2->id, $context->instanceid);
        $this->assertEquals($user1->id, $record->userid);

        // Let's allocate the user to another tenant.
        $manager->allocate_user($user1->id, $tenant2->get('id'), 'tool_tenant', 'testing');

        $data = $DB->get_records('role_assignments');
        $record = current($data);
        // Check that the user has the role with the new tenant.
        $context = \context::instance_by_id($record->contextid);
        $this->assertEquals($roles['tool_tenant_user'], $record->roleid);
        $this->assertEquals($category3->id, $context->instanceid);
        $this->assertEquals($user1->id, $record->userid);
    }

    /**
     * Test assigning users the tenant admin role.
     */
    public function test_assign_tenant_admin_role() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $category = $this->getDataGenerator()->create_category();

        $manager = new \tool_tenant\manager();
        $tenant1 = $manager->create_tenant_quick();
        $tenant1 = $manager->update_tenant($tenant1->get('id'), (object) ['categoryid' => $category->id]);

        $manager->allocate_user($user1->id, $tenant1->get('id'), 'tool_tenant', 'testing');
        $manager->assign_tenant_admin_role($tenant1->get('id'), [$user1->id, $user2->id]);

        $records = $DB->get_records('role_assignments', ['userid' => $user1->id]);
        // This user should have three roles: tenantadmin, tenantmanager, and tenantuser.
        $this->assertCount(3, $records);

        $systemroles = $DB->get_records_menu('role', [], 'sortorder', 'shortname, id');
        // Check that user 1 has both tenantadmin and tenantmanager roles.
        $record = $DB->get_records('role_assignments', ['userid' => $user1->id, 'roleid' => $systemroles['tool_tenant_admin']]);
        $this->assertCount(1, $record);
        $record = $DB->get_records('role_assignments', ['userid' => $user1->id, 'roleid' => $systemroles['tool_tenant_manager']]);
        $this->assertCount(1, $record);

        // Check that we have two tenant admins.
        $tenantadmins = \tool_tenant\tenancy::get_tenant_admins($tenant1->get('id'));
        $this->assertEquals([$user1->id => $user1->id, $user2->id => $user2->id], $tenantadmins);

        // Let's remove user 2 and add user 3 as tenant admins.
        $manager->assign_tenant_admin_role($tenant1->get('id'), [$user1->id, $user3->id]);
        $tenantadmins = \tool_tenant\tenancy::get_tenant_admins($tenant1->get('id'));
        $this->assertEquals([$user1->id => $user1->id, $user3->id => $user3->id], $tenantadmins);

        // Now Let's remove all of the users as admins.
        $manager->assign_tenant_admin_role($tenant1->get('id'), []);
        $tenantadmins = \tool_tenant\tenancy::get_tenant_admins($tenant1->get('id'));
        $this->assertEquals([], $tenantadmins);
    }

    /**
     * Test that the css for the logos is returned.
     */
    public function test_get_logo_css() {
        global $CFG;

        $manager = new manager();
        $tenant = $manager->create_tenant_quick();

        // Need to set a user to save files.
        $this->setAdminuser();

        $context = context_system::instance();
        $filepath = $CFG->dirroot.'/lib/filestorage/tests/fixtures/testimage.jpg';
        $fs = get_file_storage();
        $expectedresult = '';

        foreach (['headerlogo', 'loginlogo', 'loginbackground'] as $logoarea) {
            $filerecord = array(
                'contextid' => $context->id,
                'component' => 'tool_tenant',
                'filearea'  => $logoarea,
                'itemid'    => $tenant->get('id'),
                'filepath'  => '/',
                'filename'  => 'testimage.jpg',
            );

            $file = $fs->create_file_from_pathname($filerecord, $filepath);

            $expectedresult .= '$' . $logoarea . ': "https://www.example.com/moodle/pluginfile.php/' . $context->id
                    . '/' . $file->get_component()
                    . '/' . $file->get_filearea()
                    . '/' . $file->get_itemid()
                    . '/' . $file->get_filename() . '";';

        }
        $data = $manager->get_logo_css($tenant->get('id'));
        $this->assertEquals($expectedresult, $data);
    }
}

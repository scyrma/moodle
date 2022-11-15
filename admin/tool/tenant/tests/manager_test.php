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
use context_coursecat;
use context_system;
use core_component;
use core_user_external;
use external_api;
use tool_tenant_generator;
use stdClass;
use moodle_exception;

/**
 * Tests for the tool_tenant\manager class methods.
 *
 * @package    tool_tenant
 * @covers     \tool_tenant\manager
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager_test extends advanced_testcase {

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
        // Tenant manager can assign coursecreator role in the category context.
        $assignableroles = get_assignable_roles($context);
        $this->assertEqualsCanonicalizing([$fliproles['coursecreator']],
            array_keys($assignableroles));
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
        $this->assertFalse($tenant->get('archived'));
        $this->assertEquals('/' . $tenant->get('id'), $tenant->get('path'));
        $this->assertEquals(1, $tenant->get('depth'));
    }

    /**
     * Create tenant
     */
    public function test_create_tenant() {

        $manager = new \tool_tenant\manager();
        $tenants = $manager->get_tenants();
        $this->assertEquals(1, count($tenants));

        $defaulttenant = reset($tenants);
        $css = '{"primary":"#04DC9B","brand":"#04DC9C","button":"#04DC9A","drawer":"#04DC9D",' .
            '"footer":"#04DC9E","customcss":"","footertext":"Hello, is it me you\'re looking for?"}';
        $defaulttenant->set('cssconfig', $css);
        $defaulttenant->update();

        $this->generator->create_tenant(['name' => "New tenant '1'"]);
        $tenants = $manager->get_tenants();
        $this->assertEquals(2, count($tenants));

        $this->generator->create_tenant(['name' => "New tenant '2'"]);
        $tenants = $manager->get_tenants();
        $this->assertEquals(3, count($tenants));

        $tenant = reset($tenants);
        $this->assertEquals(get_string('defaultname', 'tool_tenant'), $tenant->get('name'));
        $this->assertFalse($tenant->get('archived'));
        $this->assertEquals(0, $tenant->get('sortorder'));

        $tenant = next($tenants);
        $this->assertEquals(get_string('newname', 'tool_tenant', 1), $tenant->get('name'));
        $this->assertFalse($tenant->get('archived'));
        $this->assertEquals(1, $tenant->get('sortorder'));

        // Assert CSS config was copied from Default tenant.
        $this->assertEquals($css, $tenant->get('cssconfig'));

        // Check that images were copied from Default tenant.
        $fs = get_file_storage();

        $files = $fs->get_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo',
            $tenant->get('id'), '', false);
        $fileheaderlogo = reset($files);
        $this->assertEquals('workplacelogo.png', $fileheaderlogo->get_filename());

        $files = $fs->get_area_files(context_system::instance()->id, 'tool_tenant', 'loginlogo',
            $tenant->get('id'), '', false);
        $fileloginlogo = reset($files);
        $this->assertEquals('workplacelogo.png', $fileloginlogo->get_filename());

        $files = $fs->get_area_files(context_system::instance()->id, 'tool_tenant', 'tenantselectorlogo',
            $tenant->get('id'), '', false);
        $filetenantselectorlogo = reset($files);
        $this->assertEquals('workplacelogo.png', $filetenantselectorlogo->get_filename());

        $files = $fs->get_area_files(context_system::instance()->id, 'tool_tenant', 'loginbackground',
            $tenant->get('id'), '', false);
        $fileloginbackground = reset($files);
        $this->assertEquals('login-image.png', $fileloginbackground->get_filename());

        $tenant = next($tenants);
        $this->assertEquals(get_string('newname', 'tool_tenant', 2), $tenant->get('name'));
        $this->assertFalse($tenant->get('archived'));
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
        $tenantid = $this->generator->create_tenant()->id;
        $this->assertEquals(2, count($manager->get_tenants()));
        $this->assertEquals(2, $DB->count_records('tool_tenant'));

        $archivedtenant = $manager->archive_tenant($tenantid);
        $this->assertTrue($archivedtenant->get('archived'));
        $this->assertEquals(1, count($manager->get_tenants()));
        $this->assertEquals(2, $DB->count_records('tool_tenant')); // Not deleted from DB.
        $this->assertEquals(1, count((new \tool_tenant\manager())->get_tenants()));

        try {
            (new \tool_tenant\manager())->get_tenant($tenantid);
            $this->fail('Expected exception');
        } catch (moodle_exception $e) {
            $this->assertEquals('Tenant not found', $e->getMessage());
        }

        // Default tenant can not be archived.
        try {
            (new \tool_tenant\manager())->archive_tenant(\tool_tenant\tenancy::get_default_tenant_id());
            $this->fail('Expected exception');
        } catch (moodle_exception $e) {
            $this->assertEquals('Cannot archive the default tenant.', $e->getMessage());
        }

        // The tenant can still be retrieved.
        $archivedtenant = new \tool_tenant\tenant($tenantid);
        $this->assertTrue($archivedtenant->get('archived'));

        // The tenant can be restored.
        $manager = new \tool_tenant\manager();
        $manager->restore_tenant($tenantid);
        $this->assertEquals(2, count($manager->get_tenants()));
        $this->assertEquals(2, $DB->count_records('tool_tenant'));
        $this->assertFalse($manager->get_tenant($tenantid)->get('archived'));
        // And once more to be sure, without cache.
        $manager = new \tool_tenant\manager();
        $this->assertEquals(2, count($manager->get_tenants()));
        $this->assertFalse($manager->get_tenant($tenantid)->get('archived'));
    }

    /**
     * Archive tenant and delete archived tenant
     */
    public function test_delete_tenant() {
        global $DB;
        $manager = new \tool_tenant\manager();
        $tenantid = $this->generator->create_tenant()->id;
        $this->assertEquals(2, count($manager->get_tenants()));
        $this->assertEquals(0, count($manager->get_archived_tenants()));

        // Make sure that the images were created.
        $this->assertNotEquals(0, $DB->count_records('files', ['component' => 'tool_tenant', 'itemid' => $tenantid]));

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
        $this->assertEquals(0, $DB->count_records('files', ['component' => 'tool_tenant', 'itemid' => $tenantid]));
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
        $this->generator->create_tenant();
        $this->generator->create_tenant();
        $this->generator->create_tenant();
        $this->generator->create_tenant();
        list ($t1, $t2, $t3, $t4, $t5) = array_keys($manager->get_tenants());
        $this->assert_sortorders([$t1, $t2, $t3, $t4, $t5]);

        // The first tenant is "default" and others are not.
        $this->assertTrue($manager->get_tenant($t1)->get('isdefault'));
        $this->assertFalse($manager->get_tenant($t2)->get('isdefault'));

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
        $tenantid = $this->generator->create_tenant()->id;

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
        $fulltenant1 = $this->generator->create_tenant($tenant1);
        $tenant2 = (object) [
            'name' => 'tenant 1',
            'sitename' => 'Site name for tenant 1',
            'idnumber' => '001',
            'categoryid' => $category2->id
        ];
        $fulltenant2 = $this->generator->create_tenant($tenant2);
        // Check that changing to 0 / null is fine.
        $this->assertTrue(\tool_tenant\manager::can_change_category($fulltenant1->id, 0));
        // Check that changing to the second tenants category returns false.
        $this->assertFalse(\tool_tenant\manager::can_change_category($fulltenant1->id, $category2->id));
        // Check that changing to an unused category is fine and returns true.
        $this->assertTrue(\tool_tenant\manager::can_change_category($fulltenant1->id, $category3->id));
    }

    public function test_assign_tenant_user_role() {
        global $DB;

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();
        $category3 = $this->getDataGenerator()->create_category();

        $manager = new \tool_tenant\manager();
        $tenant1id = $this->generator->create_tenant((object) ['categoryid' => $category1->id])->id;
        $tenant2id = $this->generator->create_tenant((object) ['categoryid' => $category3->id])->id;

        $user1 = $this->generator->create_user(['tenantid' => $tenant1id]);
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
        $manager->update_tenant($tenant1id, (object) ['categoryid' => $category2->id]);

        $data = $DB->get_records('role_assignments');
        $record = current($data);
        // Check that the user now has the role with the new context.
        $context = \context::instance_by_id($record->contextid);
        $this->assertEquals($roles['tool_tenant_user'], $record->roleid);
        $this->assertEquals($category2->id, $context->instanceid);
        $this->assertEquals($user1->id, $record->userid);

        // Let's allocate the user to another tenant.
        $manager->allocate_user($user1->id, $tenant2id, 'tool_tenant', 'testing');

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
        $tenant1id = $this->generator->create_tenant((object) ['categoryid' => $category->id])->id;

        $manager->allocate_user($user1->id, $tenant1id, 'tool_tenant', 'testing');
        $manager->assign_tenant_admin_role($tenant1id, [$user1->id, $user2->id]);

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
        $tenantadmins = $manager->get_tenant_admins($tenant1id);
        $this->assertEquals([$user1->id => $user1->id, $user2->id => $user2->id], $tenantadmins);

        // Let's remove user 2 and add user 3 as tenant admins.
        $manager->assign_tenant_admin_role($tenant1id, [$user1->id, $user3->id]);
        $tenantadmins = $manager->get_tenant_admins($tenant1id);
        $this->assertEquals([$user1->id => $user1->id, $user3->id => $user3->id], $tenantadmins);

        // Now Let's remove all of the users as admins.
        $manager->assign_tenant_admin_role($tenant1id, []);
        $tenantadmins = $manager->get_tenant_admins($tenant1id);
        $this->assertEquals([], $tenantadmins);
    }

    /**
     * Test that the css for the logos is returned.
     */
    public function test_get_logo_css() {
        $manager = new manager();
        $tenantid = $this->generator->create_tenant()->id;

        $url = 'https://www.example.com/moodle/pluginfile.php/1/tool_tenant';
        $expectedresult =
            '$headerlogo: "'. $url . '/headerlogo/' . $tenantid . '/workplacelogo.png";' .
            '$loginlogo: "'. $url . '/loginlogo/'.$tenantid.'/workplacelogo.png";' .
            '$tenantselectorlogo: "'. $url . '/tenantselectorlogo/'.$tenantid.'/workplacelogo.png";' .
            '$loginbackground: "'. $url . '/loginbackground/'.$tenantid.'/login-image.png";';

        $data = $manager->get_logo_css($tenantid);
        $this->assertEquals($expectedresult, $data);
    }

    /**
     * Test for function add_plugin_capabilities_to_tenant_admin_role()
     */
    public function test_add_plugin_capabilities_to_tenant_admin_role() {
        if (!core_component::get_component_directory('tool_organisation')) {
            $this->markTestSkipped();
        }
        $this->resetAfterTest();

        $adminrole = (object)['id' => manager::get_tenant_admin_role()];

        // Capability assignjobs is present in admin role because it was added during installation.
        $caps = array_column(get_capabilities_from_role_on_context($adminrole, context_system::instance()), 'capability');
        $this->assertTrue(in_array('tool/organisation:assignjobs', $caps));

        // Remove capability assignjobs form the tenant admin role.
        unassign_capability('tool/organisation:assignjobs', $adminrole->id);

        $caps = array_column(get_capabilities_from_role_on_context($adminrole, context_system::instance()), 'capability');
        $this->assertFalse(in_array('tool/organisation:assignjobs', $caps));

        // Imagine we are installing plugin (or running upgrade) and want to add all default capabilities for tool_organisation.
        \tool_tenant\tenancy::add_plugin_capabilities_to_tenant_admin_role('tool_organisation');

        // Now capability assignjobs is present in the tenantadmin role again.
        $caps = array_column(get_capabilities_from_role_on_context($adminrole, context_system::instance()), 'capability');
        $this->assertTrue(in_array('tool/organisation:assignjobs', $caps));
    }

    /**
     * Test that events are triggered in the expected order
     *
     * Note we don't use event sink here, we analyse log instead. Sinker intercepts the observers.
     *
     * Test 1. User is created inside default tenant. The user_created event is triggered.
     */
    public function test_events_sequence_create_user_in_default_tenant() {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Logging waits till the transaction gets committed.
        // Enable logging plugin.
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        set_config('logguests', 1, 'logstore_standard');
        $manager = get_log_manager(true);

        $this->setAdminUser();

        $tenant = $this->generator->create_tenant([]);

        // When user is created inside default tenant, only the user_created event is triggered.
        $maxlogid = $DB->get_field_sql('SELECT max(id) FROM {logstore_standard_log}');
        $user1 = $this->generator->create_user([]);
        $this->assertEquals(\tool_tenant\tenancy::get_default_tenant_id(), \tool_tenant\tenancy::get_tenant_id($user1->id));
        $logs = array_values($DB->get_records_select('logstore_standard_log', 'id>?', [$maxlogid], 'id'));

        $this->assertEquals(2, count($logs));
        $this->assertEquals('\\' . \core\event\user_created::class, $logs[0]->eventname);
        $this->assertEquals('\\' . \tool_tenant\event\tenant_user_created::class, $logs[1]->eventname);
        $this->assertEquals($user1->id, $logs[0]->relateduserid);

        // User is moved to another tenant. The tenant_user_updated is triggered.
        $maxlogid = $DB->get_field_sql('SELECT max(id) FROM {logstore_standard_log}');
        $this->generator->allocate_user($user1->id, $tenant->id);
        $logs = array_values($DB->get_records_select('logstore_standard_log', 'id>?', [$maxlogid], 'id'));
        $this->assertEquals($tenant->id, \tool_tenant\tenancy::get_tenant_id($user1->id));

        $this->assertEquals(1, count($logs));
        $this->assertEquals('\\' . \tool_tenant\event\tenant_user_updated::class, $logs[0]->eventname);
        $this->assertEqualsCanonicalizing(
            ['tenantid' => $tenant->id, 'oldtenantid' => \tool_tenant\tenancy::get_default_tenant_id()],
            json_decode($logs[0]->other, true));
        $this->assertEquals($user1->id, $logs[0]->relateduserid);
    }

    /**
     * Test that events are triggered in the expected order
     *
     * Note we don't use event sink here, we analyse log instead. Sinker intercepts the observers.
     *
     * Test 2. User is created inside another tenant. The user_created AND tenant_user_created events are triggered.
     */
    public function test_events_sequence_create_user_in_another_tenant() {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Logging waits till the transaction gets committed.
        // Enable logging plugin.
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        set_config('logguests', 1, 'logstore_standard');
        $manager = get_log_manager(true);

        $this->setAdminUser();

        $tenant = $this->generator->create_tenant([]);

        $maxlogid = $DB->get_field_sql('SELECT max(id) FROM {logstore_standard_log}');
        $user1 = $this->generator->create_user(['tenantid' => $tenant->id]);
        $logs = array_values($DB->get_records_select('logstore_standard_log', 'id>?', [$maxlogid], 'id'));
        $this->assertEquals($tenant->id, \tool_tenant\tenancy::get_tenant_id($user1->id));

        // When user is created inside another tenant, the user_created AND tenant_user_created events are triggered.
        $this->assertEquals(2, count($logs));
        $this->assertEquals('\\' . \core\event\user_created::class, $logs[0]->eventname);
        $this->assertEquals($user1->id, $logs[0]->relateduserid);
        $this->assertEquals('\\' . \tool_tenant\event\tenant_user_created::class, $logs[1]->eventname);
        $this->assertEquals($user1->id, $logs[1]->relateduserid);

        // User is moved to another tenant. The tenant_user_updated is triggered.
        $maxlogid = $DB->get_field_sql('SELECT max(id) FROM {logstore_standard_log}');
        $this->generator->allocate_user($user1->id, \tool_tenant\tenancy::get_default_tenant_id());
        $logs = array_values($DB->get_records_select('logstore_standard_log', 'id>?', [$maxlogid], 'id'));
        $this->assertEquals(\tool_tenant\tenancy::get_default_tenant_id(), \tool_tenant\tenancy::get_tenant_id($user1->id));

        $this->assertEquals(1, count($logs));
        $this->assertEquals('\\' . \tool_tenant\event\tenant_user_updated::class, $logs[0]->eventname);
        $this->assertEqualsCanonicalizing(
            ['tenantid' => \tool_tenant\tenancy::get_default_tenant_id(), 'oldtenantid' => $tenant->id],
            json_decode($logs[0]->other, true));
        $this->assertEquals($user1->id, $logs[0]->relateduserid);
    }

    /**
     * Test core hack add user with site limit enabled.
     */
    public function test_precheck_create_user() {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$tenant1, $users1] = $this->generator->create_tenant_and_users(2);
        // Site limit settings.
        set_config('userlimitenabled', 1);
        set_config('userlimit', 3);
        $this->assertNotTrue(permission::check_quotas_to_add_users(0, 1));

        // Add a new user.
        try {
            $this->getDataGenerator()->create_user();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('User accounts limit reached',
                $e->getMessage());
        }
        set_config('userlimit', 4);
        $user = new stdClass();
        $user->username = 'newuser01';
        $newuser = $this->getDataGenerator()->create_user($user);
        $this->assertEquals('newuser01', $newuser->username);
    }

    /**
     * Test to check that user cannot be unsuspended when site quota is reached.
     */
    public function test_precheck_update_user() {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$tenant1, $users1] = $this->generator->create_tenant_and_users(2);

        // Site limit settings.
        set_config('userlimitenabled', 1);
        set_config('userlimit', 3);
        $this->assertNotTrue(permission::check_quotas_to_add_users(0, 1));
        // Suspend a user.
        $users1[0]->suspended = 1;
        user_update_user($users1[0], false, false);
        $this->assertTrue(permission::check_quotas_to_add_users(0, 1));
        // Reduce the limit.
        set_config('userlimit', 2);
        $this->assertNotTrue(permission::check_quotas_to_add_users(0, 1));
        // Unsuspend user.
        $users1[0]->suspended = 0;
        // Error should be thrown.
        try {
            user_update_user($users1[0], false, false);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('User accounts limit reached',
                $e->getMessage());
        }
    }

    /**
     * Test external get_users method respects visibility of tenancy users
     */
    public function test_get_users_in_multitenancy() {
        global $DB, $CFG;

        require_once("{$CFG->dirroot}/user/externallib.php");

        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant1, [$user11, $user12]] = $generator->create_tenant_and_users(2, [], ['firstname' => 'John']);
        [$tenant2, [$user21, $user22]] = $generator->create_tenant_and_users(2, [], ['firstname' => 'John']);
        (new manager())->assign_tenant_admin_role($tenant1->id, [$user11->id]);
        $role = $DB->get_record('role', ['shortname' => 'user']);
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $role->id, context_system::instance()->id);
        reload_all_capabilities();

        $searchparams = [['key' => 'auth', 'value' => 'manual']];

        // Admin can find 6 users.
        $this->setAdminUser();
        $result = core_user_external::get_users($searchparams);
        $result = external_api::clean_returnvalue(core_user_external::get_users_returns(), $result);
        $this->assertCount(6, $result['users']);

        // Tenant administrator in tenant1 can find 2 users.
        $this->setUser($user11);
        $result = core_user_external::get_users($searchparams);
        $result = external_api::clean_returnvalue(core_user_external::get_users_returns(), $result);
        $this->assertCount(2, $result['users']);

        // Ordinary user can only find themselves.
        $this->setUser($user12);
        $result = core_user_external::get_users($searchparams);
        $result = external_api::clean_returnvalue(core_user_external::get_users_returns(), $result);
        $this->assertCount(1, $result['users']);
    }
}

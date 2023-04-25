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

/**
 * Tests for shared space
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use advanced_testcase;
use DomDocument;
use DOMXPath;
use tool_tenant_generator;

/**
 * Tests for the tool_tenant\sharedspace class methods.
 *
 * @package    tool_tenant
 * @covers     \tool_tenant\sharedspace
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class sharedspace_test extends advanced_testcase {

    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    public function test_enable_shared_space() {
        global $DB;
        $this->resetAfterTest();

        $sharedid = \tool_tenant\sharedspace::enable_shared_space();
        $this->assertGreaterThan(0, $sharedid);

        $shared = $DB->get_record('tool_tenant', ['id' => $sharedid]);
        $this->assertEquals('-', $shared->name);
        $this->assertNull($shared->parentid);

        // No errors if called twice.
        $sharedid2 = \tool_tenant\sharedspace::enable_shared_space();
        $this->assertEquals($sharedid, $sharedid2);

        $tenant = (new manager())->get_tenant($sharedid);
        $this->assertEquals($sharedid, $tenant->get('id'));
        $this->assertEquals('-', $tenant->get('name'));
        $this->assertEquals('/'.$sharedid, $tenant->get('path'));
        $this->assertEquals(1, $tenant->get('depth'));
        $this->assertEquals('Shared space', $tenant->get_formatted_name());

        $tenant = \tool_tenant\tenancy::get_tenants()[$sharedid];
        $this->assertEquals($sharedid, $tenant->id);
        $this->assertEquals('Shared space', $tenant->name);
    }

    public function test_create_tenant() {
        global $DB;
        $this->resetAfterTest();

        $tenant1 = $this->generator->create_tenant([]);
        $this->assertNull($tenant1->parentid);
        $this->assertNull($DB->get_field('tool_tenant', 'parentid', ['id' => $tenant1->id]));
        $this->assertEquals('/'.$tenant1->id, $tenant1->path);
        $this->assertEquals(1, $tenant1->depth);

        $sharedid = \tool_tenant\sharedspace::enable_shared_space();
        $this->assertNotEmpty($sharedid);

        $this->assertEquals($sharedid, $DB->get_field('tool_tenant', 'parentid', ['id' => $tenant1->id]));

        $tenant2 = $this->generator->create_tenant([]);
        $this->assertEquals($sharedid, $tenant2->parentid);
        $this->assertEquals($sharedid, $DB->get_field('tool_tenant', 'parentid', ['id' => $tenant1->id]));
        $this->assertEquals('/'.$sharedid.'/'.$tenant2->id, $tenant2->path);
        $this->assertEquals(2, $tenant2->depth);
    }

    /**
     * Test that get_tenants() returns the shared tenant
     */
    public function test_get_tenants() {
        $this->resetAfterTest();

        $this->generator->create_tenant();
        $this->generator->create_tenant();

        $tenants = \tool_tenant\tenancy::get_tenants();
        $this->assertCount(3, $tenants);
        $tenants = (new \tool_tenant\manager())->get_tenants();
        $this->assertCount(3, $tenants);
        $tenants = (new \tool_tenant\manager())->get_tenants_without_shared();
        $this->assertCount(3, $tenants);

        $sharedid = \tool_tenant\sharedspace::enable_shared_space();

        $tenants = \tool_tenant\tenancy::get_tenants();
        $this->assertCount(4, $tenants);
        $this->assertTrue(array_key_exists($sharedid, $tenants));

        $tenants = (new \tool_tenant\manager())->get_tenants();
        $this->assertCount(4, $tenants);
        $this->assertTrue(array_key_exists($sharedid, $tenants));

        $tenants = (new \tool_tenant\manager())->get_tenants_without_shared();
        $this->assertCount(3, $tenants);
        $this->assertFalse(array_key_exists($sharedid, $tenants));
    }

    /**
     * Test that tenant_menu() returns the shared tenant at all times except when the reminder is disabled.
     */
    public function test_get_tenants_menu() {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->generator->create_tenant();
        $this->generator->create_tenant();
        $PAGE->set_url('/');
        $output = $PAGE->get_renderer('core');

        // Default.
        $menu = \tool_tenant\tenancy::tenant_menu($output);
        $menuitems = $this->get_tenant_menu_items($menu);
        $expected = ['Shared space', 'New tenant 1', 'New tenant 2'];
        $this->assertEqualsCanonicalizing($expected, $menuitems);

        // Disable shared space reminder.
        \tool_tenant\sharedspace::disable_shared_space_reminder();
        $menu = \tool_tenant\tenancy::tenant_menu($output);
        $menuitems = $this->get_tenant_menu_items($menu);
        $expected = ['New tenant 1', 'New tenant 2'];
        $this->assertEqualsCanonicalizing($expected, $menuitems);

        // Enable Shared space.
        \tool_tenant\sharedspace::enable_shared_space();
        $menu = \tool_tenant\tenancy::tenant_menu($output);
        $expected = ['Shared space', 'New tenant 1', 'New tenant 2'];
        $menuitems = $this->get_tenant_menu_items($menu);
        $this->assertEqualsCanonicalizing($expected, $menuitems);
    }

    /**
     * Return menu items values from HTML DOM.
     *
     * @param array $menu HTML menu output
     * @return array Returns menu items in array format
     */
    public function get_tenant_menu_items($menu) {
        $items = [];
        $document = new DOMDocument();
        $document->loadHTML($menu);
        $xpath = new DomXPath($document);
        $selectors = $xpath->query("//div/a[@class='dropdown-item']");
        foreach ($selectors as $selector) {
            $items[] = $selector->nodeValue;
        }
        return $items;
    }
    /**
     * Test that manager::get_tenant() returns an object when requested on a shared tenant
     */
    public function test_get_tenant() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant1 = $this->generator->create_tenant();
        $this->generator->create_tenant();
        $sharedid = \tool_tenant\sharedspace::enable_shared_space();

        $this->assertFalse(\tool_tenant\sharedspace::is_shared_space($tenant1->id));
        $this->assertTrue(\tool_tenant\sharedspace::is_shared_space($sharedid));

        $t = (new manager())->get_tenant($tenant1->id);
        $this->assertEquals($tenant1->id, $t->get('id'));

        $t = (new manager())->get_tenant($sharedid);
        $this->assertEquals($sharedid, $t->get('id'));
    }

    public function test_switch_tenant() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant1 = $this->generator->create_tenant();
        $this->generator->create_tenant();
        $sharedid = \tool_tenant\sharedspace::enable_shared_space();

        \tool_tenant\tenancy::set_switched_tenant_id($tenant1->id);
        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_tenant_id());
        \tool_tenant\tenancy::set_switched_tenant_id($sharedid);
        $this->assertEquals($sharedid, \tool_tenant\tenancy::get_tenant_id());
    }

    /**
     * Data provider for {@see test_show_shared_space_in_switch_operations}
     *
     * @return array[]
     */
    public function show_shared_space_in_switch_operations_provider(): array {
        return [
            'Unset' => [null, true],
            'Disabled' => [0, false],
            'Enabled' => [1, true],
        ];
    }

    /**
     * Test method to determine whether shared space should be shown in switch operations
     *
     * @param int|null $sharedid Any integer will be set as current config value, NULL skipped
     * @param bool $expected
     *
     * @dataProvider show_shared_space_in_switch_operations_provider
     */
    public function test_show_shared_space_in_switch_operations(?int $sharedid, bool $expected): void {
        $this->resetAfterTest();

        if ($sharedid !== null) {
            set_config('tool_tenant_shared_tenant_id', $sharedid);
        }

        $this->assertEquals($expected, sharedspace::show_shared_space_in_switch_operations());
    }

    public function test_permissions() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $tenant1 = $this->generator->create_tenant();
        $sharedid = \tool_tenant\sharedspace::enable_shared_space();

        // Permissions performing actions on the tenants.
        // Can do everything with a normal tenant.
        $this->assertTrue(\tool_tenant\permission::can_archive_tenant($tenant1->id));
        $this->assertTrue(\tool_tenant\permission::can_edit_tenant($tenant1->id));
        $this->assertTrue(\tool_tenant\permission::can_edit_tenant_details($tenant1->id));
        $this->assertTrue(\tool_tenant\permission::can_edit_tenant_theme($tenant1->id));
        $this->assertTrue(\tool_tenant\permission::can_create_users($tenant1->id));
        $this->assertTrue(\tool_tenant\permission::can_browse_users($tenant1->id));
        $this->assertTrue(\tool_tenant\permission::can_move_tenant($tenant1->id));
        $this->assertTrue(\tool_tenant\permission::can_see_roles_tab($tenant1->id));
        $this->assertTrue(\tool_tenant\permission::can_view_tenant_details($tenant1->id));
        $this->assertTrue(\tool_tenant\permission::can_assign_tenant_admin($tenant1->id));
        $this->assertTrue(\tool_tenant\permission::can_access_tenant($tenant1->id));

        // Can not do the same things with the shared tenant.
        $this->assertFalse(\tool_tenant\permission::can_archive_tenant($sharedid));
        $this->assertFalse(\tool_tenant\permission::can_edit_tenant($sharedid));
        $this->assertFalse(\tool_tenant\permission::can_edit_tenant_details($sharedid));
        $this->assertFalse(\tool_tenant\permission::can_edit_tenant_theme($sharedid));
        $this->assertFalse(\tool_tenant\permission::can_create_users($sharedid));
        $this->assertFalse(\tool_tenant\permission::can_browse_users($sharedid));
        $this->assertFalse(\tool_tenant\permission::can_move_tenant($sharedid));
        $this->assertFalse(\tool_tenant\permission::can_see_roles_tab($sharedid));
        $this->assertFalse(\tool_tenant\permission::can_view_tenant_details($sharedid));
        $this->assertFalse(\tool_tenant\permission::can_assign_tenant_admin($sharedid));
        $this->assertFalse(\tool_tenant\permission::can_delete_tenant($sharedid));

        // But - can access.
        $this->assertTrue(\tool_tenant\permission::can_access_tenant($sharedid));

        // Can browse users and edit theme in their own tenant, in the other tenant but not in shared tenant.
        // Even when being switched to a shared tenant can still see the menu item to browse users.
        $this->assertTrue(\tool_tenant\permission::can_browse_users_anywhere());
        $this->assertTrue(\tool_tenant\permission::can_browse_users());
        $this->assertTrue(\tool_tenant\permission::can_browse_users($tenant1->id));
        $this->assertFalse(\tool_tenant\permission::can_browse_users($sharedid));
        $this->assertTrue(\tool_tenant\permission::can_edit_tenant_theme_anywhere());
        $this->assertTrue(\tool_tenant\permission::can_edit_tenant_theme());
        $this->assertTrue(\tool_tenant\permission::can_edit_tenant_theme($tenant1->id));
        $this->assertFalse(\tool_tenant\permission::can_edit_tenant_theme($sharedid));

        \tool_tenant\tenancy::set_switched_tenant_id($sharedid);
        $this->assertTrue(\tool_tenant\permission::can_browse_users_anywhere());
        $this->assertFalse(\tool_tenant\permission::can_browse_users());
        $this->assertTrue(\tool_tenant\permission::can_browse_users($tenant1->id));
        $this->assertFalse(\tool_tenant\permission::can_browse_users($sharedid));
        $this->assertTrue(\tool_tenant\permission::can_edit_tenant_theme_anywhere());
        $this->assertFalse(\tool_tenant\permission::can_edit_tenant_theme());
        $this->assertTrue(\tool_tenant\permission::can_edit_tenant_theme($tenant1->id));
        $this->assertFalse(\tool_tenant\permission::can_edit_tenant_theme($sharedid));
    }

    public function test_hierarchy() {
        // These tests are also included in hierarchy_test and can be removed from here when we introduce sub-tenants
        // and remove the 'shared space' functionality.
        global $DB;
        $this->resetAfterTest();

        $tenant = $this->generator->create_tenant();
        $tenant2 = $this->generator->create_tenant();

        $this->assertEquals([], \tool_tenant\hierarchy::get_parent_tenants_ids($tenant->id));

        [$sql, $params] = \tool_tenant\hierarchy::get_subtenants_sql($tenant->id, true);
        $ids = $DB->get_fieldset_sql("SELECT id FROM {tool_tenant} WHERE id ".$sql, $params);
        $this->assertEqualsCanonicalizing([$tenant->id], $ids);

        $sharedid = \tool_tenant\sharedspace::enable_shared_space();

        $this->assertEquals([$sharedid], \tool_tenant\hierarchy::get_parent_tenants_ids($tenant->id));
        $this->assertEquals([], \tool_tenant\hierarchy::get_parent_tenants_ids($sharedid));

        [$sql, $params] = \tool_tenant\hierarchy::get_subtenants_sql($tenant->id, true);
        $ids = $DB->get_fieldset_sql("SELECT id FROM {tool_tenant} WHERE id ".$sql, $params);
        $this->assertEqualsCanonicalizing([$tenant->id], $ids);

        [$sql, $params] = \tool_tenant\hierarchy::get_subtenants_sql($tenant->id, false);
        $ids = $DB->get_fieldset_sql("SELECT id FROM {tool_tenant} WHERE id ".$sql, $params);
        $this->assertEqualsCanonicalizing([], $ids);

        [$sql, $params] = \tool_tenant\hierarchy::get_subtenants_sql($sharedid, false);
        $ids = $DB->get_fieldset_sql("SELECT id FROM {tool_tenant} WHERE id ".$sql, $params);
        $this->assertEqualsCanonicalizing([\tool_tenant\tenancy::get_default_tenant_id(), $tenant->id, $tenant2->id], $ids);
    }
}

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
use context_user;
use tool_tenant_generator;
use stdClass;

/**
 * Tests for the dashboard_manager class.
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class dashboard_manager_test extends advanced_testcase {
    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Load required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/my/lib.php');
    }

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test for generate_tenant_page_name
     */
    public function test_generate_tenant_page_name(): void {
        $tenant1 = $this->generator->create_tenant(['dashboardlinked' => 0]);
        $tenant2 = $this->generator->create_tenant();
        $pagename = dashboard_manager::generate_tenant_page_name($tenant1->id);
        $this->assertEquals('tenant-' . $tenant1->id, $pagename);
        $pagename = dashboard_manager::generate_tenant_page_name($tenant2->id);
        $this->assertEquals('tenant-' . $tenant2->id, $pagename);
    }

    /**
     * Test for create_tenant_dashboard_page
     */
    public function test_create_tenant_dashboard_page(): void {
        global $DB;

        $tenant = $this->generator->create_tenant(['dashboardlinked' => 0]);
        $pagename = dashboard_manager::generate_tenant_page_name($tenant->id);

        // Sanity check. Tenant dashboard page does not exist.
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => null, 'private' => 1, 'name' => $pagename]));

        $tenantpage = dashboard_manager::get_tenant_dashboard_page($tenant->id);
        // Check that page has been created.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => null, 'private' => 1, 'name' => $pagename]));

        $this->assertNull($tenantpage->userid);
        $this->assertEquals($pagename, $tenantpage->name);
        $this->assertEquals(1, $tenantpage->private);
    }

    /**
     * Test for get_tenant_dashboard_page
     */
    public function test_get_tenant_dashboard_page(): void {
        global $DB;

        $tenant = $this->generator->create_tenant(['dashboardlinked' => 0]);
        $pagename = dashboard_manager::generate_tenant_page_name($tenant->id);
        $record = [
            'userid' => null,
            'name' => $pagename,
            'private' => 1,
        ];

        // Sanity check. Tenant dashboard page does not exist.
        $this->assertFalse($DB->record_exists('my_pages', $record));

        $tenantpage = dashboard_manager::get_tenant_dashboard_page($tenant->id);

        // Check that page record is created if it did not exist.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => null, 'private' => 1, 'name' => $pagename]));

        $tenantpage2 = dashboard_manager::get_tenant_dashboard_page($tenant->id);
        // Check that getting the dashboard when record exists.
        $this->assertEquals($tenantpage, $tenantpage2);
        $this->assertEquals(null, $tenantpage2->userid);
        $this->assertEquals($pagename, $tenantpage2->name);
        $this->assertEquals(1, $tenantpage2->private);
    }

    /**
     * Test for delete_tenant_dashboard_page
     */
    public function test_delete_tenant_dashboard_page(): void {
        global $DB;

        // Create the tenant dashboard page.
        $tenant = $this->generator->create_tenant(['dashboardlinked' => 0]);
        $tenantpage = dashboard_manager::get_tenant_dashboard_page($tenant->id);
        $this->assertTrue($DB->record_exists('my_pages', ['id' => $tenantpage->id]));

        // Add a block to the tenant dashboard.
        $this->create_dashboard_mockblock(context_system::instance()->id, $tenantpage->id);
        $this->assertCount(10, $this->get_blocks($tenantpage->id)); // Ten blocks: 9(site default) + 1(mockblock created).

        dashboard_manager::delete_tenant_dashboard_page($tenant->id);

        // Check that tenant dashboard page no longer exists.
        $this->assertFalse($DB->record_exists('my_pages', ['id' => $tenantpage->id]));

        // Check that all blocks in tenant dashboard page have been deleted.
        $this->assertEmpty($this->get_blocks($tenantpage->id));
    }

    /**
     * Test for link_tenant_dashboard
     */
    public function test_link_tenant_dashboard() {
        global $DB;

        // Create the tenant dashboard page.
        $tenant = $this->generator->create_tenant(['dashboardlinked' => 0]);
        $tenantpage = dashboard_manager::get_tenant_dashboard_page($tenant->id);
        // Sanity check.
        $this->assertTrue($DB->record_exists('my_pages', ['id' => $tenantpage->id]));
        $this->assertEquals(0, $tenant->dashboardlinked);

        // Add a block to tenant dashboard page.
        $tenantpage = dashboard_manager::get_tenant_dashboard_page($tenant->id);
        $this->create_dashboard_mockblock(context_system::instance()->id, $tenantpage->id);
        $this->assertCount(10, $this->get_blocks($tenantpage->id));

        // Link the dashboard.
        dashboard_manager::link_tenant_dashboard($tenant->id);

        // Check tenant dashboard is now linked and tenant dashboard page was deleted with its block.
        $tenant = (new \tool_tenant\manager())->get_tenant($tenant->id)->to_record();
        $this->assertEquals(1, $tenant->dashboardlinked);
        $this->assertFalse($DB->record_exists('my_pages', ['id' => $tenantpage->id]));
        $this->assertEmpty($this->get_blocks($tenantpage->id));
    }

    /**
     * Test for unlink_tenant_dashboard
     */
    public function test_unlink_tenant_dashboard() {
        global $DB;

        // Create the tenant dashboard page.
        $tenant = $this->generator->create_tenant(['dashboardlinked' => 1]);
        $tenantpagename = dashboard_manager::generate_tenant_page_name($tenant->id);
        // Sanity check.
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => null, 'name' => $tenantpagename]));
        $this->assertEquals(1, $tenant->dashboardlinked);

        // Un-link the dashboard.
        dashboard_manager::unlink_tenant_dashboard($tenant->id);

        // Check tenant dashboard is now un-linked, tenant page created and default site dashboard blocks were copied.
        $this->assertEquals(0, tenancy::get_tenants()[$tenant->id]->dashboardlinked);
        $tenantpage = $DB->get_record('my_pages', ['userid' => null, 'name' => $tenantpagename]);
        $this->assertNotEmpty($tenantpage);
        $this->assertCount(9, $this->get_blocks($tenantpage->id));
    }

    /**
     * Test for copy_dashboard_page
     */
    public function test_copy_dashboard_page(): void {
        global $DB;

        // Create a user (in Default tenant by default).
        $user0 = $this->getDataGenerator()->create_user();

        // Create a tenant (linked) dashboard and user1 in it.
        [$tenant1, [$user1]] = $this->generator->create_tenant_and_users(1, ['dashboardlinked' => 1]);

        // Create a tenant (un-linked) dashboard with two blocks and user2 in it.
        [$tenant2, [$user2]] = $this->generator->create_tenant_and_users(1, ['dashboardlinked' => 0]);

        $tenant2page = dashboard_manager::get_tenant_dashboard_page($tenant2->id);
        $this->create_dashboard_mockblock(context_system::instance()->id, $tenant2page->id);
        $this->create_dashboard_mockblock(context_system::instance()->id, $tenant2page->id);

        // Sanity check.
        $tenant2dashboardblocks = $this->get_blocks($tenant2page->id);
        $this->assertCount(11, $tenant2dashboardblocks);

        // Sanity check for default sitedashboard.
        $sitedashboardpage = $DB->get_record('my_pages', ['userid' => null, 'name' => '__default', 'private' => 1]);
        $sitedashboardblocks = $this->get_blocks($sitedashboardpage->id);
        $this->assertCount(9, $sitedashboardblocks);

        // Copy page for user1 (in a tenant with linked dashboard).
        $this->setUser($user1);
        $user1page = dashboard_manager::copy_dashboard_page($user1->id);
        // Check that no page record was created for the user in linked tenant.
        $this->assertFalse($user1page);
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $user1->id, 'name' => '__default', 'private' => 1]));

        // Copy page for user2 (in a tenant with un-linked dashboard).
        $this->setUser($user2);
        $user2page = dashboard_manager::copy_dashboard_page($user2->id);
        // Check that page record was created for the user.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user2->id, 'name' => '__default', 'private' => 1]));
        // Check that tenant dashboard blocks were copied for the user dashboard.
        $user2dashboardblocks = $this->get_blocks($user2page->id);
        $this->assertEquals($tenant2dashboardblocks, $user2dashboardblocks);

        // Copy page for user0 (in the default tenant).
        $user0page = $this->generate_user_dashboard_page($user0);
        // Check that page record was created for the user.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user0->id, 'name' => '__default', 'private' => 1]));
        // Check that default site dashboard blocks were copied for the user dashboard.
        $user0dashboardblocks = $this->get_blocks($user0page->id);
        $this->assertEquals($sitedashboardblocks, $user0dashboardblocks);
    }

    /**
     * Test for reset_dashboard_page_for_users
     */
    public function test_reset_dashboard_page_for_users(): void {
        global $DB;

        // Create page for 3 users.
        [$tenant, [$user1, $user2, $user3]] = $this->generator->create_tenant_and_users(3, ['dashboardlinked' => 0]);
        $user1page = $this->generate_user_dashboard_page($user1);
        $user2page = $this->generate_user_dashboard_page($user2);
        $user3page = $this->generate_user_dashboard_page($user3);
        $this->create_dashboard_mockblock(context_user::instance($user1->id)->id, $user1page->id);
        $this->create_dashboard_mockblock(context_user::instance($user2->id)->id, $user2page->id);
        $this->create_dashboard_mockblock(context_user::instance($user3->id)->id, $user3page->id);

        // Sanity check.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user1->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(10, $this->get_blocks($user1page->id));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user2->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(10, $this->get_blocks($user2page->id));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user3->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(10, $this->get_blocks($user3page->id));

        // Reset pages for user1 and user3.
        dashboard_manager::reset_dashboard_page_for_users([$user1->id, $user3->id]);

        // Check user1 anb user3 pages were reset.
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $user1->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(0, $this->get_blocks($user1page->id));
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $user3->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(0, $this->get_blocks($user3page->id));

        // Check page for user2 was not reset.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user2->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(10, $this->get_blocks($user2page->id));
    }

    /**
     * Test for get_users_in_linked_tenants
     */
    public function test_get_users_in_linked_tenants() {
        // Create user1 and user2 in a linked tenant.
        [$tenant1, [$user1, $user2]] = $this->generator->create_tenant_and_users(2, ['dashboardlinked' => 1]);
        // Create user3 in a un-linked tenant.
        [$tenant2, [$user3]] = $this->generator->create_tenant_and_users(1, ['dashboardlinked' => 0]);
        // Create user 4 in default tenant (all tenants are linked by default).
        $user4 = $this->getDataGenerator()->create_user();
        $this->generator->allocate_user($user4->id, \tool_tenant\tenancy::get_default_tenant_id());
        // Create user 5 (Part of default tenant because is not allocated in any tenant).
        $user5 = $this->getDataGenerator()->create_user();

        // Generate user pages.
        $user1page = $this->generate_user_dashboard_page($user1);
        $user2page = $this->generate_user_dashboard_page($user2);
        $user3page = $this->generate_user_dashboard_page($user3);
        $user3page = $this->generate_user_dashboard_page($user4);
        $user3page = $this->generate_user_dashboard_page($user5);

        $userids = dashboard_manager::get_users_in_linked_tenants();
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user4->id, $user5->id], $userids);

        // Unlink the default tenant.
        dashboard_manager::unlink_tenant_dashboard(\tool_tenant\tenancy::get_default_tenant_id());

        $userids = dashboard_manager::get_users_in_linked_tenants();
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $userids);
    }

    /**
     * Test for link_all_tenant_dashboards
     */
    public function test_test_link_all_tenant_dashboards() {
        global $DB;

        // Create user1 in a linked tenant.
        [$tenant1, [$user1]] = $this->generator->create_tenant_and_users(1, ['dashboardlinked' => 1]);
        // Create user2 in a un-linked tenant.
        [$tenant2, [$user2]] = $this->generator->create_tenant_and_users(1, ['dashboardlinked' => 0]);
        // Create user3 in a un-linked tenant.
        [$tenant3, [$user3]] = $this->generator->create_tenant_and_users(1, ['dashboardlinked' => 0]);
        // Sanity check.
        $this->assertEquals(1, $tenant1->dashboardlinked);
        $this->assertEquals(0, $tenant2->dashboardlinked);
        $this->assertEquals(0, $tenant3->dashboardlinked);

        // Generate user pages.
        $user1page = $this->generate_user_dashboard_page($user1);
        $user2page = $this->generate_user_dashboard_page($user2);
        $user3page = $this->generate_user_dashboard_page($user3);
        // Sanity check.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user1->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user1page->id));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user2->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user2page->id));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user3->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user3page->id));

        dashboard_manager::link_all_tenant_dashboards();

        // Check tenant dashboard are now linked.
        $this->assertEquals(1, tenancy::get_tenants()[$tenant1->id]->dashboardlinked);
        $this->assertEquals(1, tenancy::get_tenants()[$tenant2->id]->dashboardlinked);
        $this->assertEquals(1, tenancy::get_tenants()[$tenant3->id]->dashboardlinked);

        // Check user pages were not reset.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user1->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user1page->id));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user2->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user2page->id));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user3->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user3page->id));
    }

    /**
     * Test for link_all_tenant_dashboards also resetting user dashboards
     */
    public function test_link_all_tenant_dashboards_with_resetuserdashboards() {
        global $DB;

        // Set default tenant to un-linked.
        $defaulttenantid = tenancy::get_default_tenant_id();
        (new \tool_tenant\manager())->update_tenant($defaulttenantid, (object)['dashboardlinked' => 0]);

        // Create user1 in a linked tenant.
        [$tenant1, [$user1]] = $this->generator->create_tenant_and_users(1, ['dashboardlinked' => 1]);
        // Create user2 in a un-linked tenant.
        [$tenant2, [$user2]] = $this->generator->create_tenant_and_users(1, ['dashboardlinked' => 0]);
        // Create user3 in a un-linked tenant.
        [$tenant3, [$user3]] = $this->generator->create_tenant_and_users(1, ['dashboardlinked' => 0]);
        // Create user4 in default tenant.
        $user4 = $this->getDataGenerator()->create_user();

        // Sanity check.
        $this->assertEquals(1, $tenant1->dashboardlinked);
        $this->assertEquals(0, $tenant2->dashboardlinked);
        $this->assertEquals(0, $tenant3->dashboardlinked);
        $this->assertEquals(0, tenancy::get_tenants()[$defaulttenantid]->dashboardlinked);

        // Generate user pages.
        $user1page = $this->generate_user_dashboard_page($user1);
        $user2page = $this->generate_user_dashboard_page($user2);
        $user3page = $this->generate_user_dashboard_page($user3);
        $user4page = $this->generate_user_dashboard_page($user4);
        // Sanity check.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user1->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user1page->id));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user2->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user2page->id));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user3->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user3page->id));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user4->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user4page->id));

        dashboard_manager::link_all_tenant_dashboards(true);

        // Check tenant dashboard are now linked.
        $this->assertEquals(1, tenancy::get_tenants()[$tenant1->id]->dashboardlinked);
        $this->assertEquals(1, tenancy::get_tenants()[$tenant2->id]->dashboardlinked);
        $this->assertEquals(1, tenancy::get_tenants()[$tenant3->id]->dashboardlinked);
        $this->assertEquals(1, tenancy::get_tenants()[$defaulttenantid]->dashboardlinked);

        // Check user1 page was not reset.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user1->id, 'name' => '__default', 'private' => 1]));
        $this->assertCount(9, $this->get_blocks($user1page->id));

        // Check user2, user3 and user4 pages were reset.
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $user2->id, 'name' => '__default', 'private' => 1]));
        $this->assertEmpty($this->get_blocks($user2page->id));
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $user3->id, 'name' => '__default', 'private' => 1]));
        $this->assertEmpty($this->get_blocks($user3page->id));
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $user4->id, 'name' => '__default', 'private' => 1]));
        $this->assertEmpty($this->get_blocks($user4page->id));
    }

    /**
     * Generates a dashboard block.
     *
     * @param int $parentcontextid
     * @param string|null $subpagepattern
     */
    private function create_dashboard_mockblock(int $parentcontextid, ?string $subpagepattern): void {
        global $DB;

        $configdata = base64_encode(serialize((object) ['example' => 'content']));
        $blockinstance = [
            'blockname' => 'html',
            'parentcontextid' => $parentcontextid,
            'timecreated' => time(),
            'timemodified' => time(),
            'defaultweight' => 1,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $subpagepattern,
            'showinsubcontexts' => 0,
            'defaultregion' => 'side-pre',
            'configdata' => $configdata,
        ];

        $DB->insert_record('block_instances', (object)$blockinstance);
    }

    /**
     * Generates the user dashboard page (Same as first access by the user to the dashboard).
     *
     * @param stdClass $user
     * @return stdClass
     *
     */
    private function generate_user_dashboard_page(stdClass $user): stdClass {
        $this->setUser($user);
        if (!$page = \tool_tenant\dashboard_manager::copy_dashboard_page($user->id)) {
            $page = my_copy_page($user->id);
        }
        return $page;
    }

    /**
     * Returns an array of the 'my-index' pagetype blocks for a subpagepattern.
     *
     * @param string $subpagepattern
     * @return array
     */
    private function get_blocks(string $subpagepattern): array {
        global $DB;

        $sql = "SELECT blockname FROM {block_instances}
                WHERE pagetypepattern = :pagetypepattern AND subpagepattern = :subpagepattern
                ORDER BY blockname";
        return $DB->get_fieldset_sql($sql, ['pagetypepattern' => 'my-index', 'subpagepattern' => $subpagepattern]);
    }
}

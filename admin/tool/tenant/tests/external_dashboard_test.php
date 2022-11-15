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
use tool_tenant_external;
use tool_tenant_generator;
use stdClass;

/**
 * Tests for the tool_tenant dashboard external classes.
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external_dashboard_test extends advanced_testcase {
    /** @var tool_tenant_generator */
    protected $generator;
    /** @var stdClass */
    protected $tenant1;
    /** @var stdClass */
    protected $tenant2;
    /** @var stdClass */
    protected $defaulttenantadmin;
    /** @var stdClass */
    protected $tenant1admin;
    /** @var stdClass */
    protected $tenant2admin;
    /** @var stdClass */
    protected $user1;
    /** @var stdClass */
    protected $user2;
    /** @var stdClass */
    protected $user3;
    /** @var stdClass */
    protected $user4;
    /** @var stdClass */
    protected $user5;
    /** @var stdClass */
    protected $user6;
    /** @var stdClass */
    protected $user1page;
    /** @var stdClass */
    protected $user2page;
    /** @var stdClass */
    protected $user3page;
    /** @var stdClass */
    protected $user4page;
    /** @var stdClass */
    protected $user5page;
    /** @var stdClass */
    protected $user6page;
    /** @var int */
    protected $initialblockscount;

    /**
     * Set up
     */
    protected function setUp(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $DB->get_record('my_pages', ['userid' => null, 'private' => 1, 'name' => '__default']);
        $sql = "SELECT count(id) FROM {block_instances}
                WHERE pagetypepattern = :pagetypepattern AND subpagepattern = :subpagepattern";
        $this->initialblockscount = $DB->get_field_sql($sql,
            ['pagetypepattern' => 'my-index', 'subpagepattern' => $page->id]);

        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $defaulttenantid = tenancy::get_default_tenant_id();

        // Create tenants and users.
        [$this->tenant1, [$this->user1, $this->user5]] = $this->generator->create_tenant_and_users(2,
            ['dashboardlinked' => 0]);
        [$this->tenant2, [$this->user2, $this->user6]] = $this->generator->create_tenant_and_users(2,
            ['dashboardlinked' => 1]);
        $this->user3 = $this->getDataGenerator()->create_user();
        $this->user4 = $this->getDataGenerator()->create_user();
        $this->generator->allocate_user($this->user4->id, $defaulttenantid);

        // Create an admin for tenants.
        $manager = new \tool_tenant\manager();
        $this->defaulttenantadmin = $this->getDataGenerator()->create_user();
        $manager->allocate_user($this->defaulttenantadmin->id, $defaulttenantid, 'tool_tenant', 'testing');
        $manager->assign_tenant_admin_roles([$this->defaulttenantadmin->id], $defaulttenantid);
        $this->tenant1admin = $this->getDataGenerator()->create_user();
        $manager->allocate_user($this->tenant1admin->id, $this->tenant1->id, 'tool_tenant', 'testing');
        $manager->assign_tenant_admin_roles([$this->tenant1admin->id], $this->tenant1->id);
        $this->tenant2admin = $this->getDataGenerator()->create_user();
        $manager->allocate_user($this->tenant2admin->id, $this->tenant2->id, 'tool_tenant', 'testing');
        $manager->assign_tenant_admin_roles([$this->tenant2admin->id], $this->tenant2->id);

        // Generate user pages.
        $this->user1page = $this->generate_user_dashboard_page($this->user1);
        $this->user2page = $this->generate_user_dashboard_page($this->user2);
        $this->user3page = $this->generate_user_dashboard_page($this->user3);
        $this->user4page = $this->generate_user_dashboard_page($this->user4);
        $this->user5page = $this->generate_user_dashboard_page($this->user5);
        $this->user6page = $this->generate_user_dashboard_page($this->user6);

        /*
         * Initial structure:
         *
         * Tenant 1 (un-linked)
         *      tenant1admin (tenant admin)
         *      user1
         *      user5
         * Tenant 2 (linked)
         *      tenand2admin (tenant admin)
         *      user2
         *      user6
         * Default tenant (linked)
         *      defaulttenantadmin (tenant admin)
         *      user3
         *      user4 (manually allocated)
         */
    }

    /**
     * Load required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->libdir . '/externallib.php');
        require_once($CFG->dirroot . '/my/lib.php');
    }

    /**
     * Test for tool_tenant_reset_linked_dashboard WS
     *
     * @covers \tool_tenant\external\dashboard\reset_all_linked_dashboards
     */
    public function test_reset_linked_dashboard(): void {
        global $DB;

        // Add a block to every users dashboard.
        // Linked dashboard have already 9 blocks, and un-linked are empty.
        $this->create_dashboard_mockblock(context_user::instance($this->user1->id)->id, $this->user1page->id);
        $this->create_dashboard_mockblock(context_user::instance($this->user2->id)->id, $this->user2page->id);
        $this->create_dashboard_mockblock(context_user::instance($this->user3->id)->id, $this->user3page->id);
        $this->create_dashboard_mockblock(context_user::instance($this->user4->id)->id, $this->user4page->id);
        $this->create_dashboard_mockblock(context_user::instance($this->user5->id)->id, $this->user5page->id);
        $this->create_dashboard_mockblock(context_user::instance($this->user6->id)->id, $this->user6page->id);

        // Sanity checks.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $this->user1->id, 'name' => '__default']));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $this->user2->id, 'name' => '__default']));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $this->user3->id, 'name' => '__default']));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $this->user4->id, 'name' => '__default']));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $this->user5->id, 'name' => '__default']));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $this->user6->id, 'name' => '__default']));
        $this->assertCount($this->initialblockscount + 1, $this->get_blocks($this->user1page->id));
        $this->assertCount($this->initialblockscount + 1, $this->get_blocks($this->user2page->id));
        $this->assertCount($this->initialblockscount + 1, $this->get_blocks($this->user3page->id));
        $this->assertCount($this->initialblockscount + 1, $this->get_blocks($this->user4page->id));
        $this->assertCount($this->initialblockscount + 1, $this->get_blocks($this->user5page->id));
        $this->assertCount($this->initialblockscount + 1, $this->get_blocks($this->user6page->id));

        // Execute the WS as tenant1 admin.
        $this->setAdminUser();
        $result = tool_tenant_external::clean_returnvalue(
            \tool_tenant\external\dashboard\reset_all_linked_dashboards::execute_returns(),
            \tool_tenant\external\dashboard\reset_all_linked_dashboards::execute()
        );
        $this->assertTrue($result);

        // Check that all user dashboards in linked tenants were reset.
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $this->user2->id, 'name' => '__default']));
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $this->user3->id, 'name' => '__default']));
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $this->user4->id, 'name' => '__default']));
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $this->user6->id, 'name' => '__default']));
        $this->assertEmpty($this->get_blocks($this->user2page->id));
        $this->assertEmpty($this->get_blocks($this->user3page->id));
        $this->assertEmpty($this->get_blocks($this->user4page->id));
        $this->assertEmpty($this->get_blocks($this->user6page->id));

        // Check that all user dashboards in un-linked tenants were not reset.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $this->user1->id, 'name' => '__default']));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $this->user5->id, 'name' => '__default']));
        $this->assertCount($this->initialblockscount + 1, $this->get_blocks($this->user1page->id));
        $this->assertCount($this->initialblockscount + 1, $this->get_blocks($this->user5page->id));

        // Check that tenant admin has no permissions to execute the WS for tenant1.
        $this->setUser($this->tenant1admin);
        $this->expectException('required_capability_exception');
        \tool_tenant\external\dashboard\reset_all_linked_dashboards::execute();
    }

    /**
     * Test for tool_tenant_reset_tenant_dashboard WS with a linked tenant
     *
     * @covers \tool_tenant\external\dashboard\reset_tenant_dashboard
     */
    public function test_reset_tenant_dashboard(): void {
        global $DB;

        // Add a block to the user1 and user5 dashboard.
        $this->create_dashboard_mockblock(context_user::instance($this->user1->id)->id, $this->user1page->id);
        $this->create_dashboard_mockblock(context_user::instance($this->user5->id)->id, $this->user5page->id);

        // Sanity check.
        $this->assertcount($this->initialblockscount + 1, $this->get_blocks($this->user1page->id));
        $this->assertcount($this->initialblockscount + 1, $this->get_blocks($this->user5page->id));

        // Execute the WS as tenant1 admin.
        $this->setUser($this->tenant1admin);
        $result = tool_tenant_external::clean_returnvalue(
            \tool_tenant\external\dashboard\reset_tenant_dashboard::execute_returns(),
            \tool_tenant\external\dashboard\reset_tenant_dashboard::execute($this->tenant1->id)
        );
        $this->assertTrue($result);

        // Check after reset_tenant_dashboard user1 page has been removed and has no blocks.
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $this->user1->id, 'name' => '__default']));
        $this->assertEmpty($this->get_blocks($this->user1page->id));

        // Check after reset_tenant_dashboard user5 page has been removed and has no blocks.
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $this->user5->id, 'name' => '__default']));
        $this->assertEmpty($this->get_blocks($this->user5page->id));

        // Check user2 (in tenant2) and user3 (in default tenant) are not affected by the reset.
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $this->user2->id, 'name' => '__default']));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $this->user3->id, 'name' => '__default']));
        // Tenant2 dashboard is linked, and site default dashboard has 9 blocks by default.
        $this->assertcount($this->initialblockscount, $this->get_blocks($this->user2page->id));
        $this->assertcount($this->initialblockscount, $this->get_blocks($this->user3page->id));

        // Check that tenant2 admin has no permissions to execute the WS for tenant1.
        $this->setUser($this->tenant2admin);
        $this->expectException('required_capability_exception');
        \tool_tenant\external\dashboard\reset_tenant_dashboard::execute($this->tenant1->id);
    }

    /**
     * Test for tool_tenant_reset_tenant_dashboard WS with not linked default tenant
     *
     * @covers \tool_tenant\external\dashboard\reset_tenant_dashboard
     */
    public function test_reset_default_tenant_dashboard(): void {
        global $DB;

        $defaulttenantid = tenancy::get_default_tenant_id();
        (new \tool_tenant\manager())->update_tenant($defaulttenantid, (object)['dashboardlinked' => 0]);

        // Add an extra block to the user3 and user4 dashboard.
        $this->create_dashboard_mockblock(context_user::instance($this->user3->id)->id, $this->user3page->id);
        $this->create_dashboard_mockblock(context_user::instance($this->user4->id)->id, $this->user4page->id);

        // Sanity check.
        $this->assertcount($this->initialblockscount + 1, $this->get_blocks($this->user3page->id));
        $this->assertcount($this->initialblockscount + 1, $this->get_blocks($this->user4page->id));

        // Execute the WS as defaulttenantadmin.
        $this->setUser($this->defaulttenantadmin);
        $result = tool_tenant_external::clean_returnvalue(
            \tool_tenant\external\dashboard\reset_tenant_dashboard::execute_returns(),
            \tool_tenant\external\dashboard\reset_tenant_dashboard::execute($defaulttenantid)
        );
        $this->assertTrue($result);

        // Check after reset_tenant_dashboard user3 page has been removed and has no blocks.
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $this->user3->id, 'name' => '__default']));
        $this->assertEmpty($this->get_blocks($this->user3page->id));

        // Check after reset_tenant_dashboard user5 page has been removed and has no blocks.
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $this->user4->id, 'name' => '__default']));
        $this->assertEmpty($this->get_blocks($this->user4page->id));
    }

    /**
     * Test for tool_tenant_update_dashboardlinked WS linking a tenant
     *
     * @covers \tool_tenant\external\dashboard\update_dashboardlinked
     */
    public function test_update_dashboardlinked_link(): void {
        global $DB;

        // Add a block to tenant1 dashboard pagge.
        $tenant1page = dashboard_manager::get_tenant_dashboard_page($this->tenant1->id);
        $this->create_dashboard_mockblock(context_system::instance()->id, $tenant1page->id);

        // Sanity check.
        $this->assertEquals(0, $this->tenant1->dashboardlinked);
        $this->assertEquals(1, $this->tenant2->dashboardlinked);
        $tenant1pagename = dashboard_manager::generate_tenant_page_name($this->tenant1->id);
        $tenant2pagename = dashboard_manager::generate_tenant_page_name($this->tenant2->id);
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => null, 'name' => $tenant1pagename]));
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => null, 'name' => $tenant2pagename]));
        $this->assertCount($this->initialblockscount + 1, $this->get_blocks($tenant1page->id));

        // Execute WS as tenant1 admin.
        $this->setUser($this->tenant1admin);
        $result = tool_tenant_external::clean_returnvalue(
            \tool_tenant\external\dashboard\update_dashboardlinked::execute_returns(),
            \tool_tenant\external\dashboard\update_dashboardlinked::execute(1, $this->tenant1->id)
        );
        $this->assertTrue($result);

        // Check tenant1 dashboard is now linked and tenant1 dashboard page was deleted with its block.
        $this->tenant1 = (new \tool_tenant\manager())->get_tenant($this->tenant1->id)->to_record();
        $this->assertEquals(1, $this->tenant1->dashboardlinked);
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => null, 'name' => $tenant1pagename]));
        $this->assertEmpty($this->get_blocks($tenant1page->id));

        // Check tenant2 was not modified.
        $this->tenant2 = (new \tool_tenant\manager())->get_tenant($this->tenant2->id)->to_record();
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => null, 'name' => $tenant2pagename]));
        $this->assertEquals(1, $this->tenant2->dashboardlinked);

        // Check that tenant2 admin has no permissions to execute the WS for tenant1.
        $this->setUser($this->tenant2admin);
        $this->expectException('required_capability_exception');
        \tool_tenant\external\dashboard\update_dashboardlinked::execute(1, $this->tenant1->id);
    }

    /**
     * Test for tool_tenant_update_dashboardlinked WS un-linking a tenant
     *
     * @covers \tool_tenant\external\dashboard\update_dashboardlinked
     */
    public function test_update_dashboardlinked_unlink(): void {
        // Sanity check.
        $this->assertEquals(0, $this->tenant1->dashboardlinked);
        $this->assertEquals(1, $this->tenant2->dashboardlinked);
        $tenant1pagename = dashboard_manager::generate_tenant_page_name($this->tenant1->id);
        $tenant2pagename = dashboard_manager::generate_tenant_page_name($this->tenant2->id);

        // Execute WS as tenant2 admin.
        $this->setUser($this->tenant2admin);
        $result = tool_tenant_external::clean_returnvalue(
            \tool_tenant\external\dashboard\update_dashboardlinked::execute_returns(),
            \tool_tenant\external\dashboard\update_dashboardlinked::execute(0, $this->tenant2->id)
        );
        $this->assertTrue($result);

        // Check tenant2 dashboard is now un-linked.
        $this->tenant2 = (new \tool_tenant\manager())->get_tenant($this->tenant2->id)->to_record();
        $this->assertEquals(0, $this->tenant2->dashboardlinked);

        // Check tenant1 was not modified.
        $this->tenant1 = (new \tool_tenant\manager())->get_tenant($this->tenant1->id)->to_record();
        $this->assertEquals(0, $this->tenant1->dashboardlinked);

        // Check that tenant2 admin has no permissions to execute the WS for tenant1.
        $this->setUser($this->tenant1admin);
        $this->expectException('required_capability_exception');
        \tool_tenant\external\dashboard\update_dashboardlinked::execute(0, $this->tenant2->id);
        $this->assertTrue(true);
    }

    /**
     * Generates a dashboard block.
     *
     * @param int $parentcontextid
     * @param string|null $subpagepattern
     */
    private function create_dashboard_mockblock(int $parentcontextid, ?string $subpagepattern): void {
        global $DB;

        $blockinstance = [
            'blockname' => 'mockblock',
            'parentcontextid' => $parentcontextid,
            'timecreated' => time(),
            'timemodified' => time(),
            'defaultweight' => 1,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $subpagepattern,
            'showinsubcontexts' => 0,
            'defaultregion' => 'side-pre',
            'configdata' => null,
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

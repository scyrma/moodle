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

namespace tool_custompage\local\helpers;

use advanced_testcase;
use context_system;
use tool_custompage_generator;
use tool_tenant_generator;
use tool_tenant\tenancy;
use tool_custompage\local\models\{audience, page as model};
use tool_custompage\tool_custompage\audience\manual;

/**
 * Unit tests for the helper class
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\local\helpers\page
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class page_test extends advanced_testcase {

    /**
     * Test creating a page in the current tenant
     */
    public function test_create_page_current_tenant(): void {
        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user]] = $generator->create_tenant_and_users(1);

        tenancy::set_switched_tenant_id($tenant->id);
        $this->setUser($user);

        $page = page::create_page((object) ['name' => 'My page', 'weight' => 0]);
        $this->assertEquals($tenant->id, $page->get('tenantid'));
    }

    /**
     * Test creating a page in specific tenant
     */
    public function test_create_page_specific_tenant(): void {
        $this->resetAfterTest();

        $defaulttenantid = tenancy::get_default_tenant_id();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user]] = $generator->create_tenant_and_users(1);

        tenancy::set_switched_tenant_id($tenant->id);
        $this->setUser($user);

        // Tenant property should be that of the users own tenant.
        $page = page::create_page((object) ['name' => 'My first page', 'weight' => 0, 'tenantid' => $defaulttenantid]);
        $this->assertEquals($tenant->id, $page->get('tenantid'));

        // Now test as admin.
        $this->setAdminUser();
        $page = page::create_page((object) ['name' => 'My second page', 'weight' => 0, 'tenantid' => $defaulttenantid]);
        $this->assertEquals($defaulttenantid, $page->get('tenantid'));
    }

    /**
     * Test creating a global page (without tenant property)
     */
    public function test_create_page_global(): void {
        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user]] = $generator->create_tenant_and_users(1);

        tenancy::set_switched_tenant_id($tenant->id);
        $this->setUser($user);

        // Tenant property should be that of the users own tenant.
        $page = page::create_page((object) ['name' => 'My first page', 'weight' => 0, 'tenantid' => 0]);
        $this->assertEquals($tenant->id, $page->get('tenantid'));

        // Now test as admin.
        $this->setAdminUser();
        $page = page::create_page((object) ['name' => 'My second page', 'weight' => 0, 'tenantid' => 0]);
        $this->assertEquals(0, $page->get('tenantid'));
    }

    /**
     * Test updating a page
     */
    public function test_update_page(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'title' => 'My cool page', 'weight' => 0]);

        // Update the page, then re-read it.
        page::update_page($page->get('id'), (object) [
            'name' => 'My page updated',
            'title' => 'My cool page updated',
            'weight' => 3,
        ]);

        $page->read();

        $this->assertEquals('My page updated', $page->get('name'));
        $this->assertEquals('My cool page updated', $page->get('title'));
        $this->assertEquals(3, $page->get('weight'));
    }

    /**
     * Test page deletion
     */
    public function test_delete_page(): void {
        global $DB;

        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        $page = $generator->create_page(['name' => 'My page', 'weight' => 0]);
        $generator->create_page_block(['pageid' => $page->get('id'), 'blockname' => 'html']);

        $pageid = $page->get('id');

        $deleted = page::delete_page($page);
        $this->assertTrue($deleted);

        $this->assertFalse($DB->record_exists(model::TABLE, ['id' => $pageid]));
        $this->assertFalse($DB->record_exists('block_instances', [
            'pagetypepattern' => 'admin-tool-custompage',
            'subpagepattern' => $pageid,
        ]));
    }

    /**
     * Test page duplication, preserving tenant
     */
    public function test_duplicate_page_preserve_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 0]);
        $generator->create_audience(['pageid' => $pageone->get('id'), 'classname' => manual::class, 'configdata' => [
            'users' => [-1],
        ]]);
        $generator->create_page_block(['pageid' => $pageone->get('id'), 'blockname' => 'html']);

        // Duplicate it.
        $pagecopy = page::duplicate_page($pageone);
        $this->assertNotEquals($pagecopy->get('id'), $pageone->get('id'));
        $this->assertEquals($pageone->get('tenantid'), $pagecopy->get('tenantid'));
        $this->assertFalse($pagecopy->get('global'));
        $this->assertEquals('Page one (copy)', $pagecopy->get('name'));

        // Audience should have been duplicated.
        $pagecopyaudiences = audience::get_records(['pageid' => $pagecopy->get('id')]);
        $this->assertCount(1, $pagecopyaudiences);

        [$audienceone] = array_values($pagecopyaudiences);
        $this->assertEquals(manual::class, $audienceone->get('classname'));
        $this->assertEquals(['users' => [-1]], json_decode($audienceone->get('configdata'), true));

        // Blocks should have been duplicated.
        $pagecopyblocks = page::get_page_blocks($pagecopy);
        $this->assertCount(1, $pagecopyblocks);

        [$blockone] = array_values($pagecopyblocks);
        $this->assertEquals('html', $blockone->blockname);
    }

    /**
     * Test duplicate a page, preserving global state
     */
    public function test_duplicate_page_preserve_global(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 0, 'global' => true]);
        $generator->create_audience(['pageid' => $pageone->get('id'), 'classname' => manual::class, 'configdata' => [
            'users' => [-1],
        ]]);

        // Duplicate it.
        $pagecopy = page::duplicate_page($pageone);
        $this->assertEquals($pageone->get('tenantid'), $pagecopy->get('tenantid'));
        $this->assertTrue($pagecopy->get('global'));

        // Audience should have been duplicated.
        $pagecopyaudiences = audience::get_records(['pageid' => $pagecopy->get('id')]);
        $this->assertCount(1, $pagecopyaudiences);

        [$audienceone] = array_values($pagecopyaudiences);
        $this->assertEquals(manual::class, $audienceone->get('classname'));
        $this->assertEquals(['users' => [-1]], json_decode($audienceone->get('configdata'), true));
    }

    /**
     * Test duplicate a page validating all permissions.
     */
    public function test_duplicate_page_validating(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user1, $user2, $user3]] = $generator->create_tenant_and_users(3);

        // Create two roles with different specific capabilities.
        $context = context_system::instance();
        $roleskip = create_role('User skip', 'userskip', 'User skiping validation');
        $roleedit = create_role('User edit block', 'usereditblocks', 'User only can edit blocks');
        assign_capability('tool/custompage:skipblockvalidation', CAP_ALLOW, $roleskip, $context->id);
        assign_capability('moodle/block:edit', CAP_ALLOW, $roleedit, $context->id);

        // Assign roles to users created previously.
        $this->getDataGenerator()->role_assign($roleskip, $user1->id);
        $this->getDataGenerator()->role_assign($roleedit, $user2->id);

        // Create two custom pages.
        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $pageone = $generator->create_page([
            'name' => 'Page one duplicate',
            'weight' => 0,
            'tenantid' => $tenant->id,
            'global' => true
        ]);
        $pagetwo = $generator->create_page([
            'name' => 'Page two duplicate',
            'weight' => 0,
            'tenantid' => $tenant->id,
            'global' => false
        ]);

        // Add some blocks to previous created pages.
        $generator->create_page_block(['pageid' => $pageone->get('id'), 'blockname' => 'html']);
        $generator->create_page_block(['pageid' => $pageone->get('id'), 'blockname' => 'tags']);
        $generator->create_page_block(['pageid' => $pagetwo->get('id'), 'blockname' => 'html']);
        $generator->create_page_block(['pageid' => $pagetwo->get('id'), 'blockname' => 'tags']);

        // Change current user to $user1.
        $this->setUser($user1);
        // Duplicate process with user with skipblockvalidation capability.
        $pagecopy = page::duplicate_page($pageone);
        $this->assertEquals($pageone->get('tenantid'), $pagecopy->get('tenantid'));
        $this->assertTrue($pagecopy->get('global'));
        $pageonecopyblocks = page::get_page_blocks($pagecopy);
        $this->assertCount(2, $pageonecopyblocks);

        // Change current user to $user1.
        $this->setUser($user2);
        // Duplicate process with user with block:edit capability.
        $pagecopy1 = page::duplicate_page($pagetwo, false);
        $this->assertEquals($pagetwo->get('tenantid'), $pagecopy1->get('tenantid'));
        $this->assertFalse($pagecopy1->get('global'));
        $pagetwocopyblocks = page::get_page_blocks($pagecopy1);
        $this->assertCount(2, $pagetwocopyblocks);

        // Change current user to $user3.
        $this->setUser($user3);
        // Duplicate process with user without skipblockvalidation/block:edit capabilities.
        $pagecopy2 = page::duplicate_page($pagetwo, false);
        $this->assertEquals($pagetwo->get('tenantid'), $pagecopy2->get('tenantid'));
        $this->assertFalse($pagecopy2->get('global'));
        $pagenocopyblocks = page::get_page_blocks($pagecopy2);
        $this->assertCount(0, $pagenocopyblocks);
    }

    /**
     * Test page duplication, enabling it's global state
     */
    public function test_duplicate_page_to_global(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 0, 'global' => false]);
        $generator->create_audience(['pageid' => $pageone->get('id'), 'classname' => manual::class, 'configdata' => [
            'users' => [-1],
        ]]);

        // Duplicate it.
        $pagecopy = page::duplicate_page($pageone, true);
        $this->assertEquals($pageone->get('tenantid'), $pagecopy->get('tenantid'));
        $this->assertTrue($pagecopy->get('global'));

        // Audience should not have been duplicated.
        $pagecopyaudiences = audience::get_records(['pageid' => $pagecopy->get('id')]);
        $this->assertEmpty($pagecopyaudiences);
    }

    /**
     * Test global page duplication, into the current tenant
     */
    public function test_duplicate_page_to_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant = $generator->create_tenant();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 0, 'tenantid' => $tenant->id, 'global' => true]);
        $generator->create_audience(['pageid' => $pageone->get('id'), 'classname' => manual::class, 'configdata' => [
            'users' => [-1],
        ]]);

        // Duplicate it.
        $pagecopy = page::duplicate_page($pageone, false);
        $this->assertEquals(tenancy::get_tenant_id(), $pagecopy->get('tenantid'));
        $this->assertfalse($pagecopy->get('global'));

        // Audience should not have been duplicated.
        $pagecopyaudiences = audience::get_records(['pageid' => $pagecopy->get('id')]);
        $this->assertEmpty($pagecopyaudiences);
    }

    /**
     * Test retrieving page block instance records
     */
    public function test_get_page_blocks(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        // Add a couple of blocks to first page.
        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 1]);
        $generator->create_page_block(['pageid' => $pageone->get('id'), 'blockname' => 'html']);
        $generator->create_page_block(['pageid' => $pageone->get('id'), 'blockname' => 'calendar_month']);

        // Add another to the second page.
        $pagetwo = $generator->create_page(['name' => 'Page two', 'weight' => 2]);
        $generator->create_page_block(['pageid' => $pagetwo->get('id'), 'blockname' => 'tags']);

        $pageoneblocks = page::get_page_blocks($pageone);
        $this->assertCount(2, $pageoneblocks);

        [$blockone, $blocktwo] = array_values($pageoneblocks);
        $this->assertEquals('html', $blockone->blockname);
        $this->assertEquals('calendar_month', $blocktwo->blockname);

        $pagetwoblocks = page::get_page_blocks($pagetwo);
        $this->assertCount(1, $pagetwoblocks);

        [$blockthree] = array_values($pagetwoblocks);
        $this->assertEquals('tags', $blockthree->blockname);
    }

    /**
     * Test retrieving page block class instances
     */
    public function test_get_page_blocks_instance(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        // Add a couple of blocks to page.
        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 1]);
        $generator->create_page_block(['pageid' => $pageone->get('id'), 'blockname' => 'html']);
        $generator->create_page_block(['pageid' => $pageone->get('id'), 'blockname' => 'calendar_month']);

        $blockinstances = page::get_page_blocks($pageone, true);
        $this->assertContainsOnlyInstancesOf(\block_base::class, $blockinstances);
    }

    /**
     * Test transforming block instance records to appropriate class instance
     */
    public function test_get_block_instances(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 1]);
        $blockone = $generator->create_page_block(['pageid' => $pageone->get('id'), 'blockname' => 'html']);

        [$blockinstance] = page::get_block_instances([$blockone]);
        $this->assertInstanceOf(\block_html::class, $blockinstance);
    }
}

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

namespace tool_custompage\privacy;

use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use tool_custompage_generator;
use tool_custompage\local\models\{page, audience};
use tool_custompage\tool_custompage\audience\manual;
use tool_tenant_generator;

/**
 * Unit tests for the plugin privacy provider
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\privacy\provider
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider_test extends provider_testcase {

    /**
     * Test provider metadata
     */
    public function test_get_metadata(): void {
        $collection = new collection('tool_custompage');
        $metadata = provider::get_metadata($collection)->get_collection();

        $this->assertCount(2, $metadata);
        $this->assertContainsOnlyInstancesOf(database_table::class, $metadata);

        $this->assertEquals(page::TABLE, $metadata[0]->get_name());
        $this->assertEquals(audience::TABLE, $metadata[1]->get_name());
    }

    /**
     * Test getting contexts for user who created a page and/or audience
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        // Page.
        $pageuser = $this->getDataGenerator()->create_user();
        $this->setUser($pageuser);
        $page = $generator->create_page(['name' => 'My page', 'weight' => 1]);

        $contextlist = $this->get_contexts_for_userid((int) $pageuser->id, 'tool_custompage');
        $this->assertCount(1, $contextlist);
        $this->assertInstanceOf(context_system::class, $contextlist->current());

        // Audience.
        $audienceuser = $this->getDataGenerator()->create_user();
        $this->setUser($audienceuser);
        $audience = $generator->create_audience(['pageid' => $page->get('id'), 'configdata' => []]);

        $contextlist = $this->get_contexts_for_userid((int) $audienceuser->id, 'tool_custompage');
        $this->assertCount(1, $contextlist);
        $this->assertInstanceOf(context_system::class, $contextlist->current());
    }

    /**
     * Test getting users with data in given context
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        // Page.
        $pageuser = $this->getDataGenerator()->create_user();
        $this->setUser($pageuser);
        $page = $generator->create_page(['name' => 'My page', 'weight' => 1]);

        // Audience.
        $audienceuser = $this->getDataGenerator()->create_user();
        $this->setUser($audienceuser);
        $audience = $generator->create_audience(['pageid' => $page->get('id'), 'configdata' => []]);

        $userlist = new userlist(context_system::instance(), 'tool_custompage');
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing([$pageuser->id, $audienceuser->id], $userlist->get_userids());
    }

    /**
     * Test exporting user data
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user]] = $generator->create_tenant_and_users(1);

        $this->setUser($user);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        $page = $generator->create_page(['name' => 'My page', 'title' => 'My cool page', 'weight' => 1]);
        $audience = $generator->create_audience([
            'pageid' => $page->get('id'),
            'classname' => manual::class,
            'configdata' => ['users' => $user->id]],
        );

        $context = context_system::instance();
        $this->export_context_data_for_user((int) $user->id, $context, 'tool_custompage');

        /** @var \core_privacy\tests\request\content_writer $writer */
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $subcontext = provider::get_export_subcontext($page);

        // Page.
        $pagedata = $writer->get_data($subcontext);
        $this->assertEquals($tenant->id, $pagedata->tenantid);
        $this->assertEquals('No', $pagedata->global);
        $this->assertEquals('My page', $pagedata->name);
        $this->assertEquals('My cool page', $pagedata->title);
        $this->assertEquals(1, $pagedata->weight);
        $this->assertEquals($user->id, $pagedata->usercreated);
        $this->assertEquals($user->id, $pagedata->usermodified);
        $this->assertNotEmpty($pagedata->timecreated);
        $this->assertNotEmpty($pagedata->timemodified);

        // Audience.
        $audiencedata = $writer->get_related_data($subcontext, 'audiences')->data;
        $this->assertCount(1, $audiencedata);

        $audiencedata = reset($audiencedata);
        $this->assertEquals(manual::class, $audiencedata->classname);
        $this->assertEquals(json_encode(['users' => $user->id]), $audiencedata->configdata);
        $this->assertEquals($user->id, $audiencedata->usercreated);
        $this->assertEquals($user->id, $audiencedata->usermodified);
        $this->assertNotEmpty($audiencedata->timecreated);
        $this->assertNotEmpty($audiencedata->timemodified);
    }
}

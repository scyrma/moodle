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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * File containing privacy tests for tool_tenant\privacy\provider class.
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve <adrian@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_tenant\manager;
use tool_tenant\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the tool_tenant\privacy\provider class methods.
 *
 * @package    tool_tenant
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_tenant_privacy_provider_testcase extends provider_testcase {
    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test getting the contexts given a user ID.
     */
    public function test_get_contexts_for_userid() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        // The only context we need.
        $context = \context_system::instance();

        // Set user1 as the creator of the tenants.
        $this->setUser($user1);
        $manager = new manager();
        $tenant1id = $this->generator->create_tenant((object) ['categoryid' => $category1->id])->id;

        // Allocate user2 to the tenant.
        $manager->allocate_user($user2->id, $tenant1id, 'tool_tenant', 'manual');

        // Create another tenant with user 1.
        $tenant2id = $this->generator->create_tenant((object) ['categoryid' => $category2->id])->id;

        $contextlist = provider::get_contexts_for_userid($user2->id);

        // User 2 should only be in one context, the user context.
        $this->assertEquals(1, $contextlist->count());
        $this->assertEquals($context->id, $contextlist->current()->id);
        // User 1 should be also only be in one context.
        $contextlist = provider::get_contexts_for_userid($user2->id);
        $this->assertEquals(1, $contextlist->count());
        $this->assertEquals($context->id, $contextlist->current()->id);
    }

    /**
     * Test exporting user data.
     */
    public function test_export_user_data() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $context = \context_system::instance();

        // Set user1 as the creator of the tenants.
        $this->setUser($user1);
        $manager = new manager();
        $tenant1 = $this->generator->create_tenant();
        $tenant2 = $this->generator->create_tenant();

        $manager->allocate_user($user2->id, $tenant1->id, 'tool_tenant', 'manual');
        $manager->allocate_user($user3->id, $tenant1->id, 'tool_tenant', 'manual');
        $manager->allocate_user($user4->id, $tenant2->id, 'tool_tenant', 'manual');

        $contextlist = new approved_contextlist($user2, 'tool_tenant', [$context->id]);
        provider::export_user_data($contextlist);
        $data = writer::with_context($context)->get_data([
            get_string('tenants', 'tool_tenant'),
            $tenant1->name . $tenant1->id
        ]);

        // User 2 has been assigned to a tenant and only has one entry.
        $this->assertEquals($tenant1->name, $data->tenant_name);
        $this->assertCount(1, $data->tenant_users);

        $contextlist = new approved_contextlist($user1, 'tool_tenant', [$context->id]);
        provider::export_user_data($contextlist);
        $data = writer::with_context($context)->get_data([
            get_string('tenants', 'tool_tenant'),
            $tenant1->name . $tenant1->id
        ]);

        // User 1 has been assigning users to tenants and so assigned two to this tenant.
        $this->assertEquals($tenant1->name, $data->tenant_name);
        $this->assertCount(2, $data->tenant_users);

        $data = writer::with_context($context)->get_data([
            get_string('tenants', 'tool_tenant'),
            $tenant2->name . $tenant2->id
        ]);

        // And assigned one user to this tenant.
        $this->assertEquals($tenant2->name, $data->tenant_name);
        $this->assertCount(1, $data->tenant_users);
    }

    /**
     * Test deleting all the data for all users in a context.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $userctx = context_user::instance($user1->id);

        $context = \context_system::instance();

        // Set user1 as the creator of the tenants.
        $this->setUser($user1);
        $manager = new manager();
        $tenant1id = $this->generator->create_tenant()->id;
        $tenant2id = $this->generator->create_tenant()->id;

        $manager->allocate_user($user2->id, $tenant1id, 'tool_tenant', 'manual');
        $manager->allocate_user($user3->id, $tenant1id, 'tool_tenant', 'manual');
        $manager->allocate_user($user4->id, $tenant2id, 'tool_tenant', 'manual');

        // Check current records for users.
        // User 1 allocated three users to different tenants.
        $this->assertCount(3, $DB->get_records('tool_tenant_user', ['usermodified' => $user1->id]));
        // User 2 has only been assigned to the one tenant.
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user2->id]));

        // Try sending the wrong context and then check for the users again.
        provider::delete_data_for_all_users_in_context($userctx);
        $this->assertCount(3, $DB->get_records('tool_tenant_user', ['usermodified' => $user1->id]));
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user2->id]));

        // Okay with the system context. All records in tool_tenant_user should be removed.
        provider::delete_data_for_all_users_in_context($context);
        $this->assertEmpty($DB->get_records('tool_tenant_user'));
    }

    /**
     * Test deleting the data for a specific user.
     */
    public function test_delete_data_for_user() {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $userctx = context_user::instance($user1->id);
        $userctx2 = context_user::instance($user2->id);

        $context = \context_system::instance();

        // Set user1 as the creator of the tenants.
        $this->setUser($user1);
        $manager = new manager();
        $tenant1id = $this->generator->create_tenant()->id;
        $tenant2id = $this->generator->create_tenant()->id;

        $manager->allocate_user($user2->id, $tenant1id, 'tool_tenant', 'manual');
        $manager->allocate_user($user3->id, $tenant1id, 'tool_tenant', 'manual');
        $manager->allocate_user($user4->id, $tenant2id, 'tool_tenant', 'manual');

        // Check current records for users.
        // User 1 allocated three users to different tenants.
        $this->assertCount(3, $DB->get_records('tool_tenant_user', ['usermodified' => $user1->id]));
        // User 2 has only been assigned to the one tenant.
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user2->id]));

        // Wrong context first.
        $contextlist = new approved_contextlist($user2, 'tool_tenant', [$userctx->id, $userctx2->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertCount(3, $DB->get_records('tool_tenant_user', ['usermodified' => $user1->id]));
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user2->id]));

        // This time the correct context is passed through (eventually).
        $contextlist = new approved_contextlist($user2, 'tool_tenant', [$userctx->id, $userctx2->id, $context->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertCount(2, $DB->get_records('tool_tenant_user', ['usermodified' => $user1->id]));
        $this->assertEmpty($DB->get_records('tool_tenant_user', ['userid' => $user2->id]));
    }

    /**
     * Test getting all of the users in a context.
     */
    public function test_get_users_in_context() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $userctx = context_user::instance($user1->id);
        $userctx2 = context_user::instance($user2->id);

        $context = \context_system::instance();

        // Set user1 as the creator of the tenants.
        $this->setUser($user1);
        $manager = new manager();
        $tenant1id = $this->generator->create_tenant()->id;
        $tenant2id = $this->generator->create_tenant()->id;

        $manager->allocate_user($user2->id, $tenant1id, 'tool_tenant', 'manual');
        $manager->allocate_user($user3->id, $tenant1id, 'tool_tenant', 'manual');
        $manager->allocate_user($user4->id, $tenant2id, 'tool_tenant', 'manual');

        // Wrong type of context.
        $userlist = new userlist($userctx, 'tool_tenant');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());
        // With the correct context.
        $userlist = new userlist($context, 'tool_tenant');
        provider::get_users_in_context($userlist);
        $this->assertCount(4, $userlist->get_userids());
    }

    /**
     * Test deleting a list of users.
     */
    public function test_delete_data_for_users() {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $userctx = context_user::instance($user1->id);
        $userctx2 = context_user::instance($user2->id);

        $context = \context_system::instance();

        // Set user1 as the creator of the tenants.
        $this->setUser($user1);
        $manager = new manager();
        $tenant1id = $this->generator->create_tenant()->id;
        $tenant2id = $this->generator->create_tenant()->id;

        $manager->allocate_user($user2->id, $tenant1id, 'tool_tenant', 'manual');
        $manager->allocate_user($user3->id, $tenant1id, 'tool_tenant', 'manual');
        $manager->allocate_user($user4->id, $tenant2id, 'tool_tenant', 'manual');

        $this->assertCount(3, $DB->get_records('tool_tenant_user', ['usermodified' => $user1->id]));
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user2->id]));
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user3->id]));
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user4->id]));

        // Try the wrong context first.
        $userlist = new approved_userlist($userctx, 'tool_tenant', [$user1->id, $user2->id, $user4->id]);
        provider::delete_data_for_users($userlist);
        // Nothing has changed.
        $this->assertCount(3, $DB->get_records('tool_tenant_user', ['usermodified' => $user1->id]));
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user2->id]));
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user3->id]));
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user4->id]));

        // Now with the correct context.
        $userlist = new approved_userlist($context, 'tool_tenant', [$user1->id, $user2->id, $user4->id]);
        provider::delete_data_for_users($userlist);
        // There should only be on user left in a tenant.
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['usermodified' => $user1->id]));
        $this->assertEmpty($DB->get_records('tool_tenant_user', ['userid' => $user2->id]));
        // This user is still as is.
        $this->assertCount(1, $DB->get_records('tool_tenant_user', ['userid' => $user3->id]));
        $this->assertEmpty($DB->get_records('tool_tenant_user', ['userid' => $user4->id]));
    }
}

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
 * File containing privacy tests for tool_certification\privacy\provider class.
 *
 * @package    tool_certification
 * @category   test
 * @author     2019 Adrian Greeve <adrian@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_certification\api;
use tool_certification\constants;
use tool_certification\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Tests for the tool_certification\privacy\provider class methods.
 *
 * @package    tool_certification
 * @author     2019 Adrian Greeve <adrian@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_provider_testcase extends advanced_testcase {

    /** @var tool_certification_generator generator */
    private $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->resetAfterTest();
    }

    /**
     * Test getting the contexts given a user ID.
     */
    public function test_get_contexts_for_userid(): void {
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // The only context we need.
        $systemcontext = context_system::instance();

        $certification1 = $this->generator->generate_certification();

        // Allocate user1 to the certification.
        $this->generator->allocate_user($user1->id, $certification1->get('id'));

        $contextlist = provider::get_contexts_for_userid($user1->id);

        // User1 should only be in one context.
        $this->assertEquals(1, $contextlist->count());
        $this->assertEquals($systemcontext->id, $contextlist->current()->id);

        // User2 should not be in any context since it is unrelated to any certification.
        $contextlist = provider::get_contexts_for_userid($user2->id);
        $this->assertEquals(0, $contextlist->count());
    }

    /**
     * Test exporting user data.
     */
    public function test_export_user_data(): void {
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $context = context_system::instance();

        $certification1 = $this->generator->generate_certification([
            'certification_tags' => ['Cool', 'Beans'],
        ]);
        $certification2 = $this->generator->generate_certification();

        $userdata = (object) [
            'certificationid' => $certification1->get('id'),
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        $certificationuser1 = api::allocate_user($certification1, $userdata);

        $userdata = (object) [
            'certificationid' => $certification2->get('id'),
            'userid' => $user2->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        $certificationuser2 = api::allocate_user($certification2, $userdata);

        \tool_certification\api::set_user_as_certified($user2->id, $certification2->get('id'));

        $contextlist = new approved_contextlist($user1, 'tool_certification', [$context->id]);
        provider::export_user_data($contextlist);

        $contextpath = [
            get_string('certifications', 'tool_certification'),
            $certification1->get('fullname') . $certification1->get('id')
        ];
        $data = writer::with_context($context)->get_data($contextpath);

        // User1 has been allocated to certification1 and has no completed sets.
        $this->assertEquals($certification1->get('fullname'), $data->certification_name);
        $this->assertCount(1, $data->allocations);
        $this->assertCount(0, $data->completions);

        // Check tags were exported.
        $tags = writer::with_context($context)->get_related_data($contextpath, 'tags');
        $this->assertEquals(['Cool', 'Beans'], $tags);

        $contextlist = new approved_contextlist($user2, 'tool_certification', [$context->id]);
        provider::export_user_data($contextlist);
        $data = writer::with_context($context)->get_data([
            get_string('certifications', 'tool_certification'),
            $certification2->get('fullname') . $certification2->get('id')
        ]);

        // User2 have been allocated to certification1 and has completed the program/certification.
        $this->assertEquals($certification2->get('fullname'), $data->certification_name);
        $this->assertCount(1, $data->allocations);
        $this->assertCount(1, $data->completions);
    }

    /**
     * Test deleting all the data for all users in a context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        $context = context_system::instance();
        $userctx = context_user::instance($user1->id);

        $certification1 = $this->generator->generate_certification();
        $certification2 = $this->generator->generate_certification();

        $this->generator->allocate_user($user2->id, $certification1->get('id'));
        $this->generator->allocate_user($user3->id, $certification1->get('id'));
        $this->generator->allocate_user($user4->id, $certification2->get('id'));

        // Check current records count for users.
        $this->assertCount(3, $DB->get_records('tool_certification_users'));

        // Try sending the wrong context and then check for the users again.
        provider::delete_data_for_all_users_in_context($userctx);

        $this->assertCount(3, $DB->get_records('tool_certification_users'));

        // Okay with the system context. All records in tool_certification_user should be removed.
        provider::delete_data_for_all_users_in_context($context);
        $this->assertEmpty($DB->get_records('tool_certification_users'));
    }

    /**
     * Test deleting the data for a specific user.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        $userctx = context_user::instance($user1->id);
        $userctx2 = context_user::instance($user2->id);

        $context = context_system::instance();

        $certification1 = $this->generator->generate_certification();
        $certification2 = $this->generator->generate_certification();

        $this->generator->allocate_user($user2->id, $certification1->get('id'));
        $this->generator->allocate_user($user3->id, $certification1->get('id'));
        $this->generator->allocate_user($user4->id, $certification2->get('id'));

        // Test total allocations count.
        $this->assertCount(3, $DB->get_records('tool_certification_users'));

        // User2 has only been allocated once.
        $this->assertCount(1, $DB->get_records('tool_certification_users', ['userid' => $user2->id]));

        // Wrong contexts first. Allocations should be unchanged.
        $contextlist = new approved_contextlist($user2, 'tool_certification', [$userctx->id, $userctx2->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertCount(3, $DB->get_records('tool_certification_users'));
        $this->assertCount(1, $DB->get_records('tool_certification_users', ['userid' => $user2->id]));

        // This time the correct context is passed through (eventually).
        $contextlist = new approved_contextlist($user2, 'tool_certification', [$userctx->id, $userctx2->id, $context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertCount(2, $DB->get_records('tool_certification_users'));
        $this->assertEmpty($DB->get_records('tool_certification_users', ['userid' => $user2->id]));
    }

    /**
     * Test getting all of the users in a context.
     */
    public function test_get_users_in_context(): void {
        $user1 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $userctx = context_user::instance($user1->id);
        $context = context_system::instance();

        $certification2 = $this->generator->generate_certification();
        $certification1 = $this->generator->generate_certification();

        $this->generator->allocate_user($user2->id, $certification1->get('id'));
        $this->generator->allocate_user($user3->id, $certification1->get('id'));
        $this->generator->allocate_user($user4->id, $certification2->get('id'));

        // Wrong type of context.
        $userlist = new userlist($userctx, 'tool_certification');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());

        // With the correct context.
        $userlist = new userlist($context, 'tool_certification');
        provider::get_users_in_context($userlist);
        $this->assertCount(3, $userlist->get_userids());
    }

    /**
     * Test deleting a list of users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        $userctx = context_user::instance($user1->id);
        $context = context_system::instance();

        $certification2 = $this->generator->generate_certification();
        $certification1 = $this->generator->generate_certification();

        $this->generator->allocate_user($user2->id, $certification1->get('id'));
        $this->generator->allocate_user($user3->id, $certification1->get('id'));
        $this->generator->allocate_user($user4->id, $certification2->get('id'));

        $this->assertCount(3, $DB->get_records('tool_certification_users'));
        $this->assertCount(1, $DB->get_records('tool_certification_users', ['userid' => $user2->id]));
        $this->assertCount(1, $DB->get_records('tool_certification_users', ['userid' => $user3->id]));
        $this->assertCount(1, $DB->get_records('tool_certification_users', ['userid' => $user4->id]));

        // Try the wrong context first.
        $userlist = new approved_userlist($userctx, 'tool_certification', [$user1->id, $user2->id, $user4->id]);
        provider::delete_data_for_users($userlist);

        // Nothing has changed.
        $this->assertCount(3, $DB->get_records('tool_certification_users'));
        $this->assertCount(1, $DB->get_records('tool_certification_users', ['userid' => $user2->id]));
        $this->assertCount(1, $DB->get_records('tool_certification_users', ['userid' => $user3->id]));
        $this->assertCount(1, $DB->get_records('tool_certification_users', ['userid' => $user4->id]));

        // Now with the correct context.
        $userlist = new approved_userlist($context, 'tool_certification', [$user1->id, $user2->id, $user4->id]);
        provider::delete_data_for_users($userlist);

        // There should only be on user left in a certification.
        $this->assertCount(1, $DB->get_records('tool_certification_users'));
        $this->assertEmpty($DB->get_records('tool_certification_users', ['userid' => $user2->id]));

        // This user is still as is.
        $this->assertCount(1, $DB->get_records('tool_certification_users', ['userid' => $user3->id]));
        $this->assertEmpty($DB->get_records('tool_certification_users', ['userid' => $user4->id]));
    }
}

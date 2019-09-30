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
 * File containing privacy tests for tool_program privacy provider class.
 *
 * @package     tool_program
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve <adrian@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_program\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Tests for the privacy provider class methods.
 *
 * @covers      \tool_program\privacy\provider
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Adrian Greeve <adrian@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_program_privacy_provider_testcase extends advanced_testcase {
    /** @var tool_program_generator generator */
    private $generator;
    /** @var tool_certification_generator */
    private $certgenerator;

    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->certgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->resetAfterTest();
    }

    /**
     * Test getting the contexts given a user ID.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // The only context we need.
        $systemcontext = context_system::instance();

        $program1 = $this->generator->generate_program();

        // Allocate user1 to the program.
        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);

        $contextlist = provider::get_contexts_for_userid($user1->id);

        // User1 should only be in one context.
        $this->assertEquals(1, $contextlist->count());
        $this->assertEquals($systemcontext->id, $contextlist->current()->id);

        // User2 should not be in any context since it is unrelated to any program.
        $contextlist = provider::get_contexts_for_userid($user2->id);
        $this->assertEquals(0, $contextlist->count());
    }

    /**
     * Test exporting user data.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $context = context_system::instance();

        $program1 = $this->generator->generate_program();
        $program2 = $this->generator->generate_program_with_base_set();
        $certification1 = $this->certgenerator->generate_certification();

        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $this->generator->allocate_user_to_program($program2->get('id'), $user2->id);
        $this->generator->allocate_user_to_program($program2->get('id'), $user2->id, $certification1->get('id'));
        $this->generator->complete_program($program2, $user2->id);

        $contextlist = new approved_contextlist($user1, 'tool_program', [$context->id]);
        provider::export_user_data($contextlist);
        $data = writer::with_context($context)->get_data([
            get_string('programs', 'tool_program'),
            $program1->get('fullname') . $program1->get('id')
        ]);

        // User1 has been allocated to program1 and has no completed sets.
        $this->assertEquals($program1->get('fullname'), $data->program_name);
        $this->assertCount(1, $data->allocations);
        $this->assertCount(0, $data->completions);

        $contextlist = new approved_contextlist($user2, 'tool_program', [$context->id]);
        provider::export_user_data($contextlist);
        $data = writer::with_context($context)->get_data([
            get_string('programs', 'tool_program'),
            $program2->get('fullname') . $program2->get('id')
        ]);

        // User2 have been allocated to program1 twice (different origins) and has one completed set (the base set).
        $this->assertEquals($program2->get('fullname'), $data->program_name);
        $this->assertCount(2, $data->allocations);
        $this->assertCount(1, $data->completions);
    }

    /**
     * Test deleting all the data for all users in a context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        $userctx = context_user::instance($user1->id);

        $context = context_system::instance();

        $program1 = $this->generator->generate_program();
        $program2 = $this->generator->generate_program();

        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);
        $this->generator->allocate_user_to_program($program1->get('id'), $user3->id);
        $this->generator->allocate_user_to_program($program2->get('id'), $user4->id);

        // Check current records count for users.
        $this->assertCount(3, $DB->get_records('tool_program_users'));

        // Try sending the wrong context and then check for the users again.
        provider::delete_data_for_all_users_in_context($userctx);

        $this->assertCount(3, $DB->get_records('tool_program_users'));

        // Okay with the system context. All records in tool_program_user should be removed.
        provider::delete_data_for_all_users_in_context($context);
        $this->assertEmpty($DB->get_records('tool_program_users'));
    }

    /**
     * Test deleting the data for a specific user.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        $userctx = context_user::instance($user1->id);
        $userctx2 = context_user::instance($user2->id);

        $context = context_system::instance();

        $program1 = $this->generator->generate_program();
        $program2 = $this->generator->generate_program();

        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);
        $this->generator->allocate_user_to_program($program1->get('id'), $user3->id);
        $this->generator->allocate_user_to_program($program2->get('id'), $user4->id);

        // Test total allocations count.
        $this->assertCount(3, $DB->get_records('tool_program_users'));

        // User2 has only been allocated once.
        $this->assertCount(1, $DB->get_records('tool_program_users', ['userid' => $user2->id]));

        // Wrong contexts first. Allocations should be unchanged.
        $contextlist = new approved_contextlist($user2, 'tool_program', [$userctx->id, $userctx2->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertCount(3, $DB->get_records('tool_program_users'));
        $this->assertCount(1, $DB->get_records('tool_program_users', ['userid' => $user2->id]));

        // This time the correct context is passed through (eventually).
        $contextlist = new approved_contextlist($user2, 'tool_program', [$userctx->id, $userctx2->id, $context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertCount(2, $DB->get_records('tool_program_users'));
        $this->assertEmpty($DB->get_records('tool_program_users', ['userid' => $user2->id]));
    }

    /**
     * Test getting all of the users in a context.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        $userctx = context_user::instance($user1->id);
        $context = context_system::instance();

        $program1 = $this->generator->generate_program();
        $program2 = $this->generator->generate_program();

        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);
        $this->generator->allocate_user_to_program($program1->get('id'), $user3->id);
        $this->generator->allocate_user_to_program($program2->get('id'), $user4->id);

        // Wrong type of context.
        $userlist = new userlist($userctx, 'tool_program');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());

        // With the correct context.
        $userlist = new userlist($context, 'tool_program');
        provider::get_users_in_context($userlist);
        $this->assertCount(3, $userlist->get_userids());
    }

    /**
     * Test deleting a list of users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        $userctx = context_user::instance($user1->id);
        $context = context_system::instance();

        $program1 = $this->generator->generate_program();
        $program2 = $this->generator->generate_program();

        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);
        $this->generator->allocate_user_to_program($program1->get('id'), $user3->id);
        $this->generator->allocate_user_to_program($program2->get('id'), $user4->id);

        $this->assertCount(3, $DB->get_records('tool_program_users'));
        $this->assertCount(1, $DB->get_records('tool_program_users', ['userid' => $user2->id]));
        $this->assertCount(1, $DB->get_records('tool_program_users', ['userid' => $user3->id]));
        $this->assertCount(1, $DB->get_records('tool_program_users', ['userid' => $user4->id]));

        // Try the wrong context first.
        $userlist = new approved_userlist($userctx, 'tool_program', [$user1->id, $user2->id, $user4->id]);
        provider::delete_data_for_users($userlist);

        // Nothing has changed.
        $this->assertCount(3, $DB->get_records('tool_program_users'));
        $this->assertCount(1, $DB->get_records('tool_program_users', ['userid' => $user2->id]));
        $this->assertCount(1, $DB->get_records('tool_program_users', ['userid' => $user3->id]));
        $this->assertCount(1, $DB->get_records('tool_program_users', ['userid' => $user4->id]));

        // Now with the correct context.
        $userlist = new approved_userlist($context, 'tool_program', [$user1->id, $user2->id, $user4->id]);
        provider::delete_data_for_users($userlist);

        // There should only be on user left in a program.
        $this->assertCount(1, $DB->get_records('tool_program_users'));
        $this->assertEmpty($DB->get_records('tool_program_users', ['userid' => $user2->id]));

        // This user is still as is.
        $this->assertCount(1, $DB->get_records('tool_program_users', ['userid' => $user3->id]));
        $this->assertEmpty($DB->get_records('tool_program_users', ['userid' => $user4->id]));
    }
}

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
 * Privacy provider tests.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\privacy\provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_userlist;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\privacy\provider
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Test provider::get_metadata
     */
    public function test_get_metadata() {
        $collection = new collection('tool_dynamicrule');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(1, $itemcollection);

        $table = array_pop($itemcollection);
        $this->assertEquals('tool_dynamicrule_match', $table->get_name());
        $this->assertEquals('privacy:metadata:tool_dynamicrule_match', $table->get_summary());

        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('ruleid', $privacyfields);
        $this->assertArrayHasKey('matchedtime', $privacyfields);
        $this->assertArrayHasKey('unmatchedtime', $privacyfields);
    }

    /**
     * Test for provider::get_contexts_for_userid().
     */
    public function test_get_contexts_for_userid() {

        // Add a rule to the site.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Perth', 'city_op' => 2];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule1->id, $configdata);
        $configdata = ['subject' => 'Perth user',
            'body' => ['text' => 'Congratulations, you are from Perth.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        // Another rule that will not match.
        $this->get_generator()->create_rule();

        // Create a user who will match a rule.
        $user = $this->getDataGenerator()->create_user(['city' => 'Perth']);

        // Check there are no contexts with user data before matching a rule.
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);

        // Process the rule.
        \tool_dynamicrule\api::enable_rule($rule1->id);
        \tool_dynamicrule\api::process_rule(\tool_dynamicrule\api::get_rule($rule1->id));

        // Check the context supplied is correct.
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);

        $contextids = $contextlist->get_contextids();
        $this->assertContains(\context_system::instance()->id, $contextids);
    }

    /**
     * Test that only users within a context are fetched.
     */
    public function test_get_users_in_context() {
        global $DB;

        $component = 'tool_dynamicrule';

        $this->setAdminUser();
        $admin = \core_user::get_user_by_username('admin');
        // Create user1.
        $user1 = $this->getDataGenerator()->create_user(['city' => 'Perth']);
        $usercontext1 = \context_user::instance($user1->id);

        // Add a rule to the site.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Perth', 'city_op' => 2];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule1->id, $configdata);
        $configdata = ['subject' => 'Perth user',
            'body' => ['text' => 'Congratulations, you are from Perth.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        // Process the rule.
        \tool_dynamicrule\api::enable_rule($rule1->id);
        \tool_dynamicrule\api::process_rule(\tool_dynamicrule\api::get_rule($rule1->id));

        // The user list for usercontext1 should not return any users.
        $userlist1 = new \core_privacy\local\request\userlist($usercontext1, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);

        // The user list for systemcontext should have user1.
        $userlist2 = new \core_privacy\local\request\userlist(\context_system::instance(), $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
    }

    /**
     * Test for provider::export_user_data().
     */
    public function test_export_user_data() {

        // Create users who will match the rules.
        $user1 = $this->getDataGenerator()->create_user(['city' => 'Perth', 'firstname' => 'Bob']);
        $user2 = $this->getDataGenerator()->create_user(['city' => 'Perth']);

        // Add a rule to the site.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Perth', 'city_op' => 2];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule1->id, $configdata);
        $configdata = ['subject' => 'Perth user',
            'body' => ['text' => 'Congratulations, you are from Perth.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        // Process the rule.
        \tool_dynamicrule\api::enable_rule($rule1->id);
        \tool_dynamicrule\api::process_rule(\tool_dynamicrule\api::get_rule($rule1->id));

        // Add another rule to the site.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => 'firstname', 'firstname_value' => 'Bob', 'firstname_op' => 2];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule2->id, $configdata);
        $configdata = ['subject' => 'Hi Bob',
            'body' => ['text' => 'Congratulations, you are Bob', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule2->id, $configdata);

        // Process the rule.
        \tool_dynamicrule\api::enable_rule($rule2->id);
        \tool_dynamicrule\api::process_rule(\tool_dynamicrule\api::get_rule($rule2->id));

        // Export all of the data for the context for user 1.
        $context = \context_system::instance();
        $contextpath = [get_string('pluginname', 'tool_dynamicrule')];

        $this->export_context_data_for_user($user1->id, $context, 'tool_dynamicrule');
        $writer = \core_privacy\local\request\writer::with_context($context);

        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data($contextpath);
        $this->assertCount(2, $data->matches);

        list($match1, $match2) = $data->matches;

        $this->assertEquals($rule1->name, $match1['rulename']);
        $this->assertArrayHasKey('matchedtime', $match1);
        $this->assertArrayHasKey('unmatchedtime', $match1);

        $this->assertEquals($rule2->name, $match2['rulename']);
        $this->assertArrayHasKey('matchedtime', $match2);
        $this->assertArrayHasKey('unmatchedtime', $match2);
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        // Add a rule to the site.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Perth', 'city_op' => 2];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule1->id, $configdata);
        $configdata = ['subject' => 'Perth user',
            'body' => ['text' => 'Congratulations, you are from Perth.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        // Create users who will match the rule.
        $user1 = $this->getDataGenerator()->create_user(['city' => 'Perth']);
        $user2 = $this->getDataGenerator()->create_user(['city' => 'Perth']);

        // Process the rule.
        \tool_dynamicrule\api::enable_rule($rule1->id);
        \tool_dynamicrule\api::process_rule(\tool_dynamicrule\api::get_rule($rule1->id));

        // Before deletion, we should have 2 matches the first rule.
        $count = $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id]);
        $this->assertEquals(2, $count);

        // Delete data on user context will do nothing.
        $usercontext1 = \context_user::instance($user1->id);
        provider::delete_data_for_all_users_in_context($usercontext1);

        $count = $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id]);
        $this->assertEquals(2, $count);

        // Delete data on system context will delete rule matches.
        $context = \context_system::instance();
        provider::delete_data_for_all_users_in_context($context);

        // After deletion, all matches should have been deleted.
        $count = $DB->count_records('tool_dynamicrule_match');
        $this->assertEquals(0, $count);
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user() {
        global $DB;

        // Add a rule to the site.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Perth', 'city_op' => 2];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule1->id, $configdata);
        $configdata = ['subject' => 'Perth user',
            'body' => ['text' => 'Congratulations, you are from Perth.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        // Create users who will match the rule.
        $user1 = $this->getDataGenerator()->create_user(['city' => 'Perth']);
        $user2 = $this->getDataGenerator()->create_user(['city' => 'Perth']);
        $usercontext1 = \context_user::instance($user1->id);

        // Process the rule.
        \tool_dynamicrule\api::enable_rule($rule1->id);
        \tool_dynamicrule\api::process_rule(\tool_dynamicrule\api::get_rule($rule1->id));

        // Before deletion we should have 2 matches.
        $count = $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id]);
        $this->assertEquals(2, $count);

        // Delete data without context will do nothing.
        $context = \context_system::instance();
        $contextlist = new \core_privacy\local\request\approved_contextlist($user1, 'tool_dynamicrule', []);
        provider::delete_data_for_user($contextlist);

        $count = $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id]);
        $this->assertEquals(2, $count);

        // Delete data on user context will do nothing.
        $context = \context_system::instance();
        $contextlist = new \core_privacy\local\request\approved_contextlist($user1, 'tool_dynamicrule', [$usercontext1->id]);
        provider::delete_data_for_user($contextlist);

        $count = $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id]);
        $this->assertEquals(2, $count);

        $context = \context_system::instance();
        $contextlist = new \core_privacy\local\request\approved_contextlist($user1, 'tool_dynamicrule', [$context->id]);
        provider::delete_data_for_user($contextlist);

        // After deletion, the matches for the first user should have been deleted.
        $count = $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user1->id]);
        $this->assertEquals(0, $count);

        // Check the matches for the other user are still there.
        $count = $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id, 'userid' => $user2->id]);
        $this->assertEquals(1, $count);
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users() {

        $component = 'tool_dynamicrule';

        // Add a rule to the site.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Perth', 'city_op' => 2];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule1->id, $configdata);
        $configdata = ['subject' => 'Perth user',
            'body' => ['text' => 'Congratulations, you are from Perth.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        // Create users who will match the rule.
        $user1 = $this->getDataGenerator()->create_user(['city' => 'Perth']);
        $user2 = $this->getDataGenerator()->create_user(['city' => 'Perth']);
        $usercontext1 = \context_user::instance($user1->id);

        // Process the rule.
        \tool_dynamicrule\api::enable_rule($rule1->id);
        \tool_dynamicrule\api::process_rule(\tool_dynamicrule\api::get_rule($rule1->id));

        $systemcontext = \context_system::instance();
        $userlist1 = new \core_privacy\local\request\userlist($systemcontext, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(2, $userlist1);

        // Convert $userlist1 into an approved_contextlist.
        $approvedlist1 = new approved_userlist($systemcontext, $component, $userlist1->get_userids());

        // Delete using delete_data_for_user.
        provider::delete_data_for_users($approvedlist1);
        // Re-fetch users in systemcontext.
        $userlist1 = new \core_privacy\local\request\userlist($systemcontext, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);
    }
}

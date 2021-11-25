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
 * File contains the unit tests for condition\user_created class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Sumit Negi
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\tool_dynamicrule\condition\user_created;
use tool_dynamicrule\tool_dynamicrule\outcome\notification;
use tool_dynamicrule\rule;

/**
 * Unit tests for condition\user_created class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\user_created
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\condition_sql
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Sumit Negi
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_condition_user_created_testcase extends advanced_testcase {

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $condition = user_created::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $condition->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $condition = user_created::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = user_created::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }
    /**
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_past_and_inlast() {

        $user1 = $this->getDataGenerator()->create_user(['timecreated' => strtotime('-1 week')]);
        $user2 = $this->getDataGenerator()->create_user(['timecreated' => strtotime('-1 week')]);
        $user3 = $this->getDataGenerator()->create_user(['timecreated' => time()]);
        $user4 = $this->getDataGenerator()->create_user(['timecreated' => strtotime('-2 month')]);

        // For more than 1 day.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['usercreatetype' => user_created::CREATE_TYPE_PAST,
            'usercreaterelative' => '1 day'];
        user_created::create($rule1->id, $configdata);
        $this->assertEquals(4, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user4->id, get_admin()->id], array_column($users, 'id'));

        // In the last month.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['usercreatetype' => user_created::CREATE_TYPE_INLAST,
            'usercreaterelative' => '1 month'];
        user_created::create($rule2->id, $configdata);
        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['usercreatetype' => user_created::CREATE_TYPE_PAST,
            'usercreaterelative' => '1 week'];
        $condition1 = user_created::create($rule1->id, $configdata);

        $this->assertEquals('Users who were created prior to the last 1 week', $condition1->get_description());

        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['usercreatetype' => user_created::CREATE_TYPE_INLAST,
            'usercreaterelative' => '1 week'];
        $condition2 = user_created::create($rule2->id, $configdata);

        $this->assertEquals('Users who were created during the last 1 week', $condition2->get_description());

        $rule3 = $this->get_generator()->create_rule();
        $configdata = ['usercreatetype' => user_created::CREATE_TYPE_INLAST,
            'usercreaterelative' => '2 week'];
        $condition3 = user_created::create($rule3->id, $configdata);

        $this->assertEquals('Users who were created during the last 2 weeks', $condition3->get_description());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        $condition = user_created::instance();

        // Admin user.
        self::setAdminUser();
        $this->assertTrue($condition->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($condition->user_can_add());

        // Grant priveleges to user.
        $context = context_system::instance();
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/user:viewalldetails', CAP_ALLOW, $roleid, $context->id);
        assign_capability('tool/tenant:browseusers', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertTrue($condition->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $configdata = ['usercreatetype' => user_created::CREATE_TYPE_PAST,
            'usercreaterelative' => '1 day'];
        $condition = user_created::instance();

        // Admin user.
        self::setAdminUser();
        $this->assertTrue($condition->user_can_edit($configdata));

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($condition->user_can_edit($configdata));

        // Grant priveleges to user.
        $context = context_system::instance();
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/user:viewalldetails', CAP_ALLOW, $roleid, $context->id);
        assign_capability('tool/tenant:browseusers', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertTrue($condition->user_can_edit($configdata));
    }

    /**
     * Test user_created event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\user_created
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing () {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user(['timecreated' => strtotime('-4 day')]);
        $user2 = $this->getDataGenerator()->create_user(['timecreated' => strtotime('-3 day')]);
        $user3 = $this->getDataGenerator()->create_user(['timecreated' => strtotime('-2 day')]);

        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['usercreatetype' => user_created::CREATE_TYPE_INLAST,
            'usercreaterelative' => '1 week'];
        user_created::create($rule0->id, $configdata);

        $configdata = ['subject' => 'User created',
            'body' => ['text' => 'Dear {{userfullname}}, Your account is created on LMS.', 'format' => FORMAT_MOODLE]];
        notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $messages = $sink->get_messages();
        $sink->close();

        // Check outcomes (note that notification ordering is undefined so we just assert the users received the messages).
        $this->assertCount(3, $messages);

        $userids = array_column($messages, 'useridto');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id], $userids);

        $subjects = array_column($messages, 'subject');
        $this->assertEquals(array_fill(0, 3, 'User created'), $subjects);

        // Check matches record presence.
        $this->assertEquals(3, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }
}

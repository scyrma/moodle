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
 * File contains the unit tests for condition\user_last_login class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\tool_dynamicrule\condition\user_last_login;

/**
 * Unit tests for condition\user_last_login class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\user_last_login
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\condition_sql
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_condition_user_last_login_testcase extends advanced_testcase {

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
     * Test get_title
     */
    public function test_get_title() {
        $condition = user_last_login::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = user_last_login::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {

        $condition = user_last_login::instance();
        $configform = ['lastloginrelative' => ''];
        $this->assertArrayHasKey('lastloginrelative', $condition->validate_config_form($configform));

        $configform = ['lastloginrelative' => '- 1 year'];
        $this->assertArrayNotHasKey('lastloginrelative', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_past_and_inlast() {

        $user1 = $this->getDataGenerator()->create_user(['lastaccess' => strtotime('-1 week')]);
        $user2 = $this->getDataGenerator()->create_user(['lastaccess' => strtotime('-1 week')]);
        $user3 = $this->getDataGenerator()->create_user(['lastaccess' => time()]);

        // For more than 1 day.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => user_last_login::LAST_LOGIN_TYPE_PAST,
                       'lastloginrelative' => '1 day'];
        user_last_login::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'id'));

        // In the last month.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => user_last_login::LAST_LOGIN_TYPE_INLAST,
                       'lastloginrelative' => '1 month'];
        user_last_login::create($rule2->id, $configdata, true);

        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id], array_column($users, 'id'));
    }

    /**
     * Test condition matching for ever and never operators.
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_ever_and_never() {

        $user1 = $this->getDataGenerator()->create_user(['lastaccess' => time()]);
        $user2 = $this->getDataGenerator()->create_user(['lastaccess' => time()]);
        $user3 = $this->getDataGenerator()->create_user();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => user_last_login::LAST_LOGIN_TYPE_EVER];
        \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'id'));

        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => user_last_login::LAST_LOGIN_TYPE_NEVER];
        \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule2->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([get_admin()->id, $user3->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => user_last_login::LAST_LOGIN_TYPE_PAST,
                       'lastloginrelative' => '1 week'];
        $condition1 = user_last_login::create($rule1->id, $configdata);

        $this->assertEquals('Users who last logged in prior to the last 1 week', $condition1->get_description());

        $rule3 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => user_last_login::LAST_LOGIN_TYPE_INLAST,
                       'lastloginrelative' => '1 week'];
        $condition3 = user_last_login::create($rule3->id, $configdata);

        $this->assertEquals('Users who logged in during the last 1 week', $condition3->get_description());

        $rule4 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => user_last_login::LAST_LOGIN_TYPE_NEVER,
                       'lastloginrelative' => ''];
        $condition4 = user_last_login::create($rule4->id, $configdata);

        $this->assertStringMatchesFormat('%a never logged in', $condition4->get_description());

        $rule5 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => user_last_login::LAST_LOGIN_TYPE_EVER,
                       'lastloginrelative' => ''];
        $condition5 = user_last_login::create($rule5->id, $configdata);

        $this->assertStringMatchesFormat('%a logged in at least once', $condition5->get_description());

        $rule6 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => user_last_login::LAST_LOGIN_TYPE_INLAST,
            'lastloginrelative' => '2 week'];
        $condition6 = user_last_login::create($rule6->id, $configdata);

        $this->assertEquals('Users who logged in during the last 2 weeks', $condition6->get_description());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        $condition = user_last_login::instance();

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
        $configdata = ['lastlogintype' => user_last_login::LAST_LOGIN_TYPE_PAST,
                       'lastloginrelative' => '1 day'];
        $condition = user_last_login::instance();

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
}

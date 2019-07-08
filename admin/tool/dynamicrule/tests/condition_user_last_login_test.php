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
 * File contains the unit tests for condition\user_last_login class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition\user_last_login class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\user_last_login
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\condition_sql
 * @copyright  2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Test get_title
     */
    public function test_get_title() {
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\user_last_login();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\user_last_login();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $this->resetAfterTest();

        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\user_last_login();
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

        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user(['lastaccess' => strtotime('-1 week')]);
        $user2 = $this->getDataGenerator()->create_user(['lastaccess' => strtotime('-1 week')]);
        $user3 = $this->getDataGenerator()->create_user(['lastaccess' => time()]);

        // For more than 1 day.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_PAST,
                       'lastloginrelative' => '1 day'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals([$user1->id, $user2->id], array_column($users, 'id'), '', 0, 10, true);

        // In the last month.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_INLAST,
                       'lastloginrelative' => '1 month'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule2->id, $configdata, true);

        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEquals([$user1->id, $user2->id, $user3->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test condition matching for ever and never operators.
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_ever_and_never() {

        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user(['lastaccess' => time()]);
        $user2 = $this->getDataGenerator()->create_user(['lastaccess' => time()]);
        $user3 = $this->getDataGenerator()->create_user();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_EVER];
        \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals([$user1->id, $user2->id], array_column($users, 'id'), '', 0, 10, true);

        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_NEVER];
        \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule2->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEquals([get_admin()->id, $user3->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        $this->resetAfterTest();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_PAST,
                       'lastloginrelative' => '1 week'];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule1->id, $configdata);

        $this->assertStringMatchesFormat('%a ago', $condition1->get_description());

        $rule3 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_INLAST,
                       'lastloginrelative' => '1 week'];
        $condition3 = \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule3->id, $configdata);

        $this->assertStringMatchesFormat('%a in the last %a', $condition3->get_description());

        $rule4 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_NEVER,
                       'lastloginrelative' => ''];
        $condition4 = \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule4->id, $configdata);

        $this->assertStringMatchesFormat('%a never logged in', $condition4->get_description());

        $rule5 = $this->get_generator()->create_rule();
        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_EVER,
                       'lastloginrelative' => ''];
        $condition5 = \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule5->id, $configdata);

        $this->assertStringMatchesFormat('%a logged in at least once', $condition5->get_description());
    }
}

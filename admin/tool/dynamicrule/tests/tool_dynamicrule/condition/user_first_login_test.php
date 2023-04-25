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

namespace tool_dynamicrule\tool_dynamicrule\condition;

use advanced_testcase;
use context_system;
use tool_dynamicrule\rule;
use tool_dynamicrule_generator;

/**
 * Unit tests for condition\user_first_login class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\user_first_login
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\condition_sql
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_first_login_test extends advanced_testcase {

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
        $condition = user_first_login::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $condition->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = user_first_login::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = user_first_login::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = user_first_login::instance();

        // No type selected.
        $configform = ['firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_NONE];
        $this->assertArrayHasKey('firstloginformgroup', $condition->validate_config_form($configform));

        // Avoid 0 hours/days/weeks.
        $configform = ['firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_INLAST, 'firstloginrelative' => '0 days'];
        $this->assertArrayHasKey('firstloginformgroup', $condition->validate_config_form($configform));

        // Selected dates are within range.
        $configform = ['firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_RANGE, 'first_login_startdate' => time(),
        'first_login_enddate' => time() - DAYSECS];
        $this->assertArrayHasKey('first_login_enddate', $condition->validate_config_form($configform));

        // Valid cases.
        $configform = ['firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_EVER];
        $this->assertArrayNotHasKey('lastloginformgroup', $condition->validate_config_form($configform));

        $configform = ['firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_NEVER, 'firstloginrelative' => '1 year'];
        $this->assertArrayNotHasKey('lastloginformgroup', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users(): void {

        $user1 = $this->getDataGenerator()->create_user(['firstaccess' => strtotime('-2 week')]);
        $user2 = $this->getDataGenerator()->create_user(['firstaccess' => time() - HOURSECS]);
        $user3 = $this->getDataGenerator()->create_user();

        // In the last 1 week.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = [
            'firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_INLAST,
            'firstloginrelative' => '1 week',
        ];
        user_first_login::create($rule1->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals([$user2->id], array_column($users, 'id'));

        // Anytime.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = [
            'firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_EVER,
        ];
        user_first_login::create($rule2->id, $configdata);
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'id'));

        // Never.
        $rule3 = $this->get_generator()->create_rule();
        $configdata = [
            'firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_NEVER,
        ];
        user_first_login::create($rule3->id, $configdata);
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule3->id);
        $this->assertEqualsCanonicalizing([get_admin()->id, $user3->id], array_column($users, 'id'));

        // Range.
        $rule4 = $this->get_generator()->create_rule();
        $configdata = [
            'firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_RANGE,
            'first_login_startdate' => strtotime('-3 week'),
            'first_login_enddate' => strtotime('-1 week'),
        ];
        user_first_login::create($rule4->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule4->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule4->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        // In the last 1 month.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = [
            'firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_INLAST,
            'firstloginrelative' => '1 month',
        ];
        $condition1 = user_first_login::create($rule1->id, $configdata);
        $this->assertEquals('Users who have first logged in the last 1 month', $condition1->get_description());

        // Anytime.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = [
            'firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_EVER,
        ];
        $condition2 = user_first_login::create($rule2->id, $configdata);
        $this->assertEquals('Users who have first logged in at least once', $condition2->get_description());

        // Never.
        $rule3 = $this->get_generator()->create_rule();
        $configdata = [
            'firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_NEVER,
        ];
        $condition3 = user_first_login::create($rule3->id, $configdata);
        $this->assertEquals('Users who have never logged in', $condition3->get_description());

        // Range.
        $rule4 = $this->get_generator()->create_rule();
        $configdata = [
            'firstlogintype' => user_first_login::FIRST_LOGIN_TYPE_RANGE,
            'first_login_startdate' => strtotime('3 March 2022'),
            'first_login_enddate' => strtotime('3 March 2023'),
        ];
        $condition4 = user_first_login::create($rule4->id, $configdata);
        $this->assertEquals('Users who have first logged in between 3/03/22, 00:00 to 3/03/23, 00:00',
            $condition4->get_description());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $condition = user_first_login::instance();

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
    public function test_user_can_edit(): void {
        $configdata = [
            'lastlogintype' => user_first_login::FIRST_LOGIN_TYPE_INLAST,
            'lastloginrelative' => '1 day',
        ];
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

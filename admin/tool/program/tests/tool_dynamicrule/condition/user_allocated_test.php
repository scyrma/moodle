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

namespace tool_program\tool_dynamicrule\condition;

use advanced_testcase;
use tool_dynamicrule_generator;
use tool_program_generator;
use tool_dynamicrule\api;
use tool_dynamicrule\rule;
use tool_tenant_generator;

/**
 * Unit tests for condition user_allocated  class.
 *
 * @covers     \tool_program\tool_dynamicrule\condition\user_allocated
 * @package    tool_program
 * @group      tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_allocated_test extends advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;

    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /** @var tool_dynamicrule_generator */
    protected $drgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->resetAfterTest();
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $condition = user_allocated::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $condition->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = user_allocated::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = user_allocated::instance();
        $this->assertEquals(get_string('pluginname', 'tool_program'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = user_allocated::instance();
        $configform = ['programid' => 0];
        $this->assertArrayHasKey('programid', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     */
    public function test_get_matching_users(): void {
        self::getDataGenerator()->create_course();

        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $program2 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $this->generator->allocate_users_to_program($program2->get('id'), [$user2->id, $user3->id]);
        // Allocate one user with the startdate in the future, they will be considered "not allocated".
        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id, 0,
            ['startdate' => strtotime('+7 day'), 'startdatelocked' => 1]);

        // Users in program1.
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        user_allocated::create($rule1->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule1->id));
        $users = api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));

        // Users in program2.
        $rule2 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program2->get('id')];
        user_allocated::create($rule2->id, $configdata);

        $this->assertEquals(2, api::count_matching_users($rule2->id));
        $users = api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([$user2->id, $user3->id], array_column($users, 'id'));

        // Test users do not match if "on or after" date is set and they completed program before.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $date = strtotime('+1 year');
        $configdata = [
            'programid' => $program1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        user_allocated::create($rule->id, $configdata);

        $this->assertEquals(0, api::count_matching_users($rule->id));

        // Test users match if "on or after" date is set and they completed program after.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $date = strtotime('-1 year');
        $configdata = [
            'programid' => $program1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        user_allocated::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));
    }

    /**
     * Test shared program user allocated in shared rule
     */
    public function test_get_matching_users_user_allocated_shared_rule(): void {
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program1 = $this->generator->generate_program_with_course((object)['tenantid' => $sharedspaceid]);
        $this->generator->allocate_users_to_program($program1->get('id'), [$user1->id, $user21->id]);

        // Test users that matched the dynamic rule condition.
        $rule = $this->drgenerator->create_rule(['tenantid' => $sharedspaceid]);
        $configdata = ['programid' => $program1->get('id')];
        user_allocated::create($rule->id, $configdata);
        $this->assertEquals(2, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user21->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $program = $this->generator->generate_program();

        $rule = $this->drgenerator->create_rule();
        $configdata = ['programid' => $program->get('id')];
        /** @var user_allocated $condition */
        $condition = user_allocated::create($rule->id, $configdata);

        $expectedstr = get_string('conditionuserallocateddescription', 'tool_program', $program->get('fullname'));
        $this->assertEquals($expectedstr, $condition->get_description());

        // Description when date is enabled.
        $rule = $this->drgenerator->create_rule();
        $now = time();
        $configdata = [
            'programid' => $program->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $now,
        ];
        /** @var user_allocated $condition */
        $condition = user_allocated::create($rule->id, $configdata);

        $options = ['programname' => $program->get('fullname')];
        $options['conditiondate'] = userdate($now, get_string('strftimedatetimeshort'));
        $expected = get_string('conditionuserallocateddescriptionwithdate', 'tool_program', $options);
        $this->assertEquals($expected, $condition->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $condition1 = user_allocated::create($rule1->id, []);
        $this->assertFalse($condition1->is_configuration_valid());

        // Users in program1.
        $configdata = ['programid' => $program1->get('id')];
        $condition1 = user_allocated::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        // Test program is archived.
        \tool_program\api::archive_program($program1);
        $this->assertFalse($condition1->is_configuration_valid());

        // Restore program.
        \tool_program\api::restore_program($program1);
        $this->assertTrue($condition1->is_configuration_valid());

        // Delete program.
        \tool_program\api::archive_program($program1);
        \tool_program\api::delete_program($program1);
        $this->assertFalse($condition1->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        user_allocated::create($rule1->id, $configdata);
        $this->assertFalse(user_allocated::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertTrue(user_allocated::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        user_allocated::create($rule1->id, $configdata);
        $this->assertFalse(user_allocated::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertFalse(user_allocated::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(user_allocated::instance()->user_can_edit($configdata));
    }
}

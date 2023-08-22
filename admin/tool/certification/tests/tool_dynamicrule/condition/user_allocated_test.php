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

namespace tool_certification\tool_dynamicrule\condition;

use advanced_testcase;
use tool_certification_generator;
use tool_dynamicrule_generator;
use tool_program_generator;
use tool_tenant_generator;
use tool_certification\constants;
use tool_dynamicrule\api;
use tool_dynamicrule\rule;

/**
 * Unit tests for condition user_allocated  class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_allocated_test extends advanced_testcase {

    /** @var tool_certification_generator  */
    protected $generator;
    /** @var tool_program_generator  */
    protected $programgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_dynamicrule_generator */
    protected $drgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
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
        $this->assertEquals(get_string('pluginname', 'tool_certification'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = user_allocated::instance();
        $configform = ['certificationid' => 0];
        $this->assertArrayHasKey('certificationid', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     */
    public function test_get_matching_users(): void {
        self::getDataGenerator()->create_course();
        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);
        $certification1 = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $certification2 = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $status = constants::STATUS_OVERRIDE_DEFAULT;
        $user1params = (object) ['userid' => $user1->id, 'certificationid' => $certification1->get('id'), 'status' => $status];
        $user2params = (object) ['userid' => $user2->id, 'certificationid' => $certification2->get('id'), 'status' => $status];
        $user3params = (object) ['userid' => $user3->id, 'certificationid' => $certification2->get('id'), 'status' => $status];
        $user4params = (object) ['userid' => $user2->id, 'certificationid' => $certification1->get('id'),
            'status' => constants::STATUS_OVERRIDE_SUSPENDED];

        \tool_certification\api::allocate_user($certification1, $user1params);
        \tool_certification\api::allocate_user($certification2, $user2params);
        \tool_certification\api::allocate_user($certification2, $user3params);
        // Allocated one user as suspended.
        \tool_certification\api::allocate_user($certification1, $user4params);

        // Users on certification1.
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification1->get('id')];
        user_allocated::create($rule1->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule1->id));
        $users = api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));

        // Users on certification2.
        $rule2 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification2->get('id')];
        user_allocated::create($rule2->id, $configdata);

        $this->assertEquals(2, api::count_matching_users($rule2->id));
        $users = api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([$user2->id, $user3->id], array_column($users, 'id'));

        // Test users do not match if "on or after" date is set and they suspended certification before.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $date = strtotime('+1 year');
        $configdata = [
            'certificationid' => $certification2->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        user_allocated::create($rule->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        // Test users match if "on or after" date is set and they suspended certification after.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $date = strtotime('-1 year');
        $configdata = [
            'certificationid' => $certification1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        user_allocated::create($rule->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));
    }

    /**
     * Future allocation
     */
    public function test_get_matching_users_future_allocation(): void {
        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        $certification = $this->generator->generate_certification(
            ['tenantid' => $tenant->id, 'startdateabsolute' => strtotime('+5 day')]);

        $status = constants::STATUS_OVERRIDE_DEFAULT;
        $user1params = (object) ['userid' => $user1->id, 'certificationid' => $certification->get('id'), 'status' => $status];
        \tool_certification\api::allocate_user($certification, $user1params);

        // Create a rule for user_allocated.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        user_allocated::create($rule->id, $configdata);

        // User should not match because their start date is in the future.
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));

        // Update the certification to have the start date in the past.
        $data = $certification->to_record();
        $data->startdateabsolute = time() - DAYSECS;
        \tool_certification\api::update_certification_calendar($data);

        // User now matches the "allocated" condition.
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));
    }

    /**
     * Event observer for user allocation
     */
    public function test_event_observer_user_allocated(): void {
        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        // Create a rule.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $configdata = ['certificationid' => $certification->get('id')];
        user_allocated::create($rule->id, $configdata);
        $this->drgenerator->create_outcome_donothing($rule->id);

        // Allocate the user to the certification.
        $status = constants::STATUS_OVERRIDE_DEFAULT;
        $user1params = (object) ['userid' => $user1->id, 'certificationid' => $certification->get('id'), 'status' => $status];
        \tool_certification\api::allocate_user($certification, $user1params);

        // Make sure the rule was immediately triggerred for him.
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule->id);

        // Deallocate the user.
        \tool_certification\api::deallocate_user($certification->get('id'), $user1->id);

        // Make sure the rule was immediately unmatched for him.
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule->id, 1);
    }

    /**
     * Test shared certification user allocated in shared rule
     */
    public function test_get_matching_users_user_allocated_shared_rule(): void {
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11]] = $this->tenantgenerator->create_tenant_and_users(1);
        [$tenant2, [$user21]] = $this->tenantgenerator->create_tenant_and_users(1);
        $certification = $this->generator->generate_certification(['tenantid' => $sharedspaceid]);

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user11->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        $certificationuser1 = \tool_certification\api::allocate_user($certification, $userdata);
        $userdata->userid = $user21->id;
        $certificationuser2 = \tool_certification\api::allocate_user($certification, $userdata);

        // Test users that matched the dynamic rule condition.
        $rule = $this->drgenerator->create_rule(['tenantid' => $sharedspaceid]);
        $configdata = ['certificationid' => $certification->get('id')];
        user_allocated::create($rule->id, $configdata);
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user11->id, $user21->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $certification1 = $this->generator->generate_certification();
        $rule1 = $this->drgenerator->create_rule();

        $configdata = ['certificationid' => $certification1->get('id')];
        $condition1 = user_allocated::create($rule1->id, $configdata);

        $expectedstr = get_string('conditionuserallocateddescription', 'tool_certification',
            $certification1->get('fullname'));
        $this->assertEquals($expectedstr, $condition1->get_description());

        // Description when date is enabled.
        $rule = $this->drgenerator->create_rule();
        $now = time();
        $configdata = [
            'certificationid' => $certification1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $now,
        ];
        /** @var user_allocated $condition */
        $condition = user_allocated::create($rule->id, $configdata);
        $strid = 'conditionuserallocateddescriptionwithdate';
        $options = ['fullname' => $certification1->get('fullname')];
        $options['conditiondate'] = userdate($now, get_string('strftimedatetimeshort'));
        $expected = get_string($strid, 'tool_certification', $options);
        $this->assertEquals($expected, $condition->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $condition = user_allocated::create($rule1->id, []);
        $this->assertFalse($condition->is_configuration_valid());

        // Users in certification1.
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = user_allocated::create($rule1->id, $configdata);

        $this->assertTrue($condition->is_configuration_valid());

        // Test certification is archived.
        \tool_certification\api::archive_certification($certification->get('id'));
        $this->assertFalse($condition->is_configuration_valid());

        // Test certification is restored.
        \tool_certification\api::restore_certification($certification->get('id'));
        $this->assertTrue($condition->is_configuration_valid());

        // Archive the tenant and delete the tenant.
        $manager = new \tool_tenant\manager();
        $manager->archive_tenant($tenant->id);
        $manager->delete_tenant($tenant->id);
        $this->assertFalse($condition->is_configuration_valid());

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        // Delete certification.
        \tool_certification\api::archive_certification($certification->get('id'));
        $certification = new \tool_certification\certification($certification->get('id'));
        \tool_certification\api::delete_certification($certification);
        $this->assertFalse($condition->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        user_allocated::create($rule1->id, $configdata);

        $this->assertFalse(user_allocated::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertTrue(user_allocated::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        user_allocated::create($rule1->id, $configdata);
        $this->assertFalse(user_allocated::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertFalse(user_allocated::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(user_allocated::instance()->user_can_edit($configdata));
    }

    /**
     * Create a rule with two conditions and make sure events are triggerred in the correct order
     */
    public function test_user_allocated_and_not_certified(): void {
        // TODO WP-2923, this is the special test that makes sure that events are triggered in the correct order.
        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);

        // Create a certification, program and a course.
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $program = new \tool_program\persistent\program($certification->get('program'));

        // Create a rule with two conditions: User is allocated to the certification AND user is not certified.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        user_allocated::create($rule->id, $configdata);
        certification_not_certified::create($rule->id, $configdata);
        $this->drgenerator->create_outcome_donothing($rule->id);

        // Allocate user1 to the program directly.
        $this->programgenerator->allocate_user_to_program($program->get('id'), $user1->id);
        // Make user1 complete the course.
        $this->programgenerator->complete_program($program, $user1->id);

        // Allocate user1 and user2 to the certification, user1 will immediately be marked as certified.
        $status = constants::STATUS_OVERRIDE_DEFAULT;
        $user1params = (object) ['userid' => $user1->id, 'certificationid' => $certification->get('id'), 'status' => $status];
        $user2params = (object) ['userid' => $user2->id, 'certificationid' => $certification->get('id'), 'status' => $status];

        \tool_certification\api::allocate_user($certification, $user1params);
        \tool_certification\api::allocate_user($certification, $user2params);

        // This call will not be needed when both conditions listen to events.
        (new \tool_dynamicrule\task\process_rules())->execute();

        // Make sure the rule has NEVER been triggered for user1 but is triggered for user2.
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule->id);
        $this->drgenerator->assert_user_matched_rule($this, $user2->id, $rule->id);
    }
}

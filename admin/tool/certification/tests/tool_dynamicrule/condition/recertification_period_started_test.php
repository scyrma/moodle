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
use ReflectionClass;
use tool_certification_generator;
use tool_dynamicrule_generator;
use tool_tenant_generator;
use tool_certification\constants;
use tool_dynamicrule\api;

/**
 * Unit tests for condition recertification_period_started class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @covers     \tool_certification\tool_dynamicrule\condition\recertification_period_started
 * @author     2019 Mikel Martín <mikel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class recertification_period_started_test extends advanced_testcase {

    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_dynamicrule_generator */
    protected $drgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->resetAfterTest();
    }

    /**
     * tearDown.
     */
    public function tearDown(): void {
        if (extension_loaded('uopz')) {
            // Revert function overrides.
            uopz_unset_return('time');
        }
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = recertification_period_started::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = recertification_period_started::instance();
        $this->assertEquals(get_string('pluginname', 'tool_certification'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = recertification_period_started::instance();
        $configform = ['certificationid' => -2];
        $validationerrors = $condition->validate_config_form($configform);
        $this->assertArrayHasKey('certificationid', $validationerrors);
    }

    /**
     * Test get_config_attributes
     */
    public function test_get_config_attributes(): void {
        // The get_config_attributes method is protected. Use Reflection to call the method.
        $reflector = new ReflectionClass(recertification_period_started::class);
        $method = $reflector->getMethod('get_config_attributes');
        $method->setAccessible(true);

        $outcome = recertification_period_started::instance();
        $this->assertEmpty($method->invokeArgs($outcome, []));
    }

    /**
     * Test recertification period started condition matching
     */
    public function test_get_matching_users_given_recertification_period(): void {
        global $DB;

        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);
        $certification = $this->generator->generate_certification(
            ['expirydateabsolute' => strtotime('+5 day'), 'tenantid' => $tenant->id], true);

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        $certificationuser1 = \tool_certification\api::allocate_user($certification, $userdata);
        $id = $certificationuser1->get('id');
        $this->generator->allocate_user($user2->id, $certification->get('id'));
        $this->generator->allocate_user($user3->id, $certification->get('id'));

        // Test users that are Certified with recertification period started.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        recertification_period_started::create($rule->id, $configdata);
        $this->assertEmpty(api::count_matching_users($rule->id));

        // Certify user1 and user2.
        \tool_certification\api::set_user_as_certified($user1->id, $certification->get('id'));
        \tool_certification\api::set_user_as_certified($user2->id, $certification->get('id'));
        \tool_certification\api::allocate_recertification_users();

        // Test users that are Certified with recertification period started.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        recertification_period_started::create($rule->id, $configdata);

        $this->assertEquals(2, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'id'));

        // Suspend user1.
        $DB->update_record('tool_certification_users', (object)['id' => $id, 'status' => 0]);

        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        recertification_period_started::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user2->id], array_column($users, 'id'));

        // Test users that match if "on or after" date is set.
        \tool_certification\api::set_user_as_certified($user1->id, $certification->get('id'));

        // One start dates should be bigger than -1 week because the other one is suspended.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = [
            'certificationid' => $certification->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => strtotime('-1 week'),
        ];
        recertification_period_started::create($rule->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));

        $DB->update_record('tool_certification_users', (object)['id' => $id, 'status' => 1, 'isrecertification' => 1]);

        // Both start dates should be bigger than -1 week.
        (new \tool_certification\task\recertification())->execute();
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        recertification_period_started::create($rule->id, $configdata);
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));

        $DB->update_record('tool_certification_users', (object)['id' => $id, 'nextstartdate' => strtotime('+3 month')]);

        \tool_certification\api::set_user_as_certified($user2->id, $certification->get('id'));

        // One start date should be bigger than +1 day.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata['conditiondate'] = strtotime('+1 day');
        recertification_period_started::create($rule->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));

        // No start dates should be bigger than +5 months.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata['conditiondate'] = strtotime('+5 month');
        recertification_period_started::create($rule->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
    }

    /**
     * Test how rule triggers on recertification open condition.
     */
    public function test_recertification_open_condition(): void {

        // User completes the program.
        if (!extension_loaded('uopz')) {
            // Leave here if uopz is not loaded, as remaining test scenario depends on it.
            $this->markTestIncomplete('This test requires uopz php extension.');
        }

        $this->setAdminUser();
        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);

        $c1 = $this->generator->generate_certification([
            'tenantid' => $tenant->id,
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '30 days',
            'recertexpirydatetype' => constants::RECERT_EXPIRY_DATE_AFTR_PREV_COMPL,
            'recertexpirydaterelative' => '30 days',
            'recertstartdaterelative' => '15 days',
        ], true);
        $this->generator->allocate_users_to_certification($c1->get('id'), [$user1->id, $user2->id]);

        // Create rule for "recertification period start".
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        recertification_period_started::create($rule->id, ['certificationid' => $c1->get('id')]);
        $this->drgenerator->create_outcome_donothing($rule->id);

        // Mark first user as certified.
        \tool_certification\api::set_user_as_certified($user1->id, $c1->get('id'));

        // None of the users match the rule.
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule->id);
        $this->drgenerator->assert_user_did_not_match_rule($this, $user2->id, $rule->id);

        // Mark the second user as certified 10 days later.
        uopz_set_return('time', time() + 10 * DAYSECS);
        \tool_certification\api::set_user_as_certified($user2->id, $c1->get('id'));

        // 10 days later (20 in total) the recertification should start for the first user.
        uopz_set_return('time', time() + 10 * DAYSECS);
        (new \tool_certification\task\recertification())->execute();
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule->id, 1);
        $this->drgenerator->assert_user_did_not_match_rule($this, $user2->id, $rule->id);

        // 10 days later (30 in total) the recertification should start for the second user.
        uopz_set_return('time', time() + 10 * DAYSECS);
        (new \tool_certification\task\recertification())->execute();
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule->id, 1);
        $this->drgenerator->assert_user_matched_rule($this, $user2->id, $rule->id, 1);

        // Recertificate the first user.
        \tool_certification\api::set_user_as_certified($user1->id, $c1->get('id'));
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule->id, 1);

        // 1 day later (31 in total) make sure that the rule doesn't affect the users.
        uopz_set_return('time', time() + 1 * DAYSECS);
        (new \tool_certification\task\recertification())->execute();
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule->id, 1);
        $this->drgenerator->assert_user_matched_rule($this, $user2->id, $rule->id, 1);

        // 10 days later (41 in total) recertificate the second user.
        uopz_set_return('time', time() + 10 * DAYSECS);
        \tool_certification\api::set_user_as_certified($user2->id, $c1->get('id'));

        // Make sure that the rule doesn't affect the users. The recertification for the first user is still closed.
        (new \tool_certification\task\recertification())->execute();
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule->id, 1);
        $this->drgenerator->assert_user_did_not_match_rule($this, $user2->id, $rule->id, 1);

        // 10 days later (51 in total) second recertification for the first user opens.
        uopz_set_return('time', time() + 10 * DAYSECS);
        (new \tool_certification\task\recertification())->execute();
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule->id, 2);
        $this->drgenerator->assert_user_did_not_match_rule($this, $user2->id, $rule->id, 1);

        // 10 days later (61 in total) second recertification for the second user opens.
        uopz_set_return('time', time() + 10 * DAYSECS);
        (new \tool_certification\task\recertification())->execute();
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule->id, 2);
        $this->drgenerator->assert_user_matched_rule($this, $user2->id, $rule->id, 2);
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $certification1 = $this->generator->generate_certification();
        $rule1 = $this->drgenerator->create_rule();

        $configdata = ['certificationid' => $certification1->get('id')];
        /** @var recertification_period_started $condition1 */
        $condition1 = recertification_period_started::create($rule1->id, $configdata);

        $expectedstr = get_string('conditionrecertificationstarteddescription', 'tool_certification',
            ['fullname' => $certification1->get('fullname')]);
        $this->assertEquals($expectedstr, $condition1->get_description());

        // Description when date is enabled.
        $rule = $this->drgenerator->create_rule();
        $now = time();
        $configdata = [
            'certificationid' => $certification1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $now,
        ];
        /** @var recertification_period_started $condition */
        $condition = recertification_period_started::create($rule->id, $configdata);
        $strid = 'conditionrecertificationstarteddescriptionwithdate';
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
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $condition = recertification_period_started::create($rule->id, []);
        $this->assertFalse($condition->is_configuration_valid());

        // Users in certification1.
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = recertification_period_started::create($rule->id, $configdata);

        $this->assertTrue($condition->is_configuration_valid());

        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = recertification_period_started::create($rule->id, $configdata);

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
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        recertification_period_started::create($rule1->id, $configdata);

        $this->assertFalse(recertification_period_started::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertTrue(recertification_period_started::instance()->user_can_add());
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
        recertification_period_started::create($rule1->id, $configdata);
        $this->assertFalse(recertification_period_started::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertFalse(recertification_period_started::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(recertification_period_started::instance()->user_can_edit($configdata));
    }
}

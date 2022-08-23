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
use tool_certification\constants;
use tool_certification_generator;
use tool_dynamicrule\api;
use tool_dynamicrule_generator;
use tool_tenant_generator;

/**
 * Unit tests for condition recertification_grade_period_ended class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @author     2019 Mikel Martín <mikel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class recertification_grace_period_ended_test extends advanced_testcase {

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
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = recertification_grace_period_ended::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = recertification_grace_period_ended::instance();
        $this->assertEquals(get_string('pluginname', 'tool_certification'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = recertification_grace_period_ended::instance();
        $configform = ['certificationid' => -2];
        $validationerrors = $condition->validate_config_form($configform);
        $this->assertArrayHasKey('certificationid', $validationerrors);
    }

    /**
     * Test get_config_attributes
     */
    public function test_get_config_attributes(): void {
        // The get_config_attributes method is protected. Use Reflection to call the method.
        $reflector = new ReflectionClass(recertification_grace_period_ended::class);
        $method = $reflector->getMethod('get_config_attributes');
        $method->setAccessible(true);

        $outcome = recertification_grace_period_ended::instance();
        $this->assertEmpty($method->invokeArgs($outcome, []));
    }

    /**
     * Test recertification period started condition matching
     */
    public function test_get_matching_users_given_grace_period_ended(): void {
        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id], true);
        $certification->set('expirydateabsolute', strtotime('-1 week'));
        $certification->update();

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];

        $certificationuser1 = \tool_certification\api::allocate_user($certification, $userdata);
        $this->generator->allocate_user($user2->id, $certification->get('id'));

        // Certify user1.
        \tool_certification\api::set_user_as_certified($user1->id, $certification->get('id'));

        \tool_certification\api::allocate_recertification_users();

        // Test users that are certified with grace period ended.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        recertification_grace_period_ended::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));

        // Suspend user1.
        $certificationuser1->set('status', 0);
        $certificationuser1->update();

        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        recertification_grace_period_ended::create($rule->id, $configdata);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([], array_column($users, 'id'));

        // Test users that match if "on or before" date is set.
        $params2 = ['certificationid' => $certification->get('id'), 'userid' => $user2->id];
        $certificationuser2 = \tool_certification\certification_user::get_record($params2);

        // Certify user2.
        \tool_certification\api::set_user_as_certified($user2->id, $certification->get('id'));

        // There should be no graceperiod dates before -1 week.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $date = strtotime('-1 week');
        $configdata = [
            'certificationid' => $certification->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        recertification_grace_period_ended::create($rule->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));

        $certificationuser2->set('graceperiodends', strtotime('-1 day'));
        $certificationuser2->set('isrecertification', 1);
        $certificationuser2->set('currentprogramid', $certification->get('recertificationprogram'));
        $certificationuser2->update();

        $certificationuser1->set('graceperiodends', strtotime('+2 weeks'));
        $certificationuser1->set('currentprogramid', $certification->get('recertificationprogram'));
        $certificationuser1->update();

        // One grace period date should be before +1 day because the other is disabled.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata['conditiondate'] = strtotime('+3 week');
        recertification_grace_period_ended::create($rule->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));

        $certificationuser1->set('status', 1);
        $certificationuser1->update();

        // One grace period date should be before +1 day.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata['conditiondate'] = strtotime('+1 day');
        recertification_grace_period_ended::create($rule->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));

        // Both grace period date should be before +3 weeks.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata['conditiondate'] = strtotime('+3 week');
        recertification_grace_period_ended::create($rule->id, $configdata);
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $certification1 = $this->generator->generate_certification();
        $rule1 = $this->drgenerator->create_rule();

        $configdata = ['certificationid' => $certification1->get('id')];
        /** @var recertification_grace_period_ended $condition1 */
        $condition1 = recertification_grace_period_ended::create($rule1->id, $configdata);

        $expectedstr = get_string('conditionrecertificationgraceperiodendsdescription', 'tool_certification',
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
        /** @var recertification_grace_period_ended $condition */
        $condition = recertification_grace_period_ended::create($rule->id, $configdata);
        $strid = 'conditionrecertificationgraceperiodendsdescriptionwithdate';
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
        $condition = recertification_grace_period_ended::create($rule->id, []);
        $this->assertFalse($condition->is_configuration_valid());

        // Users in certification1.
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = recertification_grace_period_ended::create($rule->id, $configdata);

        $this->assertTrue($condition->is_configuration_valid());

        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = recertification_grace_period_ended::create($rule->id, $configdata);

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
        recertification_grace_period_ended::create($rule1->id, $configdata);

        $this->assertFalse(recertification_grace_period_ended::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertTrue(recertification_grace_period_ended::instance()->user_can_add());
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
        recertification_grace_period_ended::create($rule1->id, $configdata);
        $this->assertFalse(recertification_grace_period_ended::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertFalse(recertification_grace_period_ended::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(recertification_grace_period_ended::instance()->user_can_edit($configdata));
    }
}

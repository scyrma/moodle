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
use tool_tenant_generator;
use component_generator_base;
use ReflectionClass;
use tool_dynamicrule_generator;
use tool_certification\constants;
use tool_dynamicrule\api;
use tool_dynamicrule\rule;

/**
 * Unit tests for condition certification_not_certified class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @covers     \tool_certification\tool_dynamicrule\condition\certification_not_certified
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_not_certified_test extends advanced_testcase {

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
        $condition = certification_not_certified::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $condition->supports_rule_types());
    }

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator|component_generator_base
     */
    protected function get_dynamicrule_generator(): tool_dynamicrule_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Get certification generator
     *
     * @return tool_certification_generator|component_generator_base
     */
    public function get_certification_generator(): tool_certification_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_certification');
    }

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator|component_generator_base
     */
    public function get_tenant_generator(): tool_tenant_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = certification_not_certified::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = certification_not_certified::instance();
        $this->assertEquals(get_string('pluginname', 'tool_certification'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = certification_not_certified::instance();
        $configform = ['certificationid' => -2];
        $validationerrors = $condition->validate_config_form($configform);
        $this->assertArrayHasKey('certificationid', $validationerrors);
    }

    /**
     * Test get_config_attributes
     */
    public function test_get_config_attributes(): void {
        // The get_config_attributes method is protected. Use Reflection to call the method.
        $reflector = new ReflectionClass(certification_not_certified::class);
        $method = $reflector->getMethod('get_config_attributes');
        $method->setAccessible(true);

        $outcome = certification_not_certified::instance();
        $this->assertEmpty($method->invokeArgs($outcome, []));
    }

    /**
     * Test status certified condition matching
     */
    public function test_get_matching_users_given_not_certified_status(): void {
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->get_tenant_generator();
        [$tenant, [$user1, $user2, $user3]] = $tenantgenerator->create_tenant_and_users(3);

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = $this->get_certification_generator();
        $certification = $certificationgenerator->generate_certification(['tenantid' => $tenant->id]);

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        $certificationuser1 = \tool_certification\api::allocate_user($certification, $userdata);
        $userdata->userid = $user2->id;
        $certificationuser2 = \tool_certification\api::allocate_user($certification, $userdata);
        $userdata->userid = $user3->id;
        $certificationuser3 = \tool_certification\api::allocate_user($certification, $userdata);

        // Certify user1.
        \tool_certification\api::set_user_as_certified($user1->id, $certification->get('id'));

        // Users that have NOT certified certification1.
        $rule = $this->get_dynamicrule_generator()->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        certification_not_certified::create($rule->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user2->id, $user3->id], array_column($users, 'id'));
    }

    /**
     * Test shared certification certified condition matching in a shared rule
     */
    public function test_get_matching_users_shared_certification_shared_rule(): void {
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12, $user13]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program1 = $this->programgenerator->generate_program_with_course((object)['tenantid' => $sharedspaceid]);

        $certification = $this->generator->generate_certification([
            'tenantid' => $sharedspaceid, 'program' => $program1->get('id')]);

        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column([$user11, $user12, $user13], 'id'));
        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column([$user21, $user22, $user23], 'id'));

        // Complete program for one user from tenant1 and one user from tenant2.
        $this->programgenerator->complete_program($program1, $user11->id);
        $this->programgenerator->complete_program($program1, $user21->id);

        // Test users that matched the dynamic rule condition.
        $rule = $this->drgenerator->create_rule(['tenantid' => $sharedspaceid]);
        $configdata = ['certificationid' => $certification->get('id')];
        certification_not_certified::create($rule->id, $configdata);
        $this->assertEquals(4, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user12->id, $user13->id, $user22->id, $user23->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = $this->get_certification_generator();
        $certification1 = $certificationgenerator->generate_certification();

        $statusstr = get_string('certified', 'tool_certification');
        $configdata = ['certificationid' => $certification1->get('id')];

        $rule2 = $this->get_dynamicrule_generator()->create_rule();
        /** @var certification_not_certified $condition2 */
        $condition2 = certification_not_certified::create($rule2->id, $configdata, true);

        $expectedstr = get_string('conditioncertificationnotcertifieddescription', 'tool_certification',
            $certification1->get('fullname'));
        $this->assertEquals($expectedstr, $condition2->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->get_tenant_generator();
        $tenant = $tenantgenerator->create_tenant();

        $certificationgenerator = $this->get_certification_generator();
        $certification = $certificationgenerator->generate_certification(['tenantid' => $tenant->id]);
        $rule = $this->get_dynamicrule_generator()->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $condition = certification_not_certified::create($rule->id, []);
        $this->assertFalse($condition->is_configuration_valid());

        // Users in certification1.
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = certification_not_certified::create($rule->id, $configdata);

        $this->assertTrue($condition->is_configuration_valid());

        $rule = $this->get_dynamicrule_generator()->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = certification_not_certified::create($rule->id, $configdata);

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

        $certification = $certificationgenerator->generate_certification(['tenantid' => $tenant->id]);

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
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->get_tenant_generator();
        $tenant = $tenantgenerator->create_tenant();

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = $this->get_certification_generator();
        $certification = $certificationgenerator->generate_certification(['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        $this->get_tenant_generator()->allocate_user($user->id, $tenant->id);
        self::setUser($user);

        $rule1 = $this->get_dynamicrule_generator()->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        certification_not_certified::create($rule1->id, $configdata);

        $this->assertFalse(certification_not_certified::instance()->user_can_add());

        $certificationgenerator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertTrue(certification_not_certified::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->get_tenant_generator();
        $tenant = $tenantgenerator->create_tenant();

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $certification = $certificationgenerator->generate_certification(['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        self::setUser($user);

        $rule1 = $this->get_dynamicrule_generator()->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        certification_not_certified::create($rule1->id, $configdata);
        $this->assertFalse(certification_not_certified::instance()->user_can_edit($configdata));

        $certificationgenerator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertFalse(certification_not_certified::instance()->user_can_edit($configdata));

        $this->get_tenant_generator()->allocate_user($user->id, $tenant->id);
        $this->assertTrue(certification_not_certified::instance()->user_can_edit($configdata));
    }
}

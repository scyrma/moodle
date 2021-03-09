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
 * File contains the unit tests for condition certification_not_certified class.
 *
 * @package    tool_certification
 * @category   test
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\certification_completion;
use tool_certification\constants;
use tool_certification\tool_dynamicrule\condition\certification_not_certified;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition certification_not_certified class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_condition_certification_not_certified_testcase extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        global $CFG;
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
        }
        $this->resetAfterTest();
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
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->get_tenant_generator();
        $tenant = $tenantgenerator->create_tenant();
        $tenantgenerator->allocate_user($user1->id, $tenant->id);
        $tenantgenerator->allocate_user($user2->id, $tenant->id);
        $tenantgenerator->allocate_user($user3->id, $tenant->id);

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

        $expectedstr = get_string('conditioncertificationstatusdescriptionnegated', 'tool_certification',
            ['fullname' => $certification1->get('fullname'), 'status' => $statusstr]);
        $this->assertEquals($expectedstr, $condition2->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        global $DB;

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

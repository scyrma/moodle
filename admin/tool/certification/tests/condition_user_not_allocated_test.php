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
 * File contains the unit tests for condition user_not_allocated class.
 *
 * @package    tool_certification
 * @category   test
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_certification\constants;
use \tool_certification\tool_dynamicrule\condition\user_not_allocated;
use \tool_dynamicrule\api;

/**
 * Unit tests for condition user_not_allocated  class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_condition_user_not_allocated_testcase extends advanced_testcase {

    /** @var tool_certification_generator */
    public $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_dynamicrule_generator */
    protected $drgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        global $CFG;
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->resetAfterTest();
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
        }
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = user_not_allocated::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = user_not_allocated::instance();
        $this->assertEquals(get_string('pluginname', 'tool_certification'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = user_not_allocated::instance();
        $configform = ['certificationid' => 0];
        $this->assertArrayHasKey('certificationid', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     */
    public function test_get_matching_users(): void {
        self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        $tenant = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user3->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user4->id, $tenant->id);
        $this->tenantgenerator->allocate_user(get_admin()->id, $tenant->id);

        $certification1 = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $certification2 = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $status = constants::STATUS_OVERRIDE_DEFAULT;
        $user1params = (object) ['userid' => $user1->id, 'certificationid' => $certification1->get('id'), 'status' => $status];
        $user2params = (object) ['userid' => $user2->id, 'certificationid' => $certification2->get('id'), 'status' => $status];
        $user3params = (object) ['userid' => $user3->id, 'certificationid' => $certification2->get('id'), 'status' => $status];

        \tool_certification\api::allocate_user($certification1, $user1params);
        \tool_certification\api::allocate_user($certification2, $user2params);
        \tool_certification\api::allocate_user($certification2, $user3params);

        // Users on certification1.
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification1->get('id')];
        user_not_allocated::create($rule1->id, $configdata);

        // Expected 3 users + admin user.
        $this->assertEquals(4, api::count_matching_users($rule1->id));
        $users = api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([get_admin()->id, $user2->id, $user3->id, $user4->id], array_column($users, 'id'));

        // Users on certification2.
        $rule2 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification2->get('id')];
        user_not_allocated::create($rule2->id, $configdata);

        $this->assertEquals(3, api::count_matching_users($rule2->id));
        $users = api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([get_admin()->id, $user1->id, $user4->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $certification1 = $this->generator->generate_certification();

        $rule1 = $this->drgenerator->create_rule();
        $configdata = ['certificationid' => $certification1->get('id')];
        $condition1 = user_not_allocated::create($rule1->id, $configdata);

        $this->assertEquals(get_string('conditionusernotallocateddescription', 'tool_certification',
            $certification1->get('fullname')), $condition1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $condition = user_not_allocated::create($rule1->id, []);
        $this->assertFalse($condition->is_configuration_valid());

        // Users in certification1.
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = user_not_allocated::create($rule1->id, $configdata);

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
        user_not_allocated::create($rule1->id, $configdata);

        $this->assertFalse(user_not_allocated::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertTrue(user_not_allocated::instance()->user_can_add());
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
        user_not_allocated::create($rule1->id, $configdata);
        $this->assertFalse(user_not_allocated::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertFalse(user_not_allocated::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(user_not_allocated::instance()->user_can_edit($configdata));
    }
}

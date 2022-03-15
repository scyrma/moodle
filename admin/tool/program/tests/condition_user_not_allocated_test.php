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
 * File contains the unit tests for condition user_not_allocated class.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\api;
use tool_program\tool_dynamicrule\condition\user_not_allocated;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition user_not_allocated  class.
 *
 * @covers     \tool_program\tool_dynamicrule\condition\user_not_allocated
 * @package    tool_program
 * @group      tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_condition_user_not_allocated_testcase extends advanced_testcase {

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
        global $CFG;
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
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
        $this->assertEquals(get_string('pluginname', 'tool_program'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = user_not_allocated::instance();
        $configform = ['programid' => 0];
        $this->assertArrayHasKey('programid', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     */
    public function test_get_matching_users(): void {
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

        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $program2 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $this->generator->allocate_user_to_program($program2->get('id'), $user2->id);
        $this->generator->allocate_user_to_program($program2->get('id'), $user3->id);

        // Rule 1.
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        user_not_allocated::create($rule1->id, $configdata);

        $this->assertEquals(4, api::count_matching_users($rule1->id));
        $users = api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user2->id, $user3->id, $user4->id, get_admin()->id], array_column($users, 'id'));

        // Rule 2.
        $rule2 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program2->get('id')];
        user_not_allocated::create($rule2->id, $configdata);

        $this->assertEquals(3, api::count_matching_users($rule2->id));
        $users = api::get_matching_users($rule2->id);

        $this->assertEqualsCanonicalizing([$user1->id, $user4->id, get_admin()->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $program1 = $this->generator->generate_program();
        $configdata = ['programid' => $program1->get('id')];

        $rule = $this->drgenerator->create_rule();
        /** @var user_not_allocated $condition2 */
        $condition2 = user_not_allocated::create($rule->id, $configdata);

        $expected = get_string('conditionusernotallocateddescription', 'tool_program', $program1->get('fullname'));
        $this->assertEquals($expected, $condition2->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $condition1 = user_not_allocated::create($rule1->id, []);
        $this->assertFalse($condition1->is_configuration_valid());

        // Users in program1.
        $configdata = ['programid' => $program1->get('id')];
        $condition1 = user_not_allocated::create($rule1->id, $configdata);

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
        user_not_allocated::create($rule1->id, $configdata);
        $this->assertFalse(user_not_allocated::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertTrue(user_not_allocated::instance()->user_can_add());
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
        user_not_allocated::create($rule1->id, $configdata);
        $this->assertFalse(user_not_allocated::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertFalse(user_not_allocated::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(user_not_allocated::instance()->user_can_edit($configdata));
    }
}

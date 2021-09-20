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
 * File contains the unit tests for condition program_overdue class.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\api;
use tool_program\tool_dynamicrule\condition\program_overdue;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition program_overdue class.
 *
 * @covers     \tool_program\tool_dynamicrule\condition\program_overdue
 * @package    tool_program
 * @group      tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_condition_program_overdue_testcase extends advanced_testcase {

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
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
        }
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->resetAfterTest();
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = program_overdue::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = program_overdue::instance();
        $this->assertEquals(get_string('pluginname', 'tool_program'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = program_overdue::instance();
        $configform = ['programid' => -2];
        $validationerrors = $condition->validate_config_form($configform);
        $this->assertArrayHasKey('programid', $validationerrors);
    }

    /**
     * Test status overdue condition matching
     */
    public function test_get_matching_users_given_overdue_status(): void {
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        $tenant = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user3->id, $tenant->id);

        $program = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $this->generator->allocate_user_to_program($program->get('id'), $user3->id);

        // Set program allocation to Overdue for this user.
        $now = time();
        $programuser1->set('duedate', $now - 3600);
        $programuser1->update();

        // Test users that have program1 with status Overdue.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['programid' => $program->get('id')];
        program_overdue::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));

        // Test users do not match if "on or after" date is set and they completed program before.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $date = strtotime('+1 year');
        $configdata = [
            'programid' => $program->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        program_overdue::create($rule->id, $configdata);

        $this->assertEquals(0, api::count_matching_users($rule->id));

        // Test users match if "on or after" date is set and they completed program after.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $date = strtotime('-1 year');
        $configdata = [
            'programid' => $program->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        program_overdue::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $program = $this->generator->generate_program();

        $rule = $this->drgenerator->create_rule();
        $configdata = ['programid' => $program->get('id')];
        /** @var program_overdue $condition */
        $condition = program_overdue::create($rule->id, $configdata);

        $expectedstr = get_string('conditionprogramoverduedescription', 'tool_program', $program->get('fullname'));
        $this->assertEquals($expectedstr, $condition->get_description());

        // Description when date is enabled.
        $rule = $this->drgenerator->create_rule();
        $now = time();
        $configdata = [
            'programid' => $program->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $now,
        ];
        /** @var program_overdue $condition */
        $condition = program_overdue::create($rule->id, $configdata);

        $options = ['programname' => $program->get('fullname')];
        $options['conditiondate'] = userdate($now, get_string('strftimedatetimeshort'));
        $expected = get_string('conditionprogramoverduedescriptionwithdate', 'tool_program', $options);
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
        $condition1 = program_overdue::create($rule1->id, []);
        $this->assertFalse($condition1->is_configuration_valid());

        // Users in program1.
        $configdata = ['programid' => $program1->get('id')];
        $condition1 = program_overdue::create($rule1->id, $configdata);

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
        program_overdue::create($rule1->id, $configdata);
        $this->assertFalse(program_overdue::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertTrue(program_overdue::instance()->user_can_add());
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
        program_overdue::create($rule1->id, $configdata);
        $this->assertFalse(program_overdue::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertFalse(program_overdue::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(program_overdue::instance()->user_can_edit($configdata));
    }
}

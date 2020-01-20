<?php
// This file is part of Moodle - http://moodle.org/
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

    /**
     * Set up
     */
    public function setUp() {
        global $CFG;
        $this->resetAfterTest();
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
        }
    }

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        /** @var tool_dynamicrule_generator $tooldynamicrulegenerator */
        $tooldynamicrulegenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        return $tooldynamicrulegenerator;
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = new user_not_allocated();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = new user_not_allocated();
        $this->assertEquals(get_string('pluginname', 'tool_program'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = new user_not_allocated();
        $configform = ['programid' => 0];
        $this->assertArrayHasKey('programid', $condition->validate_config_form($configform));
    }

    /**
     * Test get_config_attributes
     */
    public function test_get_config_attributes(): void {
        // The get_config_attributes method is protected. Use Reflection to call the method.
        $reflector = new ReflectionClass(user_not_allocated::class);
        $method = $reflector->getMethod('get_config_attributes');
        $method->setAccessible(true);

        $outcome = new user_not_allocated();
        $this->assertEmpty($method->invokeArgs($outcome, []));
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

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $program1 = $programgenerator->generate_program();
        $program2 = $programgenerator->generate_program();

        $programgenerator->allocate_user_to_program($program1->get('id'), $user1->id);
        $programgenerator->allocate_user_to_program($program2->get('id'), $user2->id);
        $programgenerator->allocate_user_to_program($program2->get('id'), $user3->id);

        // Rule 1.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['programid' => $program1->get('id')];
        user_not_allocated::create($rule1->id, $configdata);

        $this->assertEquals(4, api::count_matching_users($rule1->id));
        $users = api::get_matching_users($rule1->id);
        $this->assertEquals([$user2->id, $user3->id, $user4->id, get_admin()->id], array_column($users, 'id'), '', 0, 10, true);

        // Rule 2.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['programid' => $program2->get('id')];
        user_not_allocated::create($rule2->id, $configdata);

        $this->assertEquals(3, api::count_matching_users($rule2->id));
        $users = api::get_matching_users($rule2->id);

        $this->assertEquals([$user1->id, $user4->id, get_admin()->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $program1 = $programgenerator->generate_program();
        $configdata = ['programid' => $program1->get('id')];

        $rule = $this->get_generator()->create_rule();
        /** @var user_not_allocated $condition2 */
        $condition2 = user_not_allocated::create($rule->id, $configdata);

        $expected = get_string('conditionusernotallocateddescription', 'tool_program', $program1->get('fullname'));
        $this->assertEquals($expected, $condition2->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        global $DB;

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $program1 = $programgenerator->generate_program();

        // Users in program1.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['programid' => $program1->get('id')];
        $condition1 = user_not_allocated::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('tool_program', ['id' => $program1->get('id')]);

        $this->assertFalse($condition1->is_configuration_valid());
    }
}

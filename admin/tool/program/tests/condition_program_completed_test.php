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
 * File contains the unit tests for condition program_completed class.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dynamicrule\api;
use tool_program\tool_dynamicrule\condition\program_completed;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition program_completed class.
 *
 * @covers     \tool_program\tool_dynamicrule\condition\program_completed
 * @package    tool_program
 * @group      tool_program
 * @copyright  2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_program_condition_program_completed_testcase extends advanced_testcase {
    /**
     * Set up
     */
    public function setUp() {
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
     * Get program generator
     *
     * @return tool_program_generator|component_generator_base
     */
    public function get_program_generator(): tool_program_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_program');
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = new program_completed();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = new program_completed();
        $this->assertEquals(get_string('pluginname', 'tool_program'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = new program_completed();
        $configform = ['programid' => -2];
        $validationerrors = $condition->validate_config_form($configform);
        $this->assertArrayHasKey('programid', $validationerrors);
    }

    /**
     * Test get_config_attributes
     */
    public function test_get_config_attributes(): void {
        // The get_config_attributes method is protected. Use Reflection to call the method.
        $reflector = new ReflectionClass(program_completed::class);
        $method = $reflector->getMethod('get_config_attributes');
        $method->setAccessible(true);

        $outcome = new program_completed();
        $this->assertEmpty($method->invokeArgs($outcome, []));
    }

    /**
     * Test completed completed condition matching
     */
    public function test_get_matching_users_given_completed_completed(): void {
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = $this->get_program_generator();
        $program1 = $programgenerator->generate_program_with_base_set();
        $programuser1 = $programgenerator->allocate_user_to_program($program1->get('id'), $user1->id);
        $programgenerator->allocate_user_to_program($program1->get('id'), $user2->id);
        $programgenerator->allocate_user_to_program($program1->get('id'), $user3->id);

        // Complete program for this user.
        $programgenerator->complete_program($program1, $user1->id);

        // Test users that completed program1.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['programid' => $program1->get('id')];
        program_completed::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'), '', 0, 10, true);

        // Test users that Completed program1 that are also Suspended should be shown as Completed if filtering by Completed.
        $programuser1->set('status', 0);
        $programuser1->update();

        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['programid' => $program1->get('id')];
        program_completed::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'), '', 0, 10, true);

        // Test users do not match if "on or after" date is set and they completed program before.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $date = strtotime('+1 year');
        $configdata = [
            'programid' => $program1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        program_completed::create($rule->id, $configdata);

        $this->assertEquals(0, api::count_matching_users($rule->id));

        // Test users match if "on or after" date is set and they completed program after.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $date = strtotime('-1 year');
        $configdata = [
            'programid' => $program1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        program_completed::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        /** @var tool_program_generator $programgenerator */
        $programgenerator = $this->get_program_generator();
        $program = $programgenerator->generate_program();

        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['programid' => $program->get('id')];
        /** @var program_completed $condition */
        $condition = program_completed::create($rule->id, $configdata);

        $expectedstr = get_string('conditionprogramcompleteddescription', 'tool_program', $program->get('fullname'));
        $this->assertEquals($expectedstr, $condition->get_description());

        // Description when date is enabled.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $now = time();
        $configdata = [
            'programid' => $program->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $now,
        ];
        /** @var program_completed $condition */
        $condition = program_completed::create($rule->id, $configdata);

        $expectedstr .= ' ' . get_string('onorafter', 'tool_program');
        $expectedstr .= ' ' . userdate($now, get_string('strftimedatefullshort'));
        $this->assertEquals($expectedstr, $condition->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        global $DB;

        $programgenerator = $this->get_program_generator();
        $program = $programgenerator->generate_program();

        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['programid' => $program->get('id')];
        $condition = program_completed::create($rule->id, $configdata);

        $this->assertTrue($condition->is_configuration_valid());

        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['programid' => $program->get('id')];
        $condition = program_completed::create($rule->id, $configdata);

        $DB->delete_records('tool_program', ['id' => $program->get('id')]);

        $this->assertFalse($condition->is_configuration_valid());
    }
}

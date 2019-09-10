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
 * File contains the unit tests for outcome deallocation class.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_program\api;
use tool_program\constants;
use tool_program\tool_dynamicrule\outcome\deallocation;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for outcome deallocation class.
 *
 * @covers     \tool_program\tool_dynamicrule\outcome\deallocation
 * @package    tool_program
 * @group      tool_program
 * @copyright  2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_program_outcome_deallocation_testcase extends advanced_testcase {

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
     * @return tool_dynamicrule_generator|component_generator_base
     */
    protected function get_generator(): tool_dynamicrule_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $outcome = new deallocation();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $outcome = new deallocation();
        $this->assertEquals(get_string('pluginname', 'tool_program'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $outcome = new deallocation();
        $configform = ['programid' => 10];
        $this->assertArrayHasKey('programid', $outcome->validate_config_form($configform));

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $program1 = $programgenerator->generate_program((object) ['tenantid' => $defaulttenantid]);

        $configform = ['programid' => $program1->get('id')];
        $this->assertArrayNotHasKey('programid', $outcome->validate_config_form($configform));
    }

    /**
     * Test get_config_attributes
     */
    public function test_get_config_attributes(): void {
        // The get_config_attributes method is protected. Use Reflection to call the method.
        $reflector = new ReflectionClass(deallocation::class);
        $method = $reflector->getMethod('get_config_attributes');
        $method->setAccessible(true);

        $outcome = new deallocation();
        $this->assertEmpty($method->invokeArgs($outcome, []));
    }

    /**
     * Test apply_to_users disable enrolment
     */
    public function test_apply_to_users(): void {
        global $DB, $CFG;

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $program1 = $programgenerator->generate_program_with_base_set();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        $params = ['userid' => $user1->id, 'programid' => $program1->get('id'), 'certificationid' => 0,
            'allocationtype' => constants::ALLOCATION_DYNAMIC];
        api::allocate_user($program1, (object) $params);

        $params['userid'] = $user2->id;
        $params['allocationtype'] = constants::ALLOCATION_CERTIFICATION;
        api::allocate_user($program1, (object) $params);

        $params['userid'] = $user3->id;
        $params['allocationtype'] = constants::ALLOCATION_MANUAL;
        api::allocate_user($program1, (object) $params);

        $rule0 = $this->get_generator()->create_rule();

        $configdata = ['programid' => $program1->get('id')];
        $outcome = deallocation::create($rule0->id, $configdata);

        $outcome->apply_to_users([]);

        $users = $DB->get_records('tool_program_users', [], '', 'userid');
        $this->assertEquals([$user1->id, $user2->id, $user3->id], array_column($users, 'userid'), '', 0, 10, true);

        $outcome->apply_to_users([$user1, $user2, $user3]);

        $users = $DB->get_records('tool_program_users', [], '', 'userid');
        // Deallocation should have only acted upon the user(s) with a dynamic allocation type.
        $this->assertEquals([$user2->id, $user3->id], array_column($users, 'userid'), '', 0, 10, true);
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {

        $this->resetAfterTest();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $program1 = $programgenerator->generate_program();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['programid' => $program1->get('id')];
        /** @var deallocation $outcome1 */
        $outcome1 = deallocation::create($rule1->id, $configdata);

        $outcomestr = get_string('outcomedeallocationdescription', 'tool_program', $program1->get('fullname'));
        $this->assertEquals($outcomestr, $outcome1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        global $DB;

        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $program1 = $programgenerator->generate_program();

        // Users in program1.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['programid' => $program1->get('id')];
        $condition1 = deallocation::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('tool_program', ['id' => $program1->get('id')]);

        $this->assertFalse($condition1->is_configuration_valid());
    }
}

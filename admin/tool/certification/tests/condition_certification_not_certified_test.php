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
 * File contains the unit tests for condition certification_not_certified class.
 *
 * @package    tool_certification
 * @category   test
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_certification\certification_completion;
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
 */
class tool_certification_condition_certification_not_certified_testcase extends advanced_testcase {

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
     * Get certification generator
     *
     * @return tool_certification_generator|component_generator_base
     */
    public function get_certification_generator(): tool_certification_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_certification');
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = new certification_not_certified();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = new certification_not_certified();
        $this->assertEquals(get_string('pluginname', 'tool_certification'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = new certification_not_certified();
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

        $outcome = new certification_not_certified();
        $this->assertEmpty($method->invokeArgs($outcome, []));
    }

    /**
     * Test status certified condition matching
     */
    public function test_get_matching_users_given_not_certified_status(): void {
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = $this->get_certification_generator();
        $certification1 = $certificationgenerator->generate_certification();
        $certificationgenerator->allocate_user($user2->id, $certification1->get('id'));
        $certificationgenerator->allocate_user($user3->id, $certification1->get('id'));

        // Certify user1.
        $certificationgenerator->complete_certification($certification1, $user1->id);

        // Users that have NOT certified certification1.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['certificationid' => $certification1->get('id')];
        certification_not_certified::create($rule->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule->id);
        $this->assertEquals([$user2->id, $user3->id], array_column($users, 'id'), '', 0, 10, true);
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

        $certificationgenerator = $this->get_certification_generator();
        $certification = $certificationgenerator->generate_certification();

        // Users in certification1.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = certification_not_certified::create($rule->id, $configdata);

        $this->assertTrue($condition->is_configuration_valid());

        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = certification_not_certified::create($rule->id, $configdata);

        $DB->delete_records('tool_certification', ['id' => $certification->get('id')]);

        $this->assertFalse($condition->is_configuration_valid());
    }
}
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
 * @package    tool_certification
 * @category   test
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_condition_user_not_allocated_testcase extends advanced_testcase {

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
        /** @var tool_dynamicrule_generator $dynamicrulegenerator */
        $dynamicrulegenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        return $dynamicrulegenerator;
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
        $this->assertEquals(get_string('pluginname', 'tool_certification'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {

        $condition = new user_not_allocated();
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

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $certification1 = $certificationgenerator->generate_certification();
        $certification2 = $certificationgenerator->generate_certification();

        $status = constants::STATUS_OVERRIDE_DEFAULT;
        $user1params = (object) ['userid' => $user1->id, 'certificationid' => $certification1->get('id'), 'status' => $status];
        $user2params = (object) ['userid' => $user2->id, 'certificationid' => $certification2->get('id'), 'status' => $status];
        $user3params = (object) ['userid' => $user3->id, 'certificationid' => $certification2->get('id'), 'status' => $status];

        \tool_certification\api::allocate_user($certification1, $user1params);
        \tool_certification\api::allocate_user($certification2, $user2params);
        \tool_certification\api::allocate_user($certification2, $user3params);

        // Users on certification1.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['certificationid' => $certification1->get('id')];
        user_not_allocated::create($rule1->id, $configdata);

        // Expected 3 users + admin user.
        $this->assertEquals(4, api::count_matching_users($rule1->id));
        $users = api::get_matching_users($rule1->id);
        $this->assertEquals([get_admin()->id, $user2->id, $user3->id, $user4->id], array_column($users, 'id'), '', 0, 10, true);

        // Users on certification2.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['certificationid' => $certification2->get('id')];
        user_not_allocated::create($rule2->id, $configdata);

        $this->assertEquals(3, api::count_matching_users($rule2->id));
        $users = api::get_matching_users($rule2->id);
        $this->assertEquals([get_admin()->id, $user1->id, $user4->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {

        $this->resetAfterTest();

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $certification1 = $certificationgenerator->generate_certification();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['certificationid' => $certification1->get('id')];
        $condition1 = user_not_allocated::create($rule1->id, $configdata);

        $this->assertEquals(get_string('conditionusernotallocateddescription', 'tool_certification',
            $certification1->get('fullname')), $condition1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        global $DB;

        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $certification1 = $certificationgenerator->generate_certification();

        // Users in program1.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['certificationid' => $certification1->get('id')];
        $condition1 = user_not_allocated::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('tool_certification', ['id' => $certification1->get('id')]);

        $this->assertFalse($condition1->is_configuration_valid());
    }
}

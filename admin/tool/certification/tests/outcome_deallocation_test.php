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
 * @package    tool_certification
 * @category   test
 * @copyright  2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_certification\tool_dynamicrule\outcome\deallocation;
use tool_certification\constants;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for outcome deallocation class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @copyright  2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certification_outcome_deallocation_testcase extends advanced_testcase {

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
        $outcome = new deallocation();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $outcome = new deallocation();
        $this->assertNotEmpty(get_string('pluginname', 'tool_certification'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $outcome = new deallocation();
        $configform = ['certificationid' => 10];
        $this->assertArrayHasKey('certificationid', $outcome->validate_config_form($configform));

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $certification1 = $certificationgenerator->generate_certification(['tenantid' => $defaulttenantid]);

        $configform = ['certificationid' => $certification1->get('id')];
        $this->assertArrayNotHasKey('certificationid', $outcome->validate_config_form($configform));
    }

    /**
     * Test apply_to_users disable enrolment
     */
    public function test_apply_to_users(): void {
        global $DB;

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $certification1 = $certificationgenerator->generate_certification();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        $params = ['userid' => $user1->id, 'certificationid' => $certification1->get('id'),
                   'allocationtype' => constants::ALLOCATION_DYNAMIC,
                   'status' => constants::STATUS_OVERRIDE_DEFAULT];
        \tool_certification\api::allocate_user($certification1, (object)$params);

        $params['userid'] = $user2->id;
        \tool_certification\api::allocate_user($certification1, (object)$params);

        $params['userid'] = $user3->id;
        $params['allocationtype'] = constants::ALLOCATION_MANUAL;
        \tool_certification\api::allocate_user($certification1, (object)$params);

        $rule0 = $this->get_generator()->create_rule();

        $configdata = ['certificationid' => $certification1->get('id')];
        $outcome = deallocation::create($rule0->id, $configdata);

        $outcome->apply_to_users([]);

        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEquals([$user1->id, $user2->id, $user3->id], array_column($users, 'userid'), '', 0, 10, true);

        $outcome->apply_to_users([$user1, $user2, $user3]);

        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEquals([], array_column($users, 'userid'), '', 0, 10, true);
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
        $outcome1 = deallocation::create($rule1->id, $configdata);

        $this->assertEquals(get_string('outcomedeallocationdescription', 'tool_certification',
            $certification1->get('fullname')), $outcome1->get_description());
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
        $condition1 = deallocation::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('tool_certification', ['id' => $certification1->get('id')]);

        $this->assertFalse($condition1->is_configuration_valid());
    }
}

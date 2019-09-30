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
 * File contains the unit tests for outcome allocation class.
 *
 * @package    tool_certification
 * @category   test
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_certification\tool_dynamicrule\outcome\allocation;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for outcome\allocation class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certification_outcome_allocation_testcase extends advanced_testcase {

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
        $outcome = new allocation();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $outcome = new allocation();
        $configform = ['certificationid' => 123];
        $this->assertArrayHasKey('certificationid', $outcome->validate_config_form($configform));

        $defaulttenantid = tenancy::get_default_tenant_id();
        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
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

        $rule0 = $this->get_generator()->create_rule();

        $configdata = ['certificationid' => $certification1->get('id')];
        $outcome = allocation::create($rule0->id, $configdata);

        $outcome->apply_to_users([$user1, $user2]);

        $users = $DB->get_records('tool_certification_users', [], '', 'userid');
        $this->assertEquals([$user1->id, $user2->id], array_column($users, 'userid'), '', 0, 10, true);
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
        $outcome1 = allocation::create($rule1->id, $configdata);

        $this->assertEquals(get_string('outcomeallocationdescription', 'tool_certification',
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
        $condition1 = allocation::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('tool_certification', ['id' => $certification1->get('id')]);

        $this->assertFalse($condition1->is_configuration_valid());
    }
}

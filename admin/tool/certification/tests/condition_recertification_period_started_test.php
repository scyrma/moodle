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
 * File contains the unit tests for condition recertification_period_started class.
 *
 * @package    tool_certification
 * @category   test
 * @author     2019 Mikel Martín <mikel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\constants;
use tool_certification\tool_dynamicrule\condition\recertification_period_started;
use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition recertification_period_started class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @author     2019 Mikel Martín <mikel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_condition_recertification_period_started_testcase extends advanced_testcase {

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
        $condition = new recertification_period_started();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = new recertification_period_started();
        $this->assertEquals(get_string('pluginname', 'tool_certification'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = new recertification_period_started();
        $configform = ['certificationid' => -2];
        $validationerrors = $condition->validate_config_form($configform);
        $this->assertArrayHasKey('certificationid', $validationerrors);
    }

    /**
     * Test get_config_attributes
     */
    public function test_get_config_attributes(): void {
        // The get_config_attributes method is protected. Use Reflection to call the method.
        $reflector = new ReflectionClass(recertification_period_started::class);
        $method = $reflector->getMethod('get_config_attributes');
        $method->setAccessible(true);

        $outcome = new recertification_period_started();
        $this->assertEmpty($method->invokeArgs($outcome, []));
    }

    /**
     * Test recertification period started condition matching
     */
    public function test_get_matching_users_given_recertification_period(): void {
        global $DB;
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = $this->get_certification_generator();
        $certification = $certificationgenerator->generate_certification(
            ['expirydateabsolute' => strtotime('+5 day')], true);

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        $certificationuser1 = \tool_certification\api::allocate_user($certification, $userdata);
        $id = $certificationuser1->get('id');
        $certificationgenerator->allocate_user($user2->id, $certification->get('id'));
        $certificationgenerator->allocate_user($user3->id, $certification->get('id'));

        // Test users that are Certified with recertification period started.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['certificationid' => $certification->get('id')];
        recertification_period_started::create($rule->id, $configdata);
        $this->assertEmpty(api::count_matching_users($rule->id));

        // Certify user1 and user2.
        \tool_certification\api::set_user_as_certified($user1->id, $certification->get('id'));
        \tool_certification\api::set_user_as_certified($user2->id, $certification->get('id'));
        \tool_certification\api::allocate_recertification_users();

        // Test users that are Certified with recertification period started.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        recertification_period_started::create($rule->id, $configdata);

        $this->assertEquals(2, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEquals([$user1->id, $user2->id], array_column($users, 'id'), '', 0, 10, true);

        // Suspend user1.
        $DB->update_record('tool_certification_users', (object)['id' => $id, 'status' => 0]);

        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['certificationid' => $certification->get('id')];
        recertification_period_started::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEquals([$user2->id], array_column($users, 'id'), '', 0, 10, true);

        // Test users that match if "on or after" date is set.
        \tool_certification\api::set_user_as_certified($user1->id, $certification->get('id'));

        // One start dates should be bigger than -1 week because the other one is suspended.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = [
            'certificationid' => $certification->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => strtotime('-1 week'),
        ];
        recertification_period_started::create($rule->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));

        $DB->update_record('tool_certification_users', (object)['id' => $id, 'status' => 1, 'isrecertification' => 1]);

        // Both start dates should be bigger than -1 week.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        recertification_period_started::create($rule->id, $configdata);
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));

        $DB->update_record('tool_certification_users', (object)['id' => $id, 'nextstartdate' => strtotime('+3 month')]);

        \tool_certification\api::set_user_as_certified($user2->id, $certification->get('id'));

        // One start date should be bigger than +1 day.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata['conditiondate'] = strtotime('+1 day');
        recertification_period_started::create($rule->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));

        // No start dates should be bigger than +5 months.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata['conditiondate'] = strtotime('+5 month');
        recertification_period_started::create($rule->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = $this->get_certification_generator();
        $certification1 = $certificationgenerator->generate_certification();

        $rule1 = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['certificationid' => $certification1->get('id')];
        /** @var recertification_period_started $condition1 */
        $condition1 = recertification_period_started::create($rule1->id, $configdata);

        $expectedstr = get_string('conditionrecertificationstarteddescription', 'tool_certification',
            ['fullname' => $certification1->get('fullname')]);
        $this->assertEquals($expectedstr, $condition1->get_description());

        // Description when date is enabled.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $now = time();
        $configdata = [
            'certificationid' => $certification1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $now,
        ];
        /** @var recertification_period_started $condition */
        $condition = recertification_period_started::create($rule->id, $configdata);
        $expectedstr .= ' ' . get_string('onorafter', 'tool_certification');
        $expectedstr .= ' ' . userdate($now, get_string('strftimedatefullshort'));
        $this->assertEquals($expectedstr, $condition->get_description());
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
        $condition = recertification_period_started::create($rule->id, $configdata);

        $this->assertTrue($condition->is_configuration_valid());

        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = recertification_period_started::create($rule->id, $configdata);

        $DB->delete_records('tool_certification', ['id' => $certification->get('id')]);

        $this->assertFalse($condition->is_configuration_valid());
    }
}

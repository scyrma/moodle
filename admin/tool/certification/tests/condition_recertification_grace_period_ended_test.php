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
 * File contains the unit tests for condition recertification_grace_period_ended class.
 *
 * @package    tool_certification
 * @category   test
 * @author     2019 Mikel Martín <mikel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\constants;
use tool_certification\tool_dynamicrule\condition\recertification_grace_period_ended;
use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition recertification_grade_period_ended class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @author     2019 Mikel Martín <mikel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_condition_recertification_grace_period_ended_testcase extends advanced_testcase {

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
        $condition = new recertification_grace_period_ended();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = new recertification_grace_period_ended();
        $this->assertEquals(get_string('pluginname', 'tool_certification'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = new recertification_grace_period_ended();
        $configform = ['certificationid' => -2];
        $validationerrors = $condition->validate_config_form($configform);
        $this->assertArrayHasKey('certificationid', $validationerrors);
    }

    /**
     * Test get_config_attributes
     */
    public function test_get_config_attributes(): void {
        // The get_config_attributes method is protected. Use Reflection to call the method.
        $reflector = new ReflectionClass(recertification_grace_period_ended::class);
        $method = $reflector->getMethod('get_config_attributes');
        $method->setAccessible(true);

        $outcome = new recertification_grace_period_ended();
        $this->assertEmpty($method->invokeArgs($outcome, []));
    }

    /**
     * Test recertification period started condition matching
     */
    public function test_get_matching_users_given_grace_period_ended(): void {

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = $this->get_certification_generator();
        $certification = $certificationgenerator->generate_certification([], true);
        $certification->set('expirydateabsolute', strtotime('-1 week'));
        $certification->update();

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];

        $certificationuser1 = \tool_certification\api::allocate_user($certification, $userdata);
        $certificationgenerator->allocate_user($user2->id, $certification->get('id'));

        // Certify user1.
        \tool_certification\api::set_user_as_certified($user1->id, $certification->get('id'));

        \tool_certification\api::allocate_recertification_users();

        // Test users that are certified with grace period ended.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['certificationid' => $certification->get('id')];
        recertification_grace_period_ended::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'), '', 0, 10, true);

        // Suspend user1.
        $certificationuser1->set('status', 0);
        $certificationuser1->update();

        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['certificationid' => $certification->get('id')];
        recertification_grace_period_ended::create($rule->id, $configdata);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule->id);
        $this->assertEquals([], array_column($users, 'id'), '', 0, 10, true);

        // Test users that match if "on or before" date is set.
        $params2 = ['certificationid' => $certification->get('id'), 'userid' => $user2->id];
        $certificationuser2 = \tool_certification\certification_user::get_record($params2);

        // Certify user2.
        \tool_certification\api::set_user_as_certified($user2->id, $certification->get('id'));

        // There should be no graceperiod dates before -1 week.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $date = strtotime('-1 week');
        $configdata = [
            'certificationid' => $certification->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        recertification_grace_period_ended::create($rule->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));

        $certificationuser2->set('graceperiodends', strtotime('-1 day'));
        $certificationuser2->set('isrecertification', 1);
        $certificationuser2->set('currentprogramid', $certification->get('recertificationprogram'));
        $certificationuser2->update();

        $certificationuser1->set('graceperiodends', strtotime('+2 weeks'));
        $certificationuser1->set('currentprogramid', $certification->get('recertificationprogram'));
        $certificationuser1->update();

        // One grace period date should be before +1 day because the other is disabled.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata['conditiondate'] = strtotime('+3 week');
        recertification_grace_period_ended::create($rule->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));

        $certificationuser1->set('status', 1);
        $certificationuser1->update();

        // One grace period date should be before +1 day.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata['conditiondate'] = strtotime('+1 day');
        recertification_grace_period_ended::create($rule->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));

        // Both grace period date should be before +3 weeks.
        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata['conditiondate'] = strtotime('+3 week');
        recertification_grace_period_ended::create($rule->id, $configdata);
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule->id));
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
        /** @var recertification_grace_period_ended $condition1 */
        $condition1 = recertification_grace_period_ended::create($rule1->id, $configdata);

        $expectedstr = get_string('conditionrecertificationgraceperiodendsdescription', 'tool_certification',
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
        /** @var recertification_grace_period_ended $condition */
        $condition = recertification_grace_period_ended::create($rule->id, $configdata);
        $expectedstr .= ' ' . get_string('onorbefore', 'tool_certification');
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
        $condition = recertification_grace_period_ended::create($rule->id, $configdata);

        $this->assertTrue($condition->is_configuration_valid());

        $rule = $this->get_dynamicrule_generator()->create_rule();
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = recertification_grace_period_ended::create($rule->id, $configdata);

        $DB->delete_records('tool_certification', ['id' => $certification->get('id')]);

        $this->assertFalse($condition->is_configuration_valid());
    }
}

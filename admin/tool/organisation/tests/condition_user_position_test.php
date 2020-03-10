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
 * File contains the unit tests for condition user_position class.
 *
 * @package    tool_organisation
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition user_position  class.
 *
 * @package    tool_organisation
 * @group      tool_organisation
 * @covers     \tool_organisation\tool_dynamicrule\condition\user_position
 * @covers     \tool_organisation\tool_dynamicrule\condition\user_without_position
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_condition_user_position_testcase extends advanced_testcase {

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
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $condition = new \tool_organisation\tool_dynamicrule\condition\user_position();
        $this->assertNotEmpty($condition->get_title());

        $condition = new \tool_organisation\tool_dynamicrule\condition\user_without_position();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = new \tool_organisation\tool_dynamicrule\condition\user_position();
        $this->assertEquals(get_string('pluginname', 'tool_organisation'), $condition->get_category());

        $condition2 = new \tool_organisation\tool_dynamicrule\condition\user_without_position();
        $this->assertEquals($condition->get_category(), $condition2->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $condition = new \tool_organisation\tool_dynamicrule\condition\user_position();
        $configform = ['positionid' => 0];
        $this->assertArrayHasKey('positionid', $condition->validate_config_form($configform));

        $condition = new \tool_organisation\tool_dynamicrule\condition\user_without_position();
        $configform = ['positionid' => 0];
        $this->assertArrayHasKey('positionid', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     * @uses \tool_dynamicrule\api::count_matching_users
     */
    public function test_get_matching_users() {
        global $CFG;

        $course1 = $this->getDataGenerator()->create_course();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $department1 = $generator->create_department();
        $department2 = $generator->create_department();

        $position1 = $generator->create_position();
        $position2 = $generator->create_position();

        $manager = new \tool_organisation\job_manager();

        $manager->create_job((object)['userid' => $user1->id,
            'positionid' => $position1->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);

        $manager->create_job((object)['userid' => $user2->id,
            'positionid' => $position2->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);

        $manager->create_job((object)['userid' => $user3->id,
            'positionid' => $position1->id, 'departmentid' => $department2->id, 'startdate' => 1262304000]);

        $manager->create_job((object)['userid' => $user4->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1262304000]);

        // Position1.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['positionid' => $position1->id];
        \tool_organisation\tool_dynamicrule\condition\user_position::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals([$user1->id, $user3->id], array_column($users, 'id'), '', 0, 10, true);

        // Rule 1, negated.
        $rule12 = $this->get_generator()->create_rule();
        $configdata = ['positionid' => $position1->id];
        \tool_organisation\tool_dynamicrule\condition\user_without_position::create($rule12->id, $configdata);

        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule12->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule12->id);
        $this->assertEquals([$user2->id, $user4->id, get_admin()->id], array_column($users, 'id'), '', 0, 10, true);

        // Position2.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['positionid' => $position2->id];
        \tool_organisation\tool_dynamicrule\condition\user_position::create($rule2->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEquals([$user2->id, $user4->id], array_column($users, 'id'), '', 0, 10, true);

        // Rule 2, negated.
        $rule22 = $this->get_generator()->create_rule();
        $configdata = ['positionid' => $position2->id];
        \tool_organisation\tool_dynamicrule\condition\user_without_position::create($rule22->id, $configdata);

        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule22->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule22->id);
        $this->assertEquals([$user1->id, $user3->id, get_admin()->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $this->resetAfterTest();

        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $position1 = $generator->create_position();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['positionid' => $position1->id];
        $condition1 = \tool_organisation\tool_dynamicrule\condition\user_position::create($rule1->id, $configdata);

        $this->assertEquals(get_string('conditionuserpositiondescription', 'tool_organisation', $position1->name),
                            $condition1->get_description());

        $rule2 = $this->get_generator()->create_rule();
        $condition2 = \tool_organisation\tool_dynamicrule\condition\user_without_position::create($rule2->id, $configdata);

        $this->assertEquals(get_string('conditionuserpositiondescriptionnegated', 'tool_organisation', $position1->name),
                            $condition2->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        global $DB;

        /** @var tool_organisation_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('tool_organisation');
        $position1 = $generator->create_position();
        $position2 = $generator->create_position();

        // Users with position.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['positionid' => $position1->id];
        $condition1 = \tool_organisation\tool_dynamicrule\condition\user_position::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('tool_organisation_position', ['id' => $position1->id]);

        $this->assertFalse($condition1->is_configuration_valid());

        // Users without position.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['positionid' => $position2->id];
        $condition2 = \tool_organisation\tool_dynamicrule\condition\user_without_position::create($rule1->id, $configdata);

        $this->assertTrue($condition2->is_configuration_valid());

        $DB->delete_records('tool_organisation_position', ['id' => $position2->id]);

        $this->assertFalse($condition2->is_configuration_valid());
    }

    /**
     * Test \tool_organisation\event\job_created event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing() {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $user0 = $this->getDataGenerator()->create_user();
        $department0 = $generator->create_department();
        $position0 = $generator->create_position();

        // Create rule0 with user has position conditon and notification outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['positionid' => $position0->id];
        \tool_organisation\tool_dynamicrule\condition\user_position::create($rule0->id, $configdata);
        $configdata = ['subject' => 'You are having position0',
            'body' => ['text' => 'Congratulations, you are having position0.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Allocate user to department, this supposed to trigger rule0.
        $manager = new \tool_organisation\job_manager();
        $manager->create_job((object)['userid' => $user0->id,
            'positionid' => $position0->id, 'departmentid' => $department0->id, 'startdate' => 1262304000]);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'You are having position0');
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }
}

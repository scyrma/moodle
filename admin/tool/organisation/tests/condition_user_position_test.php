<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * File contains the unit tests for condition user_position class.
 *
 * @package    tool_organisation
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\rule;
use tool_organisation\helper;
use tool_organisation\tool_dynamicrule\condition\user_position;

/**
 * Unit tests for condition user_position  class.
 *
 * @package    tool_organisation
 * @group      tool_organisation
 * @covers     \tool_organisation\tool_dynamicrule\condition\user_position
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_condition_user_position_testcase extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        global $CFG;
        $this->resetAfterTest();
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
        }
    }

    /**
     * tearDown.
     */
    public function tearDown(): void {
        if (extension_loaded('uopz')) {
            // Revert function overrides.
            uopz_unset_return('time');
        }
    }

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_tool_dynamicrule_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Get organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $condition = user_position::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = user_position::instance();
        $this->assertEquals(get_string('pluginname', 'tool_organisation'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $position1 = $this->get_generator()->create_position();
        $condition = user_position::instance();
        $configform = ['positionid' => [1000, $position1->id]];
        $this->assertArrayHasKey('positionid', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     * @uses \tool_dynamicrule\api::count_matching_users
     */
    public function test_get_matching_users() {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $generator = $this->get_generator();

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
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => $position1->id];
        user_position::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user3->id], array_column($users, 'id'));

        // Position2.
        $rule2 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => $position2->id];
        user_position::create($rule2->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([$user2->id, $user4->id], array_column($users, 'id'));

        // Let's test if startdate is working properly.
        $user5 = $this->getDataGenerator()->create_user();
        $manager->create_job((object)['userid' => $user5->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1583193600]);

        // Only user5 should be found using this startdate and position2.
        $rule33 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => $position2->id, 'jobstartdate' => 1583193600];
        user_position::create($rule33->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule33->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule33->id);
        $this->assertEqualsCanonicalizing([$user5->id], array_column($users, 'id'));

        // No user should be found using this start date.
        $rule34 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => $position2->id, 'jobstartdate' => 1614729600];
        user_position::create($rule34->id, $configdata);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule34->id));
        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($rule34->id));

        // Two users should be found using this start date and position1.
        $rule35 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => $position1->id, 'jobstartdate' => 1262304000];
        user_position::create($rule35->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule35->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule35->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user3->id], array_column($users, 'id'));
    }

    /**
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     * @uses \tool_dynamicrule\api::count_matching_users
     */
    public function test_get_matching_users_multiple_pos() {
        $user1 = $this->getDataGenerator()->create_user();
        $user1a = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $generator = $this->get_generator();

        $department1 = $generator->create_department();
        $department2 = $generator->create_department();

        $position1 = $generator->create_position();
        $position1a = $generator->create_position(['parentid' => $position1->id]);
        $position2 = $generator->create_position();

        $manager = new \tool_organisation\job_manager();

        // User1 - position1.
        $manager->create_job((object)['userid' => $user1->id,
            'positionid' => $position1->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);

        // User1a - position1a.
        $manager->create_job((object)['userid' => $user1a->id,
            'positionid' => $position1a->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);

        // User2 - position1 and position2.
        $manager->create_job((object)['userid' => $user2->id,
            'positionid' => $position1->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);
        $manager->create_job((object)['userid' => $user2->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1262304000]);

        // User3 - position2.
        $manager->create_job((object)['userid' => $user3->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1262304000]);

        // User4 - position1 and position2.
        $manager->create_job((object)['userid' => $user4->id,
            'positionid' => $position1->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);
        $manager->create_job((object)['userid' => $user4->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1262304000]);

        // Position 1 && 2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id],
            'criteria' => user_position::CRITERIA_ALL, 'withsubpositions' => 0];
        user_position::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user2->id, $user4->id], array_column($users, 'id'));

        // Position 1 || 2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id],
            'criteria' => user_position::CRITERIA_ANY, 'withsubpositions' => 0];
        user_position::create($rule1->id, $configdata);

        $this->assertEquals(4, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($users, 'id'));

        // Position 1 || 2 with subpositions.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id],
            'criteria' => user_position::CRITERIA_ANY, 'withsubpositions' => 1];
        user_position::create($rule1->id, $configdata);

        $this->assertEquals(5, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user1a->id, $user2->id, $user3->id, $user4->id],
            array_column($users, 'id'));

        // Let's test if startdate is working properly.
        $user5 = $this->getDataGenerator()->create_user();
        $manager->create_job((object)['userid' => $user5->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1583193600]);

        // Only user5 should be found using this startdate and position1 || position2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id],
            'criteria' => user_position::CRITERIA_ANY, 'jobstartdate' => 1583193600];
        user_position::create($rule1->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user5->id], array_column($users, 'id'));

        // No user should be found using this startdate and position1 && position2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id],
            'criteria' => user_position::CRITERIA_ALL, 'jobstartdate' => 1583193600];
        user_position::create($rule1->id, $configdata);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($rule1->id));

        // No user should be found using this start date.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id],
            'criteria' => user_position::CRITERIA_ANY, 'jobstartdate' => 1614729600];
        user_position::create($rule1->id, $configdata);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($rule1->id));

        // Five users should be found using this start date and position1 || position2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id],
            'criteria' => user_position::CRITERIA_ANY, 'jobstartdate' => 1262304000];
        user_position::create($rule1->id, $configdata);

        $this->assertEquals(5, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id, $user5->id],
            array_column($users, 'id'));

        // One user should be found using this start date and position1 && position2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id],
            'criteria' => user_position::CRITERIA_ALL, 'jobstartdate' => 1262304000];
        user_position::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user2->id, $user4->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        $position1 = $this->get_generator()->create_position();
        $position2 = $this->get_generator()->create_position();

        // One position.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => $position1->id];
        $condition1 = user_position::create($rule1->id, $configdata);

        $options = ['posname' => $position1->name, 'subposinclude' => 'Not included'];
        $expected = get_string('conditionuserpositiondescription', 'tool_organisation', $options);
        $this->assertEquals($expected, $condition1->get_description());

        // All positions.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id], 'criteria' => user_position::CRITERIA_ALL];
        $condition1 = user_position::create($rule1->id, $configdata);

        $options = ['posname' => "{$position1->name}', '{$position2->name}", 'subposinclude' => 'Not included'];
        $expected = get_string('conditionuserpositionsalldescription', 'tool_organisation', $options);
        $this->assertEquals($expected, $condition1->get_description());

        // Any positions.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id], 'criteria' => user_position::CRITERIA_ANY];
        $condition1 = user_position::create($rule1->id, $configdata);

        $options = ['posname' => "{$position1->name}', '{$position2->name}", 'subposinclude' => 'Not included'];
        $expected = get_string('conditionuserpositionsanydescription', 'tool_organisation', $options);
        $this->assertEquals($expected, $condition1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        global $DB;

        $position1 = $this->get_generator()->create_position();
        $position2 = $this->get_generator()->create_position();

        // Users with position.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['positionid' => [$position1->id, $position2->id]];
        $condition1 = user_position::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('tool_organisation_position', ['id' => $position1->id]);

        $this->assertFalse($condition1->is_configuration_valid());
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

        $user0 = $this->getDataGenerator()->create_user();
        $department0 = $this->get_generator()->create_department();
        $position0 = $this->get_generator()->create_position();

        // Create rule0 with user has position conditon and notification outcome.
        $rule0 = $this->get_tool_dynamicrule_generator()->create_rule(['enabled' => 1]);
        $configdata = ['positionid' => $position0->id];
        user_position::create($rule0->id, $configdata);
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
        $this->get_tool_dynamicrule_generator()->assert_user_matched_rule($this, $user0->id, $rule0->id);
    }

    /**
     * Test \tool_organisation\event\job_created event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing_multiple_positions_all() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();
        $position0 = $this->get_generator()->create_position();
        $position1 = $this->get_generator()->create_position();
        $department0 = $this->get_generator()->create_department();
        $department1 = $this->get_generator()->create_department();

        // Create rule0 with user in position conditon and notification outcome.
        $rule0 = $this->get_tool_dynamicrule_generator()->create_rule(['enabled' => 1]);
        $configdata = ['positionid' => [$position0->id, $position1->id],
            'withsubpositions' => 0, 'criteria' => user_position::CRITERIA_ALL];
        user_position::create($rule0->id, $configdata);
        $configdata = ['subject' => 'You are in positions 0 and 1',
            'body' => ['text' => 'Congratulations, you are in positions 0 and 1.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Allocate user to position0, this supposed to trigger rule0, but nothing will happen.
        $manager = new \tool_organisation\job_manager();
        $manager->create_job((object)['userid' => $user0->id,
            'positionid' => $position0->id, 'departmentid' => $department0->id, 'startdate' => 1262304000]);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(0, $sink->count());
        $sink->clear();

        // Check matches record presence.
        $this->get_tool_dynamicrule_generator()->assert_user_did_not_match_rule($this, $user0->id, $rule0->id);

        // Allocate user to position1, this supposed to trigger rule0, now user matches condition.
        $manager = new \tool_organisation\job_manager();
        $manager->create_job((object)['userid' => $user0->id,
            'positionid' => $position1->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'You are in positions 0 and 1');
        $sink->clear();

        // Check matches record presence.
        $this->get_tool_dynamicrule_generator()->assert_user_matched_rule($this, $user0->id, $rule0->id);
    }

    /**
     * Test \tool_organisation\event\job_created event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing_multiple_positions_any() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();
        $position0 = $this->get_generator()->create_position();
        $position1 = $this->get_generator()->create_position();
        $department0 = $this->get_generator()->create_department();
        $department1 = $this->get_generator()->create_department();

        // Create rule0 with user in position conditon and notification outcome.
        $rule0 = $this->get_tool_dynamicrule_generator()->create_rule(['enabled' => 1]);
        $configdata = ['positionid' => [$position0->id, $position1->id],
            'withsubpositions' => 0, 'criteria' => user_position::CRITERIA_ANY];
        user_position::create($rule0->id, $configdata);
        $configdata = ['subject' => 'You are in positions 0 or 1',
            'body' => ['text' => 'Congratulations, you are in positions 0 or 1.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Allocate user to position0, this supposed to trigger rule0.
        $manager = new \tool_organisation\job_manager();
        $manager->create_job((object)['userid' => $user0->id,
            'positionid' => $position0->id, 'departmentid' => $department0->id, 'startdate' => 1262304000]);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'You are in positions 0 or 1');
        $sink->clear();

        // Check matches record presence.
        $this->get_tool_dynamicrule_generator()->assert_user_matched_rule($this, $user0->id, $rule0->id);
    }

    /**
     * Test \tool_organisation\event\job_created event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing_update() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();
        $department0 = $this->get_generator()->create_department();
        $position0 = $this->get_generator()->create_position();
        $startdate = 1577841120; // Wed Jan 01 2020 01:12:00 GMT+0000.

        // Create rule0 with user has position conditon and notification outcome.
        $rule0 = $this->get_tool_dynamicrule_generator()->create_rule(['enabled' => 1]);
        $configdata = ['positionid' => $position0->id, 'jobstartdate' => $startdate];
        user_position::create($rule0->id, $configdata);
        $configdata = ['subject' => 'You are having position0',
            'body' => ['text' => 'Congratulations, you are having position0.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();

        // Allocate user to position, this will not trigger rule.
        $manager = new \tool_organisation\job_manager();
        $job = $manager->create_job((object)['userid' => $user0->id,
            'positionid' => $position0->id, 'departmentid' => $department0->id, 'startdate' => $startdate - 20 * DAYSECS]);

        // Check outcomes.
        $sink->get_messages();
        $this->assertEquals(0, $sink->count());
        $sink->clear();

        // Change job start date, this supposed to trigger rule0.
        $manager = new \tool_organisation\job_manager();
        $manager->update_job($job->get('id'), (object)['startdate' => $startdate + 20 * DAYSECS]);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'You are having position0');
        $sink->clear();

        // Check matches record presence.
        $this->get_tool_dynamicrule_generator()->assert_user_matched_rule($this, $user0->id, $rule0->id);
    }

    public function test_watch_job_startdate() {
        global $DB;

        if (!core_component::get_plugin_directory('tool', 'datewatch')) {
            $this->markTestSkipped('This test requires tool_datewatch');
        }

        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $user0 = $this->getDataGenerator()->create_user();
        $department0 = $generator->create_department();
        $position0 = $generator->create_position();
        $startdate = helper::round_time(time() + 20 * DAYSECS);

        // Create rule0 with user in department conditon and notification outcome.
        $rule0 = $this->get_tool_dynamicrule_generator()->create_rule(['enabled' => 1]);
        $configdata = ['positionid' => $position0->id];
        user_position::create($rule0->id, $configdata);
        $this->get_tool_dynamicrule_generator()->create_outcome_donothing($rule0->id);

        // Allocate user to department with the startdate in the future. The DR should not trigger.
        $manager = new \tool_organisation\job_manager();
        $job = $manager->create_job((object)[
            'userid' => $user0->id,
            'positionid' => $position0->id,
            'departmentid' => $department0->id,
            'startdate' => $startdate,
        ]);
        $this->get_tool_dynamicrule_generator()->assert_user_did_not_match_rule($this, $user0->id,  $rule0->id);

        (new tool_datewatch\task\watch())->execute();
        $this->get_tool_dynamicrule_generator()->assert_user_did_not_match_rule($this, $user0->id,  $rule0->id);

        // Time travel 20 days ahead.
        $delta = 20 * DAYSECS;
        if (extension_loaded('uopz')) {
            uopz_set_return('time', time() + $delta);
        } else {
            $this->getDataGenerator()->get_plugin_generator('tool_datewatch')
                ->shift_dates('tool_organisation_job', 'startdate', -$delta);
        }

        (new tool_datewatch\task\watch())->execute();
        $this->get_tool_dynamicrule_generator()->assert_user_matched_rule($this, $user0->id,  $rule0->id);
    }

    public function test_watch_job_enddate_unmatch() {
        if (!core_component::get_plugin_directory('tool', 'datewatch')) {
            $this->markTestSkipped('This test requires tool_datewatch');
        }

        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $user0 = $this->getDataGenerator()->create_user();
        $department0 = $generator->create_department();
        $position0 = $generator->create_position();
        $enddate = helper::round_time(time() + 19 * DAYSECS);

        // Create rule0 with user in department conditon and notification outcome.
        $rule0 = $this->get_tool_dynamicrule_generator()->create_rule(['enabled' => 1]);
        $configdata = ['positionid' => $position0->id];
        user_position::create($rule0->id, $configdata);
        $this->get_tool_dynamicrule_generator()->create_outcome_donothing($rule0->id);

        // Allocate user to department with the startdate in the future. The DR should not trigger.
        $manager = new \tool_organisation\job_manager();
        $job = $manager->create_job((object)[
            'userid' => $user0->id,
            'positionid' => $position0->id,
            'departmentid' => $department0->id,
            'startdate' => helper::round_time(time() - 5 * DAYSECS),
            'enddate' => $enddate,
        ]);
        $this->get_tool_dynamicrule_generator()->assert_user_matched_rule($this, $user0->id,  $rule0->id);

        (new tool_datewatch\task\watch())->execute();
        $this->get_tool_dynamicrule_generator()->assert_user_matched_rule($this, $user0->id,  $rule0->id);

        // Time travel 20 days ahead.
        $delta = 20 * DAYSECS;
        if (extension_loaded('uopz')) {
            uopz_set_return('time', time() + $delta);
        } else {
            $this->getDataGenerator()->get_plugin_generator('tool_datewatch')
                ->shift_dates('tool_organisation_job', 'enddate', -$delta);
        }

        (new tool_datewatch\task\watch())->execute();
        $this->get_tool_dynamicrule_generator()->assert_user_did_not_match_rule($this, $user0->id, $rule0->id, 1);
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        // Can add user in position conditon.
        $this->assertFalse(user_position::instance()->user_can_add());

        // We assign capability to user.
        $this->get_generator()->assign_capability('tool/organisation:assignjobs', $user->id, \context_system::instance());
        $this->assertTrue(user_position::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $user = self::getDataGenerator()->create_user();
        $position0 = $this->get_generator()->create_position();
        // In this test using $configdata is not compulstory, as underlying permission check is not
        // using it, we pass it for consistency with other tests.
        $configdata = ['positionid' => $position0->id];

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(user_position::instance()->user_can_edit($configdata));

        // Non-priveleged user.
        self::setUser($user);
        $this->assertFalse(user_position::instance()->user_can_edit($configdata));

        // Grant priveleges to user.
        $this->get_generator()->assign_capability('tool/organisation:assignjobs', $user->id, \context_system::instance());
        $this->assertTrue(user_position::instance()->user_can_edit($configdata));
    }

    /**
     * Test test_user_can_edit by tenant.
     */
    public function test_user_can_edit_tenant(): void {
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant = $tenantgenerator->create_tenant();
        $tenantadmin = $this->getDataGenerator()->create_user();
        $tenantgenerator->allocate_user($tenantadmin->id, $tenant->id);
        $manager = new \tool_tenant\manager();
        $manager->assign_tenant_admin_role($tenant->id, [$tenantadmin->id]);

        $position0 = $this->get_generator()->create_position(['tenantid' => $tenant->id]);
        // In this test using $configdata is not compulstory, as underlying permission check is not
        // using it, we pass it for consistency with other tests.
        $configdata = ['positionid' => $position0->id];

        // Sanity check.
        self::setAdminUser();
        $this->assertTrue(user_position::instance()->user_can_edit($configdata));

        // Tenant admin can edit conditions.
        self::setUser($tenantadmin);
        $this->assertTrue(user_position::instance()->user_can_edit($configdata));
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $outcome = user_position::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $outcome->supports_rule_types());
    }

    /**
     * Test condition matching with shared rules
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     * @uses \tool_dynamicrule\api::count_matching_users
     */
    public function test_get_matching_users_shared_rules() {
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $drgenerator = $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');

        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();

        [$tenant1, [$user11, $user12, $user13]] = $tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $tenantgenerator->create_tenant_and_users(3);

        $departmentframework1 = $this->get_generator()->create_department(['tenantid' => $tenant1->id]);
        $department1 = $this->get_generator()->create_department(['parentid' => $departmentframework1->id]);
        $departmentframework2 = $this->get_generator()->create_department(['tenantid' => $tenant2->id]);
        $department2 = $this->get_generator()->create_department(['parentid' => $departmentframework2->id]);

        $positionframework1 = $this->get_generator()->create_position(['tenantid' => $tenant1->id]);
        $position1 = $this->get_generator()->create_position(['parentid' => $positionframework1->id]);
        $positionframework2 = $this->get_generator()->create_position(['tenantid' => $tenant2->id]);
        $position2 = $this->get_generator()->create_position(['parentid' => $positionframework2->id]);
        $positionframeworkshared = $this->get_generator()->create_position(['tenantid' => $sharedtenantid]);
        $positionshared = $this->get_generator()->create_position(['parentid' => $positionframeworkshared->id]);

        // Assign User11 to Position1 and the shared position.
        $this->get_generator()->assign_job((object)[
            'userid' => $user11->id,
            'positionid' => $position1->id,
            'departmentid' => $department1->id,
        ]);
        $this->get_generator()->assign_job((object)[
            'userid' => $user11->id,
            'positionid' => $positionshared->id,
            'departmentid' => $department1->id,
        ]);

        // Assign User12 to Position1.
        $this->get_generator()->assign_job((object)[
            'userid' => $user12->id,
            'positionid' => $position1->id,
            'departmentid' => $department1->id,
        ]);

        // Assign User21 to Position2 and the shared position.
        $this->get_generator()->assign_job((object)[
            'userid' => $user21->id,
            'positionid' => $position2->id,
            'departmentid' => $department2->id,
        ]);
        $this->get_generator()->assign_job((object)[
            'userid' => $user21->id,
            'positionid' => $positionshared->id,
            'departmentid' => $department2->id,
        ]);

        // Assign User22 to Position2.
        $this->get_generator()->assign_job((object)[
            'userid' => $user22->id,
            'positionid' => $position2->id,
            'departmentid' => $department2->id,
        ]);

        // Create rule in Tenant1 that uses the position1.
        $rule1 = $drgenerator->create_rule(['tenantid' => $tenant1->id]);
        $configdata = ['positionid' => [$position1->id],
            'criteria' => user_position::CRITERIA_ALL, 'withsubpositions' => 0];
        user_position::create($rule1->id, $configdata);
        // Check for position1 and that it finds both users from Tenant1.
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user11->id, $user12->id], array_column($users, 'id'));

        // Create rule in Tenant1 that uses the shared position.
        $rule2 = $drgenerator->create_rule(['tenantid' => $tenant1->id]);
        $configdata = ['positionid' => [$positionshared->id],
            'criteria' => user_position::CRITERIA_ALL, 'withsubpositions' => 0];
        user_position::create($rule2->id, $configdata);
        // Check for shared position and that it finds just user11 from Tenant1.
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([$user11->id], array_column($users, 'id'));

        // Create rule in Shared space that uses the shared position.
        $rule3 = $drgenerator->create_rule(['tenantid' => $sharedtenantid]);
        $configdata = ['positionid' => [$positionshared->id],
            'criteria' => user_position::CRITERIA_ALL, 'withsubpositions' => 0];
        user_position::create($rule3->id, $configdata);
        // Check for shared position and that it finds users from both tenants.
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule3->id);
        $this->assertEqualsCanonicalizing([$user11->id, $user21->id], array_column($users, 'id'));
    }
}

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
 * File contains the unit tests for condition user_department class.
 *
 * @package    tool_organisation
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_organisation\tool_dynamicrule\condition\user_department;

/**
 * Unit tests for condition user_department  class.
 *
 * @package    tool_organisation
 * @group      tool_organisation
 * @covers     \tool_organisation\tool_dynamicrule\condition\user_department
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_condition_user_department_testcase extends advanced_testcase {

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
        $condition = user_department::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = user_department::instance();
        $this->assertEquals(get_string('pluginname', 'tool_organisation'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $department1 = $this->get_generator()->create_department();
        $condition = user_department::instance();
        $configform = ['departmentid' => [1000, $department1->id]];
        $this->assertArrayHasKey('departmentid', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     * @uses \tool_dynamicrule\api::count_matching_users
     */
    public function test_get_matching_users() {
        $user1 = $this->getDataGenerator()->create_user();
        $user1a = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $generator = $this->get_generator();

        $department1 = $generator->create_department();
        $department1a = $generator->create_department(['parentid' => $department1->id]);
        $department2 = $generator->create_department();

        $position1 = $generator->create_position();
        $position2 = $generator->create_position();

        $manager = new \tool_organisation\job_manager();

        $manager->create_job((object)['userid' => $user1->id,
            'positionid' => $position1->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);

        $manager->create_job((object)['userid' => $user1a->id,
            'positionid' => $position1->id, 'departmentid' => $department1a->id, 'startdate' => 1262304000]);

        $manager->create_job((object)['userid' => $user2->id,
            'positionid' => $position2->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);

        $manager->create_job((object)['userid' => $user3->id,
            'positionid' => $position1->id, 'departmentid' => $department2->id, 'startdate' => 1262304000]);

        $manager->create_job((object)['userid' => $user4->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1262304000]);

        // Department 1.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => $department1->id, 'withsubdepartments' => 0];
        user_department::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'id'));

        // Department 2.
        $rule2 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => $department2->id, 'withsubdepartments' => 0];
        user_department::create($rule2->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEqualsCanonicalizing([$user3->id, $user4->id], array_column($users, 'id'));

        // Department 1 with subdepartments.
        $rule3 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => $department1->id, 'withsubdepartments' => 1];
        user_department::create($rule3->id, $configdata);

        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule3->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user1a->id, $user2->id], array_column($users, 'id'));

        // Let's test if startdate is working properly.
        $user5 = $this->getDataGenerator()->create_user();
        $manager->create_job((object)['userid' => $user5->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1583193600]);

        // Only user5 should be found using this startdate and department2.
        $rule33 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => $department2->id, 'jobstartdate' => 1583193600];
        user_department::create($rule33->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule33->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule33->id);
        $this->assertEqualsCanonicalizing([$user5->id], array_column($users, 'id'));

        // No user should be found using this start date.
        $rule34 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => $department2->id, 'jobstartdate' => 1614729600];
        user_department::create($rule34->id, $configdata);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule34->id));
        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($rule34->id));

        // Three users should be found using this start date and department2.
        $rule35 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => $department2->id, 'jobstartdate' => 1262304000];
        user_department::create($rule35->id, $configdata);

        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule35->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule35->id);
        $this->assertEqualsCanonicalizing([$user3->id, $user4->id, $user5->id], array_column($users, 'id'));
    }

    /**
     * Test condition matching for multiple departments
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     * @uses \tool_dynamicrule\api::count_matching_users
     */
    public function test_get_matching_users_multiple_depts() {
        $user1 = $this->getDataGenerator()->create_user();
        $user1a = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $generator = $this->get_generator();

        $department1 = $generator->create_department();
        $department1a = $generator->create_department(['parentid' => $department1->id]);
        $department2 = $generator->create_department();

        $position1 = $generator->create_position();
        $position2 = $generator->create_position();

        $manager = new \tool_organisation\job_manager();

        // User1 - department1.
        $manager->create_job((object)['userid' => $user1->id,
            'positionid' => $position1->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);

        // User1a - department1a.
        $manager->create_job((object)['userid' => $user1a->id,
            'positionid' => $position1->id, 'departmentid' => $department1a->id, 'startdate' => 1262304000]);

        // User2 - department1 and department2.
        $manager->create_job((object)['userid' => $user2->id,
            'positionid' => $position2->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);
        $manager->create_job((object)['userid' => $user2->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1262304000]);

        // User3 - department2.
        $manager->create_job((object)['userid' => $user3->id,
            'positionid' => $position1->id, 'departmentid' => $department2->id, 'startdate' => 1262304000]);

        // User4 - department1 and department2.
        $manager->create_job((object)['userid' => $user4->id,
            'positionid' => $position2->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);
        $manager->create_job((object)['userid' => $user4->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1262304000]);

        // Department 1 && 2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id],
            'criteria' => user_department::CRITERIA_ALL, 'withsubdepartments' => 0];
        user_department::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user2->id, $user4->id], array_column($users, 'id'));

        // Department 1 || 2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id],
            'criteria' => user_department::CRITERIA_ANY, 'withsubdepartments' => 0];
        user_department::create($rule1->id, $configdata);

        $this->assertEquals(4, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($users, 'id'));

        // Department 1 || 2 with subdepartments.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id],
            'criteria' => user_department::CRITERIA_ANY, 'withsubdepartments' => 1];
        user_department::create($rule1->id, $configdata);

        $this->assertEquals(5, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user1a->id, $user2->id, $user3->id, $user4->id],
            array_column($users, 'id'));

        // Let's test if startdate is working properly.
        $user5 = $this->getDataGenerator()->create_user();
        $manager->create_job((object)['userid' => $user5->id,
            'positionid' => $position2->id, 'departmentid' => $department2->id, 'startdate' => 1583193600]);

        // Only user5 should be found using this startdate and department1 || department2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id],
            'criteria' => user_department::CRITERIA_ANY, 'jobstartdate' => 1583193600];
        user_department::create($rule1->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user5->id], array_column($users, 'id'));

        // No user should be found using this startdate and department1 && department2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id],
            'criteria' => user_department::CRITERIA_ALL, 'jobstartdate' => 1583193600];
        user_department::create($rule1->id, $configdata);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($rule1->id));

        // No user should be found using this start date.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id],
            'criteria' => user_department::CRITERIA_ANY, 'jobstartdate' => 1614729600];
        user_department::create($rule1->id, $configdata);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $this->assertEmpty(\tool_dynamicrule\api::get_matching_users($rule1->id));

        // Five users should be found using this start date and department1 || department2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id],
            'criteria' => user_department::CRITERIA_ANY, 'jobstartdate' => 1262304000];
        user_department::create($rule1->id, $configdata);

        $this->assertEquals(5, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id, $user5->id], array_column($users, 'id'));

        // One user should be found using this start date and department1 && department2.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id],
            'criteria' => user_department::CRITERIA_ALL, 'jobstartdate' => 1262304000];
        user_department::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user2->id, $user4->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        $department1 = $this->get_generator()->create_department();
        $department2 = $this->get_generator()->create_department();

        // One department.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => $department1->id];
        $condition1 = user_department::create($rule1->id, $configdata);

        $options = ['deptname' => $department1->name, 'subdeptsinclude' => 'Not included'];
        $expected = get_string('conditionuserdepartmentdescription', 'tool_organisation', $options);
        $this->assertEquals($expected, $condition1->get_description());

        // All departments.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id], 'criteria' => user_department::CRITERIA_ALL];
        $condition1 = user_department::create($rule1->id, $configdata);

        $options = ['deptname' => "{$department1->name}', '{$department2->name}", 'subdeptsinclude' => 'Not included'];
        $expected = get_string('conditionuserdepartmentsalldescription', 'tool_organisation', $options);
        $this->assertEquals($expected, $condition1->get_description());

        // Any departments.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id], 'criteria' => user_department::CRITERIA_ANY];
        $condition1 = user_department::create($rule1->id, $configdata);

        $options = ['deptname' => "{$department1->name}', '{$department2->name}", 'subdeptsinclude' => 'Not included'];
        $expected = get_string('conditionuserdepartmentsanydescription', 'tool_organisation', $options);
        $this->assertEquals($expected, $condition1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        global $DB;
        $department1 = $this->get_generator()->create_department();
        $department2 = $this->get_generator()->create_department();

        // Users in department.
        $rule1 = $this->get_tool_dynamicrule_generator()->create_rule();
        $configdata = ['departmentid' => [$department1->id, $department2->id]];
        $condition1 = user_department::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('tool_organisation_department', ['id' => $department1->id]);

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

        // Create rule0 with user in department conditon and notification outcome.
        $rule0 = $this->get_tool_dynamicrule_generator()->create_rule(['enabled' => 1]);
        $configdata = ['departmentid' => $department0->id, 'withsubdepartments' => 0];
        user_department::create($rule0->id, $configdata);
        $configdata = ['subject' => 'You are in department0',
            'body' => ['text' => 'Congratulations, you are in department0.', 'format' => FORMAT_MOODLE]];
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
        $this->assertEquals($messages[0]->subject, 'You are in department0');
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }

    /**
     * Test \tool_organisation\event\job_created event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing_multiple_departments_all() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();
        $department0 = $this->get_generator()->create_department();
        $department1 = $this->get_generator()->create_department();
        $position0 = $this->get_generator()->create_position();
        $position1 = $this->get_generator()->create_position();

        // Create rule0 with user in department conditon and notification outcome.
        $rule0 = $this->get_tool_dynamicrule_generator()->create_rule(['enabled' => 1]);
        $configdata = ['departmentid' => [$department0->id, $department1->id],
            'withsubdepartments' => 0, 'criteria' => user_department::CRITERIA_ALL];
        user_department::create($rule0->id, $configdata);
        $configdata = ['subject' => 'You are in departments 0 and 1',
            'body' => ['text' => 'Congratulations, you are in departments 0 and 1.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Allocate user to department0, this supposed to trigger rule0, but nothing will happen.
        $manager = new \tool_organisation\job_manager();
        $manager->create_job((object)['userid' => $user0->id,
            'positionid' => $position0->id, 'departmentid' => $department0->id, 'startdate' => 1262304000]);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(0, $sink->count());
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));

        // Allocate user to department1, this supposed to trigger rule0, now user matches condition.
        $manager = new \tool_organisation\job_manager();
        $manager->create_job((object)['userid' => $user0->id,
            'positionid' => $position1->id, 'departmentid' => $department1->id, 'startdate' => 1262304000]);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'You are in departments 0 and 1');
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }

    /**
     * Test \tool_organisation\event\job_created event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing_multiple_departments_any() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();
        $department0 = $this->get_generator()->create_department();
        $department1 = $this->get_generator()->create_department();
        $position0 = $this->get_generator()->create_position();
        $position1 = $this->get_generator()->create_position();

        // Create rule0 with user in department conditon and notification outcome.
        $rule0 = $this->get_tool_dynamicrule_generator()->create_rule(['enabled' => 1]);
        $configdata = ['departmentid' => [$department0->id, $department1->id],
            'withsubdepartments' => 0, 'criteria' => user_department::CRITERIA_ANY];
        user_department::create($rule0->id, $configdata);
        $configdata = ['subject' => 'You are in departments 0 or 1',
            'body' => ['text' => 'Congratulations, you are in departments 0 or 1.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Allocate user to department0, this supposed to trigger rule0.
        $manager = new \tool_organisation\job_manager();
        $manager->create_job((object)['userid' => $user0->id,
            'positionid' => $position0->id, 'departmentid' => $department0->id, 'startdate' => 1262304000]);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'You are in departments 0 or 1');
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
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
        $startdate = 1577841120;

        // Create rule0 with user in department conditon and notification outcome.
        $rule0 = $this->get_tool_dynamicrule_generator()->create_rule(['enabled' => 1]);
        $configdata = ['departmentid' => $department0->id, 'withsubdepartments' => 0, 'jobstartdate' => $startdate];
        user_department::create($rule0->id, $configdata);
        $configdata = ['subject' => 'You are in department0',
            'body' => ['text' => 'Congratulations, you are in department0.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();

        // Allocate user to department, this supposed to trigger rule0.
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
        $this->assertEquals($messages[0]->subject, 'You are in department0');
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $user = self::getDataGenerator()->create_user();

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(user_department::instance()->user_can_add());

        // Non-priveleged user.
        self::setUser($user);
        $this->assertFalse(user_department::instance()->user_can_add());

        // Grant priveleges to user.
        $this->get_generator()->assign_capability('tool/organisation:assignjobs', $user->id, \context_system::instance());
        $this->assertTrue(user_department::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $user = self::getDataGenerator()->create_user();
        $department0 = $this->get_generator()->create_department();
        // In this test using $configdata is not compulstory, as underlying permission check is not
        // using it, we pass it for consistency with other tests.
        $configdata = ['departmentid' => $department0->id, 'withsubdepartments' => 0];

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(user_department::instance()->user_can_edit($configdata));

        // Non-priveleged user.
        self::setUser($user);
        $this->assertFalse(user_department::instance()->user_can_edit($configdata));

        // Grant priveleges to user.
        $this->get_generator()->assign_capability('tool/organisation:assignjobs', $user->id, \context_system::instance());
        $this->assertTrue(user_department::instance()->user_can_edit($configdata));
    }

    /**
     * Test test_user_can_edit by tenant.
     */
    public function test_user_can_edit_tenant() {
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant = $tenantgenerator->create_tenant();
        $tenantadmin = $this->getDataGenerator()->create_user();
        $tenantgenerator->allocate_user($tenantadmin->id, $tenant->id);
        $manager = new \tool_tenant\manager();
        $manager->assign_tenant_admin_role($tenant->id, [$tenantadmin->id]);

        $department0 = $this->get_generator()->create_department(['tenantid' => $tenant->id]);
        // In this test using $configdata is not compulstory, as underlying permission check is not
        // using it, we pass it for consistency with other tests.
        $configdata = ['departmentid' => $department0->id, 'withsubdepartments' => 0];

        // Sanity check.
        self::setAdminUser();
        $this->assertTrue(user_department::instance()->user_can_edit($configdata));

        // Tenant admin can edit conditions.
        self::setUser($tenantadmin);
        $this->assertTrue(user_department::instance()->user_can_edit($configdata));
    }
}

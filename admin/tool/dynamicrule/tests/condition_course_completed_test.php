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
 * File contains the unit tests for condition course_completed class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition course_completed  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\course_completed
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\condition_sql
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_condition_course_completed_testcase extends advanced_testcase {

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
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\course_completed();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\course_completed();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $this->resetAfterTest();

        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\course_completed();
        $configform = ['courseid' => 0];
        $this->assertArrayHasKey('courseid', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users() {
        global $DB;

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course1->id, 'student');

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        \tool_dynamicrule\tool_dynamicrule\condition\course_completed::create($rule1->id, $configdata);

        $record = ['course' => $course1->id, 'userid' => $user1->id,
                   'timeenrolled' => time(), 'timestarted' => time(), 'timecompleted' => time(), 'reaggregate' => 0];
        $DB->insert_record('course_completions', (object)$record);

        $record['userid'] = $user2->id;
        $DB->insert_record('course_completions', (object)$record);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals([$user1->id, $user2->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test course_completed event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing() {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_self.php');
        set_config('enablecompletion', COMPLETION_ENABLED);
        $this->resetAfterTest(true);

        // Course with self-completion enabled.
        $completedcourse = $this->getDataGenerator()->create_course(['shortname' => 'MATH101', 'fullname' => 'Mathematics 101',
            'enablecompletion' => COMPLETION_ENABLED]);
        $criteriadata = new stdClass();
        $criteriadata->id = $completedcourse->id;
        $criteriadata->criteria_self = COMPLETION_CRITERIA_TYPE_SELF;
        $criterion = new completion_criteria_self();
        $criterion->update_config($criteriadata);

        // User enrolled into course.
        $user0 = $this->getDataGenerator()->create_user(['firstname' => 'John', 'lastname' => 'Smith']);
        $this->getDataGenerator()->enrol_user($user0->id, $completedcourse->id, 'student', 'manual');

        // Create rule0 with course completed conditon and notification outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $completedcourse->id];
        \tool_dynamicrule\tool_dynamicrule\condition\course_completed::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Completed {{courseshortname}}',
            'body' => ['text' => 'Congratulations {{userfullname}}, ' .
                'you have completed (<a href="{{courseurl}}">{{coursefullname}}</a>).',
                'format' => FORMAT_HTML]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Complete course, this supposed to trigger rule0.
        $this->setUser($user0);
        core_completion_external::mark_course_self_completed($completedcourse->id);
        $ccompletion = new completion_completion(array('course' => $completedcourse->id, 'userid' => $user0->id));
        $ccompletion->mark_complete();

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals('Completed MATH101', $messages[0]->subject);
        $url = $CFG->wwwroot.'/course/view.php?id=' . $completedcourse->id;
        $this->assertEquals('Congratulations John Smith, you have completed (<a href="' .
            $url . '">Mathematics 101</a>).',
            $messages[0]->fullmessagehtml);
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\course_completed::create($rule1->id, $configdata);

        $this->assertEquals(get_string('conditioncoursecompleteddescription', 'tool_dynamicrule', $course1->fullname),
                            $condition1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\course_completed::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('course', ['id' => $course1->id]);

        $this->assertFalse($condition1->is_configuration_valid());
    }
}

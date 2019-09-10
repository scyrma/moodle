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
 * File contains the unit tests for observer class.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * File contains the unit tests for observer class.
 *
 * @package     tool_dynamicrule
 * @group       tool_dynamicrule
 * @copyright   2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dynamicrule_observer_testcase extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp() {
        $this->resetAfterTest();
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
     * Test user_enrolled event is triggering rule processing.
     *
     * @covers \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_user_enrolled_trigger_rule_processing() {
        global $DB;

        // Three courses.
        $course0 = $this->getDataGenerator()->create_course();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        // Three users.
        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Create rule0 with course0 enrol conditon and notification outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course0->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Course0 enrolled', 'body' => 'Congratulations, you are course0 enrolled.'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Create rule1 with course1 enrol conditon and notification outcome.
        $rule1 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course1->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule1->id, $configdata);
        $configdata = ['subject' => 'Course1 enrolled', 'body' => 'Congratulations, you are course1 enrolled.'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        // Create rule2 (disabled) with course2 enrol conditon and notification outcome.
        $rule2 = $this->get_generator()->create_rule(['enabled' => 0]);
        $configdata = ['courseid' => $course2->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule2->id, $configdata);
        $configdata = ['subject' => 'Course1 enrolled', 'body' => 'Congratulations, you are course2 enrolled.'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule2->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Enrol user0 into course0, this supposed to trigger rule0.
        $this->getDataGenerator()->enrol_user($user0->id, $course0->id, 'student', 'manual');

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'Course0 enrolled');
        $sink->clear();

        // Enrol user1 into course1, this supposed to trigger rule1.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student', 'manual');

        // Check outcomes.
        $this->assertEquals(1, $sink->count());
        $messages = $sink->get_messages();
        $this->assertEquals($messages[0]->subject, 'Course1 enrolled');
        $sink->clear();

        // Enrol user2 into course2, this is not supposed to trigger anything (rule is disabled).
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id, 'student', 'manual');
        $this->assertEquals(0, $sink->count());

        // Check matches record presence.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id]));
    }
}

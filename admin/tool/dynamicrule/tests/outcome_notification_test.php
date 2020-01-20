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
 * File contains the unit tests for outcome\notification class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for outcome\notification  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\outcome\notification
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_outcome_notification_testcase extends advanced_testcase {

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
     * Test get_title
     */
    public function test_get_title() {
        $outcome = new \tool_dynamicrule\tool_dynamicrule\outcome\notification();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = new \tool_dynamicrule\tool_dynamicrule\outcome\notification();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {

        $outcome = new \tool_dynamicrule\tool_dynamicrule\outcome\notification();
        $configform = ['subject' => '', 'body' => ''];
        $this->assertArrayHasKey('body', $outcome->validate_config_form($configform));
        $this->assertArrayHasKey('subject', $outcome->validate_config_form($configform));

        $configform = ['subject' => 'The notification subject', 'body' => 'Here comes the message.'];
        $this->assertArrayNotHasKey('body', $outcome->validate_config_form($configform));
        $this->assertArrayNotHasKey('subject', $outcome->validate_config_form($configform));
    }

    /**
     * Test apply_to_users
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\course_completed
     */
    public function test_apply_to_users() {
        global $DB;

        $rule0 = $this->get_generator()->create_rule();

        $course1 = $this->getDataGenerator()->create_course();
        $configdata = ['courseid' => $course1->id];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\course_completed::create($rule0->id, $configdata);

        $configdata = ['subject' => 'You matched!',
            'body' => ['text' => "Congratulations {{userfullname}},\n" .
            "you completed the course {{coursefullname}}", 'format' => FORMAT_HTML]];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        $users = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];

        $sink = $this->redirectMessages();
        $outcome->apply_to_users($users);
        $savedmessages = $sink->get_messages();
        $sink->close();

        $this->assertEquals(2, $DB->count_records('notifications'));
        $this->assertCount(2, $savedmessages);

        $expected = [
            'Congratulations ' . fullname($users[0]). ",\nyou completed the course {$course1->fullname}",
            'Congratulations ' . fullname($users[1]). ",\nyou completed the course {$course1->fullname}",
        ];

        $this->assertEqualsCanonicalizing($expected, [$savedmessages[0]->fullmessagehtml, $savedmessages[1]->fullmessagehtml]);

        // TODO add more assertions about the message contents here.
    }

    /**
     * Test get_description.
     */
    public function test_get_description() {

        $rule0 = $this->get_generator()->create_rule();

        $subject = 'You matched!';
        $configdata = ['subject' => $subject,
            'body' => ['text' => 'Congratulations, you matched the condition', 'format' => FORMAT_MOODLE]];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        $str = get_string('outcomenotificationdescription', 'tool_dynamicrule', $subject);
        $this->assertEquals($str, $outcome->get_description());
    }

    /**
     * Test after_update.
     *
     * @uses \tool_dynamicrule\api::get_rule
     */
    public function test_after_update() {
        $rule0 = $this->get_generator()->create_rule(['broken' => 1]);

        $subject = 'Test subject';
        $configdata = ['subject' => $subject, 'body' => ['text' => 'Test body', 'format' => FORMAT_MOODLE]];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        $configdata = ['subject' => $subject, 'body' => ['text' => 'Test body updated', 'format' => FORMAT_MOODLE]];
        $outcome->update_configdata($configdata);

        $this->assertFalse($outcome->is_broken());
        $this->assertFalse(\tool_dynamicrule\api::get_rule($rule0->id)->is_broken());
    }
}

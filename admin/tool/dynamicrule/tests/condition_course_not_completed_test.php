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
 * File contains the unit tests for condition course_not_completed class.
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
 * Unit tests for condition course_not_completed  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_condition_course_not_completed_testcase extends advanced_testcase {

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
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\course_not_completed();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\course_not_completed();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $this->resetAfterTest();

        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\course_not_completed();
        $configform = ['courseid' => 0];
        $this->assertArrayHasKey('courseid', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
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
        \tool_dynamicrule\tool_dynamicrule\condition\course_not_completed::create($rule1->id, $configdata);

        $record1 = ['course' => $course1->id, 'userid' => $user1->id,
                   'timeenrolled' => time(), 'timestarted' => time(), 'timecompleted' => null, 'reaggregate' => 0];

        $record2 = ['course' => $course1->id, 'userid' => $user2->id,
                   'timeenrolled' => time(), 'timestarted' => time(), 'timecompleted' => null, 'reaggregate' => 0];

        $record1['id'] = $DB->insert_record('course_completions', (object)$record1);
        $record2['id'] = $DB->insert_record('course_completions', (object)$record2);

        $this->assertEquals(5, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals([$user1->id, $user2->id, 1, get_admin()->id, $user3->id], array_column($users, 'id'), '', 0, 10, true);

        $record1['timecompleted'] = time();
        $DB->update_record('course_completions', $record1);
        $record2['timecompleted'] = time();
        $DB->update_record('course_completions', $record2);

        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        \tool_dynamicrule\tool_dynamicrule\condition\course_not_completed::create($rule2->id, $configdata);

        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEquals([1, get_admin()->id, $user3->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\course_not_completed::create($rule1->id, $configdata);

        $this->assertEquals(get_string('conditioncoursenotcompleteddescription', 'tool_dynamicrule', $course1->fullname),
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
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\course_not_completed::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('course', ['id' => $course1->id]);

        $this->assertFalse($condition1->is_configuration_valid());
    }
}

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
 * File contains the unit tests for outcome course_unenrol class.
 *
 * @package    enrol_dynamicrule
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for outcome\course_enrol  class.
 *
 * @package    enrol_dynamicrule
 * @group      enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class enrol_dynamicrule_outcome_course_unenrol_testcase extends advanced_testcase {

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
        $outcome = new \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = new \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol();
        $this->assertEquals(get_string('courses'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $outcome = new \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol();
        $configform = ['coursetounenrol' => 10];
        $this->assertArrayHasKey('coursetounenrol', $outcome->validate_config_form($configform));

        $course1 = $this->getDataGenerator()->create_course();

        $configform = ['coursetounenrol' => $course1->id];
        $this->assertArrayNotHasKey('coursetoenrol', $outcome->validate_config_form($configform));
    }

    /**
     * Test apply_to_users disable enrolment
     */
    public function test_apply_to_users_disable_enrolment() {
        global $DB;

        $this->apply_to_users_setup();

        $configdata = ['coursetounenrol' => $this->course1->id,
                       'action' => \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol::ACTION_DISABLE_ENROLMENT];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol::create($this->rule0->id, $configdata);

        $outcome->apply_to_users([$this->user1, $this->user2]);

        $this->assertEquals(1, $DB->count_records('user_enrolments',
            ['userid' => $this->user1->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(1, $DB->count_records('role_assignments', ['userid' => $this->user1->id]));

        $this->assertEquals(0, $DB->count_records('user_enrolments',
            ['userid' => $this->user2->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(1, $DB->count_records('role_assignments', ['userid' => $this->user2->id]));
    }

    /**
     * Test apply_to_users disable enrolment and remove roles
     */
    public function test_apply_to_users_disable_enrolment_remove_roles() {
        global $DB;

        $this->apply_to_users_setup();

        $configdata = ['coursetounenrol' => $this->course1->id,
            'action' => \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol::ACTION_DISABLE_ENROLMENT_REMOVE_ROLES];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol::create($this->rule0->id, $configdata);

        $outcome->apply_to_users([$this->user1, $this->user2]);

        $this->assertEquals(1, $DB->count_records('user_enrolments',
            ['userid' => $this->user1->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(0, $DB->count_records('role_assignments', ['userid' => $this->user1->id]));

        $this->assertEquals(0, $DB->count_records('user_enrolments',
            ['userid' => $this->user2->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(1, $DB->count_records('role_assignments', ['userid' => $this->user2->id]));
    }

    /**
     * Test apply_to_users unenrol
     */
    public function test_apply_to_users_unenrol() {
        global $DB;

        $this->apply_to_users_setup();

        $configdata = ['coursetounenrol' => $this->course1->id,
                       'action' => \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol::ACTION_UNENROL];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol::create($this->rule0->id, $configdata);

        $outcome->apply_to_users([$this->user1, $this->user2]);

        $this->assertEquals(0, $DB->count_records('user_enrolments', ['userid' => $this->user1->id]));
        $this->assertEquals(0, $DB->count_records('role_assignments', ['userid' => $this->user1->id]));

        $this->assertEquals(0, $DB->count_records('user_enrolments',
            ['userid' => $this->user2->id, 'status' => ENROL_USER_SUSPENDED]));
        $this->assertEquals(1, $DB->count_records('role_assignments', ['userid' => $this->user2->id]));
    }

    /**
     * This is used by functions that test apply_to_users
     */
    private function apply_to_users_setup() {
        global $CFG;

        $this->rule0 = $this->get_generator()->create_rule();

        $this->course1 = $this->getDataGenerator()->create_course();

        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();

        $CFG->enrol_plugins_enabled .= ',dynamicrule';

        $plugin = enrol_get_plugin('dynamicrule');
        $plugin->add_instance(get_course($this->course1->id));

        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course1->id, 'student', 'dynamicrule');
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course1->id, 'student', 'manual');
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $rule0 = $this->get_generator()->create_rule();

        $course1 = $this->getDataGenerator()->create_course();

        $configdata = ['coursetounenrol' => $course1->id];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol::create($rule0->id, $configdata);

        $str = get_string('outcomecourseunenroldescription', 'enrol_dynamicrule', $course1->fullname);
        $this->assertEquals($str, $outcome->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['coursetounenrol' => $course1->id];
        $condition1 = \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('course', ['id' => $course1->id]);

        $this->assertFalse($condition1->is_configuration_valid());
    }
}

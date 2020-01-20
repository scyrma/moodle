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
 * File contains the unit tests for outcome course_enrol class.
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
class enrol_dynamicrule_outcome_course_enrol_testcase extends advanced_testcase {

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
        $outcome = new \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = new \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol();
        $this->assertEquals(get_string('courses'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        global $DB;

        $this->resetAfterTest();

        $outcome = new \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol();
        $configform = ['coursetoenrol' => 10];
        $this->assertArrayHasKey('coursetoenrol', $outcome->validate_config_form($configform));
        $this->assertArrayHasKey('role', $outcome->validate_config_form($configform));

        $course1 = $this->getDataGenerator()->create_course();

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configform = ['coursetoenrol' => $course1->id, 'role' => $roleid];
        $this->assertArrayNotHasKey('coursetoenrol', $outcome->validate_config_form($configform));
        $this->assertArrayNotHasKey('role', $outcome->validate_config_form($configform));
    }

    /**
     * Test apply_to_users
     */
    public function test_apply_to_users() {
        global $DB, $CFG;

        $rule0 = $this->get_generator()->create_rule();

        $course1 = $this->getDataGenerator()->create_course();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course1->id, 'role' => $roleid];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule0->id, $configdata);

        $userids = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];

        // Make plugin disabled by default.
        $CFG->enrol_plugins_enabled = '';
        $this->assertFalse(enrol_is_enabled('dynamicrule'));

        // Make sure nothing is done when applying on empty set of users.
        $outcome->apply_to_users([]);

        $this->assertEquals(0, $DB->count_records('enrol', ['enrol' => 'dynamicrule']));
        $this->assertEquals(0, $DB->count_records('user_enrolments'));

        $this->assertEquals(0, $DB->count_records('groups'));
        $this->assertEquals(0, $DB->count_records('groups_members'));

        // Now apply on valid users.
        $outcome->apply_to_users($userids);

        // Check plugin has been enabled (we force-enable in on enrol request).
        $this->assertTrue(enrol_is_enabled('dynamicrule'));

        $params = ['courseid' => $course1->id, 'enrol' => 'dynamicrule', 'customint1' => $rule0->id];
        $this->assertEquals(1, $DB->count_records('enrol', $params));
        $this->assertEquals(2, $DB->count_records('user_enrolments'));

        $this->assertEquals(0, $DB->count_records('groups'));
        $this->assertEquals(0, $DB->count_records('groups_members'));

        $userids2 = [$this->getDataGenerator()->create_user()];

        // Apply on another user.
        $outcome->apply_to_users($userids2);

        $params = ['courseid' => $course1->id, 'enrol' => 'dynamicrule', 'customint1' => $rule0->id];
        $this->assertEquals(1, $DB->count_records('enrol', $params));
        $this->assertEquals(3, $DB->count_records('user_enrolments'));

        $this->assertEquals(0, $DB->count_records('groups'));
        $this->assertEquals(0, $DB->count_records('groups_members'));

        // Test rule with a group to add users.
        $rule1 = $this->get_generator()->create_rule();

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course1->id, 'role' => $roleid, 'group' => 'Dynamic group'];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule1->id, $configdata);

        $outcome->apply_to_users(array_merge($userids, $userids2));

        $params = ['courseid' => $course1->id, 'enrol' => 'dynamicrule', 'customint1' => $rule1->id];
        $this->assertEquals(1, $DB->count_records('enrol', $params));
        $this->assertEquals(6, $DB->count_records('user_enrolments'));

        $this->assertEquals(1, $DB->count_records('groups'));
        $this->assertEquals(3, $DB->count_records('groups_members'));

        $userids3 = [$this->getDataGenerator()->create_user()];

        $outcome->apply_to_users($userids3);

        $params = ['courseid' => $course1->id, 'enrol' => 'dynamicrule', 'customint1' => $rule1->id];
        $this->assertEquals(1, $DB->count_records('enrol', $params));
        $this->assertEquals(7, $DB->count_records('user_enrolments'));

        $this->assertEquals(1, $DB->count_records('groups'));
        $this->assertEquals(4, $DB->count_records('groups_members'));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $rule0 = $this->get_generator()->create_rule();

        $course1 = $this->getDataGenerator()->create_course();

        $configdata = ['coursetoenrol' => $course1->id];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule0->id, $configdata);

        $str = get_string('outcomecourseenroldescription', 'enrol_dynamicrule', $course1->fullname);
        $this->assertEquals($str, $outcome->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();

        $roleid = $this->getDataGenerator()->create_role();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['coursetoenrol' => $course1->id, 'role' => $roleid];
        $condition1 = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('course', ['id' => $course1->id]);

        $this->assertFalse($condition1->is_configuration_valid());

        $course2 = $this->getDataGenerator()->create_course();

        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['coursetoenrol' => $course2->id, 'role' => $roleid];
        $condition1 = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule2->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('role', ['id' => $roleid]);

        $this->assertFalse($condition1->is_configuration_valid());
    }
}

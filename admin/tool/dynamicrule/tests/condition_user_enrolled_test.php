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
 * File contains the unit tests for condition user_enrolled class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition user_enrolled  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\condition_sql
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dynamicrule_condition_user_enrolled_testcase extends advanced_testcase {

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
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled();
        $configform = ['courseid' => 0];
        $this->assertArrayHasKey('courseid', $condition->validate_config_form($configform));
    }

    /**
     * Setup users and enrolments for get_matching_users testing methods
     */
    public function get_matching_users_setup() {
        global $CFG;

        $this->course1 = $this->getDataGenerator()->create_course();

        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();

        $CFG->enrol_plugins_enabled .= ',dynamicrule';

        $plugin = enrol_get_plugin('dynamicrule');
        $plugin->add_instance(get_course($this->course1->id));

        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course1->id, 'student', 'dynamicrule');
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course1->id, 'student', 'manual');
        $this->getDataGenerator()->enrol_user($this->user3->id, $this->course1->id, 'student', 'manual');
    }

    /**
     * Test condition matching in a specified course with any enrol method
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_course_any_enrol() {

        $this->get_matching_users_setup();

        // In a course, any enrolment method.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $this->course1->id];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule1->id, $configdata);

        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals([$this->user1->id, $this->user2->id, $this->user3->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test user_enrolled event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing() {
        global $DB;

        $course0 = $this->getDataGenerator()->create_course();
        $user0 = $this->getDataGenerator()->create_user();

        // Create rule0 with course0 enrol conditon and notification outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course0->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Course0 enrolled', 'body' => 'Congratulations, you are course0 enrolled.'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

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

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }

    /**
     * Test condition matching in a specified course with manual enrolment method
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_course_manual_enrol() {

        $this->get_matching_users_setup();

        // In a course, 'manual' enrolment method.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $this->course1->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule2->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule2->id);
        $this->assertEquals([$this->user2->id, $this->user3->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test condition matching in a specified course with other enrolment method
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_course_other_enrol() {

        $this->get_matching_users_setup();

        // In a course, 'dynamicrule' enrolment method.
        $rule3 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $this->course1->id, 'enrol' => 'dynamicrule'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule3->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule3->id);
        $this->assertEquals([$this->user1->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test condition matching in a specified course with specified course start time.
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_course_timeenrolled() {

        $this->get_matching_users_setup();

        $this->user4 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->user4->id, $this->course1->id, 'student', 'manual', strtotime('+1 week'));

        // In a course, enrolment starting next week.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $this->course1->id, 'timeenrolled' => strtotime('+1 week')];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule1->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals([$this->user4->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata1 = ['courseid' => $course1->id];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule1->id, $configdata1);

        $a = (object)['course' => $course1->fullname, 'enrol' => get_string('pluginname', 'enrol_manual')];

        $this->assertEquals(get_string('conditionuserenrolleddescription', 'tool_dynamicrule', $a),
                            $condition1->get_description());

        $rule3 = $this->get_generator()->create_rule();
        $configdata3 = ['courseid' => $course1->id, 'enrol' => 'manual'];
        $condition3 = \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule3->id, $configdata3);

        $this->assertEquals(get_string('conditionuserenrolleddescriptionwithenrol', 'tool_dynamicrule', $a),
                            $condition3->get_description());
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
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('course', ['id' => $course1->id]);

        $this->assertFalse($condition1->is_configuration_valid());
    }

    /**
     * Test condition matching in a specified course with any enrol method
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_with_multitenancy() {
        global $DB;

        $this->setAdminUser();

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $othertenant = $tenantgenerator->create_tenant();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $tenantgenerator->allocate_user($user1->id, $defaulttenantid);
        $tenantgenerator->allocate_user($user2->id, $othertenant->id);
        $tenantgenerator->allocate_user($user3->id, $othertenant->id);

        $category = $this->getDataGenerator()->create_category();
        $manager = new \tool_tenant\manager();
        $manager->assign_tenant_admin_role($othertenant->id, [$user3->id], $category->id);

        $rule1 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $defaulttenantid]);

        $course1 = $this->getDataGenerator()->create_course();

        $configdata = ['courseid' => $course1->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule1->id, $configdata);

        $configdata = ['subject' => 'Course1 enrolled', 'body' => 'Congratulations, you are course1 enrolled.'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Enrol user1 into course1, this supposed to trigger rule1.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student', 'manual');

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'Course1 enrolled');
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id]));

        // Now check other tenant.
        $course2 = $this->getDataGenerator()->create_course(['categoryid' => $category->id]);

        $rule2 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $othertenant->id]);

        $configdata = ['courseid' => $course2->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule2->id, $configdata);

        $configdata = ['subject' => 'Course2 enrolled', 'body' => 'Congratulations, you are course2 enrolled.'];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule2->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(1, $DB->count_records('notifications')); // The first notification is still there.

        // Enrol user2 into course2, this supposed to trigger rule2.
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id, 'student', 'manual');

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'Course2 enrolled');
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule2->id]));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id]));
    }
}

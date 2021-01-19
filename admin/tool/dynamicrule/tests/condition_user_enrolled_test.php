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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * File contains the unit tests for condition user_enrolled class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\condition;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;

use tool_dynamicrule\tool_dynamicrule\condition\user_enrolled;
use tool_dynamicrule\tool_dynamicrule\outcome\notification;

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
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class condition_user_enrolled_testcase extends \advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get dynamic rule generator
     *
     * @return \tool_dynamicrule_generator
     */
    protected function get_generator(): \tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Get workplace generator
     *
     * @return \tool_wp_generator
     */
    protected function get_workplace_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $condition = user_enrolled::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = user_enrolled::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $condition = user_enrolled::instance();
        $configform = ['courseid' => 0];

        // Invalid course.
        $this->assertArrayHasKey('courseid', $condition->validate_config_form($configform));

        // Valid course, enrolment 'any'.
        $course0 = $this->getDataGenerator()->create_course();
        $configform = ['courseid' => $course0->id, 'enrol' => ''];
        $this->assertArrayNotHasKey('enrol', $condition->validate_config_form($configform));
        $this->assertArrayNotHasKey('courseid', $condition->validate_config_form($configform));

        // Valid course, enrolment 'manual'.
        $course0 = $this->getDataGenerator()->create_course();
        $configform = ['courseid' => $course0->id, 'enrol' => 'manual'];
        $this->assertArrayNotHasKey('enrol', $condition->validate_config_form($configform));
        $this->assertArrayNotHasKey('courseid', $condition->validate_config_form($configform));

        // Valid course, invalid enrolment.
        $configform = ['courseid' => $course0->id, 'enrol' => 'notexist'];
        $this->assertArrayHasKey('enrol', $condition->validate_config_form($configform));
        $this->assertArrayNotHasKey('courseid', $condition->validate_config_form($configform));
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        // Anyone can add this condition.
        $condition = user_enrolled::instance();
        $this->assertTrue($condition->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $course0 = $this->getDataGenerator()->create_course();
        $configform = ['courseid' => $course0->id];
        $condition = user_enrolled::instance();

        // Admin user.
        $this->setAdminUser();
        $this->assertTrue($condition->user_can_edit($configform));

        // Non-priveleged user.
        $user = $this->getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($condition->user_can_edit($configform));

        // Grant priveleges to user.
        $this->getDataGenerator()->enrol_user($user->id, $course0->id, 'editingteacher');
        $this->assertTrue($condition->user_can_edit($configform));
    }

    /**
     * Setup users and enrolments for get_matching_users testing methods
     */
    public function get_matching_users_setup() {
        global $CFG;

        $this->rule1 = $this->get_generator()->create_rule();
        $this->course1 = $this->getDataGenerator()->create_course();

        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();

        $CFG->enrol_plugins_enabled .= ',dynamicrule';

        $plugin = enrol_get_plugin('dynamicrule');
        $plugin->add_instance(get_course($this->course1->id), ['customint1' => $this->rule1->id]);

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
        $configdata = ['courseid' => $this->course1->id];
        user_enrolled::create($this->rule1->id, $configdata);

        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($this->rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($this->rule1->id);
        $this->assertEqualsCanonicalizing([$this->user1->id, $this->user2->id, $this->user3->id], array_column($users, 'id'));
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
        $configdata = ['courseid' => $this->course1->id, 'enrol' => 'manual'];
        user_enrolled::create($this->rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($this->rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($this->rule1->id);
        $this->assertEqualsCanonicalizing([$this->user2->id, $this->user3->id], array_column($users, 'id'));
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
        $configdata = ['courseid' => $this->course1->id, 'enrol' => 'dynamicrule'];
        user_enrolled::create($this->rule1->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($this->rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($this->rule1->id);
        $this->assertEqualsCanonicalizing([$this->user1->id], array_column($users, 'id'));
    }

    /**
     * Test condition matching in a specified course with specified course start time.
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users_course_timeenrolled() {

        $this->get_matching_users_setup();

        $timestart = strtotime('+1 week');

        $this->user4 = $this->getDataGenerator()->create_and_enrol($this->course1, 'student', null, 'manual', $timestart);

        // In a course, enrolment starting next week.
        $configdata = ['courseid' => $this->course1->id, 'timeenrolled' => $timestart];
        user_enrolled::create($this->rule1->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($this->rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($this->rule1->id);
        $this->assertEqualsCanonicalizing([$this->user4->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata1 = ['courseid' => $course1->id];
        $condition1 = user_enrolled::create($rule1->id, $configdata1);

        $a = (object)['course' => $course1->fullname, 'enrol' => 'Any'];
        $this->assertEquals(get_string('conditionuserenrolleddescription', 'tool_dynamicrule', $a),
                            $condition1->get_description());

        $now = time();
        $rule3 = $this->get_generator()->create_rule();
        $configdata3 = ['courseid' => $course1->id, 'enrol' => 'manual', 'timeenrolled' => $now];
        $condition3 = user_enrolled::create($rule3->id, $configdata3);

        $a = (object)[
            'course' => $course1->fullname,
            'enrol' => get_string('pluginname', 'enrol_manual'),
            'conditiondate' => userdate($now, get_string('strftimedatefullshort'))
        ];
        $this->assertEquals(get_string('conditionuserenrolleddescriptionwithdate', 'tool_dynamicrule', $a),
                            $condition3->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        $course1 = $this->getDataGenerator()->create_course();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id, 'enrol' => 'manual'];
        $condition1 = user_enrolled::create($rule1->id, $configdata);

        // Valid course.
        $this->assertTrue($condition1->is_configuration_valid());

        // Valid course, enrolment method disabled.
        $maninstance1 = $DB->get_record('enrol', ['courseid' => $course1->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $maninstance1->id]);
        $this->assertFalse($condition1->is_configuration_valid());

        // Deleted course.
        $DB->delete_records('course', ['id' => $course1->id]);
        $this->assertFalse($condition1->is_configuration_valid());
    }

    /**
     * Test get_broken_description
     */
    public function test_get_broken_description() {
        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = user_enrolled::create($rule1->id, $configdata);

        $this->assertNotEmpty($condition1->get_broken_description());
    }

    /**
     * Test is_available
     */
    public function test_is_available() {
        $course0 = $this->getDataGenerator()->create_course();
        $condition = user_enrolled::instance();

        // Admin user.
        $this->setAdminUser();
        $this->assertTrue($condition::is_available());

        // Non-priveleged user.
        $user = $this->getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($condition::is_available());

        // Grant priveleges to user.
        $this->getDataGenerator()->enrol_user($user->id, $course0->id, 'editingteacher');
        cache_helper::purge_by_event('changesincourse');

        $this->assertTrue($condition::is_available());
    }

    /**
     * Test get_get_not_available_label
     */
    public function test_get_not_available_label() {
        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = user_enrolled::create($rule1->id, $configdata);

        $this->assertNotEmpty($condition1->get_not_available_label());
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
        user_enrolled::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Course0 enrolled',
            'body' => ['text' => 'Congratulations, you are course0 enrolled.', 'format' => FORMAT_MOODLE]];
        notification::create($rule0->id, $configdata);

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
        user_enrolled::create($rule1->id, $configdata);

        $configdata = ['subject' => 'Course1 enrolled',
            'body' => ['text' => 'Congratulations, you are course1 enrolled.', 'format' => FORMAT_MOODLE]];
        notification::create($rule1->id, $configdata);

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
        user_enrolled::create($rule2->id, $configdata);

        $configdata = ['subject' => 'Course2 enrolled',
            'body' => ['text' => 'Congratulations, you are course2 enrolled.', 'format' => FORMAT_MOODLE]];
        notification::create($rule2->id, $configdata);

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

    /**
     * Test field mapping during export/import
     */
    public function test_field_mapping(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['shortname' => 'My course']);

        // Create rule containing condition, pointing to the course we just created.
        $rule = $this->get_generator()->create_rule();
        $this->get_generator()->create_condition(user_enrolled::class, $rule->id, ['courseid' => $course->id]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original course, and create a new one with the same name.
        $originalid = $course->id;
        $originalname = $course->shortname;
        delete_course($course, false);

        $newcourse = $this->getDataGenerator()->create_course(['shortname' => $originalname]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the course mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('course', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported condition field should be mapped to the new course.
        $rules = rule::get_records([], 'id');

        $condition = user_enrolled::instance(0, condition::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($newcourse->id, $condition->get_configdata()['courseid']);
        $this->assertTrue($condition->is_configuration_valid());
    }
}

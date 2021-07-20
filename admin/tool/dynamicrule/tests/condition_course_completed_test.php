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
 * File contains the unit tests for condition course_completed class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\condition;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;
use tool_dynamicrule\tool_dynamicrule\condition\course_completed;
use tool_dynamicrule\tool_dynamicrule\outcome\notification;

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
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class condition_course_completed_testcase extends \advanced_testcase {

    /**
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->libdir}/completionlib.php");
    }

    /**
     * Set up
     */
    public function setUp(): void {
        set_config('enablecompletion', COMPLETION_ENABLED);
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
        $condition = course_completed::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = course_completed::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $configform = ['courseid' => 0, 'operator' => course_completed::OPERATOR_AFTER];
        $condition = course_completed::instance();

        // Invalid course.
        $this->assertArrayHasKey('courseid', $condition->validate_config_form($configform));

        // Valid course.
        $course0 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $configform = ['courseid' => $course0->id, 'operator' => course_completed::OPERATOR_AFTER];
        $this->assertArrayNotHasKey('courseid', $condition->validate_config_form($configform));

        // Valid course with invalid operator.
        $configform = ['courseid' => $course0->id, 'operator' => 'invalid'];
        $this->assertArrayHasKey('operator', $condition->validate_config_form($configform));
        $this->assertArrayNotHasKey('courseid', $condition->validate_config_form($configform));

        // Valid course with completion disabled.
        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_DISABLED]);
        $configform = ['courseid' => $course1->id, 'operator' => course_completed::OPERATOR_AFTER];
        $this->assertArrayNotHasKey('operator', $condition->validate_config_form($configform));
        $this->assertArrayHasKey('courseid', $condition->validate_config_form($configform));
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        // Anyone can add this condition.
        $condition = course_completed::instance();
        $this->assertTrue($condition->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $course0 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $configform = ['courseid' => $course0->id];
        $condition = course_completed::instance();

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
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users() {
        global $DB;

        // Course with self-completion enabled.
        $completedcourse = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $criteriadata = new stdClass();
        $criteriadata->id = $completedcourse->id;
        $criteriadata->criteria_self = COMPLETION_CRITERIA_TYPE_SELF;

        $criterion = completion_criteria::factory(['criteriatype' => COMPLETION_CRITERIA_TYPE_SELF]);
        $criterion->update_config($criteriadata);

        $user1 = $this->getDataGenerator()->create_and_enrol($completedcourse, 'student');
        $user2 = $this->getDataGenerator()->create_and_enrol($completedcourse, 'student');
        $user3 = $this->getDataGenerator()->create_and_enrol($completedcourse, 'student');

        // Create rule and condition.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $completedcourse->id];
        $condition = course_completed::create($rule1->id, $configdata);

        // Sanity check.
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));

        $timenow = time();

        // Complete course for users 1 and 2.
        $completion = new completion_completion(array('course' => $completedcourse->id, 'userid' => $user1->id));
        $completion->mark_complete($timenow - DAYSECS);
        $completion = new completion_completion(array('course' => $completedcourse->id, 'userid' => $user2->id));
        $completion->mark_complete($timenow + DAYSECS);

        // Validate matching.
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_column($users, 'id'));

        // Now match users before now.
        $condition->update_configdata($configdata + [
            'operator' => course_completed::OPERATOR_BEFORE,
            'timecompleted' => $timenow,
        ], true);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals($user1->id, reset($users)->id);

        // Now match users after now.
        $condition->update_configdata($configdata + [
            'operator' => course_completed::OPERATOR_AFTER,
            'timecompleted' => $timenow,
        ], true);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals($user2->id, reset($users)->id);
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

        // Create course customfields.
        $catid = $this->getDataGenerator()->create_custom_field_category([])->get('id');
        $this->getDataGenerator()->create_custom_field(['categoryid' => $catid, 'type' => 'text',
            'shortname' => 'credits']);
        $this->getDataGenerator()->create_custom_field(['categoryid' => $catid, 'type' => 'date',
            'shortname' => 'finaltestdate']);
        $this->getDataGenerator()->create_custom_field(['categoryid' => $catid, 'type' => 'select',
            'shortname' => 'coursetype', 'configdata' => ['options' => "Degree\nMaster"]]);

        // Course with self-completion enabled.
        $completedcourse = $this->getDataGenerator()->create_course([
            'shortname' => 'MATH101',
            'fullname' => 'Mathematics 101',
            'enablecompletion' => COMPLETION_ENABLED,
            'customfield_credits' => '60',
            'customfield_finaltestdate' => strtotime('1 January 2020 00:00'),
            'customfield_coursetype' => 2
            ]);
        $criteriadata = new \stdClass();
        $criteriadata->id = $completedcourse->id;
        $criteriadata->criteria_self = COMPLETION_CRITERIA_TYPE_SELF;

        /** @var \completion_criteria_self $criterion */
        $criterion = \completion_criteria::factory(['criteriatype' => COMPLETION_CRITERIA_TYPE_SELF]);
        $criterion->update_config($criteriadata);

        // User enrolled into course.
        $user0 = $this->getDataGenerator()->create_user(['firstname' => 'John', 'lastname' => 'Smith']);
        $this->getDataGenerator()->enrol_user($user0->id, $completedcourse->id, 'student', 'manual');

        // Create rule0 with course completed conditon and notification outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $completedcourse->id];
        course_completed::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Completed {{courseshortname}}',
            'body' => ['text' => 'Congratulations {{userfullname}}, ' .
                'you have completed (<a href="{{courseurl}}">{{coursefullname}}</a>). ' .
                'Course credits: {{coursecustomfield_credits}}, ' .
                'Course type: {{coursecustomfield_coursetype}}, ' .
                'Final test date: {{coursecustomfield_finaltestdate}}, ' .
                'Completion date: {{coursecompletiondate}}, ' .
                'Grade: {{coursegrade}}',
                'format' => FORMAT_HTML]];
        notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Set user grade to 10.00.
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $completedcourse->id]);
        $gradeitem2 = grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $assign->id,
            'courseid' => $completedcourse->id]);
        $gradeitem2->update_final_grade($user0->id, 10, 'gradebook');

        // Complete course, this supposed to trigger rule0.
        $this->setUser($user0);
        \core_completion_external::mark_course_self_completed($completedcourse->id);
        $ccompletion = new \completion_completion(array('course' => $completedcourse->id, 'userid' => $user0->id));
        $ccompletion->mark_complete();

        // Check outcomes.
        $messages = $sink->get_messages();
        // Keep only DR messages.
        $messages = array_filter($messages, function($message) {
            return ($message->eventtype === 'notificationoutcome');
        });
        $this->assertCount(1, $messages);
        $this->assertEquals('Completed MATH101', $messages[0]->subject);
        $url = $CFG->wwwroot.'/course/view.php?id=' . $completedcourse->id;
        $this->assertEquals('Congratulations John Smith, you have completed (<a href="' .
            $url . '">Mathematics 101</a>). ' .
            'Course credits: 60, ' .
            'Course type: Master, ' .
            'Final test date: Wednesday, 1 January 2020, 12:00 AM, ' .
            'Completion date: ' . userdate($ccompletion->timecompleted, get_string('strftimedatefullshort')) . ', ' .
            'Grade: 10.00',
            $messages[0]->fullmessagehtml);
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }

    /**
     * Test course_completed event with empty course fullname, empty course customfelds and empty user course grade.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing_empty_data() {
        global $CFG, $DB;

        // Create course customfields.
        $catid = $this->getDataGenerator()->create_custom_field_category([])->get('id');
        $this->getDataGenerator()->create_custom_field(['categoryid' => $catid, 'type' => 'text',
            'shortname' => 'credits']);
        $this->getDataGenerator()->create_custom_field(['categoryid' => $catid, 'type' => 'date',
            'shortname' => 'finaltestdate']);
        $this->getDataGenerator()->create_custom_field(['categoryid' => $catid, 'type' => 'select',
            'shortname' => 'coursetype', 'configdata' => ['options' => "Degree\nMaster"]]);

        // Course with self-completion enabled and empty name and empty course customfields.
        $completedcourse = $this->getDataGenerator()->create_course([
            'shortname' => 'MATH101',
            'fullname' => '',
            'enablecompletion' => COMPLETION_ENABLED,
        ]);
        $criteriadata = new \stdClass();
        $criteriadata->id = $completedcourse->id;
        $criteriadata->criteria_self = COMPLETION_CRITERIA_TYPE_SELF;

        /** @var \completion_criteria_self $criterion */
        $criterion = \completion_criteria::factory(['criteriatype' => COMPLETION_CRITERIA_TYPE_SELF]);
        $criterion->update_config($criteriadata);

        // User enrolled into course.
        $user0 = $this->getDataGenerator()->create_user(['firstname' => 'John', 'lastname' => 'Smith']);
        $this->getDataGenerator()->enrol_user($user0->id, $completedcourse->id, 'student', 'manual');

        // Create rule0 with course completed conditon and notification outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $completedcourse->id];
        course_completed::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Completed {{courseshortname}}',
            'body' => ['text' => 'Congratulations {{userfullname}}, ' .
                'you have completed (<a href="{{courseurl}}">{{coursefullname}}</a>). ' .
                'Course credits: {{coursecustomfield_credits}}, ' .
                'Course type: {{coursecustomfield_coursetype}}, ' .
                'Final test date: {{coursecustomfield_finaltestdate}}, ' .
                'Completion date: {{coursecompletiondate}}, ' .
                'Grade: {{coursegrade}}',
                'format' => FORMAT_HTML]];
        notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Complete course, this supposed to trigger rule0.
        $this->setUser($user0);
        \core_completion_external::mark_course_self_completed($completedcourse->id);
        $ccompletion = new \completion_completion(array('course' => $completedcourse->id, 'userid' => $user0->id));
        $ccompletion->mark_complete();

        // Check outcomes.
        $messages = $sink->get_messages();
        $url = $CFG->wwwroot.'/course/view.php?id=' . $completedcourse->id;

        // There should not be any exceptions with empty course fullname, empty course customfelds and empty user course grade.
        $this->assertEquals('Congratulations John Smith, you have completed (<a href="' .
            $url . '"></a>). ' .
            'Course credits: , ' .
            'Course type: , ' .
            'Final test date: , ' .
            'Completion date: ' . userdate($ccompletion->timecompleted, get_string('strftimedatefullshort')) . ', ' .
            'Grade: ',
            $messages[0]->fullmessagehtml);
        $sink->clear();
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = course_completed::create($rule1->id, $configdata);

        $this->assertNotEmpty($condition1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_DISABLED]);

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course->id];
        $condition = course_completed::create($rule1->id, $configdata);

        // Completion disabled.
        $this->assertFalse($condition->is_configuration_valid());

        // Enable completion in the course.
        $course->enablecompletion = COMPLETION_ENABLED;
        update_course($course);
        $this->assertTrue($condition->is_configuration_valid());

        // Delete course.
        $DB->delete_records('course', ['id' => $course->id]);
        $this->assertFalse($condition->is_configuration_valid());
    }

    /**
     * Test get_broken_description
     */
    public function test_get_broken_description() {
        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = course_completed::create($rule1->id, $configdata);

        $this->assertNotEmpty($condition1->get_broken_description());
    }

    /**
     * Test is_available
     */
    public function test_is_available() {
        $condition = course_completed::instance();
        $this->setAdminUser();
        $this->assertFalse($condition::is_available());

        // Add course with completion enabled.
        $course0 = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $this->assertTrue($condition::is_available());

        // Non-priveleged user.
        $user = $this->getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($condition::is_available());

        // Grant priveleges to user.
        $this->getDataGenerator()->enrol_user($user->id, $course0->id, 'editingteacher');
        \cache_helper::purge_by_event('changesincourse');

        $this->assertTrue($condition::is_available());

        // Disable completion.
        $course0->enablecompletion = COMPLETION_DISABLED;
        update_course($course0);
        $this->assertFalse($condition::is_available());
    }

    /**
     * Test get_get_not_available_label
     */
    public function test_get_not_available_label() {
        $course1 = $this->getDataGenerator()->create_course();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id];
        $condition1 = course_completed::create($rule1->id, $configdata);

        $this->assertNotEmpty($condition1->get_not_available_label());
    }

    /**
     * Test field mapping during export/import
     */
    public function test_field_mapping(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['shortname' => 'My course', 'enablecompletion' => true]);

        // Create rule containing condition, pointing to the course we just created.
        $rule = $this->get_generator()->create_rule();
        $this->get_generator()->create_condition(course_completed::class, $rule->id, ['courseid' => $course->id]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original course, and create a new one with the same name.
        $originalid = $course->id;
        $originalname = $course->shortname;
        delete_course($course, false);

        $newcourse = $this->getDataGenerator()->create_course(['shortname' => $originalname, 'enablecompletion' => true]);

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

        $condition = course_completed::instance(0, condition::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($newcourse->id, $condition->get_configdata()['courseid']);
        $this->assertTrue($condition->is_configuration_valid());
    }
}

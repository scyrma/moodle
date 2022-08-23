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
 * File contains the unit tests for observer class.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

use advanced_testcase;
use context_course;
use stdClass;
use tool_dynamicrule_generator;
use tool_tenant_generator;
use tool_dynamicrule\tool_dynamicrule\condition\user_profile_field;
use tool_dynamicrule\tool_dynamicrule\condition\user_enrolled;
use tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled;
use enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol;

/**
 * File contains the unit tests for observer class.
 *
 * @package     tool_dynamicrule
 * @group       tool_dynamicrule
 * @covers      \tool_dynamicrule\event\observer
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class observer_test extends advanced_testcase {
    /** @var tool_dynamicrule_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * Set up.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test user_enrolled event is triggering rule processing.
     *
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
        $rule0 = $this->generator->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course0->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Course0 enrolled',
            'body' => ['text' => 'Congratulations, you are course0 enrolled.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Create rule1 with course1 enrol conditon and notification outcome.
        $rule1 = $this->generator->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course1->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule1->id, $configdata);
        $configdata = ['subject' => 'Course1 enrolled',
            'body' => ['text' => 'Congratulations, you are course1 enrolled.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        // Create rule2 (disabled) with course2 enrol conditon and notification outcome.
        $rule2 = $this->generator->create_rule(['enabled' => 0]);
        $configdata = ['courseid' => $course2->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($rule2->id, $configdata);
        $configdata = ['subject' => 'Course1 enrolled',
            'body' => ['text' => 'Congratulations, you are course2 enrolled.', 'format' => FORMAT_MOODLE]];
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

    /**
     * Test tenant_deleted event is triggering rules deletion.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled
     * @uses \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     * @uses \tool_dynamicrule\api::process_rule
     * @uses \tool_tenant\manager::archive_tenant
     * @uses \tool_tenant\manager::delete_tenant
     */
    public function test_delete_tenant() {
        global $DB;

        // Tenant and users.
        $tenant = $this->tenantgenerator->create_tenant();
        $tenantuser0 = self::getDataGenerator()->create_user();
        $tenantuser1 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($tenantuser0->id, $tenant->id);
        $this->tenantgenerator->allocate_user($tenantuser1->id, $tenant->id);

        // Courses.
        $course0 = $this->getDataGenerator()->create_course();
        $tenantcourse = $this->getDataGenerator()->create_course();

        // Create default tenant rule with Course0 not enrolled conditon and Course0 enrol outcome.
        $rule = $this->generator->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course0->id, 'enrol' => 'manual'];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled::create($rule->id, $configdata);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course0->id, 'role' => $roleid];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule->id, $configdata);

        // Create tenant rule with TenantCourse not enrolled conditon and TenantCourse enrol outcome.
        $tenantrule = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['courseid' => $tenantcourse->id, 'enrol' => 'manual'];
        $tenantcondition = \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled::create($tenantrule->id, $configdata);
        $configdata = ['coursetoenrol' => $tenantcourse->id, 'role' => $roleid];
        $tenantoutcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($tenantrule->id, $configdata);

        // Trigger rules.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule->id]));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Delete tenant.
        $manager = new \tool_tenant\manager();
        $manager->archive_tenant($tenant->id);
        $manager->delete_tenant($tenant->id);

        // Check tenantrule records no longer there.
        $this->assertFalse(\tool_dynamicrule\rule::record_exists($tenantrule->id));
        $this->assertFalse(\tool_dynamicrule\condition::record_exists($tenantcondition->get_id()));
        $this->assertFalse(\tool_dynamicrule\outcome::record_exists($tenantoutcome->get_id()));
        $this->assertFalse($DB->record_exists('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check default tenant rule records are not affected.
        $this->assertTrue(\tool_dynamicrule\rule::record_exists($rule->id));
        $this->assertTrue(\tool_dynamicrule\condition::record_exists($condition->get_id()));
        $this->assertTrue(\tool_dynamicrule\outcome::record_exists($outcome->get_id()));
        $this->assertTrue($DB->record_exists('tool_dynamicrule_match', ['ruleid' => $rule->id]));
    }

    /**
     * Create a rule checking a profile condition (city) and enrolling into a course
     *
     * @param array $ruleconfig
     * @param string $city city for the condition
     * @param int $courseid course for the enrolment outcome
     * @return stdClass
     */
    protected function create_city_enrol_rule(array $ruleconfig, string $city, int $courseid): \stdClass {
        global $DB;
        $rule = $this->generator->create_rule($ruleconfig + ['enabled' => 1]);
        $conditionconfig =
            ['userprofilefield' => 'city', 'city_value' => $city, 'city_op' => user_profile_field::TEXT_IS_EQUAL_TO];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule->id, $conditionconfig);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $outcomeconfig = ['coursetoenrol' => $courseid, 'role' => $roleid];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule->id, $outcomeconfig);
        return $rule;
    }

    /**
     * Test user_created event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field
     * @uses \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     */
    public function test_create_user() {
        // Tenant.
        $tenant = $this->tenantgenerator->create_tenant();
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();

        // Courses.
        $course0 = $this->getDataGenerator()->create_course();
        $tenantcourse = $this->getDataGenerator()->create_course();
        $sharedcategory = $this->getDataGenerator()->create_category(['name' => 'Shared']);
        $sharedcourse = $this->getDataGenerator()->create_course(['categoryid' => $sharedcategory->id]);

        // Create default tenant rule with profile conditon and Course0 enrol outcome.
        $rule = $this->create_city_enrol_rule([], 'Lancaster', $course0->id);

        // Create tenant rule with with profile conditon and TenantCourse enrol outcome.
        $tenantrule = $this->create_city_enrol_rule(['tenantid' => $tenant->id], 'Barcelona', $tenantcourse->id);

        // Create shared rule with with profile conditon and SharedCourse enrol outcome.
        $sharedrule = $this->create_city_enrol_rule(['tenantid' => $sharedspaceid], 'Perth', $sharedcourse->id);

        // Create user. This is supposed to trigger rule.
        $user0 = $this->tenantgenerator->create_user(['city' => 'Lancaster']);

        // Check matches record presence.
        $this->generator->assert_user_matched_rule($this, $user0->id, $rule->id);
        $this->generator->assert_user_did_not_match_rule($this, $user0->id, $tenantrule->id);
        $this->generator->assert_user_did_not_match_rule($this, $user0->id, $sharedrule->id);

        // User0 is supposed to be enrolled into Course0.
        $this->assertTrue(is_enrolled(context_course::instance($course0->id), $user0));
        $this->assertFalse(is_enrolled(context_course::instance($tenantcourse->id), $user0->id));
        $this->assertFalse(is_enrolled(context_course::instance($sharedcourse->id), $user0->id));

        // Create tenant user with city 'Barcelona'. This is supposed to trigger tenant rule.
        $tenantuserrecord = ['tenantid' => $tenant->id, 'city' => 'Barcelona'];
        $tenantuser = $this->tenantgenerator->create_user($tenantuserrecord);

        // Check matches record presence.
        $this->generator->assert_user_did_not_match_rule($this, $tenantuser->id, $rule->id);
        $this->generator->assert_user_matched_rule($this, $tenantuser->id, $tenantrule->id);
        $this->generator->assert_user_did_not_match_rule($this, $tenantuser->id, $sharedrule->id);

        // Check enrolments.
        $this->assertFalse(is_enrolled(context_course::instance($course0->id), $tenantuser->id));
        $this->assertTrue(is_enrolled(context_course::instance($tenantcourse->id), $tenantuser->id));
        $this->assertFalse(is_enrolled(context_course::instance($sharedcourse->id), $tenantuser->id));

        // Create tenant user with city 'Perth'. This is supposed to trigger shared rule.
        $tenantuserrecord2 = ['tenantid' => $tenant->id, 'city' => 'Perth'];
        $tenantuser2 = $this->tenantgenerator->create_user($tenantuserrecord2);

        // Check matches record presence.
        $this->generator->assert_user_did_not_match_rule($this, $tenantuser2->id, $rule->id);
        $this->generator->assert_user_did_not_match_rule($this, $tenantuser2->id, $tenantrule->id);
        $this->generator->assert_user_matched_rule($this, $tenantuser2->id, $sharedrule->id);

        // Check enrolments.
        $this->assertFalse(is_enrolled(context_course::instance($course0->id), $tenantuser2->id));
        $this->assertFalse(is_enrolled(context_course::instance($tenantcourse->id), $tenantuser2->id));
        $this->assertTrue(is_enrolled(context_course::instance($sharedcourse->id), $tenantuser2->id));
    }

    /**
     * Test multiple matches using events
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_multiple_events_single_condition() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();
        $fieldshortname = 'city';

        // Create a rule with a profile field condition.
        $rule0 = $this->generator->create_rule(['enabled' => 1]);
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Tarragona',
            $fieldshortname . '_op' => user_profile_field::TEXT_CONTAINS];
        user_profile_field::create($rule0->id, $configdata);
        $this->generator->create_outcome_donothing($rule0->id);

        // Update the user and set the city so that the condition is met.
        user_update_user(['id' => $user0->id, 'city' => 'Tarragona']);
        $this->generator->assert_user_matched_rule($this, $user0->id, $rule0->id);

        // Update the user again - nothing should change.
        user_update_user(['id' => $user0->id, 'lastname' => 'Test1']);
        $this->generator->assert_user_matched_rule($this, $user0->id, $rule0->id);

        // Update the user again, set city to different value - user should unmatch.
        user_update_user(['id' => $user0->id, 'city' => 'Badalona']);
        $this->generator->assert_user_did_not_match_rule($this, $user0->id, $rule0->id, 1);

        // Update the user and set the city so that the condition is met.
        user_update_user(['id' => $user0->id, 'city' => 'Tarragona']);
        $this->generator->assert_user_matched_rule($this, $user0->id, $rule0->id, 2);
    }

    /**
     * Test multiple matches using events with many conditions
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_multiple_events_many_conditions() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();

        // Create a rule with a 2 profile field conditions.
        $rule0 = $this->generator->create_rule(['enabled' => 1]);

        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Tarragona',
            $fieldshortname . '_op' => user_profile_field::TEXT_CONTAINS];
        user_profile_field::create($rule0->id, $configdata);

        $fieldshortname = 'firstname';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Ivan',
            $fieldshortname . '_op' => user_profile_field::TEXT_CONTAINS];
        user_profile_field::create($rule0->id, $configdata);

        $this->generator->create_outcome_donothing($rule0->id);

        // Update the user and set the city so that the condition is met.
        user_update_user(['id' => $user0->id, 'city' => 'Tarragona', 'firstname' => 'Ivan']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Update the user again, set different field - nothing should change.
        user_update_user(['id' => $user0->id, 'lastname' => 'Test1']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Update the user again, set firstname to different value - user should unmatch.
        user_update_user(['id' => $user0->id, 'firstname' => 'Pavel']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);

        // Now match it again.
        user_update_user(['id' => $user0->id, 'firstname' => 'Ivan']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(2, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);
        $this->assertEmpty($matches[1]->unmatchedtime);
    }

    /**
     * Test multiple matches using events with mixed conditions (event and cron based)
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_multiple_events_mixed_conditions() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();

        // Create a rule with profile field and user not enrolled condition.
        $rule0 = $this->generator->create_rule(['enabled' => 1]);
        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Tarragona',
            $fieldshortname . '_op' => user_profile_field::TEXT_CONTAINS];
        user_profile_field::create($rule0->id, $configdata);

        $course0 = $this->getDataGenerator()->create_course();
        $configdata = ['courseid' => $course0->id, 'enrol' => 'manual'];
        user_not_enrolled::create($rule0->id, $configdata);
        $this->generator->create_outcome_donothing($rule0->id);

        // Run cron, nothing should change.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(0, count($matches));

        // Update the user and set the city so that the condition is met.
        user_update_user(['id' => $user0->id, 'city' => 'Tarragona']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Run cron - nothing should change.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Update the user again, set different field - nothing should change.
        user_update_user(['id' => $user0->id, 'lastname' => 'Test1']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Run cron - nothing should change.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Update the user again, set city  to different value - user should unmatch.
        user_update_user(['id' => $user0->id, 'city' => 'Badalona']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);

        // Run cron - nothing should change.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);

        // Now match it again.
        user_update_user(['id' => $user0->id, 'city' => 'Tarragona']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(2, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);
        $this->assertEmpty($matches[1]->unmatchedtime);

        // Run cron - nothing should change.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(2, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);
        $this->assertEmpty($matches[1]->unmatchedtime);

        // Enrol user to course.
        $this->getDataGenerator()->enrol_user($user0->id, $course0->id, 'student', 'manual');

        // Run cron - user no longer match.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(2, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);
        $this->assertNotEmpty($matches[1]->unmatchedtime);
    }

    /**
     * Test multiple matches using chained events
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_multiple_events_chained() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();

        // Rule 0 - profile field condition, course enrol outcome.
        $rule0 = $this->generator->create_rule(['enabled' => 1]);
        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Tarragona',
            $fieldshortname . '_op' => user_profile_field::TEXT_CONTAINS];
        user_profile_field::create($rule0->id, $configdata);

        $course0 = $this->getDataGenerator()->create_course();
        $enddate = time() + WEEKSECS;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course0->id, 'role' => $roleid, 'enddate' => $enddate];
        course_enrol::create($rule0->id, $configdata);

        // Rule 1 - user enrolled, donothing.
        $rule1 = $this->generator->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course0->id, 'enrol' => 'dynamicrule'];
        user_enrolled::create($rule1->id, $configdata);
        $this->generator->create_outcome_donothing($rule1->id);

        // Update the user and set the city so that the condition is met.
        user_update_user(['id' => $user0->id, 'city' => 'Tarragona']);

        // Validate.
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id, 'ruleid' => $rule0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id, 'ruleid' => $rule1->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Update the user again, set different field - nothing should change.
        user_update_user(['id' => $user0->id, 'lastname' => 'Test1']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id, 'ruleid' => $rule0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id, 'ruleid' => $rule1->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Update the user again, set city to different value - user should unmatch.
        user_update_user(['id' => $user0->id, 'city' => 'Badalona']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id, 'ruleid' => $rule0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id, 'ruleid' => $rule1->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Now match it again.
        user_update_user(['id' => $user0->id, 'city' => 'Tarragona']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id, 'ruleid' => $rule0->id], 'id'));
        $this->assertEquals(2, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);
        $this->assertEmpty($matches[1]->unmatchedtime);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id, 'ruleid' => $rule1->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);
    }

    /**
     * Test event triggers shared rule as well
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_event_triggers_shared_rule() {
        $tenant1 = $this->tenantgenerator->create_tenant();
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $user0 = $this->tenantgenerator->create_user(['tenantid' => $tenant1->id]);

        // Create a rule with a profile field condition.
        $rule0 = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $sharedspaceid]);
        $configdata =
            ['userprofilefield' => 'city', 'city_value' => 'Tarragona', 'city_op' => user_profile_field::TEXT_CONTAINS];
        user_profile_field::create($rule0->id, $configdata);
        $this->generator->create_outcome_donothing($rule0->id);

        // Update the user and set the city so that the condition is met.
        user_update_user(['id' => $user0->id, 'city' => 'Tarragona']);
        $this->generator->assert_user_matched_rule($this, $user0->id, $rule0->id);

        // Update the user again - nothing should change.
        user_update_user(['id' => $user0->id, 'lastname' => 'Test1']);
        $this->generator->assert_user_matched_rule($this, $user0->id, $rule0->id);

        // Update the user again, set city to different value - user should unmatch.
        user_update_user(['id' => $user0->id, 'city' => 'Badalona']);
        $this->generator->assert_user_did_not_match_rule($this, $user0->id, $rule0->id, 1);

        // Update the user and set the city so that the condition is met.
        user_update_user(['id' => $user0->id, 'city' => 'Tarragona']);
        $this->generator->assert_user_matched_rule($this, $user0->id, $rule0->id, 2);
    }

    /**
     * Test event triggers when user is suspended/non-suspended and condition always match
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_match_suspended_rule_always_match() {
        $configdata = [
            'userprofilefield' => 'city',
            'city_value' => 'XYZ',
            'city_op' => user_profile_field::TEXT_IS_EQUAL_TO
        ];

        // Default rule configuration.
        $ruleconfig = ['enabled' => 1, 'includesuspendedusers' => 0];

        // Test1.
        // Create a rule with a profile city field condition = 'XYZ' and "Include suspended users" unchecked.
        $rule0 = $this->generator->create_rule($ruleconfig);
        user_profile_field::create($rule0->id, $configdata);
        $this->generator->create_outcome_donothing($rule0->id);

        // Create user with 'XYZ' city.
        $user0 = $this->tenantgenerator->create_user(['city' => 'XYZ']);

        // User is considered matched.
        $this->generator->assert_user_matched_rule($this, $user0->id, $rule0->id);

        // Update the user, set as suspended - still considered matched.
        user_update_user(['id' => $user0->id, 'suspended' => 1]);
        $this->generator->assert_user_matched_rule($this, $user0->id, $rule0->id);

        // Update the user again, set as unsuspended - still considered matched.
        user_update_user(['id' => $user0->id, 'suspended' => 0]);
        $this->generator->assert_user_matched_rule($this, $user0->id, $rule0->id);
    }

    /**
     * Test event triggers when user is suspended/non-suspended and user data change
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_match_suspended_rule_userdata_change() {
        $configdata = [
            'userprofilefield' => 'city',
            'city_value' => 'XYZ',
            'city_op' => user_profile_field::TEXT_IS_EQUAL_TO
        ];

        // Default rule configuration.
        $ruleconfig = ['enabled' => 1, 'includesuspendedusers' => 0];

        // Test2.
        // Create a rule with a profile city field condition = 'XYZ' and "Include suspended users" unchecked.
        $rule1 = $this->generator->create_rule($ruleconfig);
        user_profile_field::create($rule1->id, $configdata);
        $this->generator->create_outcome_donothing($rule1->id);

        // Create user with 'ABC' city.
        $user1 = $this->tenantgenerator->create_user(['city' => 'ABC']);

        // User is considered not matched.
        $this->generator->assert_user_did_not_match_rule($this, $user1->id, $rule1->id);

        // Update the user, set as suspended - still considered not matched.
        user_update_user(['id' => $user1->id, 'suspended' => 1]);
        $this->generator->assert_user_did_not_match_rule($this, $user1->id, $rule1->id);

        // Update the user again, set city = 'XYZ' - still considered not matched.
        user_update_user(['id' => $user1->id, 'city' => 'XYZ']);
        $this->generator->assert_user_did_not_match_rule($this, $user1->id, $rule1->id);

        // Update the user again, set as non-suspended - user considered as matched.
        user_update_user(['id' => $user1->id, 'suspended' => 0]);
        $this->generator->assert_user_matched_rule($this, $user1->id, $rule1->id);
    }

    /**
     * Test event triggers when user is suspended/non-suspended and users initially suspended
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_match_suspended_rule_initially_usersuspended() {
        $configdata = [
            'userprofilefield' => 'city',
            'city_value' => 'XYZ',
            'city_op' => user_profile_field::TEXT_IS_EQUAL_TO
        ];

        // Default rule configuration.
        $ruleconfig = ['enabled' => 1, 'includesuspendedusers' => 0];

        // Test3.
        // Create user with 'XYZ' city and suspend it.
        $user2 = $this->tenantgenerator->create_user(['city' => 'XYZ']);
        user_update_user(['id' => $user2->id, 'suspended' => 1]);

        // Create a rule with a profile city field condition = 'XYZ' and "Include suspended users" unchecked.
        $rule = $this->generator->create_rule($ruleconfig);
        user_profile_field::create($rule->id, $configdata);
        $this->generator->create_outcome_donothing($rule->id);

        // User is considered not matched.
        $this->generator->assert_user_did_not_match_rule($this, $user2->id, $rule->id);

        // Update the user again, set as non-suspended - user considered as matched.
        user_update_user(['id' => $user2->id, 'suspended' => 0]);
        $this->generator->assert_user_matched_rule($this, $user2->id, $rule->id);

        // Test4.
        // Create user with 'ABC' city and suspend it.
        $user3 = $this->tenantgenerator->create_user(['city' => 'ABC']);
        user_update_user(['id' => $user3->id, 'suspended' => 1]);

        // User is considered not matched.
        $this->generator->assert_user_did_not_match_rule($this, $user3->id, $rule->id);

        // Update the user, set city = 'XYZ' - still considered not matched.
        user_update_user(['id' => $user3->id, 'city' => 'XYZ']);
        $this->generator->assert_user_did_not_match_rule($this, $user3->id, $rule->id);

        // Update the user again, set as non-suspended - user is considered matched.
        user_update_user(['id' => $user3->id, 'suspended' => 0]);
        $this->generator->assert_user_matched_rule($this, $user3->id, $rule->id);

        // Test7.
        // Create user with 'XYZ' city and as suspended.
        $user6 = $this->tenantgenerator->create_user(['city' => 'XYZ', 'suspended' => 1]);

        // User is considered not matched.
        $this->generator->assert_user_did_not_match_rule($this, $user6->id, $rule->id);

        // Update the user again, set as non-suspended - user is considered matched.
        user_update_user(['id' => $user6->id, 'suspended' => 0]);
        $this->generator->assert_user_matched_rule($this, $user6->id, $rule->id);
    }

    /**
     * Test event triggers when users matching/unmatching
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_unsuspending_triggers_rule_processing_unmatches_user() {
        $configdata = [
            'userprofilefield' => 'city',
            'city_value' => 'XYZ',
            'city_op' => user_profile_field::TEXT_IS_EQUAL_TO
        ];

        // Default rule configuration.
        $ruleconfig = ['enabled' => 1, 'includesuspendedusers' => 0];

        // Create a rule with a profile city field condition = 'XYZ' and "Include suspended users" unchecked.
        $rule = $this->generator->create_rule($ruleconfig);
        user_profile_field::create($rule->id, $configdata);
        $this->generator->create_outcome_donothing($rule->id);

        // Create user with 'XYZ' city.
        $usermatched = $this->tenantgenerator->create_user(['city' => 'XYZ']);

        // Create user with 'ABC' city.
        $usernotmatched = $this->tenantgenerator->create_user(['city' => 'ABC']);

        // Test5.
        // User is considered matched.
        $this->generator->assert_user_matched_rule($this, $usermatched->id, $rule->id);

        // Update the user, set as suspended - still considered matched.
        user_update_user(['id' => $usermatched->id, 'suspended' => 1]);
        $this->generator->assert_user_matched_rule($this, $usermatched->id, $rule->id);

        // Update the user again, set as non-suspended - user is still considered matched.
        user_update_user(['id' => $usermatched->id, 'suspended' => 0]);
        $this->generator->assert_user_matched_rule($this, $usermatched->id, $rule->id);

        // Test6.
        // User is considered not matched.
        $this->generator->assert_user_did_not_match_rule($this, $usernotmatched->id, $rule->id);

        // Update the user, set as suspended - still considered not matched.
        user_update_user(['id' => $usernotmatched->id, 'suspended' => 1]);
        $this->generator->assert_user_did_not_match_rule($this, $usernotmatched->id, $rule->id);

        // Update the user again, set as non-suspended - still considered not matched.
        user_update_user(['id' => $usernotmatched->id, 'suspended' => 0]);
        $this->generator->assert_user_did_not_match_rule($this, $usernotmatched->id, $rule->id);
    }

    /**
     * Test event triggers when match suspended user setting is checked
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_event_triggers_match_suspended_checked() {
        $configdata = [
            'userprofilefield' => 'city',
            'city_value' => 'XYZ',
            'city_op' => user_profile_field::TEXT_IS_EQUAL_TO
        ];

        // Default rule configuration.
        $ruleconfig = ['enabled' => 1, 'includesuspendedusers' => 1];

        // Test8.
        // Create a rule with a profile city field condition = 'XYZ' and "Include suspended users" checked.
        $rule = $this->generator->create_rule($ruleconfig);
        user_profile_field::create($rule->id, $configdata);
        $this->generator->create_outcome_donothing($rule->id);

        // Create user with 'XYZ' city.
        $user = $this->tenantgenerator->create_user(['city' => 'XYZ', 'suspended' => 1]);
        $user1 = $this->tenantgenerator->create_user(['city' => 'XYZ', 'suspended' => 1]);

        // User is considered matched.
        $this->generator->assert_user_matched_rule($this, $user->id, $rule->id);

        // Update the user, set as unsuspended - still considered matched.
        user_update_user(['id' => $user->id, 'suspended' => 0]);
        $this->generator->assert_user_matched_rule($this, $user->id, $rule->id);

        // Test9.
        // User is considered matched.
        $this->generator->assert_user_matched_rule($this, $user1->id, $rule->id);

        // Update the user, set city = 'ABC' - user is considered not matched.
        user_update_user(['id' => $user1->id, 'city' => 'ABC']);
        $this->generator->assert_user_did_not_match_rule($this, $user1->id, $rule->id, 1);
    }
}

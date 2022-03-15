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
 * File contains the unit tests for observer class.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

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
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_observer_testcase extends advanced_testcase {
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
     * Test user_created event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field
     * @uses \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     */
    public function test_create_user() {
        global $DB;

        // Tenant.
        $tenant = $this->tenantgenerator->create_tenant();

        // Courses.
        $course0 = $this->getDataGenerator()->create_course();
        $tenantcourse = $this->getDataGenerator()->create_course();

        // Create default tenant rule with profile conditon and Course0 enrol outcome.
        $rule = $this->generator->create_rule(['enabled' => 1]);
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Lancaster', 'city_op' => 2];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule->id, $configdata);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course0->id, 'role' => $roleid];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule->id, $configdata);

        // Create tenant rule with with profile conditon and TenantCourse enrol outcome.
        $tenantrule = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Barcelona', 'city_op' => 2];
        $tenantcondition = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($tenantrule->id, $configdata);
        $configdata = ['coursetoenrol' => $tenantcourse->id, 'role' => $roleid];
        $tenantoutcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($tenantrule->id, $configdata);

        // Create user. This is supposed to trigger rule.
        $user0 = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_user(['city' => 'Lancaster']);

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule->id]));
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check enrolments.
        $sql = "SELECT ue.userid
          FROM {user_enrolments} ue
          JOIN {enrol} e
            ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0
           AND e.enrol = 'dynamicrule' AND e.customint1 = :ruleid)
         WHERE ue.status = 0";

        // User0 is supposed to be enrolled into Course0.
        $params = ['courseid' => $course0->id, 'ruleid' => $rule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $enrolments);
        $this->assertEquals([$user0->id], array_keys($enrolments));

        // Create tenant user. This is supposed to trigger rule.
        $tenantuserrecord = ['tenantid' => $tenant->id, 'city' => 'Barcelona'];
        $tenantuser = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_user($tenantuserrecord);

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule->id]));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check enrolments.
        $sql = "SELECT ue.userid
          FROM {user_enrolments} ue
          JOIN {enrol} e
            ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0
           AND e.enrol = 'dynamicrule' AND e.customint1 = :ruleid)
         WHERE ue.status = 0";

        // Tenantuser is supposed to be enrolled into Tenantcourse.
        $params = ['courseid' => $tenantcourse->id, 'ruleid' => $tenantrule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $enrolments);
        $this->assertEquals([$tenantuser->id], array_keys($enrolments));
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
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Update the user again - nothing should change.
        user_update_user(['id' => $user0->id, 'lastname' => 'Test1']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertEmpty($matches[0]->unmatchedtime);

        // Update the user again, set city to different value - user should unmatch.
        user_update_user(['id' => $user0->id, 'city' => 'Badalona']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(1, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);

        // Update the user and set the city so that the condition is met.
        user_update_user(['id' => $user0->id, 'city' => 'Tarragona']);
        $matches = array_values($DB->get_records('tool_dynamicrule_match', ['userid' => $user0->id], 'id'));
        $this->assertEquals(2, count($matches));
        $this->assertNotEmpty($matches[0]->unmatchedtime);
        $this->assertEmpty($matches[1]->unmatchedtime);
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
}

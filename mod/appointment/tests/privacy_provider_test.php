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
 * Class mod_appointment_privacy_provider_testcase
 *
 * @package     mod_appointment
 * @category    test
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use core_privacy\tests\provider_testcase;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use core_privacy\local\request\approved_contextlist;
use mod_appointment\privacy\provider;

global $CFG;
require_once($CFG->dirroot . '/mod/appointment/lib.php');

/**
 * Class mod_appointment_privacy_provider_testcase
 *
 * @package     mod_appointment
 * @group       mod_appointment
 * @covers      \mod_appointment\privacy\provider
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_appointment_privacy_provider_testcase extends provider_testcase {

    /**
     * Test set up.
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Get generator.
     *
     * @return mod_appointment_generator
     */
    protected function get_generator(): mod_appointment_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Test provider metadata
     */
    public function test_get_metadata() {
        $collection = new collection('mod_appointment');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(3, $itemcollection);

        // Check appointment_signups.
        $table = array_shift($itemcollection);
        $this->assertEquals('appointment_signups', $table->get_name());
        $this->assertEquals('privacy:metadata:appointment_signups', $table->get_summary());
        $privacyfields = $table->get_privacy_fields();
        $fields = ['sessionid', 'userid', 'mailedreminder', 'discountcode', 'notificationtype'];
        foreach ($fields as $field) {
            $this->assertArrayHasKey($field, $privacyfields);
        }

        // Check appointment_signups_status.
        $table = array_shift($itemcollection);
        $this->assertEquals('appointment_signups_status', $table->get_name());
        $this->assertEquals('privacy:metadata:appointment_signups_status', $table->get_summary());
        $privacyfields = $table->get_privacy_fields();
        $fields = ['signupid', 'statuscode', 'grade', 'note', 'timecreated'];
        foreach ($fields as $field) {
            $this->assertArrayHasKey($field, $privacyfields);
        }

        // Check appointment_session_roles.
        $table = array_shift($itemcollection);
        $this->assertEquals('appointment_session_roles', $table->get_name());
        $this->assertEquals('privacy:metadata:appointment_session_roles', $table->get_summary());
        $privacyfields = $table->get_privacy_fields();
        $fields = ['userid', 'roleid'];
        foreach ($fields as $field) {
            $this->assertArrayHasKey($field, $privacyfields);
        }
    }

    /**
     * Test that getting the contexts for a user works.
     *
     * @uses \appointment_user_signup
     */
    public function test_get_contexts_for_userid() {
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();

        // User1 is in course1 and course3.
        $user1 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($user1->id, $course3->id, 'student');
        // User2 is in course2.
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id, 'student');

        // Create multiple appointments.
        $appointment1 = $this->getDataGenerator()->create_module('appointment', ['course' => $course1->id]);
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment1->id]);
        $appointment2 = $this->getDataGenerator()->create_module('appointment', ['course' => $course2->id]);
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment2->id]);
        $appointment3 = $this->getDataGenerator()->create_module('appointment', ['course' => $course3->id]);
        $session3 = $this->get_generator()->create_session(['appointment' => $appointment3->id]);

        // The user will be in these contexts.
        $usercontextids = [
            \context_module::instance($appointment1->cmid)->id,
            \context_module::instance($appointment3->cmid)->id,
        ];

        $contextlist = provider::get_contexts_for_userid($user1->id);
        $this->assertCount(0, $contextlist);

        // User1 sign up for session1 and session3.
        $this->setUser($user1);
        appointment_user_signup($session1, $appointment1, $course1, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_WAITLISTED);
        appointment_user_signup($session3, $appointment3, $course3, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_WAITLISTED);

        // User2 sign up for session2.
        $this->setUser($user2);
        appointment_user_signup($session2, $appointment2, $course2, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_WAITLISTED);

        // Check contexts for user.
        $contextlist = provider::get_contexts_for_userid($user1->id);
        $this->assertCount(2, $contextlist);
        // There should be no difference between the contexts.
        $this->assertEmpty(array_diff($usercontextids, $contextlist->get_contextids()));
    }

    /**
     * Test returning a list of user IDs related to a context.
     */
    public function test_get_users_in_context() {
        global $DB, $CFG;

        $course = $this->getDataGenerator()->create_course();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Enable session trainers for role 'editingteacher'.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'), MUST_EXIST);
        $CFG->appointment_session_roles = "$roleid";

        // Add appointment to course.
        $appointment1 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment1->id]);
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment1->id], [$teacher->id]);

        // User is signed up for appointment1.
        appointment_user_signup($session1, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);
        appointment_user_signup($session2, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);
        $context1 = \context_module::instance($appointment1->cmid);

        // Add another appointment.
        $appointment2 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session3 = $this->get_generator()->create_session(['appointment' => $appointment2->id]);
        appointment_user_signup($session3, $appointment2, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user3->id);
        $context2 = \context_module::instance($appointment2->cmid);

        // Check appointment1 context.
        $userlist = new \core_privacy\local\request\userlist($context1, 'appointment');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        $this->assertTrue(in_array($user1->id, $userids));
        $this->assertTrue(in_array($user2->id, $userids));
        $this->assertTrue(in_array($teacher->id, $userids));
        $this->assertFalse(in_array($user3->id, $userids));

        // Check appointment2 context.
        $userlist = new \core_privacy\local\request\userlist($context2, 'appointment');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        $this->assertFalse(in_array($user1->id, $userids));
        $this->assertFalse(in_array($user2->id, $userids));
        $this->assertFalse(in_array($teacher->id, $userids));
        $this->assertTrue(in_array($user3->id, $userids));
    }

    /**
     * Test that a student has got correct data exported.
     *
     * @uses \appointment_get_user_submissions
     * @uses \appointment_user_signup
     */
    public function test_export_user_data_student() {
        $course = $this->getDataGenerator()->create_course();

        $user = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Add two appointments to course.
        $appointment1 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment1->id]);

        $appointment2 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment1->id]);

        // User is signed up for appointment1.
        appointment_user_signup($session1, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user->id);
        $usersubmissions = appointment_get_user_submissions($appointment1->id, $user->id);
        $context1 = \context_module::instance($appointment1->cmid);

        // Pre-check.
        $this->assertCount(1, $usersubmissions);
        $writer = writer::with_context($context1);
        $this->assertFalse($writer->has_any_data());

        // Export all of the data for the context for user.
        $this->export_context_data_for_user($user->id, $context1, 'mod_appointment');
        $this->assertTrue($writer->has_any_data());

        // Check that we have general details about the appointment signups.
        $data = $writer->get_related_data([], 'sessions');
        $this->assertCount(1, $data->signups);
        $signup = array_shift($data->signups);
        $this->assertEquals($usersubmissions[$signup->id]->id, $signup->id);
        $this->assertEquals($usersubmissions[$signup->id]->sessionid, $signup->sessionid);

        // Check that we have general details about the signup status.
        $data = (array)$writer->get_related_data([], 'signupstatus');
        $this->assertCount(1, $data);
        $this->assertArrayHasKey($signup->id, $data);
        $signupstatus = $data[$signup->id][0];
        $this->assertEquals(MOD_APPOINTMENT_STATUS_BOOKED, $signupstatus->statuscode);
        $this->assertEquals(\core_privacy\local\request\transform::datetime($usersubmissions[$signup->id]->timecreated),
            $signupstatus->timecreated);

        // Check that we don't have something unexpected is exported.
        $this->assertEmpty($writer->get_related_data([], 'trainer'));
    }

    /**
     * Test that a teacher (trainer) has got correct data exported.
     *
     * @uses \appointment_get_user_submissions
     * @uses \appointment_user_signup
     */
    public function test_export_user_data_teacher() {
        global $CFG, $DB;
        $course = $this->getDataGenerator()->create_course();

        $user = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Enable session trainers for role 'editingteacher'.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'), MUST_EXIST);
        $CFG->appointment_session_roles = "$roleid";

        // Add two appointments to course.
        $appointment1 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment1->id], [$teacher->id]);

        $appointment2 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment2->id], [$teacher->id]);

        // User is signed up for appointment1.
        appointment_user_signup($session1, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user->id);
        $usersubmissions = appointment_get_user_submissions($appointment1->id, $user->id);
        $context1 = \context_module::instance($appointment1->cmid);

        // Pre-check.
        $this->assertCount(1, $usersubmissions);
        $writer = writer::with_context($context1);
        $this->assertFalse($writer->has_any_data());

        // Export all of the data for the context for teacher.
        $this->export_context_data_for_user($teacher->id, $context1, 'mod_appointment');
        $this->assertTrue($writer->has_any_data());

        // Check that we have general details about the appointment trainer.
        $data = $writer->get_related_data([], 'trainer');
        $this->assertEquals('editingteacher', $data->role);

        // Check that we don't have something unexpected is exported.
        $this->assertEmpty($writer->get_related_data([], 'sessions'));
        $this->assertEmpty($writer->get_related_data([], 'signupstatus'));
    }

    /**
     * A test for deleting all user data for a given context.
     *
     * @uses \appointment_get_user_submissions
     * @uses \appointment_user_signup
     * @uses \appointment_get_trainers
     */
    public function test_delete_data_for_all_users_in_context() {
        global $CFG, $DB;
        $course = $this->getDataGenerator()->create_course();

        $user = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Enable session trainers for role 'editingteacher'.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'), MUST_EXIST);
        $CFG->appointment_session_roles = "$roleid";

        // Add appointment to course.
        $appointment1 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment1->id], [$teacher->id]);

        // User is signed up for appointment1.
        appointment_user_signup($session1, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user->id);
        $context1 = \context_module::instance($appointment1->cmid);

        // Pre-check.
        $trainers = appointment_get_trainers($session1->id);
        $this->assertCount(1, $trainers);
        $usersubmissions = appointment_get_user_submissions($appointment1->id, $user->id);
        $this->assertCount(1, $usersubmissions);

        // Delete all user data for this appointment.
        provider::delete_data_for_all_users_in_context($context1);

        // Check all relevant tables.
        $records = $DB->get_records('appointment_signups_status');
        $this->assertEmpty($records);
        $records = $DB->get_records('appointment_signups');
        $this->assertEmpty($records);
        $records = $DB->get_records('appointment_session_roles');
        $this->assertEmpty($records);
    }

    /**
     * A test for deleting user data for a given context.
     *
     * @uses \appointment_user_signup
     */
    public function test_delete_data_for_student() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();

        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        // Add appointment to course.
        $appointment1 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment1->id]);

        // User is signed up for appointment1.
        appointment_user_signup($session1, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user->id);
        appointment_user_signup($session1, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);
        $context1 = \context_module::instance($appointment1->cmid);

        // Pre-check.
        $records = $DB->get_records('appointment_signups_status');
        $this->assertCount(2, $records);
        $records = $DB->get_records('appointment_signups');
        $this->assertCount(2, $records);
        $records = $DB->get_records('appointment_session_roles');
        $this->assertEmpty($records);

        // Delete data for user2.
        $approvedlist = new approved_contextlist($user2, 'mod_assign', [$context1->id]);
        provider::delete_data_for_user($approvedlist);

        // Check all relevant tables.
        $records = $DB->get_records('appointment_signups');
        $this->assertCount(1, $records);
        $record = array_shift($records);
        $this->assertEquals($user->id, $record->userid);
        $signupid = $record->id;

        $records = $DB->get_records('appointment_signups_status');
        $this->assertCount(1, $records);
        $record = array_shift($records);
        $this->assertEquals($signupid, $record->signupid);
    }

    /**
     * A test for deleting trainer data for a given context.
     *
     * @uses \appointment_user_signup
     */
    public function test_delete_data_for_teacher() {
        global $CFG, $DB;
        $course = $this->getDataGenerator()->create_course();

        $user = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Enable session trainers for role 'editingteacher'.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'), MUST_EXIST);
        $CFG->appointment_session_roles = "$roleid";

        // Add appointment to course.
        $appointment1 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment1->id], [$teacher->id]);

        // User is signed up for appointment1.
        appointment_user_signup($session1, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user->id);
        $context1 = \context_module::instance($appointment1->cmid);

        // Pre-check.
        $records = $DB->get_records('appointment_signups_status');
        $this->assertCount(1, $records);
        $records = $DB->get_records('appointment_signups');
        $this->assertCount(1, $records);
        $records = $DB->get_records('appointment_session_roles');
        $this->assertCount(1, $records);

        // Delete data for teacher.
        $approvedlist = new approved_contextlist($teacher, 'mod_assign', [$context1->id]);
        provider::delete_data_for_user($approvedlist);

        // Check all relevant tables.
        $records = $DB->get_records('appointment_signups');
        $this->assertCount(1, $records);
        $records = $DB->get_records('appointment_signups_status');
        $this->assertCount(1, $records);
        $records = $DB->get_records('appointment_session_roles');
        $this->assertEmpty($records);
    }

    /**
     * A test for deleting all user data for a list of users.
     */
    public function test_delete_data_for_users() {
        global $DB, $CFG;

        $course = $this->getDataGenerator()->create_course();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Enable session trainers for role 'editingteacher'.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'), MUST_EXIST);
        $CFG->appointment_session_roles = "$roleid";

        // Add appointment to course.
        $appointment1 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment1->id]);
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment1->id], [$teacher->id]);

        // User is signed up for appointment1.
        appointment_user_signup($session1, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);
        appointment_user_signup($session2, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);
        $context1 = \context_module::instance($appointment1->cmid);

        // Add another appointment.
        $appointment2 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $session3 = $this->get_generator()->create_session(['appointment' => $appointment2->id]);
        appointment_user_signup($session3, $appointment2, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user3->id);
        $context2 = \context_module::instance($appointment2->cmid);

        // Pre-check.
        $records = $DB->get_records('appointment_signups_status');
        $this->assertCount(3, $records);
        $records = $DB->get_records('appointment_signups');
        $this->assertCount(3, $records);
        $records = $DB->get_records('appointment_session_roles');
        $this->assertCount(1, $records);

        // Delete data for users in context1.
        $userlist = new \core_privacy\local\request\approved_userlist($context1, 'appointment', [$user1->id, $user2->id]);
        provider::delete_data_for_users($userlist);

        $records = $DB->get_records('appointment_signups_status');
        $this->assertCount(1, $records);
        $records = $DB->get_records('appointment_signups');
        $this->assertCount(1, $records);
        $records = $DB->get_records('appointment_session_roles');
        $this->assertCount(1, $records);

        // Delete data for teacher in context1.
        $userlist = new \core_privacy\local\request\approved_userlist($context1, 'appointment', [$teacher->id]);
        provider::delete_data_for_users($userlist);

        $records = $DB->get_records('appointment_signups_status');
        $this->assertCount(1, $records);
        $records = $DB->get_records('appointment_signups');
        $this->assertCount(1, $records);
        $records = $DB->get_records('appointment_session_roles');
        $this->assertCount(0, $records);

        // Delete data for user in context2.
        $userlist = new \core_privacy\local\request\approved_userlist($context2, 'appointment', [$user3->id]);
        provider::delete_data_for_users($userlist);

        $records = $DB->get_records('appointment_signups_status');
        $this->assertCount(0, $records);
        $records = $DB->get_records('appointment_signups');
        $this->assertCount(0, $records);
        $records = $DB->get_records('appointment_session_roles');
        $this->assertCount(0, $records);
    }
}

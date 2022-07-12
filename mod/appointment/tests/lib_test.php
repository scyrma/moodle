<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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
 * Class lib_test
 *
 * @package     mod_appointment
 * @category    test
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment;

use advanced_testcase;
use cm_info;
use mod_appointment_generator;
use moodle_url;
use stdClass;

/**
 * Class generator_test
 *
 * @package     mod_appointment
 * @group       mod_appointment
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib_test extends advanced_testcase {

    /** @var stdClass $course */
    protected $course;

    /** @var stdClass $appointment */
    protected $appointment;

    /** @var stdClass $student */
    protected $student;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();

        // Create our test course/student and appointment instance.
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        // Create custom fields. Those customfileds are already hardcoded in email template.
        // This may not need to be required when WP-2641 is landed.
        $customfieldgenerator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $params = [
            'component' => 'mod_appointment',
            'area' => 'appointment',
            'itemid' => 0,
            'contextid' => \context_system::instance()->id
        ];
        $category = $customfieldgenerator->create_category($params);
        $customfieldgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'location']);
        $customfieldgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'venue']);
        $customfieldgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'room']);

        $this->appointment = $this->getDataGenerator()->create_module('appointment',
            ['course' => $this->course->id, 'usercalentry' => true]);
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
     * Test appointment_session_has_capacity.
     *
     * @covers ::appointment_session_has_capacity
     */
    public function test_appointment_session_has_capacity() {
        $studentother = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $cm = get_coursemodule_from_instance('appointment', $this->appointment->id, $this->course->id);
        $context = \context_module::instance($cm->id);

        // Add session.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + 3600;
        $session = $this->get_generator()->create_session(['appointment' => $this->appointment->id, 'capacity' => 1], [], [$date]);

        // Become student.
        $this->setUser($this->student);

        // Test empty session.
        $this->assertTrue(appointment_session_has_capacity($session, $context));

        // Add other user and test again.
        appointment_user_signup($session, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $studentother->id);

        // Test full session.
        $this->assertFalse(appointment_session_has_capacity($session, $context));

        // Become teacher (has overbook capability).
        $this->setUser($teacher);

        // Test full session.
        $this->assertTrue(appointment_session_has_capacity($session, $context));
    }

    /**
     * Test appointment_get_session_info.
     *
     * @covers ::appointment_get_session_info
     */
    public function test_appointment_get_session_info_status(): void {
        $defaultsessionparams = ['appointment' => $this->appointment->id, 'capacity' => 1];

        // Prepare all dates for sessions.
        $dateoriginal = new stdClass();
        $dateoriginal->timestart = strtotime('-5 hours');
        $dateoriginal->timefinish = strtotime('-4 hours');
        $datefirst = new stdClass();
        $datefirst->timestart = strtotime('-3 hours');
        $datefirst->timefinish = strtotime('-2 hours');
        $datestarted = new stdClass();
        $datestarted->timestart = strtotime('-1 hour');
        $datestarted->timefinish = strtotime('+1 hour');
        $datelast = new stdClass();
        $datelast->timestart = strtotime('+2 hours');
        $datelast->timefinish = strtotime('+3 hours');
        $datefinal = new stdClass();
        $datefinal->timestart = strtotime('+4 hours');
        $datefinal->timefinish = strtotime('+5 hours');

        // There are no dates for session.
        $sessionempty = $this->get_generator()->create_session($defaultsessionparams, [], []);
        $statusempty = appointment_get_session_info($sessionempty)['status'];
        $this->assertEquals(get_string('bookingopen', 'appointment'), $statusempty);

        // First date of the session has not started.
        $sessionnotstarted = $this->get_generator()->create_session($defaultsessionparams, [], [$datelast, $datefinal]);
        $statusnotstarted = appointment_get_session_info($sessionnotstarted)['status'];
        $this->assertEquals(get_string('bookingopen', 'appointment'), $statusnotstarted);

        // First date of the session has started.
        $sessionfiststarted = $this->get_generator()->create_session($defaultsessionparams, [], [$datestarted, $datelast]);
        $statusfiststarted = appointment_get_session_info($sessionfiststarted)['status'];
        $this->assertEquals(get_string('closed', 'appointment'), $statusfiststarted);

        // First date of the session has finished and second one has not started.
        $sessionbetween = $this->get_generator()->create_session($defaultsessionparams, [], [$datefirst, $datelast]);
        $statusbetween = appointment_get_session_info($sessionbetween)['status'];
        $this->assertEquals(get_string('closed', 'appointment'), $statusbetween);

        // Second date of the session has started.
        $sessionsecondstarted = $this->get_generator()->create_session($defaultsessionparams, [], [$datefirst, $datestarted]);
        $statussecondstarted = appointment_get_session_info($sessionsecondstarted)['status'];
        $this->assertEquals(get_string('closed', 'appointment'), $statussecondstarted);

        // Second date of the session has finished.
        $sessionallover = $this->get_generator()->create_session($defaultsessionparams, [], [$dateoriginal, $datefirst]);
        $statusallover = appointment_get_session_info($sessionallover)['status'];
        $this->assertEquals(get_string('sessionfinished', 'appointment'), $statusallover);
    }

    /**
     * Test appointment_get_session_info with signed up users.
     *
     * @covers ::appointment_get_session_info
     */
    public function test_appointment_get_session_info_status_user_signup(): void {
        $studentother = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $defaultsessionparams = ['appointment' => $this->appointment->id, 'capacity' => 1];

        // Prepare all dates for sessions.
        $datestarted = new stdClass();
        $datestarted->timestart = strtotime('-1 hour');
        $datestarted->timefinish = strtotime('+1 hour');
        $datelast = new stdClass();
        $datelast->timestart = strtotime('+2 hours');
        $datelast->timefinish = strtotime('+3 hours');
        $datefinal = new stdClass();
        $datefinal->timestart = strtotime('+4 hours');
        $datefinal->timefinish = strtotime('+5 hours');

        // First date of the session has not started.
        $sessionnotstarted = $this->get_generator()->create_session($defaultsessionparams, [], [$datelast, $datefinal]);

        // First date of the session has started.
        $sessionfiststarted = $this->get_generator()->create_session($defaultsessionparams, [], [$datestarted, $datelast]);

        // Check status as signed-up user.
        $this->setUser($studentother);
        appointment_user_signup($sessionnotstarted, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $studentother->id);
        if ($usersubmission = appointment_get_user_submissions($this->appointment->id, $studentother->id)) {
            $sessionnotstarted->usersubmission = array_shift($usersubmission);
        }
        $signupstatusbooked = appointment_get_status(MOD_APPOINTMENT_STATUS_BOOKED);
        $statusbooked = appointment_get_session_info($sessionnotstarted)['status'];
        $this->assertEquals(get_string('status_' . $signupstatusbooked, 'appointment'), $statusbooked);

        // Check status as signed-up user in progress appointment.
        appointment_user_signup($sessionfiststarted, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $studentother->id);
        if ($usersubmission = appointment_get_user_submissions($this->appointment->id, $studentother->id)) {
            $sessionfiststarted->usersubmission = array_shift($usersubmission);
        }
        $statusbooked = appointment_get_status(MOD_APPOINTMENT_STATUS_BOOKED);
        $statusparts[] = get_string('status_' . $statusbooked, 'appointment');
        $statusparts[] = get_string('sessioninprogress', 'appointment');
        $signupstatusbooked = implode(get_string('listsep', 'langconfig') . ' ', $statusparts);
        $statusbooked = appointment_get_session_info($sessionfiststarted)['status'];
        $this->assertEquals($signupstatusbooked, $statusbooked);

        // Check status as not signed-up user.
        $this->setUser($this->student);
        $sessionnotstarted->usersubmission = null;
        $statusfull = appointment_get_session_info($sessionnotstarted)['status'];
        $this->assertEquals(get_string('bookingfull', 'appointment'), $statusfull);

        // Check status as wait-listed user.
        appointment_user_signup($sessionnotstarted, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_WAITLISTED, $this->student->id);
        if ($usersubmission = appointment_get_user_submissions($this->appointment->id, $this->student->id)) {
            $sessionnotstarted->usersubmission = array_shift($usersubmission);
        }
        $signupstatus = appointment_get_status(MOD_APPOINTMENT_STATUS_WAITLISTED);
        $statuswaitlisted = appointment_get_session_info($sessionnotstarted)['status'];
        $this->assertEquals(get_string('status_' . $signupstatus, 'appointment'), $statuswaitlisted);
    }

    /**
     * Test appointment reset course functionality with changed course startdate
     *
     * @covers ::appointment_reset_userdata
     */
    public function test_reset_userdata_timeshift() {
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;

        // Create a new session, add our test user to it.
        $session = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [], [$date]);
        appointment_user_signup($session, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $this->student->id);

        // Simulate course reset with timeshift.
        appointment_reset_userdata((object) [
            'courseid' => $this->course->id,
            'timeshift' => DAYSECS,
        ]);

        $sessions = appointment_get_sessions($this->appointment->id);
        $this->assertCount(1, $sessions);

        $session = reset($sessions);
        $this->assertCount(1, $session->sessiondates);

        $shiftedsessiondate = reset($session->sessiondates);
        $this->assertEquals($date->timestart + DAYSECS, $shiftedsessiondate->timestart);
        $this->assertEquals($date->timefinish + DAYSECS, $shiftedsessiondate->timefinish);
    }

    /**
     * Test appointment reset course functionality resetting sessions
     *
     * @covers ::appointment_reset_userdata
     */
    public function test_reset_userdata_sessions() {
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;

        // Create a new session.
        $session = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [], [$date]);

        // Sanity check.
        $sessions = appointment_get_sessions($this->appointment->id);
        $this->assertCount(1, $sessions);

        // Simulate course reset for session signups.
        appointment_reset_userdata((object) [
            'courseid' => $this->course->id,
            'reset_sessions' => 1,
        ]);

        $sessions = appointment_get_sessions($this->appointment->id);
        $this->assertEmpty($sessions);
    }

    /**
     * Test appointment reset course functionality resetting session signups
     *
     * @covers ::appointment_reset_userdata
     */
    public function test_reset_userdata_signups() {
        global $DB;

        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;

        // Create a new session, add our test user to it.
        $session = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [], [$date]);
        appointment_user_signup($session, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $this->student->id);

        // Sanity check, our test student should now be an attendee with a calendar event for the session.
        $attendees = appointment_get_attendees($session->id);
        $this->assertCount(1, $attendees);
        $this->assertEquals($this->student->id, reset($attendees)->id);
        $this->assertEquals(1, $DB->count_records('event', [
            'userid' => $this->student->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $this->appointment->id,
            'uuid' => $session->id,
        ]));

        // Simulate course reset for session signups, should remove attendees and related calendar events.
        appointment_reset_userdata((object) [
            'courseid' => $this->course->id,
            'reset_sessions_signups' => 1,
        ]);

        $attendees = appointment_get_attendees($session->id);
        $this->assertEmpty($attendees);
        $this->assertEquals(0, $DB->count_records('event', [
            'userid' => $this->student->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $this->appointment->id,
            'uuid' => $session->id,
        ]));
    }

    /**
     * Tests for appointment_refresh_events.
     *
     * @covers ::appointment_refresh_events
     */
    public function test_refresh_events() {
        global $DB;

        // Create new sessions and sign-up.
        $timestarts0 = strtotime('+1 day');
        $timefinishs0 = $timestarts0 + HOURSECS;
        $session0 = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [],
            [(object)['timestart' => $timestarts0, 'timefinish' => $timefinishs0]]);
        appointment_user_signup($session0, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $this->student->id);

        // Create another new sessions with user sign-up.
        $student1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $timestarts1 = strtotime('+2 days');
        $timefinishs1 = $timestarts1 + HOURSECS;
        $session1 = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [],
            [(object)['timestart' => $timestarts1, 'timefinish' => $timefinishs1]]);
        appointment_user_signup($session1, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $student1->id);

        // Make sure the calendar event for users matches the initial due date.
        $eventparams0 = [
            'instance' => $this->appointment->id,
            'eventtype' => 'appointmentbooking',
            'userid' => $this->student->id,
        ];
        $eventtimes0 = $DB->get_field('event', 'timestart', $eventparams0, MUST_EXIST);
        $this->assertEquals($timestarts0, $eventtimes0);

        $eventparams1 = [
            'instance' => $this->appointment->id,
            'eventtype' => 'appointmentbooking',
            'userid' => $student1->id,
        ];
        $eventtimes1 = $DB->get_field('event', 'timestart', $eventparams1, MUST_EXIST);
        $this->assertEquals($timestarts1, $eventtimes1);

        // Manually update session0 time.
        $timestarts0new = strtotime('+1 month');
        $timefinishs0new = $timestarts0new + HOURSECS;
        $daterecord = $DB->get_record('appointment_sessions_dates', ['sessionid' => $session0->id]);
        $DB->update_record('appointment_sessions_dates', (object) [
            'id' => $daterecord->id,
            'timestart' => $timestarts0new,
            'timefinish' => $timefinishs0new,
        ]);

        // Then refresh the appointment events.
        $this->assertTrue(appointment_refresh_events($this->course->id));

        // Confirm that the appointment session0 time has changed.
        $eventtimes0 = $DB->get_field('event', 'timestart', $eventparams0, MUST_EXIST);
        $this->assertEquals($timestarts0new, $eventtimes0);

        // Confirm that the appointment session1 time has not changed.
        $eventtimes1 = $DB->get_field('event', 'timestart', $eventparams1, MUST_EXIST);
        $this->assertEquals($timestarts1, $eventtimes1);
    }

    /**
     * Tests for appointment_refresh_events for non-existing entities.
     *
     * @covers ::appointment_refresh_events
     */
    public function test_refresh_events_non_existing() {
        // Non existing course.
        $this->assertFalse(appointment_refresh_events(100));

        // No appointments in course.
        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse(appointment_refresh_events($course->id));
    }

    /**
     * Test appointment_add_session.
     *
     * @covers ::appointment_get_session
     * @covers ::appointment_add_session
     */
    public function test_appointment_add_session() {
        $studentother = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $cm = get_coursemodule_from_instance('appointment', $this->appointment->id, $this->course->id);
        $context = \context_module::instance($cm->id);

        // Add session.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + 3600;

        $sessiondata = [
            'appointment' => $this->appointment->id,
            'capacity' => 10,
            'allowwaitlist' => true,
            'details' => 'Session0',
            'detailsformat' => 1,
        ];

        $sink = $this->redirectEvents();
        $sessionid = appointment_add_session((object) $sessiondata, [$date], $context);

        // Retrieve session details.
        $session = appointment_get_session($sessionid);

        // Validate settings.
        $this->assertNotFalse($session);
        $this->assertEquals($sessiondata['details'], $session->details);
        $this->assertEquals($sessiondata['detailsformat'], $session->detailsformat);
        $this->assertEquals($sessiondata['allowwaitlist'], $session->allowwaitlist);
        $this->assertEquals($sessiondata['capacity'], $session->capacity);
        $this->assertEquals($date->timestart, $session->sessiondates[0]->timestart);
        $this->assertEquals($date->timefinish, $session->sessiondates[0]->timefinish);

        // Validate event.
        $events = $sink->get_events();
        $event = reset($events);
        $this->assertInstanceOf('\mod_appointment\event\add_session', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/mod/appointment/sessions.php', ['s' => $session->id]);
        $this->assertEquals($url, $event->get_url());
    }

    /**
     * Test appointment_candidate_selector::find_users and appointment_existing_selector::find_users.
     *
     * @covers appointment_candidate_selector::find_users
     * @covers appointment_existing_selector::find_users
     */
    public function test_appointment_selector_find_users() {
        $student2 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $cm = get_coursemodule_from_instance('appointment', $this->appointment->id, $this->course->id);
        $context = \context_module::instance($cm->id);

        // Add sessions.
        $date1 = new stdClass();
        $date1->timestart = strtotime('+1 day');
        $date1->timefinish = $date1->timestart + 3600;
        $date2 = new stdClass();
        $date2->timestart = strtotime('+3 day');
        $date2->timefinish = $date2->timestart + 3600;

        $sessiondata = [
            'appointment' => $this->appointment->id,
            'capacity' => 10,
            'allowwaitlist' => true,
            'details' => 'Session1',
            'detailsformat' => 1,
        ];

        $sessionid1 = appointment_add_session((object) $sessiondata, [$date1], $context);
        $session1 = appointment_get_session($sessionid1);
        $sessiondata['details'] = 'Session2';
        $sessionid2 = appointment_add_session((object) $sessiondata, [$date2], $context);

        // Signup user to session 1.
        appointment_user_signup($session1, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $this->student->id);

        // Assert that only not signed up users are returned.
        $potentialuserselector1 = new \appointment_candidate_selector('addselect', array('sessionid' => $sessionid1));
        $userspotential1 = self::get_user_ids_from_selector_users($potentialuserselector1->find_users(''));
        $this->assertEquals([$student2->id], $userspotential1);

        // Assert that only signed up users are returned.
        $existinguserselector1 = new \appointment_existing_selector('removeselect', array('sessionid' => $sessionid1));
        $usersexisting1 = self::get_user_ids_from_selector_users($existinguserselector1->find_users(''));
        $this->assertEquals([$this->student->id], $usersexisting1);

        // Assert that only not signed up to any appointment session users are returned.
        $potentialuserselector2 = new \appointment_candidate_selector('addselect', array('sessionid' => $sessionid2));
        $userspotential2 = self::get_user_ids_from_selector_users($potentialuserselector2->find_users(''));
        $this->assertEquals([$student2->id], $userspotential2);

        // Assert that no user was signed up.
        $existinguserselector2 = new \appointment_existing_selector('removeselect', array('sessionid' => $sessionid2));
        $usersexisting2 = self::get_user_ids_from_selector_users($existinguserselector2->find_users(''));
        $this->assertEmpty($usersexisting2);
    }

    /**
     * Returns user ids from the user objects returned by the selector.
     * @param array $users
     * @return array
     */
    private static function get_user_ids_from_selector_users(array $users): array {
        $userids = [];
        if ($users = array_shift($users)) {
            foreach ($users as $user) {
                // Here appointment_candidate_selector uses $user->userid and appointment_existing_selector uses $user->id.
                $userids[] = $user->userid ?? $user->id;
            }
        }
        return $userids;
    }

    /**
     * User sign-up.
     *
     * @covers ::appointment_user_signup
     */
    public function test_user_signup_notification() {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');

        // Create ongoing session with user. Customfileds are already hardcoded in email template.
        $date = new stdClass();
        $date->timestart = strtotime('+1 hour');
        $date->timefinish = strtotime('+2 hour');
        $location = 'Lancaster University';
        $venue = 'Business school';
        $room = 'A211';
        $sessionsettings = [
            'appointment' => $this->appointment->id,
            'customfield_location' => $location,
            'customfield_venue' => $venue,
            'customfield_room' => $room,
        ];
        // Need to be a teacher to save session customfields.
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);
        $session0 = $this->get_generator()->create_session($sessionsettings, [], [$date]);

        // Prepare for tests.
        $this->setUser($student);
        $mailsink = $this->redirectEmails();

        // Signup session0.
        $submissionid = appointment_user_signup($session0, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED);

        // Validate record present.
        $signups = $DB->get_records('appointment_signups', ['sessionid' => $session0->id, 'userid' => $student->id]);
        $this->assertCount(1, $signups);

        // Validate messages.
        $messages = $mailsink->get_messages();
        $mailsink->clear();
        $this->assertCount(1, $messages);
        $body = quoted_printable_decode($messages[0]->body);
        $subject = quoted_printable_decode($messages[0]->subject);
        $expectedsubject = appointment_email_substitutions($this->appointment->confirmationsubject,
            $this->appointment->name, $this->appointment->reminderperiod, $student, $session0, $session0->id);
        $this->assertEquals($expectedsubject, $subject);
        $this->assertStringContainsString($this->appointment->name, $body);
        $this->assertStringContainsString($student->firstname, $body);
        $this->assertStringContainsString($student->lastname, $body);
        $this->assertStringContainsString(userdate($date->timestart, get_string('strftimetime')), $body);
        $this->assertStringContainsString(userdate($date->timefinish, get_string('strftimetime')), $body);
        $this->assertStringContainsString($location, $body);
        $this->assertStringContainsString($venue, $body);
        $this->assertStringContainsString($room, $body);
    }

    /**
     * User session update notification.
     *
     * @covers ::appointment_update_session
     * @uses ::appointment_get_session
     * @uses ::appointment_user_signup
     */
    public function test_session_update_user_notification() {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');

        $cm = get_coursemodule_from_instance('appointment', $this->appointment->id, $this->course->id);
        $context = \context_module::instance($cm->id);

        // Create ongoing session. Customfileds are already hardcoded in email template.
        $date = new stdClass();
        $date->timestart = strtotime('+1 hour');
        $date->timefinish = strtotime('+2 hour');
        $sessionsettings = [
            'allowwaitlist' => true,
            'capacity' => 5,
            'appointment' => $this->appointment->id,
        ];

        // Signup session0.
        $session0 = $this->get_generator()->create_session($sessionsettings, [], [$date]);
        appointment_user_signup($session0, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $student->id);

        // Validate record present.
        $signups = $DB->get_records('appointment_signups', ['sessionid' => $session0->id, 'userid' => $student->id]);
        $this->assertCount(1, $signups);

        // Need to be a teacher to save session customfields.
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);

        // Prepare for capturing mail and events.
        $mailsink = $this->redirectEmails();
        $eventsink = $this->redirectEvents();

        // Edit session time.
        $date->timefinish = strtotime('+3 hour');
        appointment_update_session($session0, [$date], $context);

        // Validate message is update conmfirmation.
        $messages = $mailsink->get_messages();
        $mailsink->clear();
        $session0 = appointment_get_session($session0->id);
        $this->assertCount(1, $messages);
        $body = quoted_printable_decode($messages[0]->body);
        $subject = quoted_printable_decode($messages[0]->subject);
        $session0 = appointment_get_session($session0->id);
        $expectedsubject = appointment_email_substitutions($this->appointment->updatesubject,
            $this->appointment->name, $this->appointment->reminderperiod, $student, $session0, $session0->id);
        $this->assertEquals($expectedsubject, $subject);
        $this->assertStringContainsString($this->appointment->name, $body);
        $this->assertStringContainsString($student->firstname, $body);
        $this->assertStringContainsString($student->lastname, $body);
        $this->assertStringContainsString(userdate($date->timestart, get_string('strftimetime')), $body);
        $this->assertStringContainsString(userdate($date->timefinish, get_string('strftimetime')), $body);

        // Validate events.
        $events = $eventsink->get_events();
        $eventsink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_appointment\event\update_session', $event);
        $this->assertEquals($context, $event->get_context());

        // Edit session customfield.
        $location = 'Lancaster University';
        $session0->customfield_location = $location;
        appointment_update_session($session0, [$date], $context);

        // Validate message is update conmfirmation.
        $messages = $mailsink->get_messages();
        $mailsink->clear();
        $this->assertCount(1, $messages);
        $body = quoted_printable_decode($messages[0]->body);
        $subject = quoted_printable_decode($messages[0]->subject);
        $expectedsubject = appointment_email_substitutions($this->appointment->updatesubject,
            $this->appointment->name, $this->appointment->reminderperiod, $student, $session0, $session0->id);
        $this->assertEquals($expectedsubject, $subject);
        $this->assertStringContainsString($this->appointment->name, $body);
        $this->assertStringContainsString($student->firstname, $body);
        $this->assertStringContainsString($student->lastname, $body);
        $this->assertStringContainsString(userdate($date->timestart, get_string('strftimetime')), $body);
        $this->assertStringContainsString(userdate($date->timefinish, get_string('strftimetime')), $body);
        $this->assertStringContainsString($location, $body);

        // Validate events.
        $events = $eventsink->get_events();
        $eventsink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_appointment\event\update_session', $event);
        $this->assertEquals($context, $event->get_context());

        // Edit session, change capacity. Keep location customfield the same, so we change only capacity.
        $session0->capacity = 2;
        appointment_update_session($session0, [$date], $context);

        // Validate message, no updates expected.
        $messages = $mailsink->get_messages();
        $mailsink->clear();
        $this->assertCount(0, $messages);

        // Validate events, we expect event to be triggered.
        $events = $eventsink->get_events();
        $eventsink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_appointment\event\update_session', $event);
        $this->assertEquals($context, $event->get_context());

        // Simply edit session without changing anything.
        appointment_update_session($session0, [$date], $context);

        // Validate message, no updates expected.
        $messages = $mailsink->get_messages();
        $mailsink->clear();
        $this->assertCount(0, $messages);

        // Validate events, no events expected.
        $events = $eventsink->get_events();
        $eventsink->clear();
        $this->assertCount(0, $events);
    }

    /**
     * User becomes booked when capacity permits and receive notification.
     */
    public function test_session_update_waitlisted_becomes_booked() {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($student1->id, $this->course->id, 'student');

        // Create ongoing session. Customfileds are already hardcoded in email template.
        $date = new stdClass();
        $date->timestart = strtotime('+1 hour');
        $date->timefinish = strtotime('+2 hour');
        $sessionsettings = [
            'allowwaitlist' => true,
            'capacity' => 1,
            'appointment' => $this->appointment->id,
        ];

        // Signup session0.
        $session0 = $this->get_generator()->create_session($sessionsettings, [], [$date]);
        appointment_user_signup($session0, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $student->id);
        appointment_user_signup($session0, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_WAITLISTED, $student1->id);

        // Validate.
        $signups = $DB->get_records('appointment_signups', ['sessionid' => $session0->id]);
        $this->assertCount(2, $signups);

        $sink = $this->redirectEmails();

        // Need to be a teacher to save session customfields.
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);

        // Edit session, change capacity.
        $session0->capacity = 2;
        $cm = get_coursemodule_from_instance('appointment', $this->appointment->id, $this->course->id);
        appointment_update_session($session0, [$date], \context_module::instance($cm->id));

        // Capture the message.
        $messages = $sink->get_messages();
        $sink->clear();

        // Validate message is booking confirmation.
        $this->assertCount(1, $messages);
        $body = quoted_printable_decode($messages[0]->body);
        $subject = quoted_printable_decode($messages[0]->subject);
        $expectedsubject = appointment_email_substitutions($this->appointment->confirmationsubject,
            $this->appointment->name, $this->appointment->reminderperiod, $student1, $session0, $session0->id);
        $this->assertEquals($expectedsubject, $subject);
        $this->assertStringContainsString($this->appointment->name, $body);
        $this->assertStringContainsString($student1->firstname, $body);
        $this->assertStringContainsString($student1->lastname, $body);
        $this->assertStringContainsString(userdate($date->timestart, get_string('strftimetime')), $body);
        $this->assertStringContainsString(userdate($date->timefinish, get_string('strftimetime')), $body);
    }

    /**
     * User becomes booked when capacity permits and receive notification.
     */
    public function test_session_update_waitlisted_becomes_cancelled() {
        global $DB;
        $student0 = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student0->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($student1->id, $this->course->id, 'student');

        // Create ongoing session. Customfileds are already hardcoded in email template.
        $date = new stdClass();
        $date->timestart = strtotime('+1 hour');
        $date->timefinish = strtotime('+2 hour');
        $sessionsettings = [
            'allowwaitlist' => true,
            'capacity' => 1,
            'appointment' => $this->appointment->id,
        ];

        // Signup session0.
        $session0 = $this->get_generator()->create_session($sessionsettings, [], [$date]);
        appointment_user_signup($session0, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_BOOKED, $student0->id);
        appointment_user_signup($session0, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_WAITLISTED, $student1->id);

        // Validate that student1 IS waitlisted.
        $this->assertTrue(appointment_is_user_on_waitlist($session0, $student1->id));
        $this->assertFalse(appointment_was_user_on_waitlist($session0, $student1->id));
        $this->assertFalse(appointment_is_user_on_waitlist($session0, $student0->id));
        $this->assertFalse(appointment_was_user_on_waitlist($session0, $student0->id));

        // Cancel the student1.
        $errorstr = '';
        appointment_user_cancel($session0, $student1->id, false, $errorstr, '');

        // Validate that student1 WAS waitlisted and now is not.
        $this->assertFalse(appointment_is_user_on_waitlist($session0, $student1->id));
        $this->assertTrue(appointment_was_user_on_waitlist($session0, $student1->id));

        // Waitlist student1.
        appointment_user_signup($session0, $this->appointment, $this->course, null,
            MOD_APPOINTMENT_STATUS_WAITLISTED, $student1->id);

        // Validate that student1 is waitlisted.
        $this->assertTrue(appointment_is_user_on_waitlist($session0, $student1->id));
        $this->assertFalse(appointment_was_user_on_waitlist($session0, $student1->id));

        // Cancel student0.
        appointment_user_cancel($session0, $student0->id, false, $errorstr, '');

        // Validate that student1 was waitlisted and it is not anymore.
        $this->assertFalse(appointment_is_user_on_waitlist($session0, $student1->id));
        $this->assertTrue(appointment_was_user_on_waitlist($session0, $student1->id));
        // Validate that student0 was and is not waitlisted.
        $this->assertFalse(appointment_is_user_on_waitlist($session0, $student0->id));
        $this->assertFalse(appointment_was_user_on_waitlist($session0, $student0->id));
    }

    /**
     * Test the callback responsible for returning the completion rule descriptions.
     * This function should work given either an instance of the module (cm_info), such as when checking the active rules,
     * or if passed a stdClass of similar structure, such as when checking the the default completion settings for a mod type.
     */
    public function test_mod_appointment_completion_get_active_rule_descriptions() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Two activities, both with automatic completion. One has the 'completionbooked' rule, one doesn't.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $appointment1 = $this->getDataGenerator()->create_module('appointment', [
            'course' => $course->id,
            'completion' => 2,
            'completionbooked' => 1
        ]);
        $appointment2 = $this->getDataGenerator()->create_module('appointment', [
            'course' => $course->id,
            'completion' => 2,
            'completionbooked' => 0
        ]);

        $cm1 = cm_info::create(get_coursemodule_from_instance('appointment', $appointment1->id));
        $cm2 = cm_info::create(get_coursemodule_from_instance('appointment', $appointment2->id));

        // Data for the stdClass input type.
        // This type of input would occur when checking the default completion rules for an activity type, where we don't have
        // any access to cm_info, rather the input is a stdClass containing completion and customdata attributes, just like cm_info.
        $moddefaults = new stdClass();
        $moddefaults->customdata = ['customcompletionrules' => ['completionbooked' => 1]];
        $moddefaults->completion = 2;

        $activeruledescriptions = [get_string('completionbooked', 'appointment')];
        $this->assertEquals($activeruledescriptions, mod_appointment_get_completion_active_rule_descriptions($cm1));
        $this->assertEquals([], mod_appointment_get_completion_active_rule_descriptions($cm2));
        $this->assertEquals($activeruledescriptions, mod_appointment_get_completion_active_rule_descriptions($moddefaults));
        $this->assertEquals([], mod_appointment_get_completion_active_rule_descriptions(new stdClass()));
    }

    public function test_take_attendance() {
        // Create a course with an appointment.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $context = \context_module::instance($appointment->cmid);
        $session = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'timestart1' => strtotime('-1 day'), 'timefinish1' => strtotime('+1 day')]);

        // Enrol all three users into this course.
        $user1 = $this->getDataGenerator()->create_and_enrol($course);
        $user2 = $this->getDataGenerator()->create_and_enrol($course);
        $user3 = $this->getDataGenerator()->create_and_enrol($course);

        // Sign up as user2, make sure the current status is 'requested'.
        appointment_user_signup($session, $appointment, $course, null, MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);
        appointment_user_signup($session, $appointment, $course, null, MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);
        appointment_user_signup($session, $appointment, $course, null, MOD_APPOINTMENT_STATUS_BOOKED, $user3->id);

        $signups = appointment_get_user_submissions($appointment->id, $user1->id);
        $signup = reset($signups);
        $this->assertEquals(MOD_APPOINTMENT_STATUS_BOOKED, $signup->statuscode);
        $submissionid1 = $signup->id;
        $signups = appointment_get_user_submissions($appointment->id, $user2->id);
        $signup = reset($signups);
        $submissionid2 = $signup->id;
        $signups = appointment_get_user_submissions($appointment->id, $user3->id);
        $signup = reset($signups);
        $submissionid3 = $signup->id;

        $data = ['s' => $session->id];
        $data['submissionid_'.$submissionid1] = MOD_APPOINTMENT_STATUS_FULLY_ATTENDED;
        $data['submissionid_'.$submissionid2] = MOD_APPOINTMENT_STATUS_PARTIALLY_ATTENDED;
        $data['submissionid_'.$submissionid3] = MOD_APPOINTMENT_STATUS_NO_SHOW;
        appointment_take_attendance((object)$data);

        $this->assertTrue(user_has_booked_sessions($user1->id, $appointment->id));
        $this->assertTrue(user_has_booked_sessions($user2->id, $appointment->id));
        $this->assertFalse(user_has_booked_sessions($user3->id, $appointment->id));
    }
}

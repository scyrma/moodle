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

        $this->appointment = $this->getDataGenerator()->create_module('appointment', [
            'course' => $this->course->id,
            'usercalentry' => true,
            'showoncalendar' => MOD_APPOINTMENT_CAL_COURSE
        ]);
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
        appointment_user_signup($session, $this->appointment, $this->course, MOD_APPOINTMENT_STATUS_BOOKED, $studentother->id);

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
        appointment_user_signup($sessionnotstarted, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $studentother->id);
        if ($usersubmission = appointment_get_user_submissions($this->appointment->id, $studentother->id)) {
            $sessionnotstarted->usersubmission = array_shift($usersubmission);
        }
        $signupstatusbooked = appointment_get_status(MOD_APPOINTMENT_STATUS_BOOKED);
        $statusbooked = appointment_get_session_info($sessionnotstarted)['status'];
        $this->assertEquals(get_string('status_' . $signupstatusbooked, 'appointment'), $statusbooked);

        // Check status as signed-up user in progress appointment.
        appointment_user_signup($sessionfiststarted, $this->appointment, $this->course,
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
        appointment_user_signup($sessionnotstarted, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_WAITLISTED, $this->student->id);
        if ($usersubmission = appointment_get_user_submissions($this->appointment->id, $this->student->id)) {
            $sessionnotstarted->usersubmission = array_shift($usersubmission);
        }
        $signupstatus = appointment_get_status(MOD_APPOINTMENT_STATUS_WAITLISTED);
        $statuswaitlisted = appointment_get_session_info($sessionnotstarted)['status'];
        $this->assertEquals(get_string('status_' . $signupstatus, 'appointment'), $statuswaitlisted);
    }

    /**
     * Make sure all statuses strings exist
     *
     * @covers ::appointment_statuses
     */
    public function test_appointment_get_status() {
        foreach (appointment_statuses() as $code => $stringkey) {
            $this->assertNotEmpty(
                get_string('status_'.appointment_get_status($code), 'mod_appointment'));
        }
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
        appointment_user_signup($session, $this->appointment, $this->course,
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
        appointment_user_signup($session, $this->appointment, $this->course,
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
        appointment_user_signup($session0, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $this->student->id);

        // Create another new sessions with user sign-up.
        $student1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $timestarts1 = strtotime('+2 days');
        $timefinishs1 = $timestarts1 + HOURSECS;
        $session1 = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [],
            [(object)['timestart' => $timestarts1, 'timefinish' => $timefinishs1]]);
        appointment_user_signup($session1, $this->appointment, $this->course,
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
        global $DB;

        $cm = get_coursemodule_from_instance('appointment', $this->appointment->id, $this->course->id);
        $context = \context_module::instance($cm->id);

        // Add session.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;

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

        // Validate course calendar events.
        $session1eventparams = [
            'courseid' => $this->course->id,
            'eventtype' => 'appointmentsession',
            'instance' => $this->appointment->id,
            'uuid' => $session->id,
        ];
        $this->assertTrue($DB->record_exists('event', $session1eventparams));

        // Validate event.
        $events = $sink->get_events();
        $event = reset($events);
        $this->assertInstanceOf('\mod_appointment\event\add_session', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/mod/appointment/sessions.php', ['s' => $session->id]);
        $this->assertEquals($url, $event->get_url());
    }

    /**
     * Test appointment_add_session with site calendar.
     *
     * @covers ::appointment_get_session
     * @covers ::appointment_add_session
     */
    public function test_appointment_add_session_site_cal() {
        global $DB;

        // Create appointment.
        $appointment = $this->getDataGenerator()->create_module('appointment', [
            'course' => $this->course->id,
            'usercalentry' => true,
            'showoncalendar' => MOD_APPOINTMENT_CAL_SITE
        ]);

        $cm = get_coursemodule_from_instance('appointment', $appointment->id, $this->course->id);
        $context = \context_module::instance($cm->id);

        // Add session.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;

        $sessiondata = [
            'appointment' => $appointment->id,
            'capacity' => 10,
            'allowwaitlist' => true,
            'details' => 'Session0',
            'detailsformat' => 1,
        ];

        $sessionid = appointment_add_session((object) $sessiondata, [$date], $context);

        // Retrieve session details.
        $session = appointment_get_session($sessionid);

        // Validate site calendar events.
        $session1eventparams = [
            'courseid' => SITEID,
            'eventtype' => 'appointmentsession',
            'instance' => $appointment->id,
            'uuid' => $session->id,
        ];
        $this->assertTrue($DB->record_exists('event', $session1eventparams));
    }

    /**
     * Test appointment_user_signup.
     *
     * @covers ::appointment_user_signup
     */
    public function test_appointment_user_signup() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $this->course->id, 'student');

        // Create new sessions.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session1 = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [], [$date]);
        $date->timestart = strtotime('+2 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session2 = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [], [$date]);

        // Users are signed up for appointments.
        appointment_user_signup($session1, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);
        appointment_user_signup($session2, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);

        // Test users should now be an attendee with a calendar event for the session.
        $attendees = appointment_get_attendees($session1->id);
        $this->assertEquals([$user1->id], array_column($attendees, 'id'));
        $user1eventparams = [
            'userid' => $user1->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $this->appointment->id,
            'uuid' => $session1->id,
        ];
        $this->assertTrue($DB->record_exists('event', $user1eventparams));

        $attendees = appointment_get_attendees($session2->id);
        $this->assertEquals([$user2->id], array_column($attendees, 'id'));
        $user2eventparams = [
            'userid' => $user2->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $this->appointment->id,
            'uuid' => $session2->id,
        ];
        $this->assertTrue($DB->record_exists('event', $user2eventparams));

        // Validate records.
        $signup1 = $DB->get_field('appointment_signups', 'id', ['sessionid' => $session1->id]);
        $signup2 = $DB->get_field('appointment_signups', 'id', ['sessionid' => $session2->id]);

        $this->assertCount(1, $DB->get_records('appointment_signups_status', ['signupid' => $signup1]));
        $this->assertCount(1, $DB->get_records('appointment_signups', ['sessionid' => $session1->id]));
        $this->assertCount(1, $DB->get_records('appointment_signups_status', ['signupid' => $signup2]));
        $this->assertCount(1, $DB->get_records('appointment_signups', ['sessionid' => $session2->id]));
    }

    /**
     * Test appointment_delete_session.
     *
     * @covers ::appointment_delete_session
     */
    public function test_appointment_delete_session() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $this->course->id, 'student');

        // Create new sessions.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session1 = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [], [$date]);
        $date->timestart = strtotime('+2 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session2 = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [], [$date]);

        // Users are signed up for appointments.
        appointment_user_signup($session1, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);
        appointment_user_signup($session2, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);

        // Sanity check, our test students should now be an attendee with a calendar event for the session.
        $user1eventparams = [
            'userid' => $user1->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $this->appointment->id,
            'uuid' => $session1->id,
        ];
        $this->assertTrue($DB->record_exists('event', $user1eventparams));

        $user2eventparams = [
            'userid' => $user2->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $this->appointment->id,
            'uuid' => $session2->id,
        ];
        $this->assertTrue($DB->record_exists('event', $user2eventparams));

        // Sanity check, sessions should have course calendar events too.
        $session1eventparams = [
            'courseid' => $this->course->id,
            'eventtype' => 'appointmentsession',
            'instance' => $this->appointment->id,
            'uuid' => $session1->id,
        ];
        $this->assertTrue($DB->record_exists('event', $session1eventparams));

        $session2eventparams = [
            'courseid' => $this->course->id,
            'eventtype' => 'appointmentsession',
            'instance' => $this->appointment->id,
            'uuid' => $session2->id,
        ];
        $this->assertTrue($DB->record_exists('event', $session2eventparams));

        // Get session sign-ups.
        $signup1 = $DB->get_field('appointment_signups', 'id', ['sessionid' => $session1->id]);
        $signup2 = $DB->get_field('appointment_signups', 'id', ['sessionid' => $session2->id]);

        // Delete session 1.
        appointment_delete_session($session1);

        // Validate records.
        $this->assertCount(1, $DB->get_records('appointment_sessions', ['appointment' => $this->appointment->id]));
        $this->assertCount(0, $DB->get_records('appointment_signups_status', ['signupid' => $signup1]));
        $this->assertCount(0, $DB->get_records('appointment_signups', ['sessionid' => $session1->id]));
        $this->assertCount(1, $DB->get_records('appointment_signups_status', ['signupid' => $signup2]));
        $this->assertCount(1, $DB->get_records('appointment_signups', ['sessionid' => $session2->id]));

        // Check that calendar events for session 1 were deleted.
        $this->assertFalse($DB->record_exists('event', $user1eventparams));
        $this->assertFalse($DB->record_exists('event', $session1eventparams));

        // Check that calendar events for session 2 are still present.
        $this->assertTrue($DB->record_exists('event', $user2eventparams));
        $this->assertTrue($DB->record_exists('event', $session2eventparams));
    }

    /**
     * Test appointment_delete_session with site calendar.
     *
     * @covers ::appointment_delete_session
     */
    public function test_appointment_delete_session_site_cal() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $this->course->id, 'student');

        // Create appointment.
        $appointment = $this->getDataGenerator()->create_module('appointment', [
            'course' => $this->course->id,
            'usercalentry' => true,
            'showoncalendar' => MOD_APPOINTMENT_CAL_SITE
        ]);

        // Create new sessions.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);
        $date->timestart = strtotime('+2 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);

        // Users are signed up for appointments.
        appointment_user_signup($session1, $appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);
        appointment_user_signup($session2, $appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);

        // Sanity check, sessions should not have course calendar events.
        $session1eventparams = [
            'courseid' => $this->course->id,
            'eventtype' => 'appointmentsession',
            'instance' => $appointment->id,
            'uuid' => $session1->id,
        ];
        $this->assertFalse($DB->record_exists('event', $session1eventparams));

        $session2eventparams = [
            'courseid' => $this->course->id,
            'eventtype' => 'appointmentsession',
            'instance' => $appointment->id,
            'uuid' => $session2->id,
        ];
        $this->assertFalse($DB->record_exists('event', $session2eventparams));

        // Sanity check, sessions should have site calendar events.
        $session1eventparams['courseid'] = SITEID;
        $this->assertTrue($DB->record_exists('event', $session1eventparams));
        $session2eventparams['courseid'] = SITEID;
        $this->assertTrue($DB->record_exists('event', $session2eventparams));

        // Delete session 1.
        appointment_delete_session($session1);

        // Check that calendar events for session 1 were deleted.
        $this->assertFalse($DB->record_exists('event', $session1eventparams));

        // Check that calendar events for session 2 still present.
        $this->assertTrue($DB->record_exists('event', $session2eventparams));
    }

    /**
     * Test appointment_delete_instance.
     *
     * @covers ::appointment_delete_instance
     */
    public function test_appointment_delete_instance() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $this->course->id, 'student');

        // Create appointment.
        $appointment = $this->getDataGenerator()->create_module('appointment', [
            'course' => $this->course->id,
            'usercalentry' => true,
            'showoncalendar' => MOD_APPOINTMENT_CAL_COURSE
        ]);

        // Create new sessions.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);
        $date->timestart = strtotime('+2 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);

        // Users are signed up for appointments.
        appointment_user_signup($session1, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);
        appointment_user_signup($session2, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);

        // Sanity check.
        $user1eventparams = [
            'userid' => $user1->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $appointment->id,
            'uuid' => $session1->id,
        ];
        $this->assertTrue($DB->record_exists('event', $user1eventparams));

        $user2eventparams = [
            'userid' => $user2->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $appointment->id,
            'uuid' => $session2->id,
        ];
        $this->assertTrue($DB->record_exists('event', $user2eventparams));

        $session1eventparams = [
            'courseid' => $this->course->id,
            'eventtype' => 'appointmentsession',
            'instance' => $appointment->id,
            'uuid' => $session1->id,
        ];
        $this->assertTrue($DB->record_exists('event', $session1eventparams));

        $session2eventparams = [
            'courseid' => $this->course->id,
            'eventtype' => 'appointmentsession',
            'instance' => $appointment->id,
            'uuid' => $session2->id,
        ];
        $this->assertTrue($DB->record_exists('event', $session2eventparams));

        // Delete appointment.
        appointment_delete_instance($appointment->id);

        // Validate records.
        $this->assertFalse($DB->record_exists('appointment', ['id' => $appointment->id]));
        $this->assertCount(0, $DB->get_records('appointment_sessions', ['appointment' => $appointment->id]));
        $this->assertCount(0, $DB->get_records('appointment_signups', ['sessionid' => $session1->id]));

        // Check that calendar events for session 1 were deleted.
        $this->assertFalse($DB->record_exists('event', $user1eventparams));
        $this->assertFalse($DB->record_exists('event', $session1eventparams));

        // Check that calendar events for session 2 were deleted.
        $this->assertFalse($DB->record_exists('event', $user2eventparams));
        $this->assertFalse($DB->record_exists('event', $session2eventparams));
    }

    /**
     * Test appointment_delete_instance with site calendar.
     *
     * @covers ::appointment_delete_instance
     */
    public function test_appointment_delete_instance_site_cal() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $this->course->id, 'student');

        // Create appointment.
        $appointment = $this->getDataGenerator()->create_module('appointment', [
            'course' => $this->course->id,
            'usercalentry' => false,
            'showoncalendar' => MOD_APPOINTMENT_CAL_SITE
        ]);

        // Create new sessions.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);
        $date->timestart = strtotime('+2 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);

        // Users are signed up for appointments.
        appointment_user_signup($session1, $appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);
        appointment_user_signup($session2, $appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);

        // Sanity check, no user records are expected.
        $user1eventparams = [
            'userid' => $user1->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $appointment->id,
            'uuid' => $session1->id,
        ];
        $this->assertFalse($DB->record_exists('event', $user1eventparams));

        $user2eventparams = [
            'userid' => $user2->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $appointment->id,
            'uuid' => $session2->id,
        ];
        $this->assertFalse($DB->record_exists('event', $user2eventparams));

        $session1eventparams = [
            'courseid' => SITEID,
            'eventtype' => 'appointmentsession',
            'instance' => $appointment->id,
            'uuid' => $session1->id,
        ];
        $this->assertTrue($DB->record_exists('event', $session1eventparams));

        $session2eventparams = [
            'courseid' => SITEID,
            'eventtype' => 'appointmentsession',
            'instance' => $appointment->id,
            'uuid' => $session2->id,
        ];
        $this->assertTrue($DB->record_exists('event', $session2eventparams));

        // Delete appointment.
        appointment_delete_instance($appointment->id);

        // Validate records.
        $this->assertFalse($DB->record_exists('appointment', ['id' => $appointment->id]));
        $this->assertCount(0, $DB->get_records('appointment_sessions', ['appointment' => $appointment->id]));
        $this->assertCount(0, $DB->get_records('appointment_signups', ['sessionid' => $session1->id]));

        // Check that calendar events for session 1 were deleted.
        $this->assertFalse($DB->record_exists('event', $session1eventparams));

        // Check that calendar events for session 2 were deleted.
        $this->assertFalse($DB->record_exists('event', $session2eventparams));
    }

    /**
     * Test appointment_candidate_selector::find_users and appointment_existing_selector::find_users.
     *
     * @covers \appointment_candidate_selector::find_users
     * @covers \appointment_existing_selector::find_users
     */
    public function test_appointment_selector_find_users() {
        $student2 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $cm = get_coursemodule_from_instance('appointment', $this->appointment->id, $this->course->id);
        $context = \context_module::instance($cm->id);

        // Add sessions.
        $date1 = new stdClass();
        $date1->timestart = strtotime('+1 day');
        $date1->timefinish = $date1->timestart + HOURSECS;
        $date2 = new stdClass();
        $date2->timestart = strtotime('+3 day');
        $date2->timefinish = $date2->timestart + HOURSECS;

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
        appointment_user_signup($session1, $this->appointment, $this->course,
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
        $submissionid = appointment_user_signup($session0, $this->appointment, $this->course,
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
        appointment_user_signup($session0, $this->appointment, $this->course,
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
        appointment_update_session($session0, [$date]);

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
        appointment_update_session($session0, [$date]);

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
        appointment_update_session($session0, [$date]);

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
        appointment_update_session($session0, [$date]);

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
     * Session custom fields update.
     *
     * @covers ::appointment_update_session
     */
    public function test_session_update_custom_fields() {
        $this->setAdminUser();

        $cfgenerator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $customfieldhandler = customfield\appointment_handler::create();

        // Create appointment custom fields available in report_appointments datasource.
        $params = [
            'component' => 'mod_appointment',
            'area' => 'appointment',
            'itemid' => 0,
            'name' => 'Appointment custom field',
            'contextid' => \context_system::instance()->id
        ];
        $category = $cfgenerator->create_category($params);
        $categoryid = $category->get('id');

        $cfgenerator->create_field(['shortname' => 'textareafield', 'name' => 'Name1',
            'type' => 'textarea', 'categoryid' => $categoryid]);
        $cfgenerator->create_field(['shortname' => 'textfield', 'name' => 'Name2',
            'type' => 'text', 'categoryid' => $categoryid]);
        $cfgenerator->create_field(['shortname' => 'datefield', 'name' => 'Name3',
            'type' => 'date', 'categoryid' => $categoryid]);
        $cfgenerator->create_field(['shortname' => 'checkboxfield', 'name' => 'Name4',
            'type' => 'checkbox', 'categoryid' => $categoryid]);
        $cfgenerator->create_field(['shortname' => 'selectfield', 'name' => 'Name5',
            'configdata' => ['options' => "a\nb\nc"], 'type' => 'select', 'categoryid' => $categoryid]);

        // Create ongoing session. Customfileds are already hardcoded in email template.
        $date = new stdClass();
        $date->timestart = strtotime('+1 hour');
        $date->timefinish = strtotime('+2 hour');
        $sessionsettings = [
            'appointment' => $this->appointment->id,
            'customfield_textareafield_editor' => ['text' => 'Test textarea', 'format' => FORMAT_HTML],
            'customfield_textfield' => 'Test text field',
            'customfield_datefield' => strtotime('1 January 2020 00:00'),
            'customfield_checkboxfield' => 1,
            'customfield_selectfield' => 1,
        ];

        // Create session0.
        $session = $this->get_generator()->create_session($sessionsettings, [], [$date]);

        // Custom field tests are executed individually because when textarea was updated on its own it did not update,
        // but when it was updated along with another field it was updated correctly.

        // Validate custom fields values.
        $fieldinstancedata = $customfieldhandler->export_instance_data_object($session->id, true);
        $this->assertEquals('Test textarea', $fieldinstancedata->textareafield);
        $this->assertEquals('Test text field', $fieldinstancedata->textfield);
        $this->assertEquals('Wednesday, 1 January 2020, 12:00 AM', $fieldinstancedata->datefield);
        $this->assertEquals('Yes', $fieldinstancedata->checkboxfield);
        $this->assertEquals('a', $fieldinstancedata->selectfield);

        // Edit textarea field and make sure nothing else is updated.
        $fieldvalue = ['text' => 'Test textarea 2', 'format' => FORMAT_HTML];
        $session->customfield_textareafield_editor = $fieldvalue;
        appointment_update_session($session, [$date]);
        $fieldinstancedata = $customfieldhandler->export_instance_data_object($session->id, true);
        $this->assertEquals('Test textarea 2', $fieldinstancedata->textareafield);
        $this->assertEquals('Test text field', $fieldinstancedata->textfield);
        $this->assertEquals('Wednesday, 1 January 2020, 12:00 AM', $fieldinstancedata->datefield);
        $this->assertEquals('Yes', $fieldinstancedata->checkboxfield);
        $this->assertEquals('a', $fieldinstancedata->selectfield);

        // Edit text field and make sure nothing else is updated.
        $session->customfield_textfield = 'Test text field 2';
        appointment_update_session($session, [$date]);
        $fieldinstancedata = $customfieldhandler->export_instance_data_object($session->id, true);
        $this->assertEquals('Test textarea 2', $fieldinstancedata->textareafield);
        $this->assertEquals('Test text field 2', $fieldinstancedata->textfield);
        $this->assertEquals('Wednesday, 1 January 2020, 12:00 AM', $fieldinstancedata->datefield);
        $this->assertEquals('Yes', $fieldinstancedata->checkboxfield);
        $this->assertEquals('a', $fieldinstancedata->selectfield);

        // Edit date field and make sure nothing else is updated.
        $session->customfield_datefield = strtotime('2 January 2020 00:00');
        appointment_update_session($session, [$date]);
        $fieldinstancedata = $customfieldhandler->export_instance_data_object($session->id, true);
        $this->assertEquals('Test textarea 2', $fieldinstancedata->textareafield);
        $this->assertEquals('Test text field 2', $fieldinstancedata->textfield);
        $this->assertEquals('Thursday, 2 January 2020, 12:00 AM', $fieldinstancedata->datefield);
        $this->assertEquals('Yes', $fieldinstancedata->checkboxfield);
        $this->assertEquals('a', $fieldinstancedata->selectfield);

        // Edit checkbox field and make sure nothing else is updated.
        $session->customfield_checkboxfield = 0;
        appointment_update_session($session, [$date]);
        $fieldinstancedata = $customfieldhandler->export_instance_data_object($session->id, true);
        $this->assertEquals('Test textarea 2', $fieldinstancedata->textareafield);
        $this->assertEquals('Test text field 2', $fieldinstancedata->textfield);
        $this->assertEquals('Thursday, 2 January 2020, 12:00 AM', $fieldinstancedata->datefield);
        $this->assertEquals('No', $fieldinstancedata->checkboxfield);
        $this->assertEquals('a', $fieldinstancedata->selectfield);

        // Edit dropdown field and make sure nothing else is updated.
        $session->customfield_selectfield = 2;
        appointment_update_session($session, [$date]);
        $fieldinstancedata = $customfieldhandler->export_instance_data_object($session->id, true);
        $this->assertEquals('Test textarea 2', $fieldinstancedata->textareafield);
        $this->assertEquals('Test text field 2', $fieldinstancedata->textfield);
        $this->assertEquals('Thursday, 2 January 2020, 12:00 AM', $fieldinstancedata->datefield);
        $this->assertEquals('No', $fieldinstancedata->checkboxfield);
        $this->assertEquals('b', $fieldinstancedata->selectfield);
    }

    /**
     * User becomes booked when capacity permits and receive notification.
     *
     * @covers ::appointment_user_signup
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
        appointment_user_signup($session0, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $student->id);
        appointment_user_signup($session0, $this->appointment, $this->course,
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
        appointment_update_session($session0, [$date]);

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
     *
     * @covers ::appointment_is_user_on_waitlist
     * @covers ::appointment_was_user_on_waitlist
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
        appointment_user_signup($session0, $this->appointment, $this->course,
            MOD_APPOINTMENT_STATUS_BOOKED, $student0->id);
        appointment_user_signup($session0, $this->appointment, $this->course,
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
        appointment_user_signup($session0, $this->appointment, $this->course,
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
     *
     * @covers ::mod_appointment_get_completion_active_rule_descriptions
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

    /**
     * Take attendance
     *
     * @covers ::appointment_take_attendance
     * @covers ::user_has_booked_sessions()
     */
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
        appointment_user_signup($session, $appointment, $course, MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);
        appointment_user_signup($session, $appointment, $course, MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);
        appointment_user_signup($session, $appointment, $course, MOD_APPOINTMENT_STATUS_BOOKED, $user3->id);

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

    /**
     * Test for approval functionality
     *
     * This functionality is disabled from the UI, however we did not remove the code yet and we
     * need to make sure it still works. Test can be removed later when functionality is modified to
     * use organisation structure and the new appropriate tests are added.
     *
     * @covers ::appointment_get_requests
     * @covers ::appointment_approve_requests
     * @return void
     */
    public function test_approvals() {
        // Create a manager (user1) and two users reporting to them (user2, user3).
        $this->getDataGenerator()->create_custom_profile_field([
            'shortname' => MOD_APPOINTMENT_MANAGERSEMAIL_FIELD, 'name' => 'Manager email', 'datatype' => 'text']);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user(['profile_field_'.MOD_APPOINTMENT_MANAGERSEMAIL_FIELD => $user1->email]);
        $user3 = $this->getDataGenerator()->create_user(['profile_field_'.MOD_APPOINTMENT_MANAGERSEMAIL_FIELD => $user1->email]);

        // Create a course with an appointment that requires approvals.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id, 'approvalreqd' => 1]);
        $context = \context_module::instance($appointment->cmid);
        $session = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'timestart1' => strtotime('+1 day')]);

        // Enrol all three users into this course.
        $this->getDataGenerator()->enrol_user($user1->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $this->course->id, 'student');

        // Sign up as user2, make sure the current status is 'requested'.
        $this->setUser($user2);
        attendee::signup_self($session, $context);

        $signups = appointment_get_user_submissions($appointment->id, $user2->id);
        $signup = reset($signups);
        $this->assertEquals(MOD_APPOINTMENT_STATUS_REQUESTED, $signup->statuscode);

        // Sign up as user3.
        $this->setUser($user3);
        attendee::signup_self($session, $context);

        // Test get requests.
        $requests = appointment_get_requests($session->id);
        $this->assertEquals(2, count($requests));
        $this->assertEquals($user2->id, $requests[$user2->id]->id);
        $this->assertEquals($user3->id, $requests[$user3->id]->id);

        // As user1 decline user2's request and approve user1's.
        $this->setUser($user1);
        appointment_approve_requests((object)[
            's' => $session->id,
            'requests' => [
                $user2->id => 1, // Decline.
                $user3->id => 2, // Approve.
            ],
        ]);

        // Test event triggering.
        $event = \mod_appointment\event\approve_requests::create(['objectid' => $session->id, 'context' => $context]);
        $event->trigger();
        $this->assertNotEmpty($event->get_name());
        $this->assertNotEmpty($event->get_description());
        $this->assertNotEmpty($event->get_url());

        // Test declines.
        $declines = appointment_get_declines($session->id);
        $this->assertEquals(1, count($declines));
        $this->assertEquals($user2->id, $declines[$user2->id]->id);

        // Make sure users user2 and user3 have correct statuses now.
        $signups = appointment_get_user_submissions($appointment->id, $user2->id, true);
        $signup = reset($signups);
        $this->assertEquals(MOD_APPOINTMENT_STATUS_DECLINED, $signup->statuscode);

        $signups = appointment_get_user_submissions($appointment->id, $user3->id);
        $signup = reset($signups);
        $this->assertEquals(MOD_APPOINTMENT_STATUS_BOOKED, $signup->statuscode);

        // Prevent the following approval-related strings from accidental removals.
        $usefulstrings = [
            'approvalreqd',
            'approvalreqd_help',
            'approve',
            'cancellationinstrmngr',
            'cancellationinstrmngr_help',
            'cannotapproveatcapacity',
            'confirmationinstrmngr',
            'confirmationinstrmngr_help',
            'decline',
            'decidelater',
            'emailmanager',
            'emailmanagercancellation',
            'emailmanagercancellation_help',
            'emailmanagerconfirmation',
            'emailmanagerconfirmation_help',
            'emailmanagerreminder',
            'emailmanagerreminder_help',
            'error:manageremailaddressmissing',
            'noactionableunapprovedrequests',
            'requestmessage',
            'requestmessage_help',
            'requeststablesummary',
            'setting:defaultrequestmessagedefault',
            'setting:defaultrequestsubjectdefault',
            'unapprovedrequests',
            'sessionrequiresmanagerapproval',
        ];
        foreach ($usefulstrings as $key) {
            $this->assertNotEmpty(get_string($key, 'mod_appointment'));
        }
    }
}

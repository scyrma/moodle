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
 * Class lib_test
 *
 * @package     mod_appointment
 * @category    test
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class generator_test
 *
 * @package     mod_appointment
 * @group       mod_appointment
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_appointment_lib_testcase extends advanced_testcase {

    /** @var stdClass $course */
    protected $course;

    /** @var stdClass $appointment */
    protected $appointment;

    /** @var stdClass $student */
    protected $student;

    /**
     * Set up
     */
    public function setUp() {
        $this->resetAfterTest();

        // Create our test course/student and appointment instance.
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($this->student);

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
        appointment_user_signup($session, $this->appointment, $this->course, MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $studentother->id);

        // Test full session.
        $this->assertFalse(appointment_session_has_capacity($session, $context));

        // Become teacher (has overbook capability).
        $this->setUser($teacher);

        // Test full session.
        $this->assertTrue(appointment_session_has_capacity($session, $context));
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
        appointment_user_signup($session, $this->appointment, $this->course, MOD_APPOINTMENT_BOTH,
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
        appointment_user_signup($session, $this->appointment, $this->course, MOD_APPOINTMENT_BOTH,
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
}
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
        appointment_user_signup($session0, $this->appointment, $this->course, MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $this->student->id);

        // Create another new sessions with user sign-up.
        $student1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $timestarts1 = strtotime('+2 days');
        $timefinishs1 = $timestarts1 + HOURSECS;
        $session1 = $this->get_generator()->create_session(['appointment' => $this->appointment->id], [],
            [(object)['timestart' => $timestarts1, 'timefinish' => $timefinishs1]]);
        appointment_user_signup($session1, $this->appointment, $this->course, MOD_APPOINTMENT_BOTH,
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
     * User signup notification.
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
        // Need to be a teacher to save session.
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);
        $session0 = $this->get_generator()->create_session($sessionsettings, [], [$date]);

        // Prepare for tests.
        $this->setUser($student);
        $sink = $this->redirectEmails();

        // Signup session0.
        $submissionid = appointment_user_signup($session0, $this->appointment, $this->course,
            MOD_APPOINTMENT_TEXT, MOD_APPOINTMENT_STATUS_BOOKED);

        // Validate.
        $signups = $DB->get_records('appointment_signups', ['sessionid' => $session0->id, 'userid' => $student->id]);
        $this->assertCount(1, $signups);

        // Capture the message.
        $messages = $sink->get_messages();
        $sink->clear();

        // Validate messages.
        $this->assertCount(1, $messages);
        $body = quoted_printable_decode($messages[0]->body);
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
}

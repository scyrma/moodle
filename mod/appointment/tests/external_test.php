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
 * Class mod_appointment_external_testcase
 *
 * @package     mod_appointment
 * @category    test
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment;

use externallib_advanced_testcase;
use mod_appointment_generator;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Class mod_appointment_external_testcase
 *
 * @package     mod_appointment
 * @group       mod_appointment
 * @covers      \mod_appointment\external
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_test extends externallib_advanced_testcase {

    /**
     * Test set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get generator.
     *
     * @return \mod_appointment_generator
     */
    protected function get_generator(): mod_appointment_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Delete session.
     */
    public function test_delete_session() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $this->assertFalse($DB->record_exists('appointment_sessions', ['appointment' => $appointment->id]));

        // Create session.
        $session0 = $this->get_generator()->create_session(['appointment' => $appointment->id]);
        $this->assertCount(1, $DB->get_records('appointment_sessions', ['appointment' => $appointment->id]));

        // Create ongoing session with user.
        $date = new stdClass();
        $date->timestart = strtotime('-1 hour');
        $date->timefinish = strtotime('+1 hour');
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);
        appointment_user_signup($session1, $appointment, $course, MOD_APPOINTMENT_STATUS_BOOKED, $student->id);

        $this->assertCount(2, $DB->get_records('appointment_sessions', ['appointment' => $appointment->id]));
        $signups = $DB->get_records('appointment_signups', ['sessionid' => $session1->id]);
        $this->assertCount(1, $signups);
        $signup1 = reset($signups);
        $this->assertCount(1, $DB->get_records('appointment_signups_status', ['signupid' => $signup1->id]));

        // Prepare for tests.
        $this->setUser($teacher);
        $sink = $this->redirectEvents();

        // Delete session1.
        $result = \mod_appointment\external::delete_session($session1->id);
        $result = \external_api::clean_returnvalue(\mod_appointment\external::delete_session_returns(), $result);
        $this->assertTrue($result);

        // Capture the event.
        $events = $sink->get_events();
        $sink->clear();

        // Validate event.
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\mod_appointment\event\delete_session', $events[0]);

        // Ensure records were deleted from appointment_sessions and appointment_signups.
        $this->assertCount(1, $DB->get_records('appointment_sessions', ['appointment' => $appointment->id]));
        $this->assertCount(0, $DB->get_records('appointment_signups_status', ['signupid' => $signup1->id]));
        $this->assertCount(0, $DB->get_records('appointment_signups', ['sessionid' => $session1->id]));
    }

    /**
     * User signup.
     */
    public function test_user_signup() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $this->assertFalse($DB->record_exists('appointment_sessions', ['appointment' => $appointment->id]));

        // Create ongoing session with user.
        $date = new stdClass();
        $date->timestart = strtotime('+1 hour');
        $date->timefinish = strtotime('+2 hour');
        $session0 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);

        // Prepare for tests.
        $this->setUser($student);
        $sink = $this->redirectEvents();

        // Signup session0.
        $result = \mod_appointment\external::user_signup($session0->id);
        $result = \external_api::clean_returnvalue(\mod_appointment\external::user_signup_returns(), $result);
        $this->assertTrue($result);

        // Validate.
        $signups = $DB->get_records('appointment_signups', array('sessionid' => $session0->id, 'userid' => $student->id));
        $this->assertCount(1, $signups);

        // Capture the event.
        $events = $sink->get_events();
        $sink->clear();

        // Validate event.
        $this->assertCount(1, $events);
        $this->assertInstanceOf('mod_appointment\event\signup_success', $events[0]);
    }

    /**
     * Cancel session booking.
     */
    public function test_user_cancel() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $this->assertFalse($DB->record_exists('appointment_sessions', ['appointment' => $appointment->id]));

        // Create ongoing session with user.
        $date = new stdClass();
        $date->timestart = strtotime('+1 hour');
        $date->timefinish = strtotime('+2 hour');
        $session0 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);
        appointment_user_signup($session0, $appointment, $course, MOD_APPOINTMENT_STATUS_BOOKED, $student->id);

        // Validate.
        $signups = $DB->get_records('appointment_signups', array('sessionid' => $session0->id, 'userid' => $student->id));
        $this->assertCount(1, $signups);

        // Prepare for tests.
        $this->setUser($student);
        $sink = $this->redirectEvents();

        // Cancel booking session0.
        $result = \mod_appointment\external::user_cancel($session0->id, '');
        $result = \external_api::clean_returnvalue(\mod_appointment\external::user_cancel_returns(), $result);
        $this->assertTrue($result);

        // Capture the event.
        $events = $sink->get_events();
        $sink->clear();

        // Validate event.
        $this->assertCount(1, $events);
        $this->assertInstanceOf('mod_appointment\event\cancel_booking', $events[0]);
    }
}

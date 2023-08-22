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

namespace mod_appointment;

use advanced_testcase;
use mod_appointment_generator;

/**
 * Class observer_test
 *
 * @package     mod_appointment
 * @covers      \mod_appointment_observer
 * @author      2022 Marina Glancy
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer_test extends advanced_testcase {

    /**
     * Get generator.
     *
     * @return mod_appointment_generator
     */
    protected function get_generator(): mod_appointment_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Helper method to create a course and a module.
     *
     * @param string $modulename
     * @param array $moduledata
     * @return array
     */
    protected function create_course_and_module(string $modulename, array $moduledata = []) {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['startdate' => strtotime('1 Jan 2017 00:00 GMT')]);
        $module = $this->getDataGenerator()->create_module($modulename, $moduledata + ['course' => $course->id]);
        return [$course, $module];
    }

    /**
     * Test unenrolment observer
     */
    public function test_observer() {
        global $DB;
        $this->resetAfterTest();

        // Create a course and an appointment with a session.
        list($course, $appointment) = $this->create_course_and_module('appointment');
        $session = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'timestart1' => strtotime('+1 day')]);

        // Create a user, enrol them and sign up for the appointment.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->get_generator()->create_signup(['sessionid' => $session->id, 'userid' => $user->id]);

        // Now user has records in the signup table signup status table.
        $signup = $DB->get_record('appointment_signups', array('sessionid' => $session->id, 'userid' => $user->id));
        $signupstatus = $DB->get_record('appointment_signups_status', ['signupid' => $signup->id]);
        $this->assertNotEmpty($signup);
        $this->assertNotEmpty($signupstatus);

        // Unenrol user from the course.
        $enrol = enrol_get_plugin('manual');
        $manualenrol = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'manual'));
        $enrol->unenrol_user($manualenrol, $user->id);

        // User no longer has records in the sign up tables and status table.
        $signup2 = $DB->get_record('appointment_signups', array('sessionid' => $session->id, 'userid' => $user->id));
        $signupstatus2 = $DB->get_record('appointment_signups_status', ['signupid' => $signup->id]);
        $this->assertEmpty($signup2);
        $this->assertEmpty($signupstatus2);
    }
}

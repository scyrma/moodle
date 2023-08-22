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

namespace mod_appointment\output;

use advanced_testcase;
use mod_appointment_generator;

/**
 * Class mobile_test
 *
 * @package     mod_appointment
 * @covers      \mod_appointment\output\mobile
 * @author      2022 Marina Glancy
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile_test extends advanced_testcase {

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
     * Test mobile_sessions_view
     */
    public function test_mobile_sessions_view() {
        global $DB, $PAGE;
        $this->resetAfterTest();

        // Create a course and an appointment with a session.
        list($course, $appointment) = $this->create_course_and_module('appointment');
        $session = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'timestart1' => strtotime('+1 day')]);

        // Create a user, enrol them and sign up for the appointment.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->get_generator()->create_signup(['sessionid' => $session->id, 'userid' => $user->id]);

        $this->setUser($user);
        $PAGE->reset_theme_and_output();
        $res = mobile::mobile_sessions_view(['cmid' => $appointment->cmid, 'courseid' => $course->id]);
        $this->assertNotEmpty($res['templates']);
    }

    /**
     * Test mobile_session_details
     */
    public function test_mobile_session_details() {
        global $DB, $PAGE;
        $this->resetAfterTest();

        // Create a course and an appointment with a session.
        list($course, $appointment) = $this->create_course_and_module('appointment');
        $session = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'timestart1' => strtotime('+1 day')]);

        // Create a user, enrol them and sign up for the appointment.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->get_generator()->create_signup(['sessionid' => $session->id, 'userid' => $user->id]);

        $this->setUser($user);
        $PAGE->reset_theme_and_output();
        $res = mobile::mobile_session_details(['sessionid' => $session->id]);
        $this->assertNotEmpty($res['templates']);
    }

    /**
     * Test mobile_session_book
     */
    public function test_mobile_session_book() {
        global $DB, $PAGE;
        $this->resetAfterTest();

        // Create a course and an appointment with a session.
        list($course, $appointment) = $this->create_course_and_module('appointment');
        $session = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'timestart1' => strtotime('+1 day')]);

        // Create a user, enrol them and sign up for the appointment.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setUser($user);
        $PAGE->reset_theme_and_output();
        $res = mobile::mobile_session_book(['sessionid' => $session->id]);
        $this->assertNotEmpty($res['templates']);
    }

    /**
     * Test mobile_session_cancel
     */
    public function test_mobile_session_cancel() {
        global $DB, $PAGE;
        $this->resetAfterTest();

        // Create a course and an appointment with a session.
        list($course, $appointment) = $this->create_course_and_module('appointment');
        $session = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'timestart1' => strtotime('+1 day')]);

        // Create a user, enrol them and sign up for the appointment.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->get_generator()->create_signup(['sessionid' => $session->id, 'userid' => $user->id]);

        $this->setUser($user);
        $PAGE->reset_theme_and_output();
        $res = mobile::mobile_session_cancel(['sessionid' => $session->id]);
        $this->assertNotEmpty($res['templates']);
    }
}

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
 * Class permission_test
 *
 * @package     mod_appointment
 * @category    test
 * @covers      \mod_appointment\permission
 * @author      2022 Marina Glancy
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission_test extends advanced_testcase {

    /**
     * Get generator.
     *
     * @return mod_appointment_generator
     */
    protected function get_generator(): mod_appointment_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Create sessions and signups for the tests
     *
     * @return array
     */
    protected function create_sessions_and_signups() {
        $course = $this->getDataGenerator()->create_course();
        $users = [];
        $users['user1'] = $this->getDataGenerator()->create_user(['username' => 'user1']);
        // Create and enrol 9 students.
        for ($i = 1; $i < 10; $i++) {
            $users["student$i"] = $this->getDataGenerator()->create_and_enrol($course, 'student', ['username' => "student$i"]);
        }
        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $sessions = [];

        // Session 1 without dates.
        $sessions[1] = $this->get_generator()->create_session(['appointment' => $appointment->id, 'capacity' => 10]);

        // Session 2 in the future.
        $sessions[2] = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'capacity' => 10, 'timestart1' => strtotime('2040-01-01')]);

        // Session 3 in the future and has no capacity.
        $sessions[3] = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'capacity' => 2, 'timestart1' => strtotime('2040-01-01'),
                'allowwaitlist' => false, 'allowcancellations' => true]);
        $this->get_generator()->create_signup(['sessionid' => $sessions[3]->id, 'userid' => $users['student1']->id]);
        $this->get_generator()->create_signup(['sessionid' => $sessions[3]->id, 'userid' => $users['student2']->id]);

        // Session 4 is current.
        $sessions[4] = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'capacity' => 10,
                'timestart1' => strtotime('2000-01-01'), 'timefinish1' => strtotime('2040-01-01')]);
        $this->get_generator()->create_signup(['sessionid' => $sessions[4]->id, 'userid' => $users['student8']->id]);

        // Session 5 is past.
        $sessions[5] = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'capacity' => 10,
                'timestart1' => strtotime('2000-01-01'), 'timefinish1' => strtotime('2001-01-01')]);

        // Session 6 in the future, has no capacity but has waitlist.
        $sessions[6] = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'capacity' => 2, 'timestart1' => strtotime('2040-01-01'),
                'allowwaitlist' => true, 'allowcancellations' => true]);
        $this->get_generator()->create_signup(['sessionid' => $sessions[6]->id, 'userid' => $users['student3']->id]);
        $this->get_generator()->create_signup(['sessionid' => $sessions[6]->id, 'userid' => $users['student4']->id]);
        $this->get_generator()->create_signup(
            ['sessionid' => $sessions[6]->id, 'userid' => $users['student5']->id, 'status' => 'waitlisted']);

        // Session 7 in the future, does not allow cancellations.
        $sessions[7] = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'capacity' => 2, 'timestart1' => strtotime('2040-01-01'),
                'allowwaitlist' => true, 'allowcancellations' => false]);
        $this->get_generator()->create_signup(['sessionid' => $sessions[7]->id, 'userid' => $users['student6']->id]);

        return [$users, $appointment, $sessions];
    }

    /**
     * Provider for test_require_can_signup
     *
     * @return array[]
     */
    public function require_can_signup_provider() {
        return [
            'User without access to the course can not sign up' => [
                'username' => 'user1', 'session' => 1, 'exception' => '/Sorry, but you do not currently have permissions/'
            ],
            'Student can sign up for session without dates' => [
                'username' => 'student9', 'session' => 1, 'exception' => null
            ],
            'Student can sign up for future session' => [
                'username' => 'student9', 'session' => 2, 'exception' => null
            ],
            'Student can not sign up for current session' => [
                'username' => 'student9', 'session' => 4, 'exception' => '/You cannot sign up, this session is in progress/'
            ],
            'Student can not sign up for past session' => [
                'username' => 'student9', 'session' => 5, 'exception' => '/You cannot sign up, this session is over/'
            ],
            'Student can not sign up for session without capacity' => [
                'username' => 'student9', 'session' => 3, 'exception' => '/This session is now full/'
            ],
            'Student can not sign up for session if they are already signed up' => [
                'username' => 'student2', 'session' => 2, 'exception' => '/You have already signed-up/'
            ],
            'Student can not sign up for session if they are wait-listed' => [
                'username' => 'student5', 'session' => 2, 'exception' => '/You have already signed-up/'
            ],
        ];
    }

    /**
     * Test method permission::require_can_signup
     *
     * @dataProvider require_can_signup_provider
     * @param string $username
     * @param int $session
     * @param string $exception
     */
    public function test_require_can_signup($username, $session, $exception = null) {
        $this->resetAfterTest();
        [$users, $appointment, $sessions] = $this->create_sessions_and_signups();
        $context = \context_module::instance($appointment->cmid);

        // Enrolled student can sign up.
        $this->setUser($users[$username]);
        $this->assertEquals(!$exception, permission::can_signup($sessions[$session], $context));
        if ($exception) {
            $this->expectExceptionMessageMatches($exception);
        }
        permission::require_can_signup($sessions[$session], $context);
    }

    /**
     * Provider for test_require_can_cancel_signup
     *
     * @return array[]
     */
    public function require_can_cancel_signup_provider() {
        return [
            'User without access to the course can not cancel' => [
                'username' => 'user1', 'session' => 1, 'exception' => '/Sorry, but you do not currently have permissions/'
            ],
            'User who is not signed up can not cancel' => [
                'username' => 'student9', 'session' => 1, 'exception' => '/You are not signed up for this session/'
            ],
            'User who is signed up can not cancel on another session' => [
                'username' => 'student1', 'session' => 4, 'exception' => '/You are not signed up for this session/'
            ],
            'User can cancel their sign up' => [
                'username' => 'student1', 'session' => 3, 'exception' => null
            ],
            'User can cancel their wait-list' => [
                'username' => 'student5', 'session' => 6, 'exception' => null
            ],
            'User can not cancel if cancellations are not allowed' => [
                'username' => 'student6', 'session' => 7, 'exception' => '/You are not allowed to cancel this sign-up/'
            ],
            'User can not cancel after the session started' => [
                'username' => 'student8', 'session' => 4, 'exception' => '/You cannot cancel an event that has already occurred/'
            ],
        ];
    }

    /**
     * Test method permission::require_can_cancel_signup
     *
     * @dataProvider require_can_cancel_signup_provider
     * @param string $username
     * @param int $session
     * @param string $exception
     */
    public function test_require_can_cancel_signup($username, $session, $exception = null) {
        $this->resetAfterTest();
        [$users, $appointment, $sessions] = $this->create_sessions_and_signups();
        $context = \context_module::instance($appointment->cmid);

        // Enrolled student can sign up.
        $this->setUser($users[$username]);
        $this->assertEquals(!$exception, permission::can_cancel_signup($sessions[$session], $context));
        if ($exception) {
            $this->expectExceptionMessageMatches($exception);
        }
        permission::require_can_cancel_signup($sessions[$session], $context);
    }

    /**
     * Provider for test_require_various
     *
     * @return array
     */
    public function require_various_provider() {
        return [
            'require_can_add_instance' => ['user1', 'require_can_add_instance', '/Add instance/', true],
            'require_can_view_attendees' => ['user1', 'require_can_view_attendees', '/to view attendees/'],
            'require_can_view_appointment' => ['user1', 'require_can_view_appointment', '/to view this appointment/'],
            'require_can_edit_sessions' => ['user1', 'require_can_edit_sessions', '/to edit sessions/'],
        ];
    }

    /**
     * Test for custom error messages in various require methods
     *
     * @param string $username
     * @param string $function
     * @param string $exception
     * @param bool $usecoursecontext
     * @return void
     * @dataProvider require_various_provider
     */
    public function test_require_various($username, $function, $exception, bool $usecoursecontext = false) {
        $this->resetAfterTest();
        [$users, $appointment, $sessions] = $this->create_sessions_and_signups();
        $context = \context_module::instance($appointment->cmid);
        if ($usecoursecontext) {
            $context = $context->get_parent_context();
        }
        $this->setUser($users['user1']);

        $this->setUser($users[$username]);
        if ($exception) {
            $this->expectExceptionMessageMatches($exception);
        }
        component_class_callback(permission::class, $function, [$context]);
    }
}

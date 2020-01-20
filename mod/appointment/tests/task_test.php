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
 * Task test.
 *
 * @package     mod_appointment
 * @category    test
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Task test.
 *
 * @package     mod_appointment
 * @group       mod_appointment
 * @covers      \mod_appointment\task\cron_task
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_appointment_task_testcase extends advanced_testcase {

    /**
     * Get generator.
     *
     * @return \mod_appointment_generator
     */
    protected function get_generator(): mod_appointment_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Test get_name.
     */
    public function test_get_name() {
        $task = new \mod_appointment\task\cron_task();
        $this->assertNotEmpty($task->get_name());
    }

    /**
     * Test the cron task.
     */
    public function test_cron_task() {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // Add appointment to course.
        $appointment1 = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);

        // Add sessions with no date.
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment1->id]);

        // User is signed up for session1.
        appointment_user_signup($session1, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_WAITLISTED, $user->id);

        // Run job.
        $task = new \mod_appointment\task\cron_task();
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Test result.
        $this->assertContains("No reminders need to be sent", $output);

        // Add sessions with a date.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + 3600;
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment1->id], [], [$date]);

        // User is signed up for session2.
        appointment_user_signup($session2, $appointment1, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $user->id);

        // Run job.
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Test result.
        $this->assertContains("Sent reminder email to user", $output);

        // TODO: We can't use messages sink here to test actual content,
        // as plugin is using email_to_user() function explicitly (WP-1099).
    }
}

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
 * Task test.
 *
 * @package     mod_appointment
 * @category    test
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment;

use advanced_testcase;
use mod_appointment_generator;
use stdClass;

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
class task_test extends advanced_testcase {
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
        appointment_user_signup($session1, $appointment1, $course,
            MOD_APPOINTMENT_STATUS_WAITLISTED, $user->id);

        // Run job.
        $task = new \mod_appointment\task\cron_task();
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Test result.
        $this->assertStringContainsString("No reminders need to be sent", $output);

        // Add sessions with a date.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + 3600;
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment1->id], [], [$date]);

        // User is signed up for session2.
        appointment_user_signup($session2, $appointment1, $course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user->id);

        // Run job.
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Test result.
        $this->assertEmpty($output);

        // TODO: We can't use messages sink here to test actual content,
        // as plugin is using email_to_user() function explicitly (WP-1099).
    }

    public function test_update_calendar_entries_task() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        $appointment = $this->getDataGenerator()->create_module('appointment',
            ['course' => $course->id, 'usercalentry' => true, 'showoncalendar' => MOD_APPOINTMENT_CAL_COURSE]);

        // Create a new session, add our test user to it.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);
        $date->timestart = strtotime('+2 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);

        // Users are signed up for appointments.
        appointment_user_signup($session1, $appointment, $course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);
        appointment_user_signup($session2, $appointment, $course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user2->id);

        // Sanity check, our test students should now be an attendee with a calendar event for the session.
        $attendees = appointment_get_attendees($session1->id);
        $this->assertCount(1, $attendees);
        $this->assertEquals($user1->id, reset($attendees)->id);
        $user1eventparams = [
            'userid' => $user1->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $appointment->id,
            'uuid' => $session1->id,
        ];
        $user1event = $DB->get_records('event', $user1eventparams);
        $this->assertCount(1, $user1event);
        $user1event = reset($user1event);

        $attendees = appointment_get_attendees($session2->id);
        $this->assertCount(1, $attendees);
        $this->assertEquals($user2->id, reset($attendees)->id);
        $user2eventparams = [
            'userid' => $user2->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $appointment->id,
            'uuid' => $session2->id,
        ];
        $user2event = $DB->get_records('event', $user2eventparams);
        $this->assertCount(1, $user2event);
        $user2event = reset($user2event);

        // Sanity check, sessions should have course calendar events too.
        $session1eventparams = [
            'courseid' => $course->id,
            'eventtype' => 'appointmentsession',
            'instance' => $appointment->id,
            'uuid' => $session1->id,
        ];
        $session1event = $DB->get_records('event', $session1eventparams);
        $this->assertCount(1, $session1event);
        $session1event = reset($session1event);

        $session2eventparams = [
            'courseid' => $course->id,
            'eventtype' => 'appointmentsession',
            'instance' => $appointment->id,
            'uuid' => $session2->id,
        ];
        $session2event = $DB->get_records('event', $session2eventparams);
        $this->assertCount(1, $session2event);
        $session2event = reset($session2event);

        // Save description values of user1 and session1 events, then clear them in db.
        $user1desc = $user1event->description;
        $user1event->description = '';
        $DB->update_record('event', $user1event);

        $session1desc = $session1event->description;
        $session1event->description = '';
        $DB->update_record('event', $session1event);

        // Remove user2 and session2 events completely.
        $DB->delete_records('event', ['id' => $user2event->id]);
        $DB->delete_records('event', ['id' => $session2event->id]);

        // Second round of sanity check. Empty description for user1 and session1.
        $this->assertEmpty($DB->get_field('event', 'description', $user1eventparams));
        $this->assertEmpty($DB->get_field('event', 'description', $session1eventparams));
        // No records for user2 and session2 events.
        $this->assertCount(0, $DB->get_records('event', $user2eventparams));
        $this->assertCount(0, $DB->get_records('event', $session2eventparams));

        // Run the task.
        (new \mod_appointment\task\update_calendar_entries())->execute();

        // Description is present and matching original one.
        $user1event = $DB->get_records('event', $user1eventparams);
        $this->assertCount(1, $user1event);
        $user1event = reset($user1event);
        $this->assertSame(html_to_text($user1desc), html_to_text($user1event->description));

        $session1event = $DB->get_records('event', $session1eventparams);
        $this->assertCount(1, $session1event);
        $session1event = reset($session1event);
        $this->assertSame(html_to_text($session1desc), html_to_text($session1event->description));

        // Missing events have been recovered.
        $this->assertCount(1, $DB->get_records('event', $user2eventparams));
        $this->assertCount(1, $DB->get_records('event', $session2eventparams));
    }

    /**
     * Tests for update_calendar_entries_task when events were deleted.
     * This is effectively validating that user events are re-created when they are
     * removed as part of upgrade step followed by update_calendar_entries_task call (fix for MDL-67494).
     *
     */
    public function test_update_calendar_entries_task_deleted() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $student0 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $appointment = $this->getDataGenerator()->create_module('appointment',
            ['course' => $course->id, 'usercalentry' => true, 'showoncalendar' => MOD_APPOINTMENT_CAL_COURSE]);

        // Create new sessions and sign-up.
        $timestarts0 = strtotime('+1 day');
        $timefinishs0 = $timestarts0 + HOURSECS;
        $session0 = $this->get_generator()->create_session(['appointment' => $appointment->id], [],
            [(object)['timestart' => $timestarts0, 'timefinish' => $timefinishs0]]);
        appointment_user_signup($session0, $appointment, $course,
            MOD_APPOINTMENT_STATUS_BOOKED, $student0->id);

        // Create another new sessions with user sign-up.
        $timestarts1 = strtotime('+2 days');
        $timefinishs1 = $timestarts1 + HOURSECS;
        $session1 = $this->get_generator()->create_session(
            ['appointment' => $appointment->id, 'allowwaitlist' => true, 'capacity' => 1], [],
            [(object)['timestart' => $timestarts1, 'timefinish' => $timefinishs1]]);
        appointment_user_signup($session1, $appointment, $course,
            MOD_APPOINTMENT_STATUS_BOOKED, $student1->id);
        appointment_user_signup($session1, $appointment, $course,
            MOD_APPOINTMENT_STATUS_WAITLISTED, $student2->id);

        // Sanity check.
        $this->assertEquals(2, $DB->count_records('event', ['eventtype' => 'appointmentbooking', 'modulename' => 0]));
        $this->assertEquals(1, $DB->count_records('event', ['eventtype' => 'appointmentsession', 'modulename' => 0]));

        // Delete user events.
        [$insql, $params] = $DB->get_in_or_equal(['appointmentbooking', 'appointmentsession']);
        $whereclause = "modulename = '0' AND eventtype $insql";
        $DB->delete_records_select('event', $whereclause, $params);

        $this->assertEquals(0, $DB->count_records('event', ['eventtype' => 'appointmentbooking', 'modulename' => 0]));
        $this->assertEquals(0, $DB->count_records('event', ['eventtype' => 'appointmentsession', 'modulename' => 0]));

        // Run the task.
        (new \mod_appointment\task\update_calendar_entries())->execute();

        // Make sure the calendar event for users matches the initial due date.
        $eventparams0 = [
            'instance' => $appointment->id,
            'eventtype' => 'appointmentbooking',
            'userid' => $student0->id,
        ];
        $eventtimes0 = $DB->get_field('event', 'timestart', $eventparams0, MUST_EXIST);
        $this->assertEquals($timestarts0, $eventtimes0);

        $eventparams1 = [
            'instance' => $appointment->id,
            'eventtype' => 'appointmentbooking',
            'userid' => $student1->id,
        ];
        $eventtimes1 = $DB->get_field('event', 'timestart', $eventparams1, MUST_EXIST);
        $this->assertEquals($timestarts1, $eventtimes1);

        $eventparams2 = [
            'instance' => $appointment->id,
            'eventtype' => 'appointmentsession',
            'userid' => $student2->id,
        ];
        $eventtimes2 = $DB->get_field('event', 'timestart', $eventparams2, MUST_EXIST);
        $this->assertEquals($timestarts1, $eventtimes2);
    }

    public function test_update_custom_fields_task() {
        $this->resetAfterTest(true);
        // Appointments and custom fields were not reset correctly.
        \mod_appointment\customfield\appointment_handler::create()->delete_all();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);

        $categoryparams = [
                'component' => 'mod_appointment',
                'area' => 'appointment',
        ];
        $category = $DB->get_records('customfield_category', $categoryparams);
        // No custom fields are created during the appointment creation.
        $this->assertCount(0, $category);

        (new \mod_appointment\task\update_custom_fields())->execute();

        $categorynew = $DB->get_records('customfield_category', $categoryparams);
        // The upgrade step creates all necessary custom fields.
        $this->assertCount(1, $categorynew);
        $categorynew = reset($categorynew);

        $customfieldsparams = [
                'categoryid' => $categorynew->id,
        ];
        $customfieldsnew = $DB->get_records('customfield_field', $customfieldsparams);
        $this->assertCount(3, $customfieldsnew);
    }
}

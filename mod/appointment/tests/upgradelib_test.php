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
 * Class upgradelib_test
 *
 * @package     mod_appointment
 * @category    test
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgradelib_test extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
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
     * Test mod_appointment_upgrade_remove_orphaned_user_events upgrade script.
     *
     * @covers ::mod_appointment_upgrade_remove_orphaned_user_events
     */
    public function test_mod_appointment_upgrade_remove_orphaned_user_events() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/appointment/db/upgradelib.php');

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, 'student');

        // Create appointment.
        $appointment = $this->getDataGenerator()->create_module('appointment', [
            'course' => $course->id,
            'usercalentry' => true,
            'showoncalendar' => MOD_APPOINTMENT_CAL_SITE
        ]);

        // Create new sessions.
        $date = new \stdClass();
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
        appointment_user_signup($session2, $appointment, $course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user3->id);

        // Sanity check, our test users should now be an attendee with a calendar event for the session.
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

        $user3eventparams = [
            'userid' => $user3->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $appointment->id,
            'uuid' => $session2->id,
        ];
        $user3event = $DB->get_record('event', $user3eventparams);
        $this->assertNotEmpty($user3event);

        // Make user 3 event belong to the different appointment, so it becomes orphaned.
        $user3event->instance = 1024;
        $DB->update_record('event', $user3event);

        // Run upgrade script.
        mod_appointment_upgrade_remove_orphaned_user_events();

        // Validate that user 3 event has been deleted, all the others remained.
        $this->assertTrue($DB->record_exists('event', $session1eventparams));
        $this->assertTrue($DB->record_exists('event', $session2eventparams));
        $this->assertTrue($DB->record_exists('event', $user1eventparams));
        $this->assertTrue($DB->record_exists('event', $user2eventparams));
        $this->assertFalse($DB->get_record('event', ['id' => $user3event->id]));
    }

    /**
     * Test mod_appointment_upgrade_remove_orphaned_user_events upgrade script when
     * there are no appointments in the system.
     *
     * @covers ::mod_appointment_upgrade_remove_orphaned_user_events
     */
    public function test_mod_appointment_upgrade_remove_orphaned_user_events_no_appintments() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/appointment/db/upgradelib.php');

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');

        // Create appointment.
        $appointment = $this->getDataGenerator()->create_module('appointment', [
            'course' => $course->id,
            'usercalentry' => true,
            'showoncalendar' => MOD_APPOINTMENT_CAL_SITE
        ]);

        // Create new session.
        $date = new \stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;
        $session1 = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);

        // User is signed up for appointment.
        appointment_user_signup($session1, $appointment, $course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user1->id);

        // Make user event belong to the different appointment, so it becomes orphaned.
        $usereventparams = [
            'userid' => $user1->id,
            'eventtype' => 'appointmentbooking',
            'instance' => $appointment->id,
            'uuid' => $session1->id,
        ];
        $userevent = $DB->get_record('event', $usereventparams);
        $userevent->instance = 1024;
        $DB->update_record('event', $userevent);

        // Delete appointment.
        appointment_delete_instance($appointment->id);

        // Sanity check.
        $this->assertTrue($DB->record_exists('event', ['id' => $userevent->id]));

        // Run upgrade script.
        mod_appointment_upgrade_remove_orphaned_user_events();

        // Validate that user event has been deleted.
        $this->assertFalse($DB->record_exists('event', ['id' => $userevent->id]));
    }
}

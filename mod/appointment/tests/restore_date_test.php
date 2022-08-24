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
 * Class mod_appointment_restore_date_testcase
 *
 * @package     mod_appointment
 * @category    test
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment;

use mod_appointment_generator;
use restore_date_testcase;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Class mod_appointment_restore_date_testcase
 *
 * @package     mod_appointment
 * @group       mod_appointment
 * @covers      \backup_appointment_activity_task
 * @covers      \backup_appointment_activity_structure_step
 * @covers      \restore_appointment_activity_task
 * @covers      \restore_appointment_activity_structure_step
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_date_test extends restore_date_testcase {

    /**
     * Get generator.
     *
     * @return mod_appointment_generator
     */
    protected function get_generator(): mod_appointment_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Test restore dates.
     *
     * @uses \appointment_update_session
     * @uses \appointment_get_session
     * @uses \appointment_user_signup
     */
    public function test_restore_dates() {
        global $DB, $USER, $CFG;

        list($course, $appointment) = $this->create_course_and_module('appointment');
        $user = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Create session.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + 3600;
        $session = $this->get_generator()->create_session(['appointment' => $appointment->id], [], [$date]);

        // Trigger update, so that timemodified has become non-zero.
        $cm = get_coursemodule_from_instance('appointment', $appointment->id);
        appointment_update_session($session, [$date], \context_module::instance($cm->id));
        $session = appointment_get_session($session->id);

        // User is signed up for appointment1.
        appointment_user_signup($session, $appointment, $course,
            MOD_APPOINTMENT_STATUS_BOOKED, $user->id);
        $signup = $DB->get_record('appointment_signups', array('sessionid' => $session->id, 'userid' => $user->id));
        $signupstatus = $DB->get_record('appointment_signups_status', ['signupid' => $signup->id]);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course);
        $newappointment = $DB->get_record('appointment', ['course' => $newcourseid]);

        // Test appointment.
        $this->assertFieldsNotRolledForward($appointment, $newappointment, ['timecreated', 'timemodified']);

        // Test session.
        $newsession = $DB->get_record('appointment_sessions', ['appointment' => $newappointment->id]);
        $props = ['timecreated', 'timemodified'];
        $this->assertFieldsRolledForward($session, $newsession, $props);

        // Test signup and status.
        $newsignup = $DB->get_record('appointment_signups', array('sessionid' => $newsession->id, 'userid' => $user->id));
        $newsignupstatus = $DB->get_record('appointment_signups_status', ['signupid' => $newsignup->id]);
        $this->assertFieldsRolledForward($signupstatus, $newsignupstatus, ['timecreated']);

        // Test session dates.
        $sessiondates = $DB->get_record('appointment_sessions_dates', array('sessionid' => $session->id));
        $newsessiondates = $DB->get_record('appointment_sessions_dates', array('sessionid' => $newsession->id));
        $props = ['timestart', 'timefinish'];
        $this->assertFieldsRolledForward($sessiondates, $newsessiondates, $props);
    }
}

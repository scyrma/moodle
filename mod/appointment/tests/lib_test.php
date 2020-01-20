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

    /**
     * Set up
     */
    public function setUp() {
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
     * Test appointment_session_has_capacity.
     *
     * @covers ::appointment_session_has_capacity
     */
    public function test_appointment_session_has_capacity() {
        $course = self::getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $studentother = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($studentother->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Add appointment to course.
        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('appointment', $appointment->id, $appointment->course);
        $context = \context_module::instance($cm->id);

        // Add session.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + 3600;
        $session = $this->get_generator()->create_session(['appointment' => $appointment->id, 'capacity' => 1], [], [$date]);

        // Become student.
        $this->setUser($student);

        // Test empty session.
        $this->assertTrue(appointment_session_has_capacity($session, $context));

        // Add other user and test again.
        appointment_user_signup($session, $appointment, $course, '', MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $studentother->id);

        // Test full session.
        $this->assertFalse(appointment_session_has_capacity($session, $context));

        // Become teacher (has overbook capability).
        $this->setUser($teacher);

        // Test full session.
        $this->assertTrue(appointment_session_has_capacity($session, $context));
    }
}

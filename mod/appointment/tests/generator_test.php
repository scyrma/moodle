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
 * Class mod_appointment_generator_testcase
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

/**
 * Class mod_appointment_generator_testcase
 *
 * @package     mod_appointment
 * @group       mod_appointment
 * @covers      \mod_appointment_generator
 * @author      2019 Ruslan Kabalin
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator_test extends advanced_testcase {

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
     * Create instance
     */
    public function test_create_instance() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse($DB->record_exists('appointment', ['course' => $course->id]));

        // Create appointment.
        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $this->assertEquals(1, $DB->count_records('appointment', ['course' => $course->id]));
        $this->assertTrue($DB->record_exists('appointment', ['course' => $course->id, 'id' => $appointment->id]));

        // Validate that messaging settings are populated.
        $appointmenrecord = $DB->get_record('appointment', ['course' => $course->id, 'id' => $appointment->id]);
        $messagedefaults = \mod_appointment\form\messages::get_defaults();
        foreach ($messagedefaults as $key => $item) {
            $this->assertEquals($item, $appointmenrecord->{$key});
        }

        // Create another appointment.
        $params = ['course' => $course->id, 'name' => 'Another appointment'];
        $appointment = $this->getDataGenerator()->create_module('appointment', $params);
        $this->assertEquals(2, $DB->count_records('appointment', ['course' => $course->id]));
        $this->assertEquals('Another appointment', $DB->get_field('appointment', 'name', ['id' => $appointment->id]));
    }

    /**
     * Create session
     *
     * @uses \appointment_add_session
     * @uses \appointment_get_session
     */
    public function test_create_session() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id]);
        $this->assertFalse($DB->record_exists('appointment_sessions', ['appointment' => $appointment->id]));

        // Create session.
        $session = $this->get_generator()->create_session(['appointment' => $appointment->id]);
        $this->assertTrue($DB->record_exists('appointment_sessions', ['appointment' => $appointment->id]));
        $this->assertEquals('Session0', $DB->get_field('appointment_sessions', 'details', ['id' => $session->id]));

        // Create another session.
        $session = $this->get_generator()->create_session(['appointment' => $appointment->id]);
        $this->assertEquals('Session1', $DB->get_field('appointment_sessions', 'details', ['id' => $session->id]));
    }
}

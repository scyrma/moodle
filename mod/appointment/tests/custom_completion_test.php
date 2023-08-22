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

use mod_appointment\completion\custom_completion;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/completionlib.php");

/**
 * Custom completion class test.
 *
 * @package     mod_appointment
 * @group       mod_appointment
 * @covers      \mod_appointment\completion\custom_completion
 * @author      2021 Ruslan Kabalin
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion_test extends \advanced_testcase {

    /**
     * Get generator.
     *
     * @return \mod_appointment_generator
     */
    protected function get_generator(): \mod_appointment_generator {
        return $this->getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Data provider for test_completion_get_state().
     *
     * @return array[]
     */
    public function get_state_provider(): array {
        return [
            'Undefined rule' => [
                'somenonexistentrule', COMPLETION_DISABLED, \coding_exception::class
            ],
            'Rule not available' => [
                'completionbooked', COMPLETION_DISABLED, \moodle_exception::class
            ],
            'Rule available' => [
                'completionbooked', COMPLETION_ENABLED, null
            ],
        ];
    }

    /**
     * Ensure that completion state reflects the correct booking status.
     *
     * @dataProvider get_state_provider
     * @covers ::user_has_booked_sessions
     * @param string $rule The custom completion rule.
     * @param int $available Whether this rule is available.
     * @param string|null $exception Expected exception.
     */
    public function test_completion_get_state(string $rule, int $available, ?string $exception) {
        if (!is_null($exception)) {
            $this->expectException($exception);
        }
        $this->resetAfterTest();

        // Create course, user and apppointment activity.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course);
        $appointment = $this->getDataGenerator()->create_module('appointment', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            $rule => $available,
        ]);
        $cm = \cm_info::create(get_coursemodule_from_instance('appointment', $appointment->id));
        $customcompletion = new custom_completion($cm, (int)$student->id);

        // Create an appointment session.
        $session = $this->get_generator()->create_session(['appointment' => $appointment->id]);

        // User books a session.
        $this->setUser($student);
        appointment_user_signup($session, $appointment, $course, null, MOD_APPOINTMENT_STATUS_BOOKED);
        $status = ($available === COMPLETION_ENABLED) ? COMPLETION_COMPLETE : null;
        $this->assertEquals($status, $customcompletion->get_state($rule));

        // User cancels the booked session.
        appointment_user_cancel($session);
        $status = ($available === COMPLETION_ENABLED) ? COMPLETION_INCOMPLETE : null;
        $this->assertEquals($status, $customcompletion->get_state($rule));

        // User books a session again.
        appointment_user_signup($session, $appointment, $course, null, MOD_APPOINTMENT_STATUS_BOOKED);
        $status = ($available === COMPLETION_ENABLED) ? COMPLETION_COMPLETE : null;
        $this->assertEquals($status, $customcompletion->get_state($rule));

        // Remove the session.
        appointment_delete_session($session);
        $status = ($available === COMPLETION_ENABLED) ? COMPLETION_INCOMPLETE : null;
        $this->assertEquals($status, $customcompletion->get_state($rule));
    }
}

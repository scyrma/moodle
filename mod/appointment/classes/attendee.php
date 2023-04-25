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

use stdClass;

/**
 * Collection of methods allowing to sign up and change sign up status of attendees
 *
 * @package    mod_appointment
 * @author     2022 Marina Glancy
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendee {
    /**
     * Sign up the current user for the given session, if sign up is not possible - throw exception
     *
     * This method does not check capabilities and settings, you should call
     * {@see permission::require_can_signup()} before calling it
     *
     * @param \stdClass $session
     * @param \context_module $context
     * @return void
     */
    public static function signup_self(\stdClass $session, \context_module $context): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/appointment/lib.php');

        $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);

        // Get signup type.
        if (empty($session->sessiondates)) {
            $statuscode = MOD_APPOINTMENT_STATUS_WAITLISTED;
        } else if (appointment_get_num_attendees($session->id) < $session->capacity) {
            // Save available.
            $statuscode = MOD_APPOINTMENT_STATUS_BOOKED;
        } else {
            $statuscode = MOD_APPOINTMENT_STATUS_WAITLISTED;
        }

        appointment_user_signup($session, $appointment, get_course($appointment->course), $statuscode);
        $params = ['context' => $context, 'objectid' => $session->id];
        $event = \mod_appointment\event\signup_success::create($params);
        $event->add_record_snapshot('appointment_sessions', $session);
        $event->add_record_snapshot('appointment', $appointment);
        $event->trigger();
    }

    /**
     * Cancel appointment for oneself
     *
     * This method does not check capabilities and settings, you should call
     * {@see permission::require_can_cancel_signup()} before calling it
     *
     * @param stdClass $session
     * @param \context_module $context
     * @param string|null $cancelreason
     * @return void
     */
    public static function cancel_self(stdClass $session, \context_module $context, ?string $cancelreason = null) {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/mod/appointment/lib.php');

        $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);

        appointment_user_cancel($session, false, true, $errorstr, $cancelreason);

        // Logging and events trigger.
        $params = array(
            'context'  => $context,
            'objectid' => $session->id
        );
        $event = \mod_appointment\event\cancel_booking::create($params);
        $event->add_record_snapshot('appointment_sessions', $session);
        $event->add_record_snapshot('appointment', $appointment);
        $event->trigger();

        if (!empty($session->sessiondates)) {
            appointment_send_cancellation_notice($appointment, $session, $USER->id);
        }
    }

    /**
     * Add a user to the list of appointment attendees
     *
     * This method does not check if the user is not already signed up and the current user has
     * permissions to sign up (respecting capacity, etc). This has to be checked before calling this method
     *
     * @param stdClass $session
     * @param int $adduser
     * @param stdClass $appointment
     * @param stdClass $course
     * @param bool $suppressemail
     * @return void
     */
    public static function add_user(\stdClass $session, int $adduser, \stdClass $appointment,
                                    stdClass $course, bool $suppressemail): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/appointment/lib.php');

        // Check if we are waitlisting or booking.
        if (empty($session->sessiondates)) {
            $status = MOD_APPOINTMENT_STATUS_WAITLISTED;
        } else {
            $status = MOD_APPOINTMENT_STATUS_BOOKED;
        }
        appointment_user_signup($session, $appointment, $course, $status, $adduser, !$suppressemail);
    }

    /**
     * Remove a user from the list of appointment attendees
     *
     * This method does not check that the user is signed up, this has to be checked before calling this method
     *
     * @param stdClass $session
     * @param int $userid
     * @param stdClass $appointment
     * @param bool $suppressemail
     * @return void
     */
    public static function remove_user(stdClass $session, int $userid, stdClass $appointment, bool $suppressemail): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/appointment/lib.php');

        appointment_user_cancel($session, $userid, true, $cancelerr);

        // Notify the user of the cancellation if the session hasn't started yet.
        $timenow = time();
        if (!$suppressemail && !appointment_has_session_started($session, $timenow)) {
            appointment_send_cancellation_notice($appointment, $session, $userid);
        }
    }
}

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
 * The external API for the Appointments module.
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/appointment/lib.php');
require_once($CFG->dirroot . '/lib/externallib.php');

/**
 * The external API for the Appointments module.
 *
 * @package    mod_appointment
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends \external_api {

    /**
     * Returns the structure of parameters for get_session_details function.
     *
     * @return \external_function_parameters
     */
    protected static function get_session_details_parameters() {
        $params = ['sessionid' => new \external_value(PARAM_INT, 'The ID of the sessio to get details', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Return the details of a given session
     *
     * @param int $sessionid
     * @return string
     */
    public static function get_session_details(int $sessionid) : string {
        global $PAGE;

        $params = self::validate_parameters(self::get_session_details_parameters(), ['sessionid' => $sessionid]);

        $session = appointment_get_session($params['sessionid']);
        $cm = get_coursemodule_from_instance('appointment', $session->appointment, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        \mod_appointment\permission::require_can_view_appointment($context);

        $output = $PAGE->get_renderer('mod_appointment');

        $details = new \mod_appointment\output\session_details_modal($session, $context);

        return $output->render_from_template('mod_appointment/session_details_modal', $details->export_for_template($output));
    }

    /**
     * Describes the return function of enable_rule
     *
     * @return \external_value
     */
    public static function get_session_details_returns() {
        return new \external_value(PARAM_RAW, 'Session details in HTML.');
    }

    /**
     * Returns the structure of parameters for delete_session function.
     * @return \external_function_parameters
     */
    protected static function delete_session_parameters() {
        $params = ['sessionid' => new \external_value(PARAM_INT, 'The ID of the session to be deleted', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Delete the session.
     *
     * @param int $sessionid The ID of the session
     * @return bool
     */
    public static function delete_session($sessionid) {
        global $DB;
        $params = self::validate_parameters(self::delete_session_parameters(), ['sessionid' => $sessionid]);

        $session = appointment_get_session($params['sessionid']);
        $appointment = $DB->get_record('appointment', array('id' => $session->appointment), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('appointment', $appointment->id, $appointment->course);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        \mod_appointment\permission::require_can_edit_sessions($context);

        if (appointment_delete_session($session)) {
            // Deleting files.
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_appointment', 'session', $sessionid);

            // Logging and events trigger.
            $params = array(
                'context' => $context,
                'objectid' => $session->id
            );
            $event = \mod_appointment\event\delete_session::create($params);
            $event->add_record_snapshot('appointment_sessions', $session);
            $event->add_record_snapshot('appointment', $appointment);
            $event->trigger();
            return true;
        }
        return false;
    }

    /**
     * Describes the return function of delete_session
     *
     * @return \external_value
     */
    public static function delete_session_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully deleted.');
    }

    /**
     * Returns the structure of parameters for user_signup function.
     * @return \external_function_parameters
     */
    protected static function user_signup_parameters() {
        $params = [
            'sessionid' => new \external_value(PARAM_INT, 'The ID of the session to signup for', VALUE_REQUIRED),
            'notificationtype' => new \external_value(PARAM_INT, 'Notification type', VALUE_DEFAULT, null),
        ];
        return new \external_function_parameters($params);
    }

    /**
     * Signup for the session.
     *
     * TODO: WP-2920 Remove $notificationtype attribute.
     *
     * @param int $sessionid The ID of the session
     * @param int|null $notificationtype type of notifications to send to user (deprecated WP-2920)
     * @return bool
     */
    public static function user_signup($sessionid, $notificationtype) {
        global $DB;
        $params = self::validate_parameters(self::user_signup_parameters(), [
            'sessionid' => $sessionid,
            'notificationtype' => $notificationtype,
        ]);

        $session = appointment_get_session($params['sessionid']);
        $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $appointment->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('appointment', $appointment->id, $appointment->course);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        \mod_appointment\permission::require_can_signup($session, $context);

        // Get signup type.
        if (empty($session->sessiondates)) {
            $statuscode = MOD_APPOINTMENT_STATUS_WAITLISTED;
        } else if (appointment_get_num_attendees($session->id) < $session->capacity) {
            // Save available.
            $statuscode = MOD_APPOINTMENT_STATUS_BOOKED;
        } else {
            $statuscode = MOD_APPOINTMENT_STATUS_WAITLISTED;
        }

        $submissionid = appointment_user_signup($session, $appointment, $course, $params['notificationtype'], $statuscode);
        if ($submissionid) {
            $params = ['context' => $context, 'objectid' => $session->id];
            $event = \mod_appointment\event\signup_success::create($params);
            $event->add_record_snapshot('appointment_sessions', $session);
            $event->add_record_snapshot('appointment', $appointment);
            $event->trigger();
        } else {
            return false;
        }
        return true;
    }

    /**
     * Describes the return function of user_signup
     *
     * @return \external_value
     */
    public static function user_signup_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully signed up.');
    }

    /**
     * Returns the structure of parameters for user_cancel function.
     * @return \external_function_parameters
     */
    protected static function user_cancel_parameters() {
        $params = [
            'sessionid' => new \external_value(PARAM_INT, 'The ID of the session to cancel booking for', VALUE_REQUIRED),
            'cancelreason' => new \external_value(PARAM_TEXT, 'Reason of session booking cancellation', VALUE_DEFAULT, ''),
        ];
        return new \external_function_parameters($params);
    }

    /**
     * Cancel session booking.
     *
     * @param int $sessionid The ID of the session
     * @param string $cancelreason Optional justification for cancelling the signup
     * @return bool
     */
    public static function user_cancel($sessionid, $cancelreason) {
        global $DB, $USER;
        $params = self::validate_parameters(self::user_cancel_parameters(), [
            'sessionid' => $sessionid,
            'cancelreason' => $cancelreason,
        ]);

        $session = appointment_get_session($params['sessionid']);
        $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $appointment->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('appointment', $appointment->id, $appointment->course);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        \mod_appointment\permission::require_can_cancel_signup($session, $context);

        $error = '';
        if (appointment_user_cancel($session, false, false, $error, $params['cancelreason'])) {

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
                $error = appointment_send_cancellation_notice($appointment, $session, $USER->id);
                if (!empty($error)) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Describes the return function of user_cancel
     *
     * @return \external_value
     */
    public static function user_cancel_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully cancelled session booking.');
    }
}

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
 * Class mobile
 *
 * @package    mod_appointment
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\output;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/appointment/lib.php');

use mod_appointment\output\session_datetime;
use mod_appointment\output\session_capacity;
use mod_appointment\output\session_status;
use mod_appointment\output\session_signup;

/**
 * Class mobile
 *
 * @package    mod_appointment
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Returns the appointment sessions view for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array       HTML, javascript and otherdata
     */
    public static function mobile_sessions_view($args) {
        global $OUTPUT, $DB, $PAGE, $USER;

        $args = (object) $args;
        $cm = get_coursemodule_from_id('appointment', $args->cmid);

        // Capabilities check.
        require_course_login($args->courseid, false, $cm, true, true);
        $context = \context_module::instance($cm->id);
        \mod_appointment\permission::require_can_view_appointment($context);

        // Get appointment sessions.
        $appointment = $DB->get_record('appointment', ['id' => $cm->instance]);
        $appointmentsessions = appointment_get_sessions($appointment->id);
        if ($usersubmission = appointment_get_user_submissions($appointment->id, $USER->id)) {
            $usersubmission = array_shift($usersubmission);
        }

        // Populate context for sessions.
        $sessions = [];
        $output = $PAGE->get_renderer('mod_appointment');
        foreach ($appointmentsessions as $session) {
            $data = [];

            // Populate submissions.
            if ($usersubmission && ($session->id == $usersubmission->sessionid)) {
                $session->usersubmission = $usersubmission;
            }

            // Add date and time.
            $datetime = new session_datetime($session);
            $data += $datetime->export_for_template($output);

            // Add capacity.
            $capacity = new session_capacity($session, $context);
            $data += $capacity->export_for_template($output);

            // Add status.
            $status = new session_status($session);
            $data += $status->export_for_template($output);

            // Add signup.
            $signupcontext = new session_signup($session);
            $data += $signupcontext->export_for_template($output);

            $sessions[] = $data;
        }

        $data = [
            'appointment' => $appointment,
            'sessions' => $sessions,
            'cmid' => $cm->id,
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_appointment/mobile_sessions_view', $data),
                ],
            ],
            'javascript' => '
                const refresh = function(eventData) {
                    this.refreshContent();
                };
                this.CoreEventsProvider.on("mod_appointment_sessions_view_refresh", refresh.bind(this));',
            'otherdata' => '',
            'files' => '',
        ];
    }

    /**
     * Returns the appointment session details for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array       HTML, javascript and otherdata
     */
    public static function mobile_session_details($args) {
        global $OUTPUT, $PAGE;

        $args = (object) $args;
        // Get appointment session.
        $session = appointment_get_session($args->sessionid);
        $cm = get_coursemodule_from_instance('appointment', $session->appointment, 0, false, MUST_EXIST);

        // Capabilities check.
        require_course_login($cm->course, false, $cm, true, true);
        $context = \context_module::instance($cm->id);
        \mod_appointment\permission::require_can_view_appointment($context);

        // Populate context for session details.
        $data = ['cmid' => $cm->id];
        $output = $PAGE->get_renderer('mod_appointment');
        $details = new \mod_appointment\output\session_details_modal($session, $context);
        $data += $details->export_for_template($output);

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_appointment/mobile_session_details', $data),
                ],
            ],
            'javascript' => '',
            'otherdata' => '',
            'files' => '',
        ];
    }

    /**
     * Returns the appointment session booking form for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array       HTML, javascript and otherdata
     */
    public static function mobile_session_book($args) {
        global $OUTPUT, $PAGE;

        $args = (object) $args;
        // Get appointment session.
        $session = appointment_get_session($args->sessionid);
        $cm = get_coursemodule_from_instance('appointment', $session->appointment, 0, false, MUST_EXIST);

        // Capabilities check.
        require_course_login($cm->course, false, $cm, true, true);
        $context = \context_module::instance($cm->id);
        \mod_appointment\permission::require_can_signup($session, $context);

        // Populate context for session details.
        $data = [
            'cmid' => $cm->id,
            'courseid' => $cm->course,
        ];
        $output = $PAGE->get_renderer('mod_appointment');
        $details = new \mod_appointment\output\session_details_modal($session, $context);
        $data += $details->export_for_template($output);

        // Add signup (we need this for correct button title).
        $signupcontext = new session_signup($session);
        $data += $signupcontext->export_for_template($output);

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_appointment/mobile_session_book', $data),
                ],
            ],
            'javascript' => '
                this.afterCall = () => {
                    this.CoreEventsProvider.trigger("mod_appointment_sessions_view_refresh");
                    setTimeout(() => {
                        const str = this.TranslateService.instant("plugin.mod_appointment.bookingcompleted");
                        this.CoreDomUtilsProvider.showToast(str);
                    }, 1000);
                };
            ',
            'otherdata' => '',
            'files' => '',
        ];
    }

    /**
     * Returns the appointment cancellation form for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array       HTML, javascript and otherdata
     */
    public static function mobile_session_cancel($args) {
        global $OUTPUT, $PAGE;

        $args = (object) $args;
        // Get appointment session.
        $session = appointment_get_session($args->sessionid);
        $cm = get_coursemodule_from_instance('appointment', $session->appointment, 0, false, MUST_EXIST);

        // Capabilities check.
        require_course_login($cm->course, false, $cm, true, true);
        $context = \context_module::instance($cm->id);
        \mod_appointment\permission::require_can_cancel_signup($context);

        // Populate context for session details.
        $data = [
            'cmid' => $cm->id,
            'courseid' => $cm->course,
            'sessionid' => $session->id,
        ];
        $output = $PAGE->get_renderer('mod_appointment');

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_appointment/mobile_session_cancel', $data),
                ],
            ],
            'javascript' => '
                this.afterCall = () => {
                    this.CoreEventsProvider.trigger("mod_appointment_sessions_view_refresh");
                    setTimeout(() => {
                        const str = this.TranslateService.instant("plugin.mod_appointment.bookingcancelled");
                        this.CoreDomUtilsProvider.showToast(str);
                    }, 1000);
                };
            ',
            'otherdata' => '',
            'files' => '',
        ];
    }
}

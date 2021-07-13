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
 * Sign-up form
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      Daniel Neis Araujo <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/appointment/lib.php');

use \tool_wp\modal_form;
use \html_writer;

/**
 * Class mod_appointment\form\signup
 *
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signup extends modal_form {

    /**
     * Form definition
     *
     * This form is only used for adding. For editing, we use the session form.
     */
    public function definition() {
        global $PAGE;

        $mform =& $this->_form;

        $session = $this->get_session();
        $cm = get_coursemodule_from_instance('appointment', $session->appointment, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $mform->addElement('hidden', 'sessionid', $session->id);
        $mform->setType('sessionid', PARAM_INT);

        $output = $PAGE->get_renderer('mod_appointment');
        $details = new \mod_appointment\output\session_details_modal($session, $context);
        $mform->addElement('html',
            $output->render_from_template('mod_appointment/session_details_modal', $details->export_for_template($output)));

        $options = array(
            MOD_APPOINTMENT_BOTH => get_string('notificationboth', 'appointment'),
            MOD_APPOINTMENT_TEXT => get_string('notificationemail', 'appointment'),
            MOD_APPOINTMENT_ICAL => get_string('notificationical', 'appointment')
        );

        $mform->addElement('html', '<div class="container-fluid">');
        $mform->addElement('select', 'notificationtype', get_string('notificationtype', 'appointment'), $options);
        $mform->addHelpButton('notificationtype', 'notificationtype', 'appointment');
        $mform->addRule('notificationtype', null, 'required', null, 'client');
        $mform->setDefault('notificationtype', MOD_APPOINTMENT_BOTH);
        $mform->addElement('html', '</div>');
    }

    /**
     * Process form submission.
     *
     * @param \stdClass $data
     * @return string
     */
    public function process(\stdClass $data) {
        global $DB;

        $session = $this->get_session();

        // Get signup type.
        if (empty($session->sessiondates)) {
            $statuscode = MOD_APPOINTMENT_STATUS_WAITLISTED;
        } else if (appointment_get_num_attendees($session->id) < $session->capacity) {
            // Save available.
            $statuscode = MOD_APPOINTMENT_STATUS_BOOKED;
        } else {
            $statuscode = MOD_APPOINTMENT_STATUS_WAITLISTED;
        }

        $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $appointment->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('appointment', $session->appointment, $appointment->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $submissionid = appointment_user_signup($session, $appointment, $course, $data->notificationtype, $statuscode);
        if ($submissionid) {
            $params = ['context' => $context, 'objectid' => $session->id];
            $event = \mod_appointment\event\signup_success::create($params);
            $event->add_record_snapshot('appointment_sessions', $session);
            $event->add_record_snapshot('appointment', $appointment);
            $event->trigger();
        } else {
            throw new \moodle_exception('error:problemsigningup', 'appointment');
        }
    }

    /**
     * Check permissions.
     */
    public function require_access() {
        $session = $this->get_session();
        $cm = get_coursemodule_from_instance('appointment', $session->appointment, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        \mod_appointment\permission::require_can_signup($session, $context);
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB, $USER;

        $session = $this->get_session();
        $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('appointment', $session->appointment, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $errors = [];
        if (!appointment_session_has_capacity($session, $context) && (!$session->allowwaitlist)) {
            $errors[] = get_string('sessionisfull', 'mod_appointment');
        } else if (appointment_get_user_submissions($appointment->id, $USER->id)) {
            $errors[] = get_string('alreadysignedup', 'mod_appointment');
        } else if (appointment_manager_needed($appointment) && !appointment_get_manageremail($USER->id)) {
            $errors[] = get_string('error:manageremailaddressmissing', 'mod_appointment');
        }

        return $errors;
    }

    /**
     * Current session
     *
     * @return \stdClass
     */
    protected function get_session(): \stdClass {
        if (!isset($this->session)) {
            $id = !empty($this->_ajaxformdata['sessionid']) ? $this->_ajaxformdata['sessionid'] : 0;
            $this->session = $id ? appointment_get_session($id) : new \stdClass();
        }
        return $this->session;
    }
}

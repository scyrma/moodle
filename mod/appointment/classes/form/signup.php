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

use core_form\dynamic_form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/appointment/lib.php');

/**
 * Class mod_appointment\form\signup
 *
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signup extends dynamic_form {

    /** @var \stdClass */
    protected $cm;

    /**
     * Form definition
     *
     * This form is only used for adding. For editing, we use the session form.
     */
    public function definition() {
        global $PAGE;

        $mform =& $this->_form;

        $session = $this->get_session();
        $context = $this->get_context_for_dynamic_submission();

        $mform->addElement('hidden', 'sessionid', $session->id);
        $mform->setType('sessionid', PARAM_INT);

        $output = $PAGE->get_renderer('mod_appointment');
        $details = new \mod_appointment\output\session_details_modal($session, $context);
        $mform->addElement('html',
            $output->render_from_template('mod_appointment/session_details_modal', $details->export_for_template($output)));
    }

    /**
     * Process form submission.
     *
     * @return void
     */
    public function process_dynamic_submission() {
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
        $cm = $this->get_cm();
        $context = \context_module::instance($cm->id);

        $submissionid = appointment_user_signup($session, $appointment, $course, null, $statuscode);
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
    protected function check_access_for_dynamic_submission(): void {
        \mod_appointment\permission::require_can_signup($this->get_session(), $this->get_context_for_dynamic_submission());
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
        $cm = $this->get_cm();
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
            $id = $this->optional_param('sessionid', 0, PARAM_INT);
            $this->session = $id ? appointment_get_session($id) : new \stdClass();
        }
        return $this->session;
    }

    /**
     * Current course module
     *
     * @return \stdClass
     * @throws \coding_exception
     */
    protected function get_cm(): \stdClass {
        if (!$this->cm) {
            $session = $this->get_session();
            $this->cm = get_coursemodule_from_instance('appointment', $session->appointment, 0, false, MUST_EXIST);
        }
        return $this->cm;
    }

    /**
     * Set data
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data($this->_ajaxformdata);
    }

    /**
     * Returns context where this form is used
     *
     * @return \context_module
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_module::instance($this->get_cm()->id);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/mod/appointment/view.php', [
            'action' => 'signup',
            'sessionid' => $this->get_session()->id,
        ]);
    }
}

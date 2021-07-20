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
 * Cancel signup form
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      Daniel Neis Araujo <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/appointment/lib.php');

use core_form\dynamic_form;
use \html_writer;

/**
 * Class mod_appointment\form\cancelsignup
 *
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cancelsignup extends dynamic_form {

    /** @var \stdClass */
    protected $cm;

    /**
     * Form definition
     *
     * This form is only used for adding. For editing, we use the session form.
     */
    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('hidden', 'sessionid');
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('textarea', 'cancelreason', get_string('cancelreason', 'mod_appointment'));
    }

    /**
     * Process form submission.
     *
     * @return string
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        global $DB, $USER;

        $session = $this->get_session();

        $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);
        $context = $this->get_context_for_dynamic_submission();

        $errorstr = '';
        if (appointment_user_cancel($session, false, false, $errorstr, $data->cancelreason)) {

            // Logging and events trigger.
            $params = array(
                'context'  => $context,
                'objectid' => $session->id
            );
            $event = \mod_appointment\event\cancel_booking::create($params);
            $event->add_record_snapshot('appointment_sessions', $session);
            $event->add_record_snapshot('appointment', $appointment);
            $event->trigger();

            $message = get_string('bookingcancelled', 'appointment');

            if (!empty($session->sessiondates)) {
                $error = appointment_send_cancellation_notice($appointment, $session, $USER->id);
                if (empty($error)) {
                    if (!empty($session->sessiondates) && $appointment->cancellationinstrmngr) {
                        $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') .
                            get_string('cancellationsentmgr', 'appointment');
                    } else {
                        $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') .
                            get_string('cancellationsent', 'appointment');
                    }
                } else {
                    throw new \moodle_exception($error, 'appointment');
                }
            }
        } else {
            throw new \moodle_exception('somethingwentwrong');
        }
    }

    /**
     * Check permissions.
     */
    protected function check_access_for_dynamic_submission(): void {
        \mod_appointment\permission::require_can_cancel_signup($this->get_context_for_dynamic_submission());
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
     * Returns context where this form is used
     *
     * @return \context_module
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_module::instance($this->get_cm()->id);
    }

    /**
     * Set data
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data($this->_ajaxformdata);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/mod/appointment/view.php', [
            'action' => 'cancelsignup',
            'sessionid' => $this->get_session()->id,
        ]);
    }
}

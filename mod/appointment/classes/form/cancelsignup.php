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

use tool_wp\modal_form;
use \html_writer;

/**
 * Class mod_appointment\form\cancelsignup
 *
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cancelsignup extends modal_form {

    /**
     * Form definition
     *
     * This form is only used for adding. For editing, we use the session form.
     */
    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('hidden', 'sessionid', $this->_ajaxformdata['sessionid']);
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('textarea', 'cancelreason', get_string('cancelreason', 'mod_appointment'));
    }

    /**
     * Process form submission.
     *
     * @param \stdClass $data
     * @return string
     */
    public function process(\stdClass $data) {
        global $DB, $USER;

        $session = $this->get_session();

        $appointment = $DB->get_record('appointment', ['id' => $session->appointment], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $appointment->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('appointment', $session->appointment, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

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
    public function require_access() {
        $cm = get_coursemodule_from_instance('appointment', $this->get_session()->appointment, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        \mod_appointment\permission::require_can_cancel_signup($context);
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

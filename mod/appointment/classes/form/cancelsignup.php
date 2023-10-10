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

namespace mod_appointment\form;

use core_form\dynamic_form;
use mod_appointment\attendee;

/**
 * Cancel signup form
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      Daniel Neis Araujo <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cancelsignup extends dynamic_form {

    /** @var \context_module */
    protected $context;

    /**
     * Form definition
     */
    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('hidden', 'sessionid');
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('textarea', 'cancelreason', get_string('cancelreason', 'mod_appointment'));
    }

    /**
     * Process form submission.
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $session = $this->get_session();
        attendee::cancel_self($session, $this->get_context_for_dynamic_submission(), $data->cancelreason);
    }

    /**
     * Check permissions.
     */
    protected function check_access_for_dynamic_submission(): void {
        \mod_appointment\permission::require_can_cancel_signup($this->get_session(), $this->get_context_for_dynamic_submission());
    }

    /**
     * Current session
     *
     * @return \stdClass
     */
    protected function get_session(): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/mod/appointment/lib.php');

        if (!isset($this->session)) {
            $id = $this->optional_param('sessionid', 0, PARAM_INT);
            $this->session = appointment_get_session($id, MUST_EXIST);
        }
        return $this->session;
    }

    /**
     * Returns context where this form is used
     *
     * @return \context_module
     */
    public function get_context_for_dynamic_submission(): \context {
        if (!$this->context) {
            $session = $this->get_session();
            $cm = get_coursemodule_from_instance('appointment', $session->appointment, 0, false, MUST_EXIST);
            $this->context = \context_module::instance($cm->id);
        }
        return $this->context;
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

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
 * Session form
 *
 * Copyright (C) 2007-2011 Catalyst IT (http://www.catalyst.net.nz)
 * Copyright (C) 2011-2013 Totara LMS (http://www.totaralms.com)
 * Copyright (C) 2014 onwards Catalyst IT (http://www.catalyst-eu.net)
 *
 * @package    mod_appointment
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @author     Stacey Walker <stacey@catalyst-eu.net>
 * @author     Alastair Munro <alastair.munro@totaralms.com>
 * @author     Aaron Barnes <aaron.barnes@totaralms.com>
 * @author     Francois Marier <francois@catalyst.net.nz>
 * @author     Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/appointment/lib.php');

use tool_wp\modal_form;
use html_writer;

/**
 * Class mod_appointment\form\session
 *
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session extends modal_form {

    /**
     * Form definition
     */
    public function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;
        $cmid = $this->_ajaxformdata['cmid'];

        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $appointment = $DB->get_record('appointment', ['id' => $cm->instance], '*', MUST_EXIST);

        $context = \context_module::instance($cmid);

        // Course Module ID.
        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        // Appointment Instance ID.
        $mform->addElement('hidden', 'appointment', $cm->instance);
        $mform->setType('appointment', PARAM_INT);

        // Appointment Session ID.
        $mform->addElement('hidden', 'sessionid', $this->_ajaxformdata['sessionid']);
        $mform->setType('sessionid', PARAM_INT);

        $mform->addElement('header', 'sessions', get_string('sessions', 'mod_appointment'));

        \MoodleQuickForm::registerElementType('time_selector',
            "$CFG->dirroot/mod/appointment/classes/local/time_selector.php",
            'mod_appointment_time_selector');

        // Appointment sessions repeated elements.
        $repeatarray = [];
        $repeatarray[] = $mform->createElement('html', html_writer::start_div('border rounded p-4 mb-3 br-1 bg-gray020'));
        $repeatarray[] = $mform->createElement('hidden', 'sessiondateid', 0);
        $repeatarray[] = $mform->createElement('date_selector', 'startdate', get_string('date', 'appointment'));
        $repeatarray[] = $mform->createElement('time_selector', 'starttime', get_string('timestart', 'appointment'));
        $repeatarray[] = $mform->createElement('time_selector', 'endtime', get_string('endtime', 'appointment'));
        $repeatarray[] = $mform->createElement('submit', 'deletebutton', get_string('deletesession', 'appointment'));
        $repeatarray[] = $mform->createElement('html', html_writer::end_div());

        if (empty($this->_ajaxformdata['sessionid'])) {
            $repeatcount = 1;
        } else {
            if (!$session = appointment_get_session($this->_ajaxformdata['sessionid'])) {
                throw new \moodle_exception('error:incorrectcoursemodulesession', 'appointment');
            }
            $repeatcount = count($session->sessiondates);
        }

        // Default appointment start time rounded to next hour.
        $time = time();
        $nexthour = $time - ($time % HOURSECS) + HOURSECS;

        $repeatoptions = [
            'sessiondateid' => ['type' => PARAM_INT],
            'starttime' => ['default' => $nexthour],
            'endtime' => ['default' => $nexthour + HOURSECS],
        ];

        $this->repeat_elements($repeatarray, $repeatcount, $repeatoptions, 'date_repeats', 'date_add_fields',
                               1, get_string('addsession', 'mod_appointment'), true, 'deletebutton');

        $mform->addElement('header', 'advanced', get_string('advanced', 'mod_appointment'));

        $mform->addElement('text', 'capacity', get_string('capacity', 'appointment'), 'size="5"');
        $mform->addRule('capacity', null, 'required', null, 'client');
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 10);

        $mform->addElement('checkbox', 'allowwaitlist', get_string('enablewaitlist', 'appointment'));

        if (has_capability('mod/appointment:configurecancellation', $context)) {
            $mform->addElement('advcheckbox', 'allowcancellations', get_string('allowcancellations', 'appointment'));
            $mform->setDefault('allowcancellations', $appointment->allowcancellationsdefault);
        }

        $mform->addElement('editor', 'details_editor',
            get_string('sessiondescription', 'appointment'), null, $this->get_editor_options($context));
        $mform->setType('details_editor', PARAM_RAW);

        $customfieldurl = new \moodle_url('/mod/appointment/customfield.php');
        $customfieldtext = get_string('managecustomfields', 'mod_appointment');
        $mform->addElement('static', 'managecustomfields', '', html_writer::link($customfieldurl, $customfieldtext));

        $handler = \mod_appointment\customfield\appointment_handler::create();
        $handler->instance_form_definition($mform);
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (isset($data['date_repeats']) && $data['date_repeats'] > 0) {
            for ($i = 0; $i < $data['date_repeats']; $i++) {
                if (!isset($data['starttime'][$i])) {
                    break;
                }
                if ($data['starttime'][$i] > $data['endtime'][$i]) {
                    $errstr = get_string('error:sessionstartafterend', 'appointment');
                    $errors["starttime[$i]"] = $errstr;
                    $errors["endtime[$i]"] = $errstr;
                }
            }
        }
        $handler = \mod_appointment\customfield\appointment_handler::create();
        $errors = array_merge($errors, $handler->instance_form_validation($data, $files));

        return $errors;
    }

    /**
     * Check permissions.
     */
    public function require_access() {
        $context = \context_module::instance($this->_ajaxformdata['cmid']);
        \mod_appointment\permission::require_can_edit_sessions($context);
    }

    /**
     * Process form submission.
     *
     * @param \stdClass $data
     * @return string
     */
    public function process(\stdClass $data) {

        // Pre-process fields.
        if (empty($data->allowwaitlist)) {
            $data->allowwaitlist = 0;
        }

        $sessiondates = array();
        for ($i = 0; $i < $data->date_repeats; $i++) {
            if (!isset($data->startdate[$i])) {
                break;
            }
            $date = new \stdClass();
            $date->timestart = $data->startdate[$i] + $data->starttime[$i];
            $date->timefinish = $data->startdate[$i] + $data->endtime[$i];
            $sessiondates[] = $date;
        }

        $this->save($data, $sessiondates);
    }

    /**
     * Saves the current form on database.
     *
     * @param stdClass $data
     * @param array $sessiondates
     */
    protected function save(\stdClass $data, array $sessiondates) {
        global $DB;

        $cmid = $this->_ajaxformdata['cmid'];

        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $appointment = $DB->get_record('appointment', ['id' => $data->appointment], '*', MUST_EXIST);

        $context = \context_module::instance($cmid);

        if (!has_capability('mod/appointment:configurecancellation', $context)) {
            unset($data->allowcancellations);
        }

        if (empty($data->sessionid)) {
            $session = null;
        } else {
            $session = appointment_get_session($data->sessionid);
        }
        $update = false;
        $transaction = $DB->start_delegated_transaction();
        if ($session != null) {
            $update = true;
            $data->id = $session->id;

            if (!appointment_update_session($data, $sessiondates, $context)) {
                $transaction->force_transaction_rollback();
                throw new \moodle_exception('error:couldnotupdatesession', 'appointment');
            }

            // Remove old site-wide calendar entry.
            if (!appointment_remove_session_from_calendar($session, SITEID)) {
                $transaction->force_transaction_rollback();
                throw new \moodle_exception('error:couldnotupdatecalendar', 'appointment');
            }
        } else {
            if (!$data->id = appointment_add_session($data, $sessiondates, $context)) {
                $transaction->force_transaction_rollback();
                throw new \moodle_exception('error:couldnotaddsession', 'appointment');
            }
        }

        // Retrieve record that was just inserted/updated.
        if (!$session = appointment_get_session($data->id)) {
            $transaction->force_transaction_rollback();
            throw new \moodle_exception('error:couldnotfindsession', 'appointment');
        }

        // Logging and events trigger.
        $params = [
            'context' => $context,
            'objectid' => $session->id
        ];
        if ($update) {
            $event = \mod_appointment\event\update_session::create($params);
            $event->add_record_snapshot('appointment_sessions', $session);
            $event->add_record_snapshot('appointment', $appointment);
            $event->trigger();
        } else {
            $event = \mod_appointment\event\add_session::create($params);
            $event->add_record_snapshot('appointment_sessions', $session);
            $event->add_record_snapshot('appointment', $appointment);
            $event->trigger();
        }

        $transaction->allow_commit();
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

    /**
     * Options for the details editor.
     *
     * @param \context_module $context
     * @return array
     */
    protected function get_editor_options($context) {
        return [
            'noclean' => false,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => $context,
        ];
    }

    /**
     * Set data in the modal form.
     */
    public function set_data_for_modal() {
        $session = $this->get_session();
        $cmid = $this->_ajaxformdata['cmid'];
        $context = \context_module::instance($cmid);
        if (isset($session->id)) {
            // Follow repeatable elements format, otherwise form defaults ones will override.
            for ($i = 0; $i < count($session->sessiondates); $i++) {
                $startdatekey = "startdate[$i]";
                $session->$startdatekey = $session->sessiondates[$i]->timestart;
                $starttimekey = "starttime[$i]";
                $session->$starttimekey = $session->sessiondates[$i]->timestart;
                $endtimekey = "endtime[$i]";
                $session->$endtimekey = $session->sessiondates[$i]->timefinish;
            }
            unset($session->sessiondates);
            $handler = \mod_appointment\customfield\appointment_handler::create();
            $handler->instance_form_before_set_data($session);
            $session = file_prepare_standard_editor($session, 'details', $this->get_editor_options($context),
                $context, 'mod_appointment', 'session', $session->id);
        }
        $this->set_data($session);
    }
}

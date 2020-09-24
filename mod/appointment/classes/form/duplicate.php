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
 * Session duplication form
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/appointment/lib.php');

/**
 * Class mod_appointment\form\duplicate
 *
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class duplicate extends session {

    /**
     * Form definition
     */
    public function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;
        $cmid = $this->optional_param('cmid', PARAM_INT, 0);

        // Course Module ID.
        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        // Appointment Session ID.
        $mform->addElement('hidden', 'sessionid', $this->_ajaxformdata['sessionid']);
        $mform->setType('sessionid', PARAM_INT);

        \MoodleQuickForm::registerElementType('time_selector',
            "$CFG->dirroot/mod/appointment/classes/local/time_selector.php",
            'mod_appointment_time_selector');

        $mform->addElement('date_selector', 'startdate', get_string('date', 'appointment'));
        $mform->addElement('time_selector', 'starttime', get_string('timestart', 'appointment'));

        // Default appointment start time rounded to next hour.
        $time = time();
        $nexthour = $time - ($time % 3600) + 3600;
        $mform->setDefault('startdate', $nexthour);
        $mform->setDefault('starttime', $nexthour);
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = [];
        if (!isset($data['sessionid'])) {
            $errors['sessionid'] = get_string('errornosessionid', 'mod_appointment');
        }
        if (!isset($data['startdate'])) {
            $errors['startdate'] = get_string('errornostartdate', 'mod_appointment');
        }
        if (!isset($data['starttime'])) {
            $errors['starttime'] = get_string('errornostarttime', 'mod_appointment');
        }
        return $errors;
    }

    /**
     * Process form submission.
     *
     * @param \stdClass $data
     * @return string
     */
    public function process(\stdClass $data) {
        $cmid = $data->cmid;
        $context = \context_module::instance($cmid);
        $session = appointment_get_session($data->sessionid);

        // Prepare session object for use as form data.
        $session->instanceid = $session->appointment;
        $session = file_prepare_standard_editor($session, 'details', $this->get_editor_options($context),
            $context, 'mod_appointment', 'session', $session->id);
        unset($session->id);
        unset($session->timemodified);

        // Add new dates.
        $sessiondates = [];
        $firsttimestart = $data->startdate + $data->starttime;
        $timestartoffset = 0;
        $prevtimestart = 0;
        foreach ($session->sessiondates as $sessiondate) {
            $date = new \stdClass();

            // Determine updated timestart.
            if ($prevtimestart !== 0) {
                $timestartoffset += $sessiondate->timestart - $prevtimestart;
            }
            $date->timestart = $firsttimestart + $timestartoffset;

            // Determine updated timefinish.
            $currduration = $sessiondate->timefinish - $sessiondate->timestart;
            $date->timefinish = $date->timestart + $currduration;
            $sessiondates[] = $date;

            // Pass old timestart to next loop for offset calculation.
            $prevtimestart = $sessiondate->timestart;
        }
        unset($session->sessiondates);

        // Add customfields.
        $handler = \mod_appointment\customfield\appointment_handler::create();
        foreach ($handler->export_instance_data($data->sessionid, true) as $fielddata) {
            if (!empty($fielddata->get_value())) {
                $session->{'customfield_' . $fielddata->get_shortname()} = $fielddata->get_value();
            }
        }

        $this->save($session, $sessiondates);
    }
}

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
 * Multiple appointments form
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      Daniel Neis Araujo <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class mod_appointment\form\multiple
 *
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class multiple extends session {

    /**
     * Form definition
     *
     * This form is only used for adding. For editing, we use the session form.
     */
    public function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;
        $cmid = $this->optional_param('cmid', 0, PARAM_INT);

        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        $appointment = $DB->get_record('appointment', ['id' => $cm->instance], '*', MUST_EXIST);

        $context = $this->get_context_for_dynamic_submission();

        // Course Module ID.
        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        // Appointment Instance ID.
        $mform->addElement('hidden', 'appointment', $cm->instance);
        $mform->setType('appointment', PARAM_INT);

        $mform->addElement('header', 'timeframes', get_string('timeframes', 'mod_appointment'));

        \MoodleQuickForm::registerElementType('time_selector',
            "$CFG->dirroot/mod/appointment/classes/local/time_selector.php",
            'mod_appointment_time_selector');

        // TODO: Check if this custom element is still needed when MDL-43156 lands.
        \MoodleQuickForm::registerElementType('mod_appointment_duration',
            "$CFG->dirroot/mod/appointment/classes/local/duration.php",
            'mod_appointment_duration');

        // Timeframes repeated elements.
        $repeatarray = [];
        $repeatarray[] = $mform->createElement('html', \html_writer::start_div('border rounded p-4 mb-3 br-1 bg-gray020'));
        $repeatarray[] = $mform->createElement('hidden', 'sessiondateid', 0);
        $repeatarray[] = $mform->createElement('date_selector', 'startdate', get_string('date', 'appointment'));
        $repeatarray[] = $mform->createElement('time_selector', 'starttime', get_string('timestart', 'appointment'));
        $repeatarray[] = $mform->createElement('time_selector', 'endtime', get_string('endtime', 'appointment'));
        $repeatarray[] = $mform->createElement('mod_appointment_duration', 'split', get_string('split', 'appointment'));
        $repeatarray[] = $mform->createElement('mod_appointment_duration', 'break', get_string('break', 'appointment'));
        $repeatarray[] = $mform->createElement('submit', 'deletebutton', get_string('deletetimeframe', 'appointment'));
        $repeatarray[] = $mform->createElement('html', \html_writer::end_div());

        // Default appointment start time rounded to next hour.
        $time = time();
        $nexthour = $time - ($time % HOURSECS) + HOURSECS;

        $repeatoptions = [
            'sessiondateid' => ['type' => PARAM_INT],
            'starttime' => ['default' => $nexthour],
            'endtime' => ['default' => $nexthour + HOURSECS],
            'split' => ['default' => 15 * MINSECS, 'helpbutton' => ['split', 'appointment']],
            'break' => [
                'default' => 5 * MINSECS,
                'helpbutton' => ['break', 'appointment'],
                'disabledif' => ['split[number]', 'eq', 0],
            ]
        ];

        $this->repeat_elements($repeatarray, 1, $repeatoptions, 'date_repeats', 'date_add_fields',
                               1, get_string('addtimeframe', 'appointment'), true, 'deletebutton');

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

        if (\mod_appointment\permission::can_configure_custom_fields()) {
            $customfieldurl = new \moodle_url('/mod/appointment/customfield.php');
            $customfieldtext = get_string('managecustomfields', 'mod_appointment');
            $mform->addElement('static', 'managecustomfields', '', \html_writer::link($customfieldurl,
                $customfieldtext, ['target' => 'new']));
        }

        $handler = \mod_appointment\customfield\appointment_handler::create();
        $handler->set_parent_context($context);
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
                if (($data['endtime'][$i] - $data['starttime'][$i]) < $data['split'][$i]) {
                    $errors["split[$i]"] = get_string('error:sessionsplitexceeds', 'appointment');
                }
            }
        }
        return $errors;
    }

    /**
     * Process form submission.
     *
     * @return string
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();

        // Pre-process fields.
        if (empty($data->allowwaitlist)) {
            $data->allowwaitlist = 0;
        }

        for ($i = 0; $i < $data->date_repeats; $i++) {
            if (!isset($data->startdate[$i])) {
                break;
            }
            if ($data->split[$i] == 0) {
                $sessiondates = [(object)[
                    'timestart' => $data->startdate[$i] + $data->starttime[$i],
                    'timefinish' => $data->startdate[$i] + $data->endtime[$i]
                ]];
                $this->save($data, $sessiondates);
            } else {
                if ($data->break[$i] == 0) {
                    $divisor = $data->split[$i];
                } else {
                    $divisor = $data->split[$i] + $data->break[$i];
                }
                $countdates = ($data->endtime[$i] - $data->starttime[$i]) / $divisor;
                $nextstart = $data->startdate[$i] + $data->starttime[$i];
                for ($j = 0; $j < $countdates; $j++) {
                    $sessiondates = [(object)[
                        'timestart' => $nextstart,
                        'timefinish' => $nextstart + $data->split[$i]
                    ]];
                    $this->save($data, $sessiondates);
                    $nextstart += $data->split[$i] + $data->break[$i];
                }
            }
        }
    }
}

<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class containing the form for the schedule.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\form;

use html_writer;
use core\output\notification;
use tool_organisation\organisation;
use tool_reportbuilder\event\schedule_updated;
use tool_reportbuilder\helper;
use tool_reportbuilder\local\helpers\schedules;
use tool_reportbuilder\local\models\schedules as schedule_model;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;
use tool_tenant\sharedspace;
use tool_wp\modal_form;

/**
 * Class schedule
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class schedule extends modal_form {

    /** @var string Persistent class name. */
    protected static $persistentclass = 'tool_reportbuilder\\models\\schedules';

    /** @var schedule_model $schedule */
    protected $schedule;

    /** @var report_base $report */
    protected $report;

    /**
     * The current schedule being edited
     *
     * @return schedule_model|null
     */
    protected function get_schedule(): ?schedule_model {
        if (!$this->schedule && ($id = $this->optional_param('id', 0, PARAM_INT))) {
            $this->schedule = schedules::get_schedule($id);
        }

        return $this->schedule;
    }

    /**
     * The report instance this schedule is being added to
     *
     * @return report_base|null
     */
    protected function get_report(): ?report_base {
        if (!$this->report && ($id = $this->optional_param('reportid', 0, PARAM_INT))) {
            $this->report = manager::get_report($id);
        }

        return $this->report;
    }

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        global $USER, $OUTPUT;

        $mform = $this->_form;

        $reportid = $this->optional_param('reportid', 0, PARAM_INT);
        $id = $this->optional_param('id', 0, PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Basic fields.
        $mform->addElement('header', 'general', get_string('basicinformation', 'tool_reportbuilder'));

        $mform->addElement('text', 'name', get_string('schedulename', 'tool_reportbuilder'), ['size' => 30]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        if (!$reportid && !$id) {
            $sources = helper::get_reports_select();
            $sources = ['' => get_string('choose', 'tool_reportbuilder')] + $sources;
            $mform->addElement('select', 'reportid', get_string('report', 'tool_reportbuilder'), $sources);
            $mform->addRule('reportid', null, 'required', null, 'client');
        } else {
            $mform->addElement('hidden', 'reportid', $reportid);
            $mform->setType('reportid', PARAM_INT);
        }

        $formats = schedules::get_formats();
        $formats = [0 => get_string('choose', 'tool_reportbuilder')] + $formats;
        $mform->addElement('select', 'format', get_string('format', 'tool_reportbuilder'), $formats);
        $mform->addRule('format', get_string('error:mustselectformat', 'tool_reportbuilder'), 'regex', '/[^0]+/', 'client');
        $mform->setType('format', PARAM_TEXT);
        $mform->addRule('format', null, 'required', null, 'client');
        $mform->setDefault('format', "0");

        $mform->addElement('date_time_selector', 'scheduled', get_string('datetostart', 'tool_reportbuilder'),
            array('optional' => false));
        $mform->setDefault('scheduled', time());
        $mform->setType('scheduled', PARAM_INT);

        $recurrences = schedules::get_recurrences();
        ksort($recurrences);
        $mform->addElement('select', 'recurrence', get_string('recurrence', 'tool_reportbuilder'), $recurrences);
        $mform->addRule('recurrence', null, 'required', null, 'client');
        $mform->setType('recurrence', PARAM_INT);

        // User created selector.
        $options = [
            'ajax' => 'tool_wp/form-potential-user-selector',
            'data-component' => 'tool_reportbuilder',
            'data-area' => 'usercreator',
            'data-itemid' => $id,
            'valuehtmlcallback' => function($userid) {
                $user = \core_user::get_user($userid);
                return html_writer::span(fullname($user)) . ' ' .
                    html_writer::span(html_writer::tag('small', $user->email));
            }
        ];

        $mform->addElement('autocomplete', 'usercreated', get_string('userviewreportas', 'tool_reportbuilder'), [], $options);
        $mform->setDefault('usercreated', $USER->id);
        $mform->addHelpButton('usercreated', 'userviewreportas', 'tool_reportbuilder');

        // Recipient selection.
        $mform->addElement('header', 'general', get_string('recipients', 'tool_reportbuilder'));

        // Hide positionid and departmentid in shared space.
        if (!sharedspace::is_shared_space()) {
            $mform->addElement('selectgroups', 'departmentid', get_string('department', 'tool_organisation'),
                organisation::get_all_departments_menu([0 => '']));
            $mform->setType('departmentid', PARAM_INT);

            $mform->addElement('selectgroups', 'positionid', get_string('position', 'tool_organisation'),
                organisation::get_all_positions_menu([0 => '']));
            $mform->setType('positionid', PARAM_INT);
        }

        // Users selector.
        $options = array(
            'ajax' => 'tool_wp/form-potential-user-selector',
            'data-component' => 'tool_reportbuilder',
            'data-area' => 'usersmanually',
            'data-itemid' => $id,
            'multiple' => true,
            'valuehtmlcallback' => function($userid) {
                $userinfo = \core_user::get_user($userid);
                return '<span>' . fullname($userinfo) . '</span> <span><small>' . $userinfo->email . '</small></span>';
            }
        );

        $mform->addElement('autocomplete', 'users', get_string('addusers', 'tool_reportbuilder'), [], $options);
        $mform->addHelpButton('users', 'addusers', 'tool_reportbuilder');

        // Manually added emails.
        $options = array(
            'multiple' => true,
            'tags' => true, // Allow user to manually add entries by text.
            'placeholder' => get_string('enteremail', 'tool_reportbuilder'),
            'showsuggestions' => false,
        );

        $mform->addElement('autocomplete', 'emails', get_string('addemails', 'tool_reportbuilder'), [], $options);
        $mform->addElement('html', $OUTPUT->render(
            (new notification(get_string('privacywarning', 'tool_reportbuilder'), notification::NOTIFY_WARNING))
                ->set_extra_classes(['border-warning'])
                ->set_show_closebutton(false))
        );

        $mform->addElement('header', 'general', get_string('customessage', 'tool_reportbuilder'));
        $mform->addElement('text', 'subject', get_string('subject', 'tool_reportbuilder'), ['size' => 30]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');

        $mform->addElement('editor', 'message', get_string('message', 'tool_reportbuilder'));
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', null, 'required', null, 'client');

        $mform->setDefault('message', array(
                'text' => '',
                'format' => FORMAT_HTML
            )
        );
    }

    /**
     * Process form submission
     *
     * @param \stdClass $data
     * @return \tool_reportbuilder\local\models\schedules
     */
    public function process(\stdClass $data) {
        global $USER;

        $data->message = $data->message['text'];
        $data->usercreated = $data->usercreated ?: $USER->id;
        $data->recipients = json_encode([
            'users' => $data->users ?: [],
            'emails' => $data->emails ?: [],
        ]);

        $data = (object) array_intersect_key((array) $data, schedule_model::properties_definition());

        $schedule = $this->get_schedule();
        if (!$schedule) {
            return schedules::add_schedule($data);
        } else {
            $schedule->from_record($data);
            $schedule->set('nextsend', schedules::calculate_next_send_time($data->recurrence, $data->scheduled));
            $schedule->update();

            // Trigger schedule updated event.
            $event = schedule_updated::create_from_object($schedule);
            $event->trigger();

            return $schedule;
        }
    }

    /**
     * Check if current user has access to this form, otherwise throw exception
     */
    public function require_access() {
        if ($schedule = $this->get_schedule()) {
            permission::require_can_edit_schedule($schedule);
        } else {
            permission::require_can_create_schedule($this->get_report());
        }
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_modal() {
        $formdata = new \stdClass();

        if ($schedule = $this->get_schedule()) {
            $formdata = $schedule->to_record();
            $messagetext = $formdata->message;
            $formdata->message = [];
            $formdata->message['text'] = $messagetext;
            $formdata->message['format'] = FORMAT_HTML;

            // Load custom user/email recipient form fields.
            $recipients = json_decode($formdata->recipients);
            if ($recipients) {
                $formdata->users = $recipients->users ?? [];
                $formdata->emails = $recipients->emails ?? [];
            }
        }

        $this->set_data($formdata);
    }

    /**
     * Custom form validations
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Ensure we have some recipients.
        if (empty($data['departmentid']) && empty($data['positionid']) && empty($data['users']) && empty($data['emails'])) {
            $errors['users'] = get_string('errornorecipients', 'tool_reportbuilder');
        }

        // Validate each manually entered email.
        foreach ($data['emails'] as $email) {
            if (!validate_email($email)) {
                $errors['emails'] .= get_string('invalidemail', 'tool_reportbuilder', $email) . '</br>';
            }
        }

        return $errors;
    }
}

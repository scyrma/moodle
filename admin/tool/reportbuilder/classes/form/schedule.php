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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Class containing the form for the schedule.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\form;

use core_form\dynamic_form;
use html_writer;
use tool_reportbuilder\event\schedule_updated;
use tool_reportbuilder\local\helpers\audience;
use tool_reportbuilder\local\helpers\schedules;
use tool_reportbuilder\local\models\schedule as schedule_model;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;

/**
 * Class schedule
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class schedule extends dynamic_form {

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
        if (!$this->report) {
            if ($schedule = $this->get_schedule()) {
                $this->report = $schedule->get_report();
            } else if ($id = $this->optional_param('reportid', 0, PARAM_INT)) {
                $this->report = manager::get_report($id);
            }
        }

        return $this->report;
    }

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        global $USER;

        $mform = $this->_form;

        $reportid = $this->get_report()->get_id();
        $audienceinstances = audience::get_base_records($reportid);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'reportid', $reportid);
        $mform->setType('reportid', PARAM_INT);

        // Show a warning if no audiences have been set for this report.
        if (empty($audienceinstances)) {
            $html = html_writer::tag('div', get_string('noaudiencesalert', 'tool_reportbuilder'),
                ['class' => 'alert alert-danger']);
            $mform->addElement('html', $html);
        }

        // Basic fields.
        $mform->addElement('header', 'general', get_string('basicinformation', 'tool_reportbuilder'));

        $mform->addElement('text', 'name', get_string('schedulename', 'tool_reportbuilder'), ['size' => 30]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

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
            'data-itemid' => $this->optional_param('id', 0, PARAM_INT),
            'valuehtmlcallback' => function($userid) {
                $user = \core_user::get_user($userid);
                return html_writer::span(fullname($user)) . ' ' .
                    html_writer::span(html_writer::tag('small', $user->email));
            }
        ];

        $mform->addElement('autocomplete', 'usercreated', get_string('userviewreportas', 'tool_reportbuilder'), [], $options);
        $mform->setDefault('usercreated', $USER->id);
        $mform->addHelpButton('usercreated', 'userviewreportas', 'tool_reportbuilder');

        // Recipient selection, based on configured audiences.
        $mform->addElement('header', 'general', get_string('recipients', 'tool_reportbuilder'));
        $audienceelements = [];

        if (empty($audienceinstances)) {
            $html = html_writer::tag('div', get_string('noaudiences', 'tool_reportbuilder'), ['class' => 'alert alert-info']);
            $mform->addElement('static', 'noaudiences', '', $html);
        } else {
            foreach ($audienceinstances as $instance) {
                $audienceelements[] = $mform->createElement('checkbox', $instance->get_id(), $instance->get_description());
            }
        }

        $mform->addElement('group', 'audiences', '', $audienceelements, html_writer::div('', 'w-100 mb-2'));

        // Schedule subject/message content.
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
     * @return schedule_model
     */
    public function process_dynamic_submission() {
        global $USER;

        $data = $this->get_data();
        $data->message = $data->message['text'];
        $data->usercreated = $data->usercreated ?: $USER->id;
        $data->audiences = json_encode(array_keys($data->audiences));

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
    protected function check_access_for_dynamic_submission(): void {
        if ($schedule = $this->get_schedule()) {
            permission::require_can_edit_schedule($schedule);
        } else {
            permission::require_can_create_schedule($this->get_report());
        }
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_dynamic_submission(): void {
        $formdata = new \stdClass();

        if ($schedule = $this->get_schedule()) {
            $formdata = $schedule->to_record();
            $messagetext = $formdata->message;
            $formdata->message = [];
            $formdata->message['text'] = $messagetext;
            $formdata->message['format'] = FORMAT_HTML;

            // Load recipient fields.
            $audiences = json_decode($formdata->audiences);
            $formdata->audiences = array_fill_keys($audiences, 1);
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
        if (empty($data['audiences'])) {
            $errors['audiences'] = get_string('errornorecipients', 'tool_reportbuilder');
        }

        return $errors;
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    public function get_page_url_for_dynamic_submission(): \moodle_url {
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new \moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $id, 'entity' => 'schedule']);
    }

    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }
}

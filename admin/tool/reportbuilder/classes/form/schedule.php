<?php
// This file is part of Moodle - http://moodle.org/
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
 * Class containing the form for the schedule.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\form;

use tool_organisation\organisation;
use tool_reportbuilder\event\schedule_updated;
use tool_reportbuilder\helper;
use tool_reportbuilder\local\helpers\schedules;
use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die;

/**
 * Class schedule
 *
 * @package tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule extends modal_form {
    /** @var string Persistent class name. */
    protected static $persistentclass = 'tool_reportbuilder\\models\\schedules';

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {

        $mform = $this->_form;

        $reportid = $this->_ajaxformdata['reportid'];
        $id = $this->_ajaxformdata['id'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Basic fields.
        $mform->addElement('header', 'general', get_string('newschedule', 'tool_reportbuilder'));

        $mform->addElement('text', 'name', get_string('schedulename', 'tool_reportbuilder'), ['size' => 30]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        if (empty($reportid) || (int)$id === 0) {
            $sources = helper::get_reports_select();
            $sources = ['' => get_string('choose', 'tool_reportbuilder')] + $sources;
            $mform->addElement('select', 'reportid', get_string('report', 'tool_reportbuilder'), $sources);
            $mform->addRule('reportid', null, 'required', null, 'client');
        } else {
            $mform->addElement('hidden', 'reportid', $reportid);
            $mform->setType('reportid', PARAM_INT);
        }

        $formats = schedules::get_formats();
        $formats[0] = get_string('choose', 'tool_reportbuilder');
        ksort($formats);
        $mform->addElement('select', 'format', get_string('format', 'tool_reportbuilder'), $formats);
        $mform->addRule('format', get_string('error:mustselectformat', 'tool_reportbuilder'), 'regex', '/[^0]+/', 'client');
        $mform->setType('format', PARAM_INT);
        $mform->addRule('format', null, 'required', null, 'client');

        $mform->addElement('date_time_selector', 'scheduled', get_string('datetostart', 'tool_reportbuilder'),
            array('optional' => false));
        $mform->setDefault('scheduled', time());
        $mform->setType('scheduled', PARAM_INT);

        $recurrences = schedules::get_recurrences();
        $recurrences[0] = get_string('choose', 'tool_reportbuilder');
        ksort($recurrences);
        $mform->addElement('select', 'recurrence', get_string('recurrence', 'tool_reportbuilder'), $recurrences);
        $mform->addRule('recurrence', get_string('error:mustselectrecurrence', 'tool_reportbuilder'),
            'regex', '/[^0]+/', 'client');
        $mform->setType('recurrence', PARAM_INT);

        $mform->addElement('header', 'general', get_string('audience', 'tool_reportbuilder'));
        $deptoptions = organisation::get_all_departments_menu(['' => '']);
        $mform->addElement('selectgroups', 'departmentid', get_string('department', 'tool_organisation'), $deptoptions);
        $mform->setType('departmentid', PARAM_INT);

        $deptoptions = organisation::get_all_positions_menu(['' => '']);
        $mform->addElement('selectgroups', 'positionid', get_string('position', 'tool_organisation'), $deptoptions);
        $mform->setType('positionid', PARAM_INT);

        // Users selector.
        $options = array(
            'ajax' => 'tool_wp/form-potential-user-selector',
            'data-component' => 'tool_reportbuilder',
            'data-area' => 'usersmanually',
            'data-itemid' => 0,
            'multiple' => true,
            'valuehtmlcallback' => function($userid) {
                $userinfo = \core_user::get_user($userid);
                return '<span>' . fullname($userinfo) . '</span> <span><small>' . $userinfo->email . '</small></span>';
            }
        );

        $mform->addElement('autocomplete', 'users', get_string('addusers', 'tool_reportbuilder'), [], $options);

        $options = array(
            'ajax' => 'tool_wp/form-potential-user-selector',
            'data-component' => 'tool_reportbuilder',
            'data-area' => 'usersemails',
            'data-itemid' => 0,
            'multiple' => true,
            'tags' => true,
            'placeholder' => get_string('enteremail', 'tool_reportbuilder'),
            'showsuggestions' => false
        );

        $mform->addElement('html', \html_writer::start_div('autocomplete'));
        $mform->addElement('autocomplete', 'emails', get_string('addemails', 'tool_reportbuilder'), [], $options);
        $mform->addElement('html', \html_writer::end_div());

        $mform->addElement('html', \html_writer::div(
            get_string('shedulewarning', 'tool_reportbuilder'),
            'alert alert-warning py-3 p alert-dismissible border-warning fade show')
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

        $this->add_action_buttons();
    }

    /**
     * Process form submission
     *
     * @param \stdClass $data
     *
     * @return \tool_reportbuilder\local\models\schedules
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public function process(\stdClass $data) {
        global $USER;
        $messagetext = $data->message['text'];

        $audience = [];
        $audience['users'] = $data->users;
        $audience['departmentid'] = '';
        if (object_property_exists($data, 'departmentid')) {
            $audience['departmentid'] = $data->departmentid;
        }
        $audience['positionid'] = '';
        if (object_property_exists($data, 'positionid')) {
            $audience['positionid'] = $data->positionid;
        }

        $audience['emails'] = '';
        if (object_property_exists($data, 'emails')) {
            $audience['emails'] = $data->emails;
        }

        $unsetprops = [
            'departmentid',
            'positionid',
            'users',
            'emails',
            'message'
        ];

        foreach ($unsetprops as $unsetprop) {
            if (object_property_exists($data, $unsetprop)) {
                unset($data->{$unsetprop});
            }
        }

        $data->message = $messagetext;
        $data->usercreated = $USER->id;
        $data->audience = json_encode($audience);

        if (empty($data->id)) {
            return schedules::add_schedule($data);
        } else {
            /** @var \tool_reportbuilder\local\models\schedules $persistent */
            $persistent = new \tool_reportbuilder\local\models\schedules($data->id);
            $persistent->from_record($data);
            $persistent->update();

            // Trigger schedule updated event.
            $event = schedule_updated::create_from_object($persistent);
            $event->trigger();

            return $persistent;
        }
    }

    /**
     * Check if current user has access to this form, otherwise throw exception
     */
    public function require_access() {
        $context = \context_system::instance();
        return has_capability('tool/reportbuilder:edit', $context);
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_modal() {
        $scheduleid = $this->_ajaxformdata['id'];

        $formdata = new \stdClass();
        if ($scheduleid) {
            $formdata = \tool_reportbuilder\local\helpers\schedules::get_schedule($scheduleid)->to_record();
            $audiences = json_decode($formdata->audience);
            $messagetext = $formdata->message;
            $formdata->message = [];
            $formdata->message['text'] = $messagetext;
            $formdata->message['format'] = FORMAT_HTML;
            if ($audiences) {
                $formdata->departmentid = $audiences->departmentid;
                $formdata->positionid = $audiences->positionid;
                $formdata->users = $audiences->users;
                $formdata->emails = $audiences->emails;
            }
        }

        $this->set_data($formdata);
    }
}
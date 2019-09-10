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
 * Modal form to edit program users.
 *
 * @package   tool_program
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\form;

use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;

defined('MOODLE_INTERNAL') || die();

/**
 * Class edit_program_users_form_modal
 *
 * @package tool_program
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_program_users_form_modal extends \tool_wp\modal_form {
    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;
        $programid = $this->_ajaxformdata['id'];

        $mform->addElement('hidden', 'id', $programid);
        $mform->setType('id', PARAM_INT);

        // Select user autocomplete.
        $options = [
            'ajax' => 'tool_wp/form-potential-user-selector',
            'multiple' => true,
            'data-component' => 'tool_program',
            'data-area' => 'allocate',
            'data-itemid' => $programid ?: 0
        ];
        $mform->addElement('autocomplete', 'userlist', get_string('userlist', 'tool_program'), [], $options);
        $mform->addRule('userlist', get_string('nousersselected', 'tool_program'), 'required', null, 'client');
        $mform->addHelpButton('userlist', 'userlist', 'tool_program');

        // Status.
        $choices = [
            constants::STATUS_OVERRIDE_DEFAULT => get_string('active'),
            constants::STATUS_OVERRIDE_SUSPENDED => get_string('suspended'),
        ];
        $mform->addElement('select', 'status', get_string('status', 'tool_program'), $choices);
        $mform->setDefault('status', constants::STATUS_OVERRIDE_DEFAULT);
        $mform->addHelpButton('status', 'status', 'tool_program');

        $this->add_action_buttons();
    }

    /**
     * Require capabilities.
     */
    public function require_access(): void {
        $program = new program($this->_ajaxformdata['id']);
        permission::require_can_allocate_anybody($program);
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_modal(): void {
        $formdata = [
            'programid' => $this->_ajaxformdata['id']
        ];
        $this->set_data($formdata);
    }

    /**
     * Process data submited by the form.
     *
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
        $program = new program($data->id);
        foreach ($data->userlist as $userid) {
            if (permission::can_allocate_user($program, $userid)) {
                $programuserdata = (object) [
                    'userid' => $userid,
                    'allocationtype' => constants::ALLOCATION_MANUAL,
                    'certificationid' => 0,
                    'status' => $data->status
                ];
                api::allocate_user($program, $programuserdata);
            }
        }
    }
}

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
 * Class tool_certification\edit_certification_users_form_modal
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;

use context_system;

defined('MOODLE_INTERNAL') || die();

/**
 * Class edit_certification_users_form_modal
 *
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_certification_users_form_modal extends \tool_wp\modal_form {
    /** @var certification */
    protected $certification;

    /**
     * Current certification
     *
     * @return certification
     */
    protected function get_certification(): certification {
        if (!$this->certification) {
            $this->certification = new certification((int)$this->_ajaxformdata['id']);
        }
        return $this->certification;
    }

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {
        $mform = $this->_form;
        $certificationid = $this->get_certification()->get('id');

        $mform->addElement('hidden', 'id', $certificationid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'main', get_string('allocateusers', 'tool_certification'));
        $options = array(
            'ajax' => 'tool_wp/form-potential-user-selector',
            'multiple' => true,
            'data-component' => 'tool_certification',
            'data-area' => 'allocate',
            'data-itemid' => $certificationid
        );
        $mform->addElement('autocomplete', 'userlist', get_string('selectusers', 'enrol_manual'), array(), $options);
        $mform->addRule('userlist', get_string('nousersselected', 'tool_certification'), 'required', null, 'client');

        // Status.
        $choices = [
            constants::STATUS_OVERRIDE_DEFAULT => get_string('active'),
            constants::STATUS_OVERRIDE_SUSPENDED => get_string('suspended'),
        ];
        $mform->addElement('select', 'status', get_string('status', 'tool_certification'), $choices);
        $mform->setDefault('status', constants::STATUS_OVERRIDE_DEFAULT);

        $this->add_action_buttons();
    }

    /**
     * Require capabilities.
     */
    public function require_access(): void {
        permission::require_can_allocate_anybody($this->get_certification());
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_modal() {
        $formdata = [
            'id' => $this->get_certification()->get('id'),
        ];
        $this->set_data($formdata);
    }

    /**
     * Process data submited by the form.
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
        $certification = $this->get_certification();

        foreach ($data->userlist as $userid) {
            if (permission::can_allocate_user($certification, $userid)) {
                $certuserdata = (object) [
                    'certificationid' => $data->id,
                    'userid' => $userid,
                    'allocationtype' => constants::ALLOCATION_MANUAL,
                    'status' => $data->status
                ];
                api::allocate_user($certification, $certuserdata);
            }
        }
    }
}

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
 * Modal form to edit program users.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\form;

use core_form\dynamic_form;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;

defined('MOODLE_INTERNAL') || die();

/**
 * Class edit_program_users_form_modal
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_program_users_form_modal extends dynamic_form {
    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;
        $programid = $this->optional_param('id', 0, PARAM_INT);

        $mform->addElement('hidden', 'id', $programid);
        $mform->setType('id', PARAM_INT);

        // Select user autocomplete.
        $options = [
            'ajax' => 'tool_wp/form-potential-user-selector',
            'multiple' => true,
            'data-component' => 'tool_program',
            'data-area' => 'allocate',
            'data-itemid' => $programid
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
    }

    /**
     * Require capabilities.
     */
    public function check_access_for_dynamic_submission(): void {
        $program = new program($this->optional_param('id', 0, PARAM_INT));
        permission::require_can_allocate_anybody($program);
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_dynamic_submission(): void {
        $formdata = [
            'programid' => $this->optional_param('id', 0, PARAM_INT)
        ];
        $this->set_data($formdata);
    }

    /**
     * Process data submited by the form.
     *
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
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

    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'id' => $id,
        ]);
    }
}

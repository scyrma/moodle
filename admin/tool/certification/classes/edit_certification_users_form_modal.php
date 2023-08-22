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
 * Class tool_certification\edit_certification_users_form_modal
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use core_form\dynamic_form;

/**
 * Class edit_certification_users_form_modal
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_certification_users_form_modal extends dynamic_form {
    /** @var certification */
    protected $certification;

    /**
     * Current certification
     *
     * @return certification
     */
    protected function get_certification(): certification {
        if (!$this->certification) {
            $this->certification = new certification($this->optional_param('id', 0, PARAM_INT));
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

        $options = array(
            'ajax' => 'tool_wp/form-potential-user-selector',
            'multiple' => true,
            'data-component' => 'tool_certification',
            'data-area' => 'allocate',
            'data-itemid' => $certificationid
        );
        $mform->addElement('autocomplete', 'userlist', get_string('userlist', 'tool_certification'), array(), $options);
        $mform->addRule('userlist', get_string('nousersselected', 'tool_certification'), 'required', null, 'client');
        $mform->addHelpButton('userlist', 'userlist', 'tool_certification');

        // Status.
        $choices = [
            constants::STATUS_OVERRIDE_DEFAULT => get_string('active'),
            constants::STATUS_OVERRIDE_SUSPENDED => get_string('suspended'),
        ];
        $mform->addElement('select', 'status', get_string('status', 'tool_certification'), $choices);
        $mform->setDefault('status', constants::STATUS_OVERRIDE_DEFAULT);
        $mform->addHelpButton('status', 'userstatus', 'tool_certification');
    }

    /**
     * Require capabilities.
     */
    public function check_access_for_dynamic_submission(): void {
        permission::require_can_allocate_anybody($this->get_certification());
    }

    /**
     * Set Data for the modal form.
     */
    public function set_data_for_dynamic_submission(): void {
        $formdata = [
            'id' => $this->get_certification()->get('id'),
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
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'id' => $this->get_certification()->get('id'),
        ]);
    }
}

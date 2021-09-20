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
 * Form to add courses to program.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\form;

use core_form\dynamic_form;
use tool_program\api;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_set;

defined('MOODLE_INTERNAL') || die();

/**
 * Class edit_program_add_courses_form
 *
 * @package tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_program_add_courses_form extends dynamic_form {

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'parentsetid');
        $mform->setType('parentsetid', PARAM_INT);

        // Courses picker.
        $set = new program_set($this->get_parent_set_id());
        $options = [
            'multiple' => true,
            'exclude' => $set->get_courses_ids(),
        ];
        $mform->addElement('course', 'courseslist', get_string('addcourseslist', 'tool_program'), $options);
        $mform->addRule('courseslist', null, 'required');
        $mform->addHelpButton('courseslist', 'addcourseslist', 'tool_program');
        $mform->addRule('courseslist', get_string('missingcourse', 'tool_program'), 'required', null, 'client');
    }

    /**
     * Require access.
     */
    public function check_access_for_dynamic_submission(): void {
        $programset = program_set::get_record(['id' => $this->get_parent_set_id()]);
        $program = new program($programset->get('programid'));
        permission::require_can_edit_details($program);
    }

    /**
     * Process form data.
     *
     * @return void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        if (!empty($data->courseslist)) {
            foreach ($data->courseslist as $courseid) {
                api::add_course_to_parent_set($data->parentsetid, $courseid);
            }
        }
    }

    /**
     * Sets data on the modal form.
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data(['parentsetid' => $this->get_parent_set_id()]);
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
     * Return parent set id
     *
     * @return int
     */
    protected function get_parent_set_id(): int {
        return $this->optional_param('parentsetid', 0, PARAM_INT);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
            'parentsetid' => $this->get_parent_set_id(),
        ]);
    }
}

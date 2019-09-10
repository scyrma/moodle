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
 * Form to add courses to program.
 *
 * @package   tool_program
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\form;

use tool_program\api;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_set;

defined('MOODLE_INTERNAL') || die();

/**
 * Class edit_program_add_courses_form
 *
 * @package tool_program
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_program_add_courses_form extends \tool_wp\modal_form {

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'parentsetid');
        $mform->setType('parentsetid', PARAM_INT);

        // Courses picker.
        $set = new program_set($this->_ajaxformdata['parentsetid']);
        $options = [
            'multiple' => true,
            'exclude' => $set->get_courses_ids(),
        ];
        $mform->addElement('course', 'courseslist', get_string('addcourseslist', 'tool_program'), $options);
        $mform->addRule('courseslist', null, 'required');
        $mform->addHelpButton('courseslist', 'addcourseslist', 'tool_program');
        $mform->addRule('courseslist', get_string('missingcourse', 'tool_program'), 'required', null, 'client');

        $this->add_action_buttons();
    }

    /**
     * Require access.
     */
    public function require_access(): void {
        $programset = program_set::get_record(['id' => $this->_ajaxformdata['parentsetid']]);
        $program = new program($programset->get('programid'));
        permission::require_can_edit_details($program);
    }

    /**
     * Process form data.
     *
     * @param \stdClass $data
     * @return void
     */
    public function process(\stdClass $data): void {
        if (!empty($data->courseslist)) {
            foreach ($data->courseslist as $courseid) {
                api::add_course_to_parent_set($data->parentsetid, $courseid);
            }
        }
    }

    /**
     * Sets data on the modal form.
     */
    public function set_data_for_modal(): void {
        $this->set_data(['parentsetid' => $this->_ajaxformdata['parentsetid']]);
    }
}

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
 * Form to edit add sets to program.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\form;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;
use stdClass;
use tool_program\api;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_set;

/**
 * Class edit_program_add_set_form
 *
 * @package tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_program_add_set_form extends \tool_wp\modal_form {
    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'parentsetid');
        $mform->setType('parentsetid', PARAM_INT);
        // Set name text input.
        $mform->addElement('text', 'setname', get_string('setname', 'tool_program'));
        $mform->setType('setname', PARAM_TEXT);
        $mform->addHelpButton('setname', 'setname', 'tool_program');
        $mform->addRule('setname', get_string('missingsetname', 'tool_program'), 'required', null, 'client');

        // Courses picker.
        $options = [
            'multiple' => true,
            'exclude' => [],
        ];
        $mform->addElement('course', 'addsetcourseslist', get_string('selectcourses', 'tool_program'), $options);
        $mform->addHelpButton('addsetcourseslist', 'addcourseslist', 'tool_program');

        // Completion criteria selector.
        $allinorderstr = get_string('allinorder', 'tool_program');
        $allinanyorderstr = get_string('allinanyorder', 'tool_program');
        $atleaststr = get_string('atleast', 'tool_program');
        $completionstr = get_string('completion', 'tool_program');
        $criteriaoptions = [
            program_set::COMPLETION_ALL_IN_ORDER => $allinorderstr,
            program_set::COMPLETION_ALL_IN_ANY_ORDER => $allinanyorderstr,
            program_set::COMPLETION_AT_LEAST => $atleaststr,
        ];
        $group = [];
        $group[] =& $mform->createElement('select', 'completioncriteriaset', null, $criteriaoptions);
        $group[] =& $mform->createElement('text', 'completionatleastset', null, 'maxlength="3" size="3"');
        $mform->addGroup($group, 'completioncriteriagroup', $completionstr, ' ', false);
        $mform->setType('completioncriteriaset', PARAM_INT);
        $mform->setDefault('completioncriteriaset', program_set::COMPLETION_ALL_IN_ORDER);
        $mform->setType('completionatleastset', PARAM_INT);
        $mform->setDefault('completionatleastset', 1);
        $mform->hideIf('completionatleastset', 'completioncriteriaset', 'noteq', program_set::COMPLETION_AT_LEAST);
        $mform->disabledIf('completionatleastset', 'completioncriteriaset', 'noteq', program_set::COMPLETION_AT_LEAST);
        $mform->addHelpButton('completioncriteriagroup', 'completioncriteriagroup', 'tool_program');
        $mform->addRule('completioncriteriagroup', get_string('missingcompletion', 'tool_program'), 'required', null, 'client');
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
     * @param stdClass $data
     * @return mixed|void
     */
    public function process(stdClass $data): void {
        $completion = (object) [
            'completioncriteria' => $data->completioncriteriaset,
            'completionatleast' => $data->completionatleastset
        ];
        $newset = api::add_set_to_parent_set($data->parentsetid, $data->setname, $completion);
        if (!$newset->get('id')) {
            throw new moodle_exception('errorcantcreateset', 'tool_program');
        }
        if (!empty($data->addsetcourseslist)) {
            foreach ($data->addsetcourseslist as $courseid) {
                api::add_course_to_parent_set($newset->get('id'), $courseid);
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

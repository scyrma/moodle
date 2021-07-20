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
 * Form to edit add sets to program.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\form;

defined('MOODLE_INTERNAL') || die();

use core_form\dynamic_form;
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
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_program_add_set_form extends dynamic_form {
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
    public function check_access_for_dynamic_submission(): void {
        $programset = program_set::get_record(['id' => $this->get_parent_set_id()]);
        $program = new program($programset->get('programid'));
        permission::require_can_edit_details($program);
    }

    /**
     * Process form data.
     *
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
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
    public function set_data_for_dynamic_submission(): void {
        $this->set_data(['parentsetid' => $this->get_parent_set_id()]);
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
            'parentsetid' => $this->get_parent_set_id(),
        ]);
    }
}

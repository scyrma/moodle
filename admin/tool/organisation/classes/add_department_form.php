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
 * Class add_department_form
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class add_department_form
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class add_department_form extends modal_form {

    /** @var department */
    protected $parent = null;

    /** @var department */
    protected $department = null;

    /**
     * Parent department
     *
     * @return null|department
     */
    protected function get_parent(): ?department {
        if (!$this->parent && !empty($this->_ajaxformdata['parentid'])) {
            $this->parent = (new department_manager())->get_department($this->_ajaxformdata['parentid']);
        }
        return $this->parent;
    }

    /**
     * Current department
     *
     * @return null|department
     */
    protected function get_department(): ?department {
        if (!$this->department && !empty($this->_ajaxformdata['id'])) {
            $this->department = (new department_manager())->get_department($this->_ajaxformdata['id']);
        }
        return $this->department;
    }

    /**
     * Form definition
     */
    public function definition() {

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('departmentname', 'tool_organisation'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'idnumber', get_string('departmentidnumber', 'tool_organisation'));
        $mform->setType('idnumber', PARAM_RAW);

        $mform->addElement('editor', 'description_editor',
            get_string('departmentdescription', 'tool_organisation'), ['rows' => 3],
            department_manager::get_description_editor_options());

        // Add 'Parent' autocomplete field for non framework departments in edit forms.
        $department = $this->get_department();
        if ($department && !$this->is_department_framework_form()) {
            $parentstr = get_string('parent', 'tool_organisation');
            $params = null;
            $options = [
                'ajax' => 'tool_organisation/form_potential_parent_department_selector',
                'multiple' => false,
                'data-departmentid' => $department->get('id'),
                'valuehtmlcallback' => function ($value) {
                    $department = (new department_manager())->get_department($value);
                    return $department->get_formatted_name();
                }
            ];
            $mform->addElement('autocomplete', 'parentid', $parentstr, $params, $options);
            $mform->setType('parentid', PARAM_INT);
            $mform->addRule('parentid', get_string('required'), 'required', null, 'client');
        } else {
            $mform->addElement('hidden', 'parentid');
            $mform->setType('parentid', PARAM_INT);
        }
    }

    /**
     * Check access
     */
    public function require_access() {
        if ($department = $this->get_department()) {
            permission::require_can_edit_department($department);
        } else {
            permission::require_can_create_department($this->get_parent());
        }
    }

    /**
     * Prepare the department record before calling set_data()
     *
     * @param department $department
     * @return \stdClass
     */
    protected function prepare_data_for_form(department $department) {
        $record = $department->to_record();
        $record = file_prepare_standard_editor($record, 'description', department_manager::get_description_editor_options(),
            \context_system::instance(), 'tool_organisation', department_manager::get_description_filearea(), $record->id);
        return $record;
    }

    /**
     * Prepare form data before storing in the db
     *
     * @param \stdClass $data
     * @param int $id
     * @return object|\stdClass
     */
    protected function prepare_data_for_storing(\stdClass $data, int $id) {
        $record = (object)[
            'name' => $data->name,
            'idnumber' => $data->idnumber
        ];
        if ($id) {
            $record->description_editor = $data->description_editor;
            $record = file_postupdate_standard_editor($record, 'description', department_manager::get_description_editor_options(),
                \context_system::instance(), 'tool_organisation', department_manager::get_description_filearea(), $id);
            unset($record->descriptiontrust);
        } else {
            $record->parentid = $data->parentid;
        }
        return $record;
    }

    /**
     * Process form submission
     *
     * @param \stdClass $data
     * @return mixed|void
     */
    public function process(\stdClass $data) {
        $manager = new department_manager();
        $id = $data->id;
        if (!$id) {
            $department = $manager->create_department($this->prepare_data_for_storing($data, $id));
            // Now post-process and update description using persistent method (without triggering event).
            $record = $this->prepare_data_for_storing($data, $department->get('id'));
            $department->set('description', $record->description);
            $department->set('descriptionformat', $record->descriptionformat);
            $department->save();
        } else {
            $manager->update_department($id, $this->prepare_data_for_storing($data, $id));
            if (!$this->is_department_framework_form()) {
                (new department_manager())->move($id, $data->parentid);
            }
        }
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_modal() {
        if ($department = $this->get_department()) {
            // Edit department form.
            $this->set_data($this->prepare_data_for_form($department));
        } else if ($parent = $this->get_parent()) {
            // Create new department form with a parent department specified.
            $this->set_data(['parentid' => $parent->get('id')]);
        }
    }

    /**
     * Check whether this form is for department framework or child positions
     *
     * @return bool
     */
    protected function is_department_framework_form(): bool {
        $department = $this->get_department();
        return (!$department && !$this->get_parent()) ||
            ($department && $department->is_framework());
    }
}

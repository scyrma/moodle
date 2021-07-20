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
 * Class add_department_form
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use core_form\dynamic_form;

/**
 * Class add_department_form
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class add_department_form extends dynamic_form {

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
        $parentid = $this->optional_param('parentid', 0, PARAM_INT);
        if (!$this->parent && $parentid) {
            $this->parent = (new department_manager())->get_department($parentid);
        }
        return $this->parent;
    }

    /**
     * Current department
     *
     * @return null|department
     */
    protected function get_department(): ?department {
        $id = $this->optional_param('id', 0, PARAM_INT);
        if (!$this->department && $id) {
            $this->department = (new department_manager())->get_department($id);
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
    protected function check_access_for_dynamic_submission(): void {
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
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
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
    public function set_data_for_dynamic_submission(): void {
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

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    public function get_page_url_for_dynamic_submission(): \moodle_url {
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new \moodle_url('/admin/tool/organisation/index.php', ['id' => $id, 'entity' => 'department']);
    }

    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }
}

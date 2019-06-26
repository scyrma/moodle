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
 * Class add_position_form
 *
 * @package     tool_organisation
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation;

use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/admin/tool/organisation/lib.php');

/**
 * Class add_position_form
 *
 * @package     tool_organisation
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_position_form extends modal_form {

    /**
     * Form definition
     */
    public function definition() {
        global $PAGE, $OUTPUT;
        $mform = $this->_form;

        $mform->addElement('hidden', 'parentid');
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('positionname', 'tool_organisation'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'idnumber', get_string('positionidnumber', 'tool_organisation'));
        $mform->setType('idnumber', PARAM_RAW);

        $mform->addElement('editor', 'description_editor',
            get_string('positiondescription', 'tool_organisation'), ['rows' => 3],
            position_manager::get_description_editor_options());
        // This hardcoded permission should not appear for position frameworks.
        if (!$this->is_position_framework_form()) {
            $globalpermissions = organisation::get_global_manager_permissions();
            $departmentpermissions = organisation::get_department_manager_permissions();
            $mform->addElement('header', 'positionpermissions', get_string('positionpermissions', 'tool_organisation'));
            $mform->addElement('advcheckbox', 'globalmanager', '',
                get_string('globalmanager', 'tool_organisation'),
                array('class' => "permission-header global"), array(0, 1));
            $mform->addHelpButton('globalmanager', 'globalmanager', 'tool_organisation');
            $i = 0;
            foreach ($globalpermissions as $index => $globalpermission) {
                $title = '<span>'.$OUTPUT->render($globalpermission['icon']).'</span>' . $globalpermission['title'];
                $mform->addElement('advcheckbox', "globmgrpermission[$i]", '', $title ,
                    array('class' => "permission global"), array(0, $index));
                $mform->addHelpButton("globmgrpermission[$i]", $globalpermission['name'], 'tool_organisation');
                $mform->hideIf("globmgrpermission[$i]", 'globalmanager', 'notchecked');
                $i++;
            }
            $mform->addElement('advcheckbox', 'departmentmanager', '',
                get_string('departmentmanager', 'tool_organisation'),
                array('class' => "permission-header department"), array(0, 1));
            $mform->addHelpButton('departmentmanager', 'departmentmanager', 'tool_organisation');
            $i = 0;
            foreach ($departmentpermissions as $index => $departmentpermission) {
                $title = '<span>'.$OUTPUT->render($departmentpermission['icon']).'</span>' . $departmentpermission['title'];
                $mform->addElement('advcheckbox', "deptmgrpermission[$i]", '', $title ,
                    array('class' => "permission department"), array(0, $index));
                $mform->addHelpButton("deptmgrpermission[$i]", $departmentpermission['name'], 'tool_organisation');
                $mform->hideIf("deptmgrpermission[$i]", 'departmentmanager', 'notchecked');
                $i++;
            }
        }

        $PAGE->requires->js_call_amd('tool_organisation/forms', 'init');
        // Add the buttons just in case we ever use this form not inside a modal.
        $this->add_action_buttons();
        $this->set_display_vertical();
    }

    /**
     * Check access
     */
    public function require_access() {
        return require_capability('tool/organisation:managepositions', \context_system::instance());
    }

    /**
     * Prepare the position record before calling set_data()
     *
     * @param position $position
     * @return \stdClass
     */
    protected function prepare_data_for_form(position $position) {
        $record = $position->to_record();
        $record = file_prepare_standard_editor($record, 'description', position_manager::get_description_editor_options(),
            \context_system::instance(), 'tool_organisation', position_manager::get_description_filearea(), $record->id);

        if (!$this->is_position_framework_form()) {
            $globalpermissions = organisation::get_global_manager_permissions();
            $departmentpermissions = organisation::get_department_manager_permissions();
            $i = 0;
            if ($record->globalpermissions) {
                foreach ($globalpermissions as $index => $globalpermission) {
                    if ($record->globalpermissions & $index) {
                        $record->globmgrpermission[$i] = $index;
                    }
                    $i++;
                }
            }
            $i = 0;
            if ($record->departmentpermissions) {
                foreach ($departmentpermissions as $index => $departmentpermission) {
                    if ($record->departmentpermissions & $index) {
                        $record->deptmgrpermission[$i] = $index;
                    }
                    $i++;
                }
            }
        }
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
            'idnumber' => $data->idnumber,
        ];
        if (!$this->is_position_framework_form()) {
            $record->globalmanager = $data->globalmanager;
            $record->globalpermissions = $data->globalmanager ? array_sum($data->globmgrpermission) : 0;
            $record->departmentmanager = $data->departmentmanager;
            $record->departmentpermissions = $data->departmentmanager ? array_sum($data->deptmgrpermission) : 0;
        }
        if ($id) {
            $record->description_editor = $data->description_editor;
            $record = file_postupdate_standard_editor($record, 'description', position_manager::get_description_editor_options(),
                \context_system::instance(), 'tool_organisation', position_manager::get_description_filearea(), $id);
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
        $manager = new position_manager();
        $id = $data->id;
        if (!$id) {
            $position = $manager->create_position($this->prepare_data_for_storing($data, $id));
            // Now post-process and update description using persistent method (without triggering event).
            $record = $this->prepare_data_for_storing($data, $position->get('id'));
            $position->set('description', $record->description);
            $position->set('descriptionformat', $record->descriptionformat);
            $position->save();
        } else {
            $manager->update_position($id, $this->prepare_data_for_storing($data, $id));
        }
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_modal() {
        $data = (object)$this->_ajaxformdata;
        if (!empty($data->id)) {
            // Edit position form.
            $manager = new position_manager();
            $position = $manager->get_position($data->id);
            $this->set_data($this->prepare_data_for_form($position));
        } else if (!empty($data->parentid)) {
            // Create new position form with a parent position specified.
            $manager = new position_manager();
            $parent = $manager->get_position($data->parentid); // Validate that parent exists.
            $this->set_data(['parentid' => $parent->get('id')]);
        }
    }

    /**
     * Check whether this form is for position framework or child positions
     */
    protected function is_position_framework_form() {
        $data = (object)$this->_ajaxformdata;
        $manager = new position_manager();
        $isframework = !empty($data->id) ? $manager->get_position($data->id)->is_framework() : empty($data->parentid);
        return $isframework;
    }
}

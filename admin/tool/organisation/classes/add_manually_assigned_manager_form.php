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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_organisation;

use context_system;
use core_form\dynamic_form;
use html_writer;
use moodle_url;
use stdClass;
use tool_organisation\local\helpers\user_manager;
use tool_organisation\local\persistent\user_manager as user_manager_model;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/admin/tool/organisation/lib.php');

/**
 * Class add_manually_assigned_manager_form
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class add_manually_assigned_manager_form extends dynamic_form {

    /**
     * Form definition
     */
    public function definition(): void {
        global $OUTPUT;
        $mform = $this->_form;
        $mform->setDisableShortforms();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'managerid');
        $mform->setType('managerid', PARAM_INT);

        $action = $this->optional_param('title', '', PARAM_RAW);
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_RAW);

        $htmltext = $this->optional_param('htmltext', '', PARAM_RAW);
        $mform->addElement('hidden', 'htmltext', $htmltext);
        $mform->setType('htmltext', PARAM_RAW);

        if (!empty($htmltext)) {
            $mform->addElement('html', html_writer::div(get_string($htmltext, 'tool_organisation'), 'pb-6'));
        }

        // Users selector, send the userid as itemid in order to retrieve all users except the current one.
        $userid = $this->optional_param('userid', 0, PARAM_RAW);
        $options = [
            'ajax' => 'tool_wp/form-potential-user-selector',
            'data-component' => 'tool_organisation',
            'data-area' => 'manuallyassigned',
            'data-itemid' => $userid,
            'multiple' => true,
        ];

        $mform->addElement('autocomplete', 'users', get_string('users', 'tool_organisation'), [], $options);
        $mform->addRule('users', get_string('missingusers', 'tool_organisation'), 'required', null, 'client');

        // Set the manually assigned manager permissions checkboxes.
        $manuallyassignedmanagerpermissions = organisation::get_manually_assigned_manager_permissions();
        $mform->addElement('header', 'manmgrpermissions', get_string('positionpermissions', 'tool_organisation'));
        foreach ($manuallyassignedmanagerpermissions as $index => $permission) {
            $title = html_writer::span($OUTPUT->render($permission['icon'])) . $permission['title'];
            $mform->addElement('advcheckbox', "manmgrpermission[$index]", '', $title ,
                ['class' => "permission global"], [0, $index]);
            $mform->addHelpButton("manmgrpermission[$index]", $permission['name'], 'tool_organisation');
        }
    }

    /**
     * Modify elements after data is available.
     */
    public function definition_after_data(): void {
        $mform = $this->_form;
        $managerid = $mform->getElementValue('managerid');
        if ($managerid) {
            $mform->removeElement('users');
        }
    }

    /**
     * Check access
     */
    protected function check_access_for_dynamic_submission(): void {
        permission::require_can_assign_manually_assigned_manager();
    }

    /**
     * Process form submission
     *
     * @return void
     */
    public function process_dynamic_submission(): void {
        $data = $this->get_data();
        $manager = new stdClass();
        $mgrassignment = new user_manager();

        // If id is not set, then it is a new record.
        if (empty($data->id)) {
            $manager->permissions = 0;
            foreach ($data->manmgrpermission as $index => $mgrpermission) {
                if (!empty($mgrpermission)) {
                    $manager->permissions = $manager->permissions + $index;
                }
            }
            $users = !empty($data->users) ? $data->users : [];
            foreach ($users as $userid) {
                // Verify if the action is assignmanager and sent the employeeid and manager object in right way.
                if ($data->action === "assignmanager") {
                    $manager->id = $userid;
                    $mgrassignment->add_assigned_manager($data->userid, $manager);
                } else {
                    $manager->id = $data->userid;
                    $mgrassignment->add_assigned_manager($userid, $manager);
                }
            }
        } else {
            $manager->id = $data->managerid;
            $manager->permissions = array_sum($data->manmgrpermission);
            $mgrassignment->update_assigned_manager($data->userid, $manager, $manager->id);
        }
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        $data = (object)$this->_ajaxformdata;
        $this->set_data(['userid' => $data->userid]);
        if (!empty($data->id)) {
            $this->set_data(['id' => $data->id]);
            $this->set_data(['managerid' => $data->managerid]);
            $mamrecord = user_manager_model::get_record(['userid' => $data->userid, 'managerid' => $data->managerid]);
            $manuallyassignedmanagerpermissions = organisation::get_manually_assigned_manager_permissions();
            foreach ($manuallyassignedmanagerpermissions as $index => $mgrpermission) {
                if ($mamrecord->get('permissions') & $index) {
                    $this->set_data(["manmgrpermission[$index]" => $index]);
                }
            }
        }
    }

    /**
     * Performs validation of the form information
     *
     * @param array $data
     * @param array $files
     * @return array $errors An array of $errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    public function get_page_url_for_dynamic_submission(): moodle_url {
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new moodle_url('/admin/tool/organisation/user.php', ['id' => $id]);
    }

    /**
     * Returns context where this form is used
     *
     * @return context_system
     */
    public function get_context_for_dynamic_submission(): context_system {
        return context_system::instance();
    }
}

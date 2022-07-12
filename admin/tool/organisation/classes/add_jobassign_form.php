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
 * Class add_position_form
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use core_form\dynamic_form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/organisation/lib.php');

/**
 * Class add_position_form
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class add_jobassign_form extends dynamic_form {

    /** @var job */
    protected $job;

    /**
     * Current job being edited
     *
     * @return job
     */
    protected function get_job(): ?job {
        $id = $this->optional_param('id', 0, PARAM_INT);
        if (!$this->job && $id) {
            $this->job = (new job_manager())->get_job(['id' => $id]);
        }
        return $this->job;
    }

    /**
     * Form definition
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        // Users selector.
        $options = array(
            'ajax' => 'tool_wp/form-potential-user-selector',
            'data-component' => 'tool_organisation',
            'data-area' => 'jobassign',
            'data-itemid' => 0,
            'multiple' => true,
        );

        // This variable will be used to avoid printing help icons when editing a job.
        $job = $this->get_job();

        $mform->addElement('autocomplete', 'users', get_string('users', 'tool_organisation'), array(), $options);
        if (!$job) {
            $mform->addHelpButton('users', 'users', 'tool_organisation');
        }

        // Position.
        $posoptions = organisation::get_all_positions_menu(['' => '']);
        $mform->addElement('selectgroups', 'positionid', get_string('position', 'tool_organisation'), $posoptions);
        $mform->setType('positionid', PARAM_INT);
        if (!$job) {
            $mform->addHelpButton('positionid', 'position', 'tool_organisation');
        }

        // Department.
        $deptoptions = organisation::get_all_departments_menu(['' => '']);
        $mform->addElement('selectgroups', 'departmentid', get_string('department', 'tool_organisation'), $deptoptions);
        if (!$job) {
            $mform->addHelpButton('departmentid', 'department', 'tool_organisation');
        }
        $mform->setType('departmentid', PARAM_INT);

        // Start and end dates.
        $options = ['timezone' => $CFG->timezone];
        $mform->addElement('date_selector', 'startdate', get_string('startdate', 'tool_organisation'), $options);
        $mform->addHelpButton('startdate', 'startdate', 'tool_organisation');
        $mform->setDefault('startdate', time());

        $options['optional'] = true;
        $mform->addElement('date_selector', 'enddate', get_string('enddate', 'tool_organisation'), $options);
        $mform->addHelpButton('enddate', 'enddate', 'tool_organisation');
    }

    /**
     * Modify elements after data is available.
     */
    public function definition_after_data() {
        $mform = $this->_form;
        $id = $mform->getElementValue('id');
        $userid = $mform->getElementValue('userid');
        if ($id) {
            // Editing an existing job.
            $mform->getElement('positionid')->freeze();
            $mform->getElement('departmentid')->freeze();
            $mform->removeElement('users');
        } else {
            if ($userid) {
                // Adding a job for a given user.
                $mform->removeElement('users');
            } else {
                $mform->addRule('users', get_string('missingusers', 'tool_organisation'), 'required', null, 'client');
            }
            $mform->addRule('positionid', get_string('missingposition', 'tool_organisation'), 'required', null, 'client');
            $mform->addRule('departmentid', get_string('missingdepartment', 'tool_organisation'), 'required', null, 'client');
        }
    }

    /**
     * Check access
     */
    protected function check_access_for_dynamic_submission(): void {
        $userid = $this->optional_param('userid', 0, PARAM_INT);
        if ($job = $this->get_job()) {
            permission::require_can_edit_job($job);
        } else if ($userid) {
            permission::require_can_assign_job_to_user($userid);
        } else {
            permission::require_can_assign_job_to_anybody();
        }
    }

    /**
     * Prepare the position record before calling set_data()
     *
     * @param job $job
     * @return \stdClass
     */
    protected function prepare_data_for_form(job $job) {
        $record = $job->to_record();
        $record->users = [$record->userid];
        return $record;
    }

    /**
     * Prepare form data before storing in the db
     *
     * @param \stdClass $data
     * @param int $id id of existing job or 0 if the job is about to be created
     * @return object|\stdClass
     */
    protected function prepare_data_for_storing(\stdClass $data, int $id = 0) {

        $record = [
            'startdate' => $data->startdate,
            'enddate' => isset($data->enddate) ? $data->enddate : 0,
        ];
        if ($id) {
            $record['id'] = $id;
        } else {
            $record += [
                'userid' => $data->userid,
                'positionid' => $data->positionid,
                'departmentid' => $data->departmentid,
            ];
        }
        return (object)$record;

    }

    /**
     * Process form submission
     *
     * @return mixed|void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $manager = new job_manager();
        $id = $data->id;
        if (!$id) {
            $users = !empty($data->userid) ? [$data->userid] : (!empty($data->users) ? $data->users : []);
            foreach ($users as $userid) {
                if (permission::can_assign_job_to_user($userid)) {
                    $data->userid = $userid;
                    $manager->create_job($this->prepare_data_for_storing($data, 0));
                }
            }
        } else {
            $manager->update_job($id, $this->prepare_data_for_storing($data, $id));
        }
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        $data = (object)$this->_ajaxformdata;
        if ($job = $this->get_job()) {
            // Edit job form.
            $this->set_data($this->prepare_data_for_form($job));
        } else if (!empty($data->userid)) {
            // Create new job form with a tenantid and userid.
            $this->set_data(['userid' => $data->userid]);
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
        if (isset($data['enddate']) && !empty($data['enddate'])) {
            if ($data['startdate'] > $data['enddate']) {
                $errors['enddate'] = get_string('validationmsgedateonsdate', 'tool_organisation');
            }
        }
        return $errors;
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    public function get_page_url_for_dynamic_submission(): \moodle_url {
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new \moodle_url('/admin/tool/organisation/index.php', ['id' => $id, 'entity' => 'job']);
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

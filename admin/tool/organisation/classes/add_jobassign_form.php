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
use html_writer;

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
        $mform->setDisableShortforms();
        // Add empty header for consistency.
        $mform->addElement('header', 'hdr', '');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $action = $this->optional_param('action', '', PARAM_RAW);
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_RAW);

        if ($action == 'transfertojob') {
            $mform->addElement('hidden', 'currentstartdate');
            $mform->setType('currentstartdate', PARAM_INT);

            $mform->addElement('hidden', 'currentpositionid');
            $mform->setType('currentpositionid', PARAM_INT);

            $mform->addElement('hidden', 'currentdepartmentid');
            $mform->setType('currentdepartmentid', PARAM_INT);
        }

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

        if ($action !== 'editjobdates' && $action !== 'setjobfinished') {
            // Position.
            $posoptions = organisation::get_all_positions_menu(['' => '']);
            $mform->addElement('selectgroups', 'positionid', get_string('position', 'tool_organisation'), $posoptions);
            $mform->setType('positionid', PARAM_INT);
            if (!$job) {
                $mform->addHelpButton('positionid', 'position', 'tool_organisation');
            } else if ($action == 'transfertojob') {
                $position = new position($job->get('positionid'));
                $currentpositionstring = get_string('current', 'tool_organisation', $position->get_formatted_name());
                $currentpositiondiv = html_writer::div($currentpositionstring, 'text-muted my-n2');
                $mform->addElement('static', 'currentposition', '', $currentpositiondiv);
            }

            // Department.
            $deptoptions = organisation::get_all_departments_menu(['' => '']);
            $mform->addElement('selectgroups', 'departmentid', get_string('department', 'tool_organisation'), $deptoptions);
            $mform->setType('departmentid', PARAM_INT);
            if (!$job) {
                $mform->addHelpButton('departmentid', 'department', 'tool_organisation');
            } else if ($action == 'transfertojob') {
                $department = new department($job->get('departmentid'));
                $currentdepartmentstring = get_string('current', 'tool_organisation', $department->get_formatted_name());
                $currentdepartmentdiv = html_writer::div($currentdepartmentstring, 'text-muted my-n2');
                $mform->addElement('static', 'currentdepartment', '', $currentdepartmentdiv);
            }

            $mform->addRule('positionid', get_string('missingposition', 'tool_organisation'), 'required', null, 'client');
            $mform->addRule('departmentid', get_string('missingdepartment', 'tool_organisation'), 'required', null, 'client');
        }

        // Start and end dates.
        $options = ['timezone' => $CFG->timezone];
        if ($action === 'setjobfinished') {
            $mform->addElement('hidden', 'startdate');
        } else {
            $mform->addElement('date_selector', 'startdate', get_string('startdate', 'tool_organisation'), $options);
            $mform->addHelpButton('startdate', 'startdate', 'tool_organisation');
            $mform->setDefault('startdate', time());
            if ($action === 'transfertojob') {
                $previousjobnotestring = get_string('previousjobdatenote', 'tool_organisation');
                $previousjobnotediv = html_writer::div($previousjobnotestring, 'alert alert-primary my-n2');
                $mform->addElement('static', 'previousjobdatenote', '', $previousjobnotediv);
            }

            $options['optional'] = true;
        }

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
        if (!$id && !$userid) {
            $mform->addRule('users', get_string('missingusers', 'tool_organisation'), 'required', null, 'client');
        } else {
            $mform->removeElement('users');
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
        $record->currentstartdate = $record->startdate;
        $record->currentpositionid = $record->positionid;
        $record->currentdepartmentid = $record->departmentid;
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
        foreach (['positionid', 'departmentid'] as $field) {
            if (isset($data->$field)) {
                $record[$field] = $data->$field;
            }
        }
        if ($id) {
            $record['id'] = $id;
        } else {
            $record += [
                'userid' => $data->userid,
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
            if ($data->action === 'transfertojob') {
                if (permission::can_assign_job_to_user($data->userid)) {
                    $manager->update_and_create_job($id, $this->prepare_data_for_storing($data, $id));
                }
            } else if ($data->action === 'editjobdates' || $data->action === 'setjobfinished') {
                $manager->update_job($id, $this->prepare_data_for_storing($data, $id));
            }
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
            if ($data->action == 'transfertojob') {
                $empty = ['positionid' => 0, 'departmentid' => 0, 'enddate' => 0, 'startdate' => 0];
                $this->set_data($empty);
            }
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
        if ($data['action'] == 'transfertojob') {
            if (isset($data['currentstartdate']) && !empty($data['currentstartdate'])) {
                $newenddate = strtotime('-1 day', $data['startdate']);
                if ($data['currentstartdate'] > $newenddate) {
                    $errors['startdate'] = get_string('validationmsgedateonsdatechangejob', 'tool_organisation');
                }
            }
            if ($data['currentpositionid'] == $data['positionid'] && $data['currentdepartmentid'] == $data['departmentid']) {
                $errors['departmentid'] = get_string('validationmsgdeptposchangejob', 'tool_organisation');
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

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
 * Class containing modal report audience form
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\form;

use core\notification;
use html_writer;
use moodle_url;
use stdClass;
use tool_organisation\organisation;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_base;
use tool_reportbuilder\local\helpers\audience as helper;
use tool_wp\modal_form;

defined('MOODLE_INTERNAL') || die;

/**
 * Form class
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience extends modal_form {

    /** @var report_base $report. */
    protected $report;

    /**
     * Return current report
     *
     * @return report_base
     */
    protected function get_report() : report_base {
        if (!$this->report) {
            $reportid = $this->optional_param('reportid', 0, PARAM_INT);
            $this->report = manager::get_report($reportid);
        }

        return $this->report;
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    protected function get_page_url_for_modal() : moodle_url {
        return new moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $this->get_report()->get_id()]);
    }

    /**
     * Require current user can edit report
     *
     * @return void
     */
    public function require_access() : void {
        permission::require_can_edit($this->get_report());
    }

    /**
     * Form definition
     *
     * @return void
     */
    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;

        $mform->addElement('hidden', 'reportid', 0);
        $mform->setType('reportid', PARAM_INT);

        // Repeated department/position elements.
        $mform->addElement('header', 'audience', get_string('audiencejobs', 'tool_reportbuilder'));
        $mform->addHelpButton('audience', 'audiencejobs', 'tool_reportbuilder');
        $strs = get_strings(['department', 'withsubdepartments', 'position', 'withsubpositions'], 'tool_organisation');

        // If there aren't currently any audience records, display a message.
        $audiencerecordcount = helper::count_records($this->get_report());
        if ($audiencerecordcount == 0) {
            $mform->addElement('html', $OUTPUT->notification(get_string('audiencejobsempty', 'tool_reportbuilder'),
                notification::INFO));
        }

        // Wrap each job element in a div because there is no attributes param for the group element.
        $jobs[] = $mform->createElement('html', html_writer::start_div('border rounded p-4 mb-3 br-1 bg-gray020'));

        $jobs[] = $mform->createElement('hidden', 'id', 0);
        $options['id']['type'] = PARAM_INT;

        $positions = organisation::get_all_positions_menu([0 => get_string('all')]);
        $position = [
            $mform->createElement('selectgroups', 'id', null, $positions),
            $mform->createElement('advcheckbox', 'subpositions', $strs->withsubpositions),
        ];
        $jobs[] = $mform->createElement('group', 'position', $strs->position, $position);

        $departments = organisation::get_all_departments_menu([0 => get_string('all')]);
        $department = [
            $mform->createElement('selectgroups', 'id', null, $departments),
            $mform->createElement('advcheckbox', 'subdepartments', $strs->withsubdepartments),
        ];
        $jobs[] = $mform->createElement('group', 'department', $strs->department, $department);

        // Delete button.
        $jobs[] = $mform->createElement('submit', 'deletebutton', get_string('audiencejobremove', 'tool_reportbuilder'),
            [], false, ['customclassoverride' => 'btn btn-outline-secondary']);

        $jobs[] = $mform->createElement('html', html_writer::end_div());

        // Create number of repeated elements equal to number of audience records for this report.
        $this->repeat_elements($jobs, $audiencerecordcount, $options, 'jobcount', 'addjob', 1,
            get_string('audiencejobadd', 'tool_reportbuilder'), true, 'deletebutton');

        $this->add_action_buttons(false);
    }

    /**
     * Populate form defaults from existing data
     *
     * @return void
     */
    public function set_data_for_modal() : void {
        $data = [];
        $index = 0;

        parent::set_data_for_modal();

        $persistents = helper::get_records($this->get_report());
        foreach ($persistents as $persistent) {
            $data["id[{$index}]"] = $persistent->get('id');
            $data["department[{$index}][id]"] = $persistent->get('departmentid');
            $data["department[{$index}][subdepartments]"] = $persistent->get('subdepartments');
            $data["position[{$index}][id]"] = $persistent->get('positionid');
            $data["position[{$index}][subpositions]"] = $persistent->get('subpositions');

            $index++;
        }

        $this->set_data($data);
    }

    /**
     * Process form submission
     *
     * @param stdClass $data
     * @return bool
     */
    public function process(stdClass $data) {
        // Create an array of existing audience record IDs.
        $ids = array_map(function($persistent) {
            return $persistent->get('id');
        }, helper::get_records($this->get_report()));

        for ($i = 0; $i < $data->jobcount; $i++) {
            // If this job was deleted, skip, unaccounted records from audience table will be deleted at the end.
            if (!isset($data->id[$i])) {
                continue;
            }

            $record = (object) [
                'departmentid' => $data->department[$i]['id'],
                'subdepartments' => $data->department[$i]['subdepartments'],
                'positionid' => $data->position[$i]['id'],
                'subpositions' => $data->position[$i]['subpositions'],
            ];

            // Are we inserting a new record, or updating an existing one?
            $id = $data->id[$i];
            if (!in_array($id, $ids)) {
                $record->reportid = $this->get_report()->get_id();
                helper::create_record($record);
            } else {
                helper::update_record($id, $record);
                $ids = array_diff($ids, [$id]);
            }
        }

        // Delete all records from tool_reportbuilder_audience that were not updated before.
        foreach ($ids as $id) {
            helper::delete_record($id);
        }

        return true;
    }
}
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
 * Class containing main class for conditions form and logic.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\form;

use tool_reportbuilder\filter_base;
use tool_reportbuilder\manager;
use tool_reportbuilder\report_base;
use tool_wp\modal_form;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * Class conditions
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class conditions extends modal_form {
    // TODO: filters and conditions need an independent form ¡¡¡¡.
    /** @var report_base */
    protected $report;
    /** @var filter_base[] $fields */
    protected $fields;
    /**
     * Form definition
     * @throws \HTML_QuickForm_Error
     * @throws \coding_exception
     */
    public function definition(): void {
        $this->set_display_vertical();

        $mform = $this->_form;
        $this->fields = $this->get_active_conditions();

        $mform->addElement('hidden', 'reportid');
        $mform->setType('reportid', PARAM_INT);

        $mform->addElement('hidden', 'canreset');
        $mform->setType('canreset', PARAM_BOOL);

        $mform->addElement('html', \html_writer::start_div('filters-list'));
        $conditionshelper = new conditions_helper($this->get_report());
        foreach ($this->fields as $fieldindex => $ft) {
            $conditionsvalues = $conditionshelper->get_condition_values($ft->get_name());
            $ft->set_values($conditionsvalues);
            $ft->setup_form($mform);
        }
        $mform->addElement('html', \html_writer::end_div());

        if ($this->fields) {
            $resetbutton = \html_writer::link(
                '#',
                get_string("resetall", 'tool_reportbuilder'),
                [
                    'data-action' => 'reset-all',
                    'title' => get_string("resetallconditions", 'tool_reportbuilder'),
                    'class' => 'btn btn-sm btn-outline-secondary'
                ]);
            $mform->addElement('html', \html_writer::div($resetbutton, 'py-2 mb-5 d-flex border-top justify-content-end'));
        }

        $mform->disable_form_change_checker();
    }

    /**
     * Get the current report id.
     *
     * @return int
     */
    private function get_report(): report_base {
        if (!$this->report) {
            if (!empty($this->_customdata['report'])) {
                $this->report = $this->_customdata['report'];
            } else {
                $reportid = $this->optional_param('reportid', null, PARAM_INT);
                $this->report = manager::get_report($reportid);
            }
        }
        return $this->report;
    }

    /**
     * Creates known user filter if present
     *
     * @return array|filter_base[]
     * @throws \coding_exception
     */
    private function get_active_conditions() {
        global $PAGE;
        $fields = [];
        $output = $PAGE->get_renderer('tool_reportbuilder');
        $source = $this->get_report();
        $activeconditions = $source->get_active_conditions();
        $conditions = $source->get_conditions();
        $conditionsinuse = conditions_helper::get_conditions($activeconditions, $conditions, $output);
        foreach ($conditionsinuse as $keyfilter => $filter) {
            $filterclass = $filter->classname;
            $reportcondition = $conditions[$keyfilter];
            /** @var filter_base $userfilter */
            $userfilter = filter_base::create($filterclass, $reportcondition, $filter->id, $filter->heading,
                $filter->default, true);
            $fields[$keyfilter] = $userfilter;
        }

        return $fields;
    }

    /**
     * Check if current user has access to this form, otherwise throw exception
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function require_access() {
        return \tool_reportbuilder\permission::can_edit($this->get_report());
    }

    /**
     * Store the conditions values and operators
     *
     * @param null|\stdClass $data
     * @return mixed|void
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public function process(?\stdClass $data) {
        $report = $this->get_report();
        $conditions = new conditions_helper($report);
        unset($data->reportid);
        unset($data->canreset);
        $conditions->add_report_conditions(json_encode($data));
    }

    /**
     * Get the filter fields.
     *
     * @return filter_base[]
     */
    public function get_fields() {
        return $this->fields;
    }
}

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
 * Class containing main class for a filter.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\form;

use tool_reportbuilder\filter_base;
use tool_reportbuilder\manager;
use tool_wp\modal_form;
use tool_reportbuilder\local\helpers\filters as filters_helper;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * Class filters
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filters extends modal_form {
    // TODO: filters and conditions need an independent form ¡¡¡¡.
    /** @var int $reportid Report id */
    protected $reportid;
    /** @var filter_base[] $fields */
    protected $fields;
    /**
     * Form definition
     * @throws \HTML_QuickForm_Error
     * @throws \coding_exception
     */
    public function definition(): void {
        $this->set_display_vertical();

        $this->reportid = $this->get_report_id();

        $mform = $this->_form;
        $this->fields = $this->get_active_filters();

        $mform->addElement('hidden', 'reportid');
        $mform->setType('reportid', PARAM_INT);

        $mform->addElement('hidden', 'canreset');
        $mform->setType('canreset', PARAM_BOOL);

        $mform->addElement('html', \html_writer::start_div('filters-list'));
        $currentfiltersvalues = $this->get_filters_values();
        foreach ($this->fields as $fieldindex => $ft) {
            $filtervalues = $this->get_current_values($currentfiltersvalues, $ft->get_name());
            $ft->is_active($filtervalues);
            $ft->setup_form($mform);
            $this->fields[$fieldindex]->set_values($filtervalues);
        }
        $mform->addElement('html', \html_writer::end_div());

        if (true) { // TODO: review it.
            $resetbutton = \html_writer::link(
                '#',
                get_string("resetall", 'tool_reportbuilder'),
                array('data-action' => 'reset-all'));
            $mform->addElement('html', \html_writer::div($resetbutton, 'p-2 mb-5 d-flex justify-content-end'));
        }

        $mform->disable_form_change_checker();
    }

    /**
     * Get the current report ID.
     *
     * @return int
     */
    private function get_report_id() : int {
        $reportid = $this->_customdata['reportid'];
        if ($reportid) {
            return $reportid;
        }
        return $this->optional_param('reportid', null, PARAM_INT);
    }

    /**
     * Creates known user filter if present
     *
     * @return array|filter_base[]
     * @throws \coding_exception
     */
    private function get_active_filters() {
        $fields = [];
        $source = manager::get_report($this->get_report_id());
        $activefilters = filters_helper::get_active_filters($this->get_report_id());
        $reportfilters = $source->get_filters();
        $filtersinuse = filters_helper::get_filters_with_data($activefilters, $reportfilters);
        foreach ($filtersinuse as $keyfilter => $filter) {
            $filterclass = $filter->classname;
            $reportcondition = $reportfilters[$keyfilter];
            /** @var filter_base $userfilter */
            $userfilter = filter_base::create($filterclass, $reportcondition, $filter->id, $filter->heading,
                null, false);
            $fields[$keyfilter] = $userfilter;
        }

        return $fields;
    }

    /**
     * Get the current value and operator for the given filter.
     *
     * @param array $currentfilters Active filter in user preferences.
     * @param string $name The key of the filter
     * @return array
     */
    private function get_current_values(array $currentfilters, string $name): array {
        $values = array_filter($currentfilters, function($filterkey) use ($name) {
            return strpos($filterkey, $name) === 0;
        }, ARRAY_FILTER_USE_KEY);
        return $values;
    }

    /**
     * Get the the filter fields values.
     *
     * @return array
     * @throws \coding_exception
     */
    private function get_filters_values() {
        $filtershelper = new filters_helper($this->get_report_id());
        return $filtershelper->get_report_filters();
    }

    /**
     * Check if current user has access can update filter.
     *
     * Any user with can manage her own filters.
     *
     * @return bool
     */
    public function require_access() {
        return true;
    }

    /**
     * Store the conditions values and operators
     *
     * @param null|\stdClass $data
     * @return mixed|void
     * @throws \coding_exception
     */
    public function process(?\stdClass $data) {
        $reportid = $data->reportid;
        unset($data->reportid);
        unset($data->canreset);
        filters_helper::set_filter(
            $reportid,
            $data
        );
    }

    /**
     * Get filters fields.
     *
     * @return filter_base[]
     */
    public function get_fields() {
        return $this->fields;
    }
}
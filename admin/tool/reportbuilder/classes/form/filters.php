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
 * Class containing main class for a filter.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\form;

use context;
use core_form\dynamic_form;
use moodle_url;
use tool_reportbuilder\filter_base;
use tool_reportbuilder\manager;
use tool_reportbuilder\report_base;
use tool_reportbuilder\local\helpers\filters as filters_helper;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * Class filters
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class filters extends dynamic_form {
    // TODO: filters and conditions need an independent form ¡¡¡¡.
    /** @var report_base $report */
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
        $this->fields = $this->get_active_filters();

        $mform->addElement('hidden', 'reportid');
        $mform->setType('reportid', PARAM_INT);

        $mform->addElement('hidden', 'canreset');
        $mform->setType('canreset', PARAM_BOOL);

        // Add additional params from the report.
        $mform->addElement('hidden', "parameters", json_encode($this->get_report()->get_parameters()));
        $mform->setType("parameters", PARAM_RAW);

        $mform->addElement('html', \html_writer::start_div('filters-list'));
        $currentfiltersvalues = $this->get_filters_values();
        foreach ($this->fields as $fieldindex => $ft) {
            $filtervalues = $this->get_current_values($currentfiltersvalues, $ft->get_name());
            $ft->set_values($filtervalues);
            $ft->setup_form($mform);
        }
        $mform->addElement('html', \html_writer::end_div());
        $mform->disable_form_change_checker();
    }

    /**
     * Get the current report.
     *
     * @return report_base
     */
    private function get_report() : report_base {
        if ($this->report === null) {
            if (!empty($this->_customdata['report'])) {
                $this->report = $this->_customdata['report'];
            } else {
                $reportid = $this->optional_param('reportid', null, PARAM_INT);
                $parameters = json_decode($this->optional_param('parameters', null, PARAM_RAW), true);
                $this->report = manager::get_report($reportid, $parameters ?: []);
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
    private function get_active_filters() {
        $fields = [];
        $activefilters = $this->get_report()->get_active_filters();
        $reportfilters = $this->get_report()->get_filters();
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
     */
    private function get_filters_values(): array {
        $filtershelper = new filters_helper($this->get_report()->get_id());
        return $filtershelper->get_report_filters();
    }

    /**
     * Check if current user has access can update filter.
     *
     * Any user with can manage her own filters.
     */
    public function check_access_for_dynamic_submission(): void {
    }

    /**
     * Store the filter values for the report
     *
     * @return void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $reportid = $data->reportid;

        // Remove form elements unrelated specifically to filter values.
        unset($data->reportid, $data->canreset, $data->parameters);

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

    /**
     * Returns context where this form is used
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return \context_system::instance();
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data($this->_ajaxformdata);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new \moodle_url('/admin/tool/reportbuilder/index.php', [
            'form' => get_class($this),
            'reportid' => $this->optional_param('reportid', null, PARAM_INT),
        ]);
    }
}

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
 * Class containing the logic for the filter select.
 *
 * @package   tool_organisation
 * @copyright 2019 Daniel Neis Araujo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation\tool_reportbuilder\filter;

use tool_organisation\helper;
use tool_reportbuilder\filter_base;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die;

/**
 * General filter for jobs.
 *
 * @package   tool_organisation
 * @copyright 2019 Daniel Neis Araujo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class showpastjobs extends filter_base {

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        if ($values) {
            return ['', []];
        }
        $ptime1 = db::generate_param_name();
        $ptime2 = db::generate_param_name();
        $where = "(j.enddate = 0 OR j.enddate >= :{$ptime2})";
        $time = helper::round_time(time());
        $params = [$ptime1 => $time, $ptime2 => $time];
        return [$where, $params];
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param \MoodleQuickForm $mform a MoodleForm object to setup
     */
    public function setup_form(\MoodleQuickForm $mform) {
        $this->common_header($mform);
        $mform->addElement('checkbox', $this->name, get_string('showpastjobs', 'tool_organisation'));
        $mform->setDefault($this->name, $this->isactive);
        $this->common_footer($mform);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label(array $data) : string {
        // TODO.
        return 'this filter label';
    }

    /**
     * Check if the current filter is active in order to show the reset button.
     *
     * Must set the variable "isactive" to true or false.
     *
     * Each filter type must have the own logic to determinate if the filter is active or not.
     *
     * @param array $values
     * @return mixed
     */
    public function is_active(?array $values) : void {
        $this->isactive = !empty($values);
    }

    /**
     * Define the type of the filter.
     *
     * @return string
     */
    protected function filter_type() : string {
        return 'filter-showpastjobs';
    }

    /**
     * Returns sample values that can be used in the tests
     *
     * If $extended is not set, return 1-2 sets of values, they will be massively used in tests for all filters in all datasources
     * If $extended is set, return as many sets of values as possible, for extended test of this specific filter
     *
     * @param bool $extended
     * @return array
     */
    public function get_test_values(bool $extended = false) {
        return [
            [$this->name => 1],
            [$this->name => 0],
        ];
    }
}

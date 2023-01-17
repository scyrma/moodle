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
 * Class containing the logic for the filter select.
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_reportbuilder\filter;

use tool_organisation\helper;
use tool_organisation\reportbuilder\local\filters\user_position;
use tool_reportbuilder\filter_base;
use tool_reportbuilder\local\filter\select;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * Filter users who have at least one job in a specific position
 *
 * This filter does not require a join with "jobs" table, and each user is returned once even if they have
 * multiple jobs
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class position_select extends select {

    /**
     * When converting a report containing this filter to core_reportbuilder how should the values be converted
     *
     * @param array $values values stored for this condition in the tool_reportbuilder custom report
     * @param \core_reportbuilder\local\filters\base $newcondition
     * @return array values to be stored for the converted condition in the core_reportbuilder custom report
     */
    public function convert_condition_values(array $values, \core_reportbuilder\local\filters\base $newcondition): array {
        if ($newcondition instanceof user_position) {
            $operator = array_key_exists("{$this->name}_op", $values) ? $values["{$this->name}_op"] : null;
            $value = array_key_exists($this->name, $values) ? $values[$this->name] : 0;
            $newconditionname = $newcondition->get_filter_persistent()->get('uniqueidentifier');
            return [$newconditionname.'_operator' => $operator, $newconditionname => $value];
        }

        // Condition was mapped to something else - the datasource should be responsible to provide condition mapping.
        return filter_base::convert_condition_values($values, $newcondition);
    }

    /**
     * Adds controls specific to this filter in the form.
     *
     * @param \MoodleQuickForm $mform
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function setup_form(\MoodleQuickForm $mform) {
        $objs = array();
        $this->common_header($mform);

        $objs[$this->name] = $mform->createElement('selectgroups', $this->name, null,
            $this->get_select_options(), array('class' => 'js-filter-select-val'));
        $objs[$this->name]->setLabel(get_string('position', 'tool_organisation'));

        $objs['limiter'] = $mform->createElement('advcheckbox', $this->name.'_op', null,
            get_string('withsubpositions', 'tool_organisation'), array('class' => 'js-filter-advcheckbox-op'));

        $grp =& $mform->addElement('group', $this->name.'_grp', '', $objs, '', false);
        $mform->disabledIf($this->name.'_op', $this->name, 'eq', '');

        $this->common_footer($mform);
    }

    /**
     * Options for the select element
     *
     * @return array
     */
    protected function get_select_options() {
        return $this->reportfilter->get_options();
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        if (!$values) {
            return ['', []];
        }

        $operator = array_key_exists("{$this->name}_op", $values) ? $values["{$this->name}_op"] : null;
        $value = array_key_exists($this->name, $values) ? $values[$this->name] : 0;

        if (!$value) {
            return ['', []];
        }

        $usertablealias = 'u'; // TODO SP-422 replace hardcoded value with $this->reportfilter->get_field_sql().
        list ($sql, $params) = helper::user_has_position_select((int)$value, (bool)$operator, $usertablealias);
        return [$sql, $params];
    }

    /**
     * Returns a human friendly description of the filter used as label.
     *
     * @param array $data
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_label(array $data) : string {
        // TODO SP-391 not implemented yet.
        return '';
    }

    /**
     * Define the type of the filter.
     *
     * @return string
     */
    protected function filter_type() : string {
        return 'filter-select';
    }

    /**
     * Add position condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $values = $this->get_values();

        if (!empty($values[$this->name])) {
            $exporter->add_mapping('tool_organisation_position', (int) $values[$this->name]);
        }
    }

    /**
     * Get position ID condition field mapping during import
     *
     * @param importer_base $importer
     * @return array
     */
    public function get_importer_mapping(importer_base $importer): array {
        $values = $this->get_values();

        if (empty($values[$this->name])) {
            return [];
        }

        $mappedpositionid = $importer->get_mapping('tool_organisation_position',
            (int) $values[$this->name], IGNORE_MISSING) ?? 0;

        return [$this->name => $mappedpositionid];
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
        $rv = [];
        $options = $this->get_select_options();
        if (!$options) {
            return [];
        }
        $lastoptiongroup = array_keys(array_pop($options));
        $option = array_pop($lastoptiongroup);
        $operators = [0 => 0, 1 => 1];
        foreach ($operators as $operator => $unused) {
            $rv[] = [$this->name . '_op' => $operator, $this->name => $option];
        }
        return $rv;
    }
}

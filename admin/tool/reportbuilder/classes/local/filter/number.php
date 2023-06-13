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
 * File for the class containing the logic for the number filter/condition
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\filter;

use MoodleQuickForm;
use tool_wp\db;
use tool_reportbuilder\filter_base;

/**
 * Class containing the logic for the number filter/condition
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class number extends filter_base {

    /**
     * When converting a report containing this condition to core_reportbuilder how should the values be converted
     *
     * @param array $values values stored for this condition in the tool_reportbuilder custom report
     * @param \core_reportbuilder\local\filters\base $newcondition
     * @return array values to be stored for the converted condition in the core_reportbuilder custom report
     */
    public function convert_condition_values(array $values, \core_reportbuilder\local\filters\base $newcondition): array {
        if ($newcondition instanceof \core_reportbuilder\local\filters\number) {
            // The operator in tool reportbuilder is using the same constants as in core reportbuilder.
            $operator = $values[$this->name] ?? null;
            $comparisonvalue = $values["{$this->name}_op"] ?? null;
            $comparisonvalue2 = $values["{$this->name}_op2"] ?? null;
            $newconditionname = $newcondition->get_filter_persistent()->get('uniqueidentifier');
            return [
                $newconditionname.'_operator' => $operator,
                $newconditionname.'_value1' => $comparisonvalue,
                $newconditionname.'_value2' => $comparisonvalue2,
            ];
        }

        // The datasource mapped this condition to something else, not "number", so
        // datasource should provide the mapping in this case.
        return parent::convert_condition_values($values, $newcondition);
    }

    /**
     * Returns an array of comparison operators
     *
     * @return array of comparison operators
     */
    public function get_operators(): array {
        return [
            0 => get_string('numberanyvalue', 'tool_reportbuilder'),
            1 => get_string('numberisnotempty', 'tool_reportbuilder'),
            2 => get_string('numberisempty', 'tool_reportbuilder'),
            3 => get_string('numberlessthan', 'tool_reportbuilder'),
            4 => get_string('numbergreaterthan', 'tool_reportbuilder'),
            5 => get_string('numberequalto', 'tool_reportbuilder'),
            6 => get_string('numberequalorlessthan', 'tool_reportbuilder'),
            7 => get_string('numberequalorgreaterthan', 'tool_reportbuilder'),
            8 => get_string('numberbetween', 'tool_reportbuilder'),
        ];
    }

    /**
     * Adds controls specific to this filter in the form.
     *
     * @param MoodleQuickForm $mform a MoodleForm object to setup
     */
    public function setup_form(MoodleQuickForm $mform): void {
        $objs = [];
        $this->common_header($mform);

        $objs['select'] = $mform->createElement('select', $this->name, null, $this->get_operators());
        $objs['select']->setLabel(get_string('limiterfor', 'filters', $this->get_formatted_header()));
        $mform->setType($this->name, PARAM_RAW);

        $objs['text'] = $mform->createElement('text', $this->name . '_op', null, ['size' => 3]);
        $objs['text']->setLabel(get_string('valuefor', 'filters', $this->get_formatted_header()));
        $mform->setType($this->name . '_op', PARAM_INT);
        $mform->setDefault($this->name . '_op', 0);

        $objs['text2'] = $mform->createElement('text', $this->name . '_op2', null, ['size' => 3]);
        $objs['text2']->setLabel(get_string('valuefor', 'filters', $this->get_formatted_header()));
        $mform->setType($this->name . '_op2', PARAM_INT);
        $mform->setDefault($this->name . '_op2', 0);

        $mform->addElement('group', $this->name . '_grp', '', $objs, '', false);

        $mform->hideIf($this->name . '_op', $this->name, 'eq', 0);
        $mform->hideIf($this->name . '_op', $this->name, 'eq', 1);
        $mform->hideIf($this->name . '_op', $this->name, 'eq', 2);

        $mform->hideIf($this->name . '_op2', $this->name, 'eq', 0);
        $mform->hideIf($this->name . '_op2', $this->name, 'eq', 1);
        $mform->hideIf($this->name . '_op2', $this->name, 'eq', 2);
        $mform->hideIf($this->name . '_op2', $this->name, 'eq', 3);
        $mform->hideIf($this->name . '_op2', $this->name, 'eq', 4);
        $mform->hideIf($this->name . '_op2', $this->name, 'eq', 5);
        $mform->hideIf($this->name . '_op2', $this->name, 'eq', 6);
        $mform->hideIf($this->name . '_op2', $this->name, 'eq', 7);

        $this->common_footer($mform);
    }

    /**
     * Build the sql filter condition.
     *
     * @param array|null $currentvalues
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $currentvalues): array {
        $operator = $currentvalues[$this->name] ?? null;
        $comparisonvalue = $currentvalues["{$this->name}_op"] ?? null;
        $comparisonvalue2 = $currentvalues["{$this->name}_op2"] ?? null;

        if ($operator === null) {
            // Filter configuration is invalid. Ignore the filter.
            return ['', []];
        }
        if (($operator >= 3 && $operator <= 7) && $comparisonvalue === null) {
            // Filter configuration is invalid. Ignore the filter.
            return ['', []];
        }
        if (($operator === 8) && ($comparisonvalue === null || $comparisonvalue2 === null)) {
            // Filter configuration is invalid. Ignore the filter.
            return ['', []];
        }

        $param = db::generate_param_name();
        $param2 = db::generate_param_name();
        $field = $this->reportfilter->get_field_sql();
        $params = $this->reportfilter->get_field_params();
        switch ($operator) {
            case 0: // Any value.
                return ['', []];
            case 1: // Is not empty.
                $res = "$field IS NOT NULL";
                break;
            case 2: // Is empty.
                $res = "$field IS NULL";
                $params[$param] = time();
                break;
            case 3: // Less than.
                $res = "$field < :{$param}";
                $params[$param] = $comparisonvalue;
                break;
            case 4: // Greater than.
                $res = "$field > :{$param}";
                $params[$param] = $comparisonvalue;
                break;
            case 5: // Equal to.
                $res = "$field = :{$param}";
                $params[$param] = $comparisonvalue;
                break;
            case 6: // Equal or less than.
                $res = "$field <= :{$param}";
                $params[$param] = $comparisonvalue;
                break;
            case 7: // Equal or greater than.
                $res = "$field >= :{$param}";
                $params[$param] = $comparisonvalue;
                break;
            case 8: // Between.
                $res = "($field >= :{$param} AND $field <= :{$param2})";
                $params[$param] = $comparisonvalue;
                $params[$param2] = $comparisonvalue2;
                break;
            default:
                // Filter configuration is invalid. Ignore the filter.
                return ['', []];
        }
        return [$res, $params];
    }

    /**
     * Returns a human friendly description of the filter used as label.
     *
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label(array $data): string {
        $operator = $data['operator'];
        $operators = $this->get_operators();

        // TODO re-write to avoid concatenation.
        return $this->get_formatted_header() . ' ' . $operators[$operator];
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
    public function get_test_values(bool $extended = false): array {
        $rv = [];
        $operators = $extended ? $this->get_operators() : [1 => 1, 5 => 5, 8 => 8];
        foreach ($operators as $operator => $unused) {
            $rv[] = [
                $this->name => $operator,
                $this->name . '_op' => 10,
                $this->name . '_op2' => 20,
            ];
        }
        return $rv;
    }
}

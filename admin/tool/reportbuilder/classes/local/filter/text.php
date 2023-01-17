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
 * Class containing the logic for the filter text.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\filter;

use core_reportbuilder\local\helpers\database;
use tool_reportbuilder\filter_base;

/**
 * Class text
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class text extends filter_base {

    /**
     * When converting a report containing this condition to core_reportbuilder how should the values be converted
     *
     * @param array $values values stored for this condition in the tool_reportbuilder custom report
     * @param \core_reportbuilder\local\filters\base $newcondition
     * @return array values to be stored for the converted condition in the core_reportbuilder custom report
     */
    public function convert_condition_values(array $values, \core_reportbuilder\local\filters\base $newcondition): array {
        if ($newcondition instanceof \core_reportbuilder\local\filters\text) {
            $operator = array_key_exists("{$this->name}_op", $values) ? (int) $values["{$this->name}_op"] : null;
            $value = array_key_exists($this->name, $values) ? $values[$this->name] : '';
            $newconditionname = $newcondition->get_filter_persistent()->get('uniqueidentifier');

            switch ($operator) {
                case 0:
                    if ($value === '') {
                        $newoperator = \core_reportbuilder\local\filters\text::ANY_VALUE;
                    } else {
                        $newoperator = \core_reportbuilder\local\filters\text::CONTAINS;
                    }
                    break;
                case 1:
                    $newoperator = \core_reportbuilder\local\filters\text::DOES_NOT_CONTAIN;
                    break;
                case 2:
                    $newoperator = \core_reportbuilder\local\filters\text::IS_EQUAL_TO;
                    break;
                case 3:
                    $newoperator = \core_reportbuilder\local\filters\text::STARTS_WITH;
                    break;
                case 4:
                    $newoperator = \core_reportbuilder\local\filters\text::ENDS_WITH;
                    break;
                case 5:
                    $newoperator = \core_reportbuilder\local\filters\text::IS_EMPTY;
                    break;
                case 6:
                    $newoperator = \core_reportbuilder\local\filters\text::IS_NOT_EMPTY;
                    break;
                default:
                    $newoperator = \core_reportbuilder\local\filters\text::ANY_VALUE;
                    break;
            }

            return [
                $newconditionname.'_operator' => $newoperator,
                $newconditionname.'_value' => $value,
            ];
        }

        // The datasource mapped this condition to something else, not "text", so
        // datasource should provide the mapping in this case.
        return parent::convert_condition_values($values, $newcondition);
    }

    /**
     * Returns an array of comparison operators
     *
     * @return array of comparison operators
     */
    public function get_operators() : array {
        // TODO: replace all these with constants in core.
        return array(0 => get_string('contains', 'filters'),
                     1 => get_string('doesnotcontain', 'filters'),
                     2 => get_string('isequalto', 'filters'),
                     3 => get_string('startswith', 'filters'),
                     4 => get_string('endswith', 'filters'),
                     5 => get_string('isempty', 'filters'),
                     6 => get_string('isnotempty', 'tool_reportbuilder'));
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param \MoodleQuickForm $mform a MoodleForm object to setup
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function setup_form(\MoodleQuickForm $mform) {
        $objs = array();
        $this->common_header($mform);
        $objs['select'] = $mform->createElement('select', $this->name.'_op', null, $this->get_operators());
        $objs['text'] = $mform->createElement('text', $this->name, null);
        $objs['select']->setLabel(get_string('limiterfor', 'filters', $this->get_formatted_header()));
        $objs['text']->setLabel(get_string('valuefor', 'filters', $this->get_formatted_header()));
        $grp =& $mform->addElement('group', $this->name.'_grp', '', $objs, '', false);
        $mform->setType($this->name, PARAM_RAW);
        $mform->disabledIf($this->name, $this->name.'_op', 'eq', 5);
        $mform->disabledIf($this->name, $this->name.'_op', 'eq', 6);
        $this->common_footer($mform);
    }

    /**
     * Build the sql filter condition.
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        global $DB;
        $name = database::generate_param_name();

        if (!$values) {
            return ['', []];
        }

        $operator = array_key_exists("{$this->name}_op", $values) ? $values["{$this->name}_op"] : null;
        $value = array_key_exists($this->name, $values) ? $values[$this->name] : 0;

        $field = $this->reportfilter->get_field_sql();
        $params = $this->reportfilter->get_field_params();

        if (is_null($operator) || ('' . $value === '' && ($operator != 5 && $operator != 6))) {
            // Filter configuration is invalid. Ignore the filter.
            return ['', []];
        }

        switch($operator) {
            case 0: // Contains.
                $res = $DB->sql_like($field, ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value%";
                break;
            case 1: // Does not contain.
                $res = $DB->sql_like($field, ":$name", false, false, true);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value%";
                break;
            case 2: // Equal to.
                $res = $DB->sql_equal($field, ":$name", false, false);
                $params[$name] = "$value";
                break;
            case 3: // Starts with.
                $res = $DB->sql_like($field, ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "$value%";
                break;
            case 4: // Ends with.
                $res = $DB->sql_like($field, ":$name", false, false);
                $value = $DB->sql_like_escape($value);
                $params[$name] = "%$value";
                break;
            case 5: // Empty (note we also account for field not existing here).
                $res = "COALESCE({$field}, '') = :{$name}";
                $params[$name] = '';
                break;
            case 6: // Not empty.
                $res = "COALESCE({$field}, '') != :{$name}";
                $params[$name] = '';
                break;
            default:
                // Filter configuration is invalid. Ignore the filter.
                return ['', []];
        }
        return array($res, $params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     * @throws \coding_exception
     */
    public function get_label(array $data) : string {
        $operator  = $data['operator'];
        $value     = $data['value'];
        $operators = $this->get_operators();

        $a = new \stdClass();
        $a->label    = $this->get_formatted_header();
        $a->value    = '"'.s($value).'"';
        $a->operator = $operators[$operator];

        switch ($operator) {
            case 0: // Contains.
            case 1: // Doesn't contain.
            case 2: // Equal to.
            case 3: // Starts with.
            case 4: // Ends with.
                return get_string('textlabel', 'filters', $a);
            case 5:
            case 6: // Empty.
                return get_string('textlabelnovalue', 'filters', $a);
        }

        return '';
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
        $operators = $extended ? $this->get_operators() : [0 => 0, 5 => 5];
        foreach ($operators as $operator => $unused) {
            $rv[] = [$this->name . '_op' => $operator, $this->name => 'a'];
        }
        return $rv;
    }
}

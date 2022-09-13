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
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\filter;

use tool_wp\db;
use tool_reportbuilder\filter_base;

/**
 * Class select
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class select extends filter_base {

    /** @var int Any value */
    public const ANY_VALUE = 0;
    /** @var int Equal to */
    public const EQUAL_TO = 1;
    /** @var int Not equal to */
    public const NOT_EQUAL_TO = 2;

    /**
     * When converting a report containing this filter to core_reportbuilder how should the values be converted
     *
     * @param array $values values stored for this condition in the tool_reportbuilder custom report
     * @param \core_reportbuilder\local\filters\base $newcondition
     * @return array values to be stored for the converted condition in the core_reportbuilder custom report
     */
    public function convert_condition_values(array $values, \core_reportbuilder\local\filters\base $newcondition): array {
        // TODO WP-3634 - review, test, see checkbox filter as an example.
        $operator = array_key_exists("{$this->name}_op", $values) ? $values["{$this->name}_op"] : null;
        $value = array_key_exists($this->name, $values) ? $values[$this->name] : 0;
        $newconditionname = $newcondition->get_filter_persistent()->get('uniqueidentifier');
        return [$newconditionname.'_operator' => $operator, $newconditionname => $value];
    }

    /**
     * Returns an array of comparison operators
     *
     * @return array
     * @throws \coding_exception
     */
    public function get_operators() {
        return array(self::ANY_VALUE => get_string('isanyvalue', 'filters'),
                     self::EQUAL_TO => get_string('isequalto', 'filters'),
                     self::NOT_EQUAL_TO => get_string('isnotequalto', 'filters'));
    }

    /**
     * Options for the actual select element
     *
     * @return array
     */
    protected function get_options_for_select_element(): array {
        return $this->reportfilter->get_options();
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

        // We allow developer to rename or reduce list of filter oprators by
        // calling \tool_reportbuilder\report_filter->set_operators() and passing an array,
        // that will replace get_operators for the operator select form element only.
        $operators = $this->reportfilter->get_operators();
        if (!empty($operators) && array_diff_key($operators, $this->get_operators())) {
            throw new \coding_exception('One or more keys passed as operators are not
                matching list of operators returned by get_operators()');
        }
        $operators = $operators ?? $this->get_operators();

        $objs['limiter'] = $mform->createElement('select', $this->name.'_op', null, $operators,
            array('class' => 'js-filter-select-op'));
        $objs['limiter']->setLabel(get_string('limiterfor', 'filters', $this->get_formatted_header()));

        // If a multi-dimensional array is passed, we need to use a different element type.
        $options = $this->get_options_for_select_element();
        $element = (count($options) == count($options, COUNT_RECURSIVE) ? 'select' : 'selectgroups');

        $objs[$this->name] = $mform->createElement($element, $this->name, null, $options, ['class' => 'js-filter-select-val']);
        $objs[$this->name]->setLabel(get_string('valuefor', 'filters', $this->get_formatted_header()));
        $grp =& $mform->addElement('group', $this->name.'_grp', '', $objs, '', false);
        $mform->disabledIf($this->name, $this->name.'_op', 'eq', 0);
        if (!is_null($this->default)) {
            $mform->setDefault($this->name, $this->default);
        }
        $this->common_footer($mform);
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        $name = db::generate_param_name();

        $field = $this->reportfilter->get_field_sql();
        $params = $this->reportfilter->get_field_params();

        $operator = array_key_exists("{$this->name}_op", $values) ? $values["{$this->name}_op"] : null;
        $value = array_key_exists($this->name, $values) ? $values[$this->name] : 0;

        switch($operator) {
            case self::EQUAL_TO: // Equal to.
                $res = "=:$name";
                $params[$name] = $value;
                break;
            case self::NOT_EQUAL_TO: // Not equal to.
                $res = "<>:$name";
                $params[$name] = $value;
                break;
            default:
                // Filter configuration is invalid. Ignore the filter.
                return array('', array());
        }
        return array($field.$res, $params);
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
        $operators = $this->get_operators();
        $operator  = $data['operator'];
        $value     = $data['value'];

        if (empty($operator)) {
            return '';
        }

        $a = new \stdClass();
        $a->label    = $this->get_formatted_header();
        $a->value    = '"'.s($this->get_options_for_select_element()[$value]).'"';
        $a->operator = $operators[$operator];

        return get_string('selectlabel', 'filters', $a);
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
        $options = array_keys($this->get_options_for_select_element());
        if (!$options) {
            return [];
        }
        $option = array_pop($options);
        $operators = $extended ? $this->get_operators() : [1 => 1, 2 => 2];
        foreach ($operators as $operator => $unused) {
            $rv[] = [$this->name . '_op' => $operator, $this->name => $option];
        }
        return $rv;
    }
}

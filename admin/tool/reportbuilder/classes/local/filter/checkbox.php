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
 * Class containing the logic for the filter checkbox.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\filter;

defined('MOODLE_INTERNAL') || die;

use tool_reportbuilder\filter_base;

/**
 * Class checkbox
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class checkbox extends filter_base {

    /**
     * Returns an array of comparison operators
     *
     * @return array
     * @throws \coding_exception
     */
    public function get_operators() {
        return array(0 => get_string('checkboxanyvalue', 'tool_reportbuilder'),
                     1 => get_string('checkboxischecked', 'tool_reportbuilder'),
                     2 => get_string('checkboxisnotchecked', 'tool_reportbuilder'));
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
        $objs['limiter'] = $mform->createElement('select', $this->name.'_op', null, $this->get_operators(),
            array('class' => 'js-filter-select-op'));
        $objs['limiter']->setLabel(get_string('limiterfor', 'filters', $this->get_formatted_header()));

        $objs['name'] = $mform->createElement('hidden', $this->name, 1);
        $mform->setType($this->name, PARAM_INT);

        $mform->addElement('group', $this->name.'_grp', '', $objs, '', false);
        $this->common_footer($mform);
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        $field = $this->reportfilter->get_field_sql();
        $params = $this->reportfilter->get_field_params();

        $operator = array_key_exists("{$this->name}_op", $values) ? $values["{$this->name}_op"] : null;

        switch($operator) {
            case 1: // Checked.
                $res = "=1";
                break;
            case 2: // Not checked.
                $res = "=0";
                break;
            default:
                return ['', []];
        }
        return [$field . $res, $params];
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
        // TODO this uses hardcoded strings atm. It is almost impossible to find a good universal
        // wording for a label here. Maybe we should pass it as arguments to the filter?
        $operator  = $data['operator'];
        $value     = $data['value'];

        if ($operator == 1) {
            return $value . ' is set';
        } else if ($operator == 1) {
            return $value . ' is not set';
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
        return [
            [$this->name . '_op' => 0, $this->name => 1],
            [$this->name . '_op' => 1, $this->name => 1],
            [$this->name . '_op' => 2, $this->name => 1],
        ];
    }
}

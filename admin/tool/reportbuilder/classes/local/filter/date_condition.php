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
 * Class containing the logic for the condition date
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\filter;

use tool_wp\db;
use tool_reportbuilder\filter_base;
use tool_reportbuilder\local\helpers\relative_dates;

defined('MOODLE_INTERNAL') || die;

/**
 * Class date_condition
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class date_condition extends filter_base {
    /**
     * Returns an array of comparison operators
     *
     * @return array of comparison operators
     * @throws \coding_exception
     */
    public function get_operators() : array {
        return array(0 => get_string('dateanyvalue', 'tool_reportbuilder'),
            1 => get_string('dateisnotempty', 'tool_reportbuilder'),
            2 => get_string('dateisempty', 'tool_reportbuilder'),
            3 => get_string('dateinthepast', 'tool_reportbuilder'),
            4 => get_string('dateinthefuture', 'tool_reportbuilder'),
            5 => get_string('datelast', 'tool_reportbuilder'),
            6 => get_string('datenext', 'tool_reportbuilder'),
            7 => get_string('datecurrent', 'tool_reportbuilder'),
            8 => get_string('dateprevious', 'tool_reportbuilder'),
            9 => get_string('dateupcoming', 'tool_reportbuilder'));
    }

    /**
     * Returns an array of time select options
     *
     * @return array of select options
     * @throws \coding_exception
     */
    public function get_time_operators() : array {
        return array(1 => get_string('day'),
            2 => get_string('week'),
            3 => get_string('month'),
            4 => get_string('quarter', 'tool_reportbuilder'),
            5 => get_string('year'));
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
        $objs['select']->setLabel(get_string('limiterfor', 'filters', $this->get_formatted_header()));

        $objs['text'] = $mform->createElement('text', $this->name, null, ['size' => 3]);
        $objs['text']->setLabel(get_string('valuefor', 'filters', $this->get_formatted_header()));

        $objs['selecttime'] = $mform->createElement('select', $this->name.'_op2', null, $this->get_time_operators());
        $objs['selecttime']->setLabel(get_string('limiterfor', 'filters', $this->get_formatted_header()));

        $mform->setType($this->name, PARAM_RAW);
        $mform->setType($this->name.'_op', PARAM_INT);
        $mform->setDefault($this->name.'_op', 0);
        $mform->setType($this->name.'_op2', PARAM_INT);
        $mform->setDefault($this->name.'_op2', 1);

        $mform->addElement('group', $this->name.'_grp', '', $objs, '', false);

        $mform->hideIf($this->name, $this->name.'_op', 'eq', 0);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', 1);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', 2);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', 3);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', 4);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', 7);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', 8);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', 9);

        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', 0);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', 1);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', 2);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', 3);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', 4);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', 5);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', 6);

        $this->common_footer($mform);
    }

    /**
     * Build the sql filter condition.
     *
     * @param array|null $currentvalues
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $currentvalues) : array {
        $param = db::generate_param_name();

        $field = $this->reportfilter->get_field_sql();
        $params = $this->reportfilter->get_field_params();

        $operator = array_key_exists("{$this->name}_op", $currentvalues) ? $currentvalues["{$this->name}_op"] : null;
        $operator2 = array_key_exists("{$this->name}_op2", $currentvalues) ? $currentvalues["{$this->name}_op2"] : null;
        $value = array_key_exists($this->name, $currentvalues) ? $currentvalues[$this->name] : 0;

        if (is_null($operator)) {
            // Filter configuration is invalid. Ignore the filter.
            return ['', []];
        }
        if (($operator == 5 || $operator == 6) && !(int)$value) {
            // Filter configuration is invalid. Ignore the filter.
            return ['', []];
        }
        if (($operator == 7 || $operator == 8 || $operator == 9) && !(int)$operator2) {
            // Filter configuration is invalid. Ignore the filter.
            return ['', []];
        }

        switch($operator) {
            case 1: // Is not empty.
                $res = "$field IS NOT NULL AND $field <> 0";
                break;
            case 2: // Is empty.
                $res = "($field IS NULL OR $field = 0)";
                $params[$param] = time();
                break;
            case 3: // In the past.
                $res = "$field <= :{$param}";
                $params[$param] = time();
                break;
            case 4: // In the future.
                $res = "$field >= :{$param}";
                $params[$param] = time();
                break;
            case 5: // Last X days.
                $param2 = db::generate_param_name();
                $res = "$field >= :{$param} AND $field <= :{$param2}";
                $params[$param] = time() - DAYSECS * ((int)$value);
                $params[$param2] = time();
                break;
            case 6: // Next X days.
                $param2 = db::generate_param_name();
                $res = "$field >= :{$param} AND $field <= :{$param2}";
                $params[$param] = time();
                $params[$param2] = time() + DAYSECS * ((int)$value);
                break;
            case 7: // Current [day/week/month/quarter/year/financial year].
                $param2 = db::generate_param_name();
                $res = "$field >= :{$param} AND $field <= :{$param2}";
                $period = $this->get_time_operators()[$operator2];
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('current', $period);
                return [$res, $params];
                break;
            case 8: // Previous [day/week/month/quarter/year/financial year].
                $param2 = db::generate_param_name();
                $res = "$field >= :{$param} AND $field <= :{$param2}";
                $period = $this->get_time_operators()[$operator2];
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('previous', $period);
                return [$res, $params];
                break;
            case 9: // Upcoming [day/week/month/quarter/year/financial year].
                $param2 = db::generate_param_name();
                $res = "$field >= :{$param} AND $field <= :{$param2}";
                $period = $this->get_time_operators()[$operator2];
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('upcoming', $period);
                return [$res, $params];
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

        // TODO re-write to avoid concatenation.
        $operatorstr = $this->get_formatted_header() . ' ' . $operators[$operator];

        if ($operator == 5 || $operator == 6) {
            return $operatorstr . ' ' . ((int)$value) . ' ' . get_string('days');
        }

        return $operatorstr;
    }

    /**
     * Define the type of the filter.
     *
     * @return string
     */
    protected function filter_type() : string {
        // TODO this is not actually text.
        return 'filter-text';
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
        $operators = $extended ? $this->get_operators() : [1 => 1, 5 => 5];
        foreach ($operators as $operator => $unused) {
            $rv[] = [$this->name . '_op' => $operator, $this->name => 10];
        }
        return $rv;
    }
}

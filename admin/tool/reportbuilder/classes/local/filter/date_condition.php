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
 * Class containing the logic for the condition date
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\filter;

use tool_wp\db;
use tool_reportbuilder\filter_base;
use tool_reportbuilder\local\helpers\relative_dates;

/**
 * Class date_condition
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class date_condition extends filter_base {
    /** @var int Date contains any value */
    public const DATE_ANY = 0;
    /** @var int Date is not empty */
    public const DATE_NOT_EMPTY = 1;
    /** @var int Date is empty */
    public const DATE_EMPTY = 2;
    /** @var int Past date */
    public const DATE_PAST = 3;
    /** @var int Future date */
    public const DATE_FUTURE = 4;
    /** @var int Date in the last X days */
    public const DATE_LAST = 5;
    /** @var int Date in the next X days */
    public const DATE_NEXT = 6;
    /** @var int Date relative to now (current week, month, etc) */
    public const DATE_CURRENT = 7;
    /** @var int Past date relative to now (previous week, month, etc) */
    public const DATE_PREVIOUS = 8;
    /** @var int Future date relative to now (next week, month, etc) */
    public const DATE_UPCOMING = 9;

    /** @var int Time day */
    public const DATE_RELATIVE_DAY = 1;
    /** @var int Time week */
    public const DATE_RELATIVE_WEEK = 2;
    /** @var int Time month */
    public const DATE_RELATIVE_MONTH = 3;
    /** @var int Time quarter */
    public const DATE_RELATIVE_QUARTER = 4;
    /** @var int Time year */
    public const DATE_RELATIVE_YEAR = 5;

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
        $operator2 = array_key_exists("{$this->name}_op2", $values) ? $values["{$this->name}_op2"] : null;
        $value = array_key_exists($this->name, $values) ? $values[$this->name] : 0;
        $newconditionname = $newcondition->get_filter_persistent()->get('uniqueidentifier');
        return [
            $newconditionname.'_operator' => $operator,
            $newconditionname.'_operator2' => $operator2,
            $newconditionname => $value,
        ];
    }

    /**
     * Returns an array of comparison operators
     *
     * @return array of comparison operators
     * @throws \coding_exception
     */
    public function get_operators() : array {
        return [
            self::DATE_ANY       => new \lang_string('dateanyvalue', 'tool_reportbuilder'),
            self::DATE_NOT_EMPTY => new \lang_string('dateisnotempty', 'tool_reportbuilder'),
            self::DATE_EMPTY     => new \lang_string('dateisempty', 'tool_reportbuilder'),
            self::DATE_PAST      => new \lang_string('dateinthepast', 'tool_reportbuilder'),
            self::DATE_FUTURE    => new \lang_string('dateinthefuture', 'tool_reportbuilder'),
            self::DATE_LAST      => new \lang_string('datelast', 'tool_reportbuilder'),
            self::DATE_NEXT      => new \lang_string('datenext', 'tool_reportbuilder'),
            self::DATE_CURRENT   => new \lang_string('datecurrent', 'tool_reportbuilder'),
            self::DATE_PREVIOUS  => new \lang_string('dateprevious', 'tool_reportbuilder'),
            self::DATE_UPCOMING  => new \lang_string('dateupcoming', 'tool_reportbuilder')
        ];
    }

    /**
     * Returns an array of relative periods of time select options
     *
     * TODO: define the strings consistently instead of re-using from core (mixed casing in English lang pack)
     *
     * @return array of select options
     */
    public function get_time_period_operators() : array {
        return [
            self::DATE_RELATIVE_DAY     => new \lang_string('day'),
            self::DATE_RELATIVE_WEEK    => new \lang_string('week'),
            self::DATE_RELATIVE_MONTH   => new \lang_string('month'),
            self::DATE_RELATIVE_QUARTER => new \lang_string('quarter', 'tool_reportbuilder'),
            self::DATE_RELATIVE_YEAR    => new \lang_string('year')
        ];
    }

    /**
     * Return a mapping of relative time period constants used internally, to those expected by the {@see relative_dates} helper
     *
     * @param int $timeperiod One of the relative time period constants
     * @return string
     */
    protected function get_time_period_name(int $timeperiod): string {
        $mapping = [
            self::DATE_RELATIVE_DAY     => 'day',
            self::DATE_RELATIVE_WEEK    => 'week',
            self::DATE_RELATIVE_MONTH   => 'month',
            self::DATE_RELATIVE_QUARTER => 'quarter',
            self::DATE_RELATIVE_YEAR    => 'year',
        ];

        return $mapping[$timeperiod] ?? (string) $timeperiod;
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

        $objs['selecttime'] = $mform->createElement('select', $this->name.'_op2', null, $this->get_time_period_operators());
        $objs['selecttime']->setLabel(get_string('limiterfor', 'filters', $this->get_formatted_header()));

        $mform->setType($this->name, PARAM_RAW);
        $mform->setType($this->name.'_op', PARAM_INT);
        $mform->setDefault($this->name.'_op', self::DATE_ANY);
        $mform->setType($this->name.'_op2', PARAM_INT);
        $mform->setDefault($this->name.'_op2', self::DATE_RELATIVE_DAY);

        $mform->addElement('group', $this->name.'_grp', '', $objs, '', false);

        $mform->hideIf($this->name, $this->name.'_op', 'eq', self::DATE_ANY);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', self::DATE_NOT_EMPTY);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', self::DATE_EMPTY);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', self::DATE_PAST);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', self::DATE_FUTURE);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', self::DATE_CURRENT);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', self::DATE_PREVIOUS);
        $mform->hideIf($this->name, $this->name.'_op', 'eq', self::DATE_UPCOMING);

        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', self::DATE_ANY);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', self::DATE_NOT_EMPTY);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', self::DATE_EMPTY);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', self::DATE_PAST);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', self::DATE_FUTURE);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', self::DATE_LAST);
        $mform->hideIf($this->name.'_op2', $this->name.'_op', 'eq', self::DATE_NEXT);

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
        if (($operator == self::DATE_LAST || $operator == self::DATE_NEXT) && !(int)$value) {
            // Filter configuration is invalid. Ignore the filter.
            return ['', []];
        }
        if (($operator == self::DATE_CURRENT || $operator == self::DATE_PREVIOUS || $operator == self::DATE_UPCOMING)
            && !(int)$operator2) {
            // Filter configuration is invalid. Ignore the filter.
            return ['', []];
        }

        switch($operator) {
            case self::DATE_NOT_EMPTY: // Is not empty.
                $res = "$field IS NOT NULL AND $field <> 0";
                break;
            case self::DATE_EMPTY: // Is empty.
                $res = "($field IS NULL OR $field = 0)";
                $params[$param] = time();
                break;
            case self::DATE_PAST: // In the past.
                $res = "$field <= :{$param}";
                $params[$param] = time();
                break;
            case self::DATE_FUTURE: // In the future.
                $res = "$field >= :{$param}";
                $params[$param] = time();
                break;
            case self::DATE_LAST: // Last X days.
                $param2 = db::generate_param_name();
                $res = "$field >= :{$param} AND $field <= :{$param2}";
                $params[$param] = time() - DAYSECS * ((int)$value);
                $params[$param2] = time();
                break;
            case self::DATE_NEXT: // Next X days.
                $param2 = db::generate_param_name();
                $res = "$field >= :{$param} AND $field <= :{$param2}";
                $params[$param] = time();
                $params[$param2] = time() + DAYSECS * ((int)$value);
                break;
            case self::DATE_CURRENT: // Current [day/week/month/quarter/year/financial year].
                $param2 = db::generate_param_name();
                $res = "$field >= :{$param} AND $field <= :{$param2}";
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('current',
                    $this->get_time_period_name($operator2));
                return [$res, $params];
                break;
            case self::DATE_PREVIOUS: // Previous [day/week/month/quarter/year/financial year].
                $param2 = db::generate_param_name();
                $res = "$field >= :{$param} AND $field <= :{$param2}";
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('previous',
                    $this->get_time_period_name($operator2));
                return [$res, $params];
                break;
            case self::DATE_UPCOMING: // Upcoming [day/week/month/quarter/year/financial year].
                $param2 = db::generate_param_name();
                $res = "$field >= :{$param} AND $field <= :{$param2}";
                [$params[$param], $params[$param2]] = relative_dates::get_start_and_end_timestamp_for('upcoming',
                    $this->get_time_period_name($operator2));
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

        if ($operator == self::DATE_LAST || $operator == self::DATE_NEXT) {
            return $operatorstr . ' ' . ((int)$value) . ' ' . get_string('days');
        }

        return $operatorstr;
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
        foreach (array_keys($operators) as $operator) {
            $rv[] = [$this->name . '_op' => $operator, $this->name => 10];
        }
        return $rv;
    }
}

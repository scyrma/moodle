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
 * Class containing the logic for the filter date
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\filter;

use tool_wp\db;

defined('MOODLE_INTERNAL') || die;

/**
 * Class date_filter
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class date_filter extends date_condition {

    /**
     * Returns an array of comparison operators
     *
     * @return array
     */
    public function get_operators() : array {
        return [
            0 => get_string('dateanyvalue', 'tool_reportbuilder'),
            1 => get_string('dateisnotempty', 'tool_reportbuilder'),
            2 => get_string('dateisempty', 'tool_reportbuilder'),
            3 => get_string('daterange', 'tool_reportbuilder'),
        ];
    }

    /**
     * Adds controls specific to this filter in the form
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public function setup_form(\MoodleQuickForm $mform) {
        $this->common_header($mform);

        $mform->addElement('select', $this->name . '_op', null, $this->get_operators());
        $mform->setType($this->name . '_op', PARAM_INT);
        $mform->setDefault($this->name . '_op', 0);

        $mform->addElement('date_selector', $this->name . '_daterangefrom',
            get_string('daterangefrom', 'tool_reportbuilder'), ['optional' => true]);
        $mform->setType($this->name . '_daterangefrom', PARAM_INT);
        $mform->setDefault($this->name . '_daterangefrom', 0);
        $mform->hideIf($this->name . '_daterangefrom', $this->name . '_op', 'neq', 3);

        $mform->addElement('date_selector', $this->name . '_daterangeto',
            get_string('daterangeto', 'tool_reportbuilder'), ['optional' => true]);
        $mform->setType($this->name . '_daterangeto', PARAM_INT);
        $mform->setDefault($this->name . '_daterangeto', 0);
        $mform->hideIf($this->name . '_daterangeto', $this->name . '_op', 'neq', 3);

        $this->common_footer($mform);
    }

    /**
     * Build the SQL filter condition
     *
     * @param array|null $currentvalues
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $currentvalues) : array {
        $operator = $currentvalues[$this->name . '_op'] ?? null;

        // If we are using the date range operator then build SQL filter here, otherwise pass to parent class.
        if ($operator == 3) {
            $daterangefrom = $currentvalues[$this->name . '_daterangefrom'] ?? null;
            $daterangeto = $currentvalues[$this->name . '_daterangeto'] ?? null;

            $field = $this->reportfilter->get_field_sql();

            $wheres = [];
            $params = [];

            if ($daterangefrom) {
                $paramdaterangefrom = db::generate_param_name();
                $wheres[] = "$field >= :{$paramdaterangefrom}";
                $params[$paramdaterangefrom] = $daterangefrom;
            }

            if ($daterangeto) {
                $paramdaterangeto = db::generate_param_name();
                $wheres[] = "$field < :{$paramdaterangeto}";
                $params[$paramdaterangeto] = $daterangeto + DAYSECS;
            }

            return [implode(' AND ', $wheres), $params];
        } else {
            return parent::get_sql_filter($currentvalues);
        }
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
        $return = [];

        $operators = $extended ? $this->get_operators() : [3 => 3];
        foreach (array_keys($operators) as $operator) {
            $return[] = [
                $this->name . '_op' => $operator,
                $this->name . '_daterangefrom' => 10,
                $this->name . '_daterangeto' => 20,
            ];
        }

        return $return;
    }
}
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
 * Class containing the logic for the filter course_selector.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\filter;

use tool_reportbuilder\filter_base;

defined('MOODLE_INTERNAL') || die;

/**
 * Class course_selector.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_selector extends filter_base {
    /**
     * Adds controls specific to this filter in the form.
     * @param \MoodleQuickForm $mform a MoodleForm object to setup
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function setup_form(\MoodleQuickForm $mform) {
        $this->common_header($mform);

        $options = [
            'multiple' => true,
        ];
        $mform->addElement('course', $this->name.'_op', get_string('selectcourses', 'tool_reportbuilder'), $options);

        $this->common_footer($mform);
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

        $value = array_key_exists("{$this->name}_op", $values) ? $values["{$this->name}_op"] : null;
        $field = $this->reportfilter->get_field_sql();

        if (!$value) {
            return ['', []];
        }

        $sql = "($field.id = " . implode(" OR $field.id = ", $value) . ')';
        return [$sql, []];
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label(array $data) : string {
        return 'course_selector';
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
        return 'filter-course_selector';
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
        ];
    }
}
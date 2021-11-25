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
 * Class containing the logic for the filter course_selector.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\filter;

use tool_reportbuilder\filter_base;
use tool_wp\db;
use tool_wp\exporter_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die;

/**
 * Class course_selector.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
        $mform->addElement('course', $this->name.'_op', get_string('selectcourses', 'tool_reportbuilder'), $options)
            ->setHiddenLabel(true);

        $this->common_footer($mform);
    }

    /**
     * Return list of selected course ID's
     *
     * @param array $values
     * @return int[]
     */
    protected function get_selected_courses(array $values): array {
        return $values[$this->name . '_op'] ?? [];
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        global $DB;

        if (!$values) {
            return ['', []];
        }

        $value = $this->get_selected_courses($values);
        $field = $this->reportfilter->get_field_sql();

        if (!$value) {
            return ['', []];
        }

        $paramprefix = db::generate_param_name() . '_';
        list($select, $params) = $DB->get_in_or_equal($value, SQL_PARAMS_NAMED, $paramprefix);

        return ["{$field}.id $select", $params];
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
     * Add course ID condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $courseids = $this->get_selected_courses($this->get_values());
        foreach ($courseids as $courseid) {
            $exporter->add_mapping('course', $courseid);
        }
    }

    /**
     * Get course ID condition field mapping during import
     *
     * @param importer_base $importer
     * @return array
     */
    public function get_importer_mapping(importer_base $importer): array {
        $courseids = $this->get_selected_courses($this->get_values());

        $mappedcourseids = array_map(static function(int $courseid) use ($importer): int {
            return $importer->get_mapping('course', $courseid, IGNORE_MISSING) ?? 0;
        }, $courseids);

        return [$this->name . '_op' => $mappedcourseids];
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

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
 * Class containing the logic for the filter job_time.
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_reportbuilder\filter;

use tool_organisation\helper;
use tool_reportbuilder\filter_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class job_time
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class job_time extends filter_base {

    /**
     * Adds controls specific to this filter in the form.
     *
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function setup_form(\MoodleQuickForm $mform) {
        $this->common_header($mform);

        $options = [
            0 => get_string('all'),
            1 => get_string('onlycurrent', 'tool_organisation'),
            2 => get_string('onlypast', 'tool_organisation'),
            3 => get_string('onlyfuture', 'tool_organisation')
        ];
        $mform->addElement('select', $this->name, get_string('showjobs', 'tool_organisation'), $options);
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

        $value = array_key_exists($this->name, $values) ? (int)$values[$this->name] : 0;

        if ($value === 0) {
            return ['', []];
        }

        $alias = $this->reportfilter->get_field_sql();
        $now = helper::round_time(time());

        switch ($value) {
            case 1:
                $where = "$now >= {$alias}.startdate
                AND ($now <= {$alias}.enddate OR {$alias}.enddate = 0 OR {$alias}.enddate IS NULL)";
                break;
            case 2:
                $where = "$now > {$alias}.enddate AND {$alias}.enddate <> 0";
                break;
            case 3:
                $where = "{$alias}.startdate > $now";
                break;
            default:
                $where = '';
                break;
        }

        return [$where, []];
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label(array $data) : string {
        return 'job_time';
    }

    /**
     * Define the type of the filter.
     *
     * @return string
     */
    protected function filter_type() : string {
        return 'filter-job_time';
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
            [$this->name => 0],
            [$this->name => 1],
            [$this->name => 2],
            [$this->name => 3]
        ];
    }
}
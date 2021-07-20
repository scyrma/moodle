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
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Daniel Neis <daniel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_reportbuilder\filter;

use tool_organisation\helper;
use tool_reportbuilder\filter_base;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die;

/**
 * General filter for jobs.
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Daniel Neis <daniel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class showpastjobs extends filter_base {

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        // The filter is backwards, we only apply the filtering when the checkbox isn't checked/enabled.
        $value = $values[$this->name] ?? 0;
        if ($value == 1) {
            return ['', []];
        }

        $paramtime = db::generate_param_name();
        $field = $this->reportfilter->get_field_sql();
        $where = "({$field} = 0 OR {$field} >= :{$paramtime})";
        $params = [$paramtime => helper::round_time(time())];

        return [$where, $params];
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param \MoodleQuickForm $mform a MoodleForm object to setup
     */
    public function setup_form(\MoodleQuickForm $mform) {
        $this->common_header($mform);
        $mform->addElement('advcheckbox', $this->name, get_string('showpastjobs', 'tool_organisation'));
        $this->common_footer($mform);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label(array $data) : string {
        // TODO.
        return 'this filter label';
    }

    /**
     * This filter is active if the checkbox is checked, rather than if SQL filter data is returned (inverse of most filters)
     *
     * @param array $values
     * @return bool
     */
    protected function is_active(?array $values): bool {
        $value = $values[$this->name] ?? 0;

        return $value == 1;
    }

    /**
     * Define the type of the filter.
     *
     * @return string
     */
    protected function filter_type() : string {
        return 'filter-showpastjobs';
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
            [$this->name => 1],
            [$this->name => 0],
        ];
    }
}

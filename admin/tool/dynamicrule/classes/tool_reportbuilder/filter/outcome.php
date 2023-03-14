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

namespace tool_dynamicrule\tool_reportbuilder\filter;

use tool_reportbuilder\local\filter\select;
use tool_wp\db;

/**
 * Rule outcome report filter
 *
 * Does not require a join with the rule outcome table, and each rule is returned once regardless of number of times it uses
 * the matching outcome
 *
 * @package     tool_dynamicrule
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class outcome extends select {

    /**
     * Filter operators
     *
     * @return array
     */
    public function get_operators() {
        return [
            self::ANY_VALUE => get_string('isanyvalue', 'filters'),
            self::EQUAL_TO => get_string('contains', 'filters'),
        ];
    }

    /**
     * Generate SQL filter for rules with matching outcomes
     *
     * @param array|null $values
     * @return array
     */
    public function get_sql_filter(?array $values): array {
        $operator = $values["{$this->name}_op"] ?? self::ANY_VALUE;
        $outcome = $values[$this->name] ?? '';

        if ($operator == self::ANY_VALUE || empty($outcome)) {
            return ['', []];
        }

        $rulefieldsql = $this->reportfilter->get_field_sql();

        $outcometablealias = db::generate_alias();
        $outcomeparam = db::generate_param_name();

        $containsoutcomesql = "EXISTS (
            SELECT 1
              FROM {tool_dynamicrule_outcome} {$outcometablealias}
             WHERE {$outcometablealias}.classname = :{$outcomeparam}
               AND {$outcometablealias}.ruleid = {$rulefieldsql}
        )";

        return [$containsoutcomesql, [$outcomeparam => $outcome]];
    }
}

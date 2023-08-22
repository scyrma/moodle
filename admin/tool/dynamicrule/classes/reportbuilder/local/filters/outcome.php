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

declare(strict_types=1);

namespace tool_dynamicrule\reportbuilder\local\filters;

use lang_string;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use tool_dynamicrule\api;

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
     * Override parent operators, supporting only ANY_VALUE and EQUAL_TO (contains)
     *
     * @return lang_string[]
     */
    protected function get_operators(): array {
        return [
            self::ANY_VALUE => new lang_string('filterisanyvalue', 'core_reportbuilder'),
            self::EQUAL_TO => new lang_string('filtercontains', 'core_reportbuilder'),
        ];
    }

    /**
     * Override parent options, auto-populating with available outcomes
     *
     * @return string[]
     */
    protected function get_select_options(): array {
        $outcomes = [];
        foreach (api::get_outcomes() as $outcome) {
            $outcomes[get_class($outcome)] = $outcome->get_title();
        }
        return $outcomes;
    }

    /**
     * Generate SQL filter for rules with matching outcomes
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values): array {
        $operator = $values["{$this->name}_operator"] ?? self::ANY_VALUE;
        $outcome = $values["{$this->name}_value"] ?? '';

        if ($operator == self::ANY_VALUE || empty($outcome)) {
            return ['', []];
        }

        $fieldsql = $this->filter->get_field_sql();
        $fieldparams = $this->filter->get_field_params();

        $outcometablealias = database::generate_alias();
        $outcomeparam = database::generate_param_name();

        $containsoutcomesql = "EXISTS (
            SELECT 1
              FROM {tool_dynamicrule_outcome} {$outcometablealias}
             WHERE {$outcometablealias}.classname = :{$outcomeparam}
               AND {$outcometablealias}.ruleid = {$fieldsql}
        )";

        $fieldparams[$outcomeparam] = $outcome;

        return [$containsoutcomesql, $fieldparams];
    }
}

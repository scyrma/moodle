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

namespace tool_dynamicrule\external;

use tool_dynamicrule\permission;
use tool_dynamicrule\api;
use tool_dynamicrule\rule;
use tool_tenant\sharedspace;

/**
 * External function duplicate_rule_validation.
 *
 * @package   tool_dynamicrule
 * @copyright 2023 Moodle Pty Ltd <support@moodle.com>
 * @author    2023 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class duplicate_rule_validation extends \external_api {

    /**
     * Returns the structure of parameters.
     *
     * @return \external_function_parameters
     */
    protected static function execute_parameters(): \external_function_parameters {
        $params = ['id' => new \external_value(PARAM_INT, 'The ID of the rule to be validated for duplication', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Validates rule for duplication.
     *
     * @param int $ruleid The ID of the rule
     * @return string The validation output in html
     */
    public static function execute(int $ruleid): string {
        global $PAGE;
        $context = \context_system::instance();
        self::validate_context($context);
        $params = self::validate_parameters(self::execute_parameters(), ['id' => $ruleid]);
        $rule = api::get_rule($params['id']);
        permission::require_can_duplicate_rule($rule);

        $conditionsexcluded = [];
        $outcomesexcluded = [];
        if ($rule->is_shared() && !sharedspace::is_shared_space()) {
            // We are only interested in rules that are duplicated from shared
            // to normal tenant. It is not possible to duplicate in other direction.
            $conditions = api::get_rule_conditions($ruleid);
            foreach ($conditions as $condition) {
                $conditionruletypes = $condition->supports_rule_types();
                if (~ $conditionruletypes & rule::TYPE_NORMAL) {
                    $conditionsexcluded[] = $condition->get_title();
                }
            }

            $outcomes = api::get_rule_outcomes($ruleid);
            foreach ($outcomes as $outcome) {
                $outcomeruletypes = $outcome->supports_rule_types();
                if (~ $outcomeruletypes & rule::TYPE_NORMAL) {
                    $outcomesexcluded[] = $outcome->get_title();
                }
            }
        }
        $output = $PAGE->get_renderer('tool_dynamicrule');
        $params = new \tool_dynamicrule\output\duplicate_rule_validation($rule, $conditionsexcluded, $outcomesexcluded);
        return $output->render_from_template('tool_dynamicrule/duplicate_rule_validation',
            $params->export_for_template($output));
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return \external_value
     */
    public static function execute_returns(): \external_value {
        return new \external_value(PARAM_RAW, 'HTML formatted list of conflicting conditions and actions');
    }
}

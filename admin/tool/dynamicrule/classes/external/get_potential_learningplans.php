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

namespace tool_dynamicrule\external;

use context_system;
use external_function_parameters;
use external_multiple_structure;
use external_value;

/**
 * External function get_potential_learningplans.
 *
 * @package   tool_dynamicrule
 * @copyright 2023 Moodle Pty Ltd <support@moodle.com>
 * @author    2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_potential_learningplans extends \external_api {

    /**
     * Parameters for getting learning plan templates.
     *
     * @return external_function_parameters
     */
    protected static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_NOTAGS, 'Search string', VALUE_REQUIRED),
        ]);
    }

    /**
     * External function to get matching learning plan templates.
     *
     * @param string $search
     * @return array learning plan templates
     */
    public static function execute(string $search): array {
        global $DB;

        $context = context_system::instance();

        ['search' => $search] = self::validate_parameters(self::execute_parameters(), ['search' => $search]);
        self::validate_context($context);

        // Check capability.
        require_capability('moodle/competency:planmanage', $context);

        $query = "SELECT ct.id, ct.shortname
                    FROM {competency_template} ct
                   WHERE ct.contextid = :contextid";
        $params = ['contextid' => $context->id];
        $i = 0;
        foreach (preg_split('/ +/', trim($search), -1) as $word) {
            $i++;
            $query .= " AND (" . $DB->sql_like('ct.shortname', ":search{$i}", false, false) . ')';
            $params += ["search{$i}" => '%' . $DB->sql_like_escape($word) . '%'];
        }

        $learningplans = $DB->get_records_sql($query, $params);

        // We apply format string to the shortname.
        foreach ($learningplans as $learningplan) {
            $learningplan->shortname = format_string($learningplan->shortname, true, ['context' => $context, 'escape' => false]);
        }

        return $learningplans;
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new \external_single_structure([
            'id' => new external_value(PARAM_INT, 'Learning plan template ID'),
            'shortname' => new external_value(PARAM_TEXT, 'Learning plan template short name'),
        ]));
    }
}

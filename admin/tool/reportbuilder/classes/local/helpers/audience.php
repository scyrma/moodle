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
 * Class containing report audience helper methods
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use cache;
use cache_session;
use tool_reportbuilder\audience_base;
use tool_reportbuilder\local\models\audiences;
use tool_tenant\hierarchy;
use tool_wp\db;

/**
 * Helper class
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience {

    /**
     * Return audience base records for a given report
     *
     * @param int $reportid
     * @return audience_base[]
     */
    public static function get_base_records(int $reportid) : array {
        global $DB;

        $reportaudiences = [];
        $records = $DB->get_records(audiences::TABLE, ['reportid' => $reportid]);
        foreach ($records as $record) {
            if ($instance = audience_base::instance(0, $record)) {
                $reportaudiences[] = $instance;
            }
        }
        return $reportaudiences;
    }

    /**
     * Returns list of reports that the specified user can access. If no user is passed current user will be used.
     *
     * @param int $userid
     * @return array
     */
    public static function get_allowed_reports(int $userid = 0): array {
        global $USER, $DB;

        // Get all reports.
        [$where, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('rb.tenantid', 'rb.shared=1');
        $sql = "SELECT rb.id
                FROM {tool_reportbuilder} rb
                WHERE {$where}";
        $reports = $DB->get_records_sql($sql, $params);

        $allowedreports = [];
        // Get all audiences for each report.
        foreach ($reports as $report) {
            $audiences = $DB->get_records('tool_reportbuilder_audiences', ['reportid' => $report->id]);

            if ($audiences) {
                $joins = [];
                $wheres = [];
                $params = [];
                foreach ($audiences as $audience) {
                    $instance = audience_base::instance(0, $audience);
                    if (!$instance) {
                        continue 2;
                    }
                    [$instancejoin, $instancewhere, $instanceparams] = $instance->get_sql('u');
                    $joins[] = $instancejoin;
                    $wheres[] = $instancewhere;
                    $params += $instanceparams;
                }

                $paramuserid = db::generate_param_name();
                $params[$paramuserid] = $userid ?: $USER->id;

                $alljoins = implode(' ', $joins);
                $allwheres = implode(' OR ', $wheres);
                $query = "SELECT DISTINCT(u.id) FROM {user} u {$alljoins} WHERE ({$allwheres}) AND u.id = :{$paramuserid}";
                $result = $DB->get_records_sql($query, $params);
                if ($result) {
                    $allowedreports[] = $report->id;
                }
            }
        }

        return $allowedreports;
    }

    /**
     * Generate SQL select clause and params for selecting reports specified user can access
     *
     * @param string $reporttablealias
     * @param int $userid
     * @return array
     */
    public static function user_reports_list_sql(string $reporttablealias, int $userid = 0): array {
        global $DB;

        $allowedreports = self::get_allowed_reports($userid);

        if (empty($allowedreports)) {
            return ['1=0', []];
        }

        // Get all sql audiences.
        [$sqlin, $paramin] = $DB->get_in_or_equal($allowedreports, SQL_PARAMS_NAMED);
        $sql = "{$reporttablealias}.id $sqlin";

        return [$sql, $paramin];
    }

    /**
     * Return list of report ID's current user can access. This is potentially expensive to calculate and can be
     * called multiple times within a page, so the result is stored in the session cache.
     *
     * @return int[]
     */
    public static function user_reports_list() : array {
        global $USER, $DB;

        /** @var cache_session $cache */
        $cache = cache::make('tool_reportbuilder', 'userreports');
        $data = $cache->get('data');
        if ($data === false) {
            [$select, $params] = self::user_reports_list_sql('rb', $USER->id);
            $sql = "SELECT rb.id
                      FROM {tool_reportbuilder} rb
                     WHERE {$select}";

            $data = $DB->get_fieldset_sql($sql, $params);
            $cache->set('data', $data);
        }

        return $data;
    }

    /**
     * Return appropriate list of where clauses and params for given audiences
     *
     * @param audiences[] $audiences
     * @return array[] [$wheres, $params]
     */
    public static function user_audience_sql(array $audiences): array {
        $wheres = $params = [];

        foreach ($audiences as $audience) {
            if ($instance = audience_base::instance(0, $audience->to_record())) {
                [$instancejoin, $instancewhere, $instanceparams] = $instance->get_sql('u');

                $wheres[] = "u.id IN (SELECT u.id FROM {user} u {$instancejoin} WHERE {$instancewhere})";
                $params += $instanceparams;
            }
        }

        return [$wheres, $params];
    }

    /**
     * Returns the list of audiences types in the system.
     *
     * @return array
     */
    public static function get_all_audience_types(): array {
        $instances = [];
        // Go through all plugins and find out which of them got reportbuilder class instances defined.
        $componentinstances = \core_component::get_component_classes_in_namespace(null,
            '\\tool_reportbuilder\\audiences');
        foreach (array_keys($componentinstances) as $classname) {
            // Create instance if this is extending audience base class.
            if (is_subclass_of($classname, audience_base::class)) {
                $instances[] = $classname::instance();
            }
        }
        return $instances;
    }

    /**
     * Returns the list of audiences types in the system ordered by category (plugin).
     *
     * This method returns the list of all audience types filtered by the ones the user can add.
     *
     * @return array[]
     */
    public static function get_all_audience_types_by_category(): array {
        $audienceinstances = self::get_all_audience_types();

        $categorisedaudiences = [];
        $categorynamemap = [];
        foreach ($audienceinstances as $audienceinstance) {
            if ($audienceinstance->user_can_add()) {
                // Derive key from category name and store name mapping.
                $categoryname = $audienceinstance->get_category();
                $categorykey = strtolower(clean_param($categoryname, PARAM_ALPHA));
                if (!array_key_exists($categorykey, $categorynamemap)) {
                    $categorynamemap[$categorykey] = $categoryname;
                }
                // Add condition to the list of conditions in this category.
                $categorisedaudiences[$categorykey][] = $audienceinstance;
            }
        }

        return [$categorisedaudiences, $categorynamemap];
    }
}

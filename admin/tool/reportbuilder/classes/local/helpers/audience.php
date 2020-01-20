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
 * Class containing report audience helper methods
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use cache;
use stdClass;
use tool_organisation\helper as org_helper;
use tool_reportbuilder\report_base;
use tool_reportbuilder\local\models\audience as model;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die;

/**
 * Helper class
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience {

    /**
     * Return count of audience records for a given report
     *
     * @param report_base $report
     * @return int
     */
    public static function count_records(report_base $report) : int {
        return model::count_records(['reportid' => $report->get_id()]);
    }

    /**
     * Return audience records for a given report
     *
     * @param report_base $report
     * @return model[]
     */
    public static function get_records(report_base $report) : array {
        return model::get_records(['reportid' => $report->get_id()], 'id');
    }

    /**
     * Create an audience record
     *
     * @param stdClass $record
     * @return model
     */
    public static function create_record(stdClass $record) : model {
        cache::make('tool_reportbuilder', 'userreports')->purge();

        return (new model(0, $record))->create();
    }

    /**
     * Update an audience record
     *
     * @param int $id
     * @param stdClass $record
     * @return bool
     */
    public static function update_record(int $id, stdClass $record) : bool {
        cache::make('tool_reportbuilder', 'userreports')->purge();

        return (new model($id, $record))->update();
    }

    /**
     * Delete an audience record
     *
     * @param int $id
     * @return bool
     */
    public static function delete_record(int $id) : bool {
        cache::make('tool_reportbuilder', 'userreports')->purge();

        return (new model($id))->delete();
    }

    /**
     * Generate SQL select clause and params for joining job tables with report audience tables
     *
     * @param string $audiencetablealias
     * @return array [$sql, $params]
     */
    public static function user_reports_job_sql(string $audiencetablealias) : array {
        global $DB;

        $paramdeptpath = db::generate_param_name();
        $params[$paramdeptpath] = '/%';
        $departmentpathlike = $DB->sql_like('dch.path', $DB->sql_concat('d.path', ":{$paramdeptpath}"));

        $parampospath = db::generate_param_name();
        $params[$parampospath] = '/%';
        $positionpathlike = $DB->sql_like('pch.path', $DB->sql_concat('p.path', ":{$parampospath}"));

        $sql = "
             LEFT JOIN {tool_organisation_department} d ON d.id = {$audiencetablealias}.departmentid
             LEFT JOIN {tool_organisation_department} dch ON {$departmentpathlike}
             LEFT JOIN {tool_organisation_position} p ON p.id = {$audiencetablealias}.positionid
             LEFT JOIN {tool_organisation_position} pch ON {$positionpathlike}
                  JOIN {tool_organisation_job} j
                    ON (j.departmentid IN (d.id, CASE WHEN {$audiencetablealias}.subdepartments = 1 THEN dch.id ELSE 0 END)
                        OR {$audiencetablealias}.departmentid = 0)
                   AND (j.positionid IN (p.id, CASE WHEN {$audiencetablealias}.subpositions = 1 THEN pch.id ELSE 0 END)
                        OR {$audiencetablealias}.positionid = 0)";

        return [$sql, $params];
    }

    /**
     * Generate SQL select clause and params for selecting reports specified user can access
     *
     * @param string $reporttablealias
     * @param int $userid User ID (or 0 to use current user)
     * @return array [$sql, $params]
     */
    public static function user_reports_list_sql(string $reporttablealias, int $userid = 0) : array {
        global $USER;

        // Early exit if the organisation helper doesn't exist.
        if (!class_exists(org_helper::class)) {
            return ['1=2', []];
        }

        $audiencetablealias = db::generate_alias();

        list($join, $joinparams) = self::user_reports_job_sql($audiencetablealias);
        list($where, $whereparams) = org_helper::job_time_and_tenant_select(0);

        $params = array_merge($joinparams, $whereparams);

        $paramuserid = db::generate_param_name();
        $params[$paramuserid] = $userid ?: $USER->id;

        $sql = "EXISTS(
                SELECT 1
                  FROM {tool_reportbuilder_audience} {$audiencetablealias}
                       {$join}
                 WHERE {$audiencetablealias}.reportid = {$reporttablealias}.id
                   AND j.userid = :{$paramuserid}
                   AND {$where}
            )";

        return [$sql, $params];
    }

    /**
     * Return list of report ID's current user can access. This is potentially expensive to calculate and can be
     * called multiple times within a page, so the result is stored in the session cache.
     *
     * @return int[]
     */
    public static function user_reports_list() : array {
        global $USER, $DB;

        /** @var \cache_session $cache */
        $cache = cache::make('tool_reportbuilder', 'userreports');
        $data = $cache->get('data');
        if ($data === false) {
            list($select, $params) = self::user_reports_list_sql('rb', $USER->id);
            $sql = "SELECT rb.id
                      FROM {tool_reportbuilder} rb
                     WHERE {$select}";

            $data = $DB->get_fieldset_sql($sql, $params);
            $cache->set('data', $data);
        }

        return $data;
    }
}
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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_organisation\local\helpers;

use core\lock\lock_config;
use core\task\manager;
use core_reportbuilder\local\helpers\database;
use Throwable;
use tool_organisation\task\build_reporting;

/**
 * Building employee relationships
 *
 * @package    tool_organisation
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reporting {

    /**
     * Rebuilds the reporting table.
     *
     * @param int $tenantid
     */
    public static function rebuild(int $tenantid): void {
        $timeout = 5;

        // A namespace for the locks.
        $locktype = 'tool_organisation_reporting';

        // Get an instance of the currently configured lock_factory.
        $lockfactory = lock_config::get_lock_factory($locktype);

        // Get a new lock for the resource, wait for it if needed.
        // We want to prevent multiple process at time for same tenant - so include the tenantid in the key.
        if ($lock = $lockfactory->get_lock("tenantid:{$tenantid}", $timeout)) {
            try {
                // Let's build the reporting line for the current org structure.
                // First we need to build the direct reporting line rows, then we can go throughout them,
                // e.g: User A is direct manager of User B, and User B is direct manager of User C.
                $recordcount = self::insert_direct_relations($tenantid);
                $depthnumber = 1;
                while ($recordcount > 0) {
                    // Now we need to build the next reporting line, finding who is managed by User A, B and C,
                    // preventing circular dependency and supporting mixed reporting type.
                    $recordcount = self::insert_next_level_relations($tenantid, $depthnumber);
                    $depthnumber++;
                }
            } catch (Throwable $e) {
                debugging('Unable to rebuild the reporting line tree for tenant ' . $tenantid . ': ' .
                    $e->getMessage(), DEBUG_NORMAL, $e->getTrace());
            } finally {
                // Release the lock once finished.
                $lock->release();
            }
        }
    }

    /**
     * Rebuild first level of the reporting table (direct managers)
     * TODO WP-3391 add the new manually assign table to taking into account.
     *
     * @param int $tenantid
     * @return int number of inserted records
     */
    protected static function insert_direct_relations(int $tenantid): int {
        global $DB;

        // Unique aliases.
        [
            $job1,
            $job2,
            $job3,
            $job4,
            $job5,
            $pos1,
            $pos2,
            $pos3,
            $dep1,
            $dep2,
        ] = database::generate_aliases(10);
        $datatoinsert = [];

        $sqlpath1 = $DB->sql_concat("'/'", "{$job1}.userid", "'/'", "{$job2}.userid");
        $sqlpath2 = $DB->sql_concat("'/'", "{$job3}.userid", "'/'", "{$job4}.userid");

        // Condition used to retrieve the positions that could be parent over others in the same reporting line within
        // the whole position framework to define later if it is direct or not.
        $sqlpospathlikepos = $DB->sql_like("{$pos2}.path", $DB->sql_concat("{$pos1}.path", ':pathpercent'));

        // Condition used to retrieve the departments that could be parent over others in the same reporting line within
        // the whole department framework to define later if it is direct or not.
        $sqlpospathlikedep = $DB->sql_like("{$dep2}.path", $DB->sql_concat("{$dep1}.path", ':pathdep1'));
        $now = time();

        $DB->delete_records('tool_organisation_reporting', ['tenantid' => $tenantid]);

        // User with global manager position is a manager over everybody who has positions on any level below.
        // This user is a direct manager over people with position directly under them.
        $datatoinsert[] = "SELECT DISTINCT {$job1}.tenantid, {$job2}.userid AS userid, {$job1}.userid AS managerid,
               {$pos1}.globalpermissions AS permissions, 1 AS depth,
               {$sqlpath1} AS path,
               CASE WHEN {$pos2}.parentid = {$pos1}.id THEN 1 ELSE 0 END AS isdirect
        FROM {tool_organisation_job} {$job1}
            JOIN {tool_organisation_position} {$pos1} ON {$pos1}.id = {$job1}.positionid
            JOIN {tool_organisation_position} {$pos2} ON {$sqlpospathlikepos}
            JOIN {tool_organisation_job} {$job2} ON {$job2}.positionid = {$pos2}.id
        WHERE {$pos1}.globalmanager = 1
            AND {$job1}.tenantid = {$tenantid} AND {$job2}.tenantid = {$tenantid}
            AND {$job1}.userid <> {$job2}.userid
            AND ({$job1}.startdate < {$now} AND ({$job1}.enddate = 0 OR {$job1}.enddate > {$now}))
            AND ({$job2}.startdate < {$now} AND ({$job2}.enddate = 0 OR {$job2}.enddate > {$now}))";

        // User with department manager position is a direct manager over everybody in the same department
        // except for other department managers.
        $datatoinsert[] = "SELECT DISTINCT {$job3}.tenantid, {$job4}.userid AS userid, {$job3}.userid AS managerid,
               {$pos3}.departmentpermissions AS permissions, 1 AS depth, {$sqlpath2} AS path,
               CASE WHEN {$dep2}.id = {$dep1}.id THEN 1 ELSE 0 END AS isdirect
        FROM {tool_organisation_job} {$job3}
            JOIN {tool_organisation_position} {$pos3} ON {$pos3}.id = {$job3}.positionid
            JOIN {tool_organisation_department} {$dep1} ON {$dep1}.id = {$job3}.departmentid
            JOIN {tool_organisation_department} {$dep2} ON {$dep2}.id = {$dep1}.id OR {$sqlpospathlikedep}
            JOIN {tool_organisation_job} {$job4} ON {$job4}.departmentid = {$dep2}.id AND {$job4}.id <> {$job3}.id
        WHERE {$pos3}.departmentmanager = 1
            AND {$job3}.tenantid = {$tenantid}
            AND {$job4}.tenantid = {$tenantid}
            AND {$job3}.userid <> {$job4}.userid " .
                /* two department managers in the same department are not each others managers */
                "AND NOT EXISTS (SELECT 1 FROM {tool_organisation_job} {$job5}
                        JOIN {tool_organisation_position} p23 ON {$job5}.positionid = p23.id
                        WHERE {$job5}.userid = {$job4}.userid
                        AND {$job5}.departmentid = {$job4}.departmentid
                        AND p23.departmentmanager = 1
                        AND ({$job5}.startdate < {$now} AND ({$job5}.enddate = 0 OR {$job5}.enddate > {$now})))";

        $sql = "INSERT INTO {tool_organisation_reporting}
                (tenantid, userid, managerid, permissions, depth, path, isdirect)
                " . implode(" UNION ALL ", $datatoinsert);

        $DB->execute($sql, ['pathpercent' => '/%', 'pathdep1' => '/%']);

        return $DB->count_records('tool_organisation_reporting', ['tenantid' => $tenantid]);
    }

    /**
     * Rebuild next level of the reporting table (from previous levels in the same table)
     *
     * TODO WP-3391 add the new manually assign table to taking into account.
     * @param int $tenantid
     * @param int $previousdepth depth of the previous level
     * @return int number of inserted records
     */
    protected static function insert_next_level_relations(int $tenantid, int $previousdepth): int {
        global $DB;

        $rep = database::generate_alias();
        $rep2 = database::generate_alias();

        $sqlpath = $DB->sql_concat("{$rep}.path", "'/'", "{$rep2}.userid");
        $sqlcompare = $DB->sql_concat(":pl1", "{$rep2}.userid", ":pl2");

        // Condition used to exclude the circular dependencies that can end up in an infinite loops adding tons of records.
        $sqlnocircular = $DB->sql_like("{$rep}.path", $sqlcompare, true, true, true);

        $sql = "INSERT INTO {tool_organisation_reporting} (tenantid, userid, managerid, permissions, depth, path, isdirect)
        SELECT DISTINCT {$rep2}.tenantid, {$rep2}.userid, {$rep}.managerid, {$rep}.permissions, {$rep}.depth+1 AS depth,
                        {$sqlpath} AS path, 0 AS isdirect
        FROM {tool_organisation_reporting} {$rep}
            JOIN {tool_organisation_reporting} {$rep2} ON {$rep2}.managerid = {$rep}.userid
        WHERE {$rep}.tenantid = $tenantid
            AND {$rep}.depth = $previousdepth
            AND {$rep2}.tenantid = $tenantid
            AND {$rep2}.depth = 1
            AND {$rep2}.userid <> {$rep}.managerid
            AND $sqlnocircular";

        $DB->execute($sql, ['pl1' => '%/', 'pl2' => '/%']);

        return $DB->count_records('tool_organisation_reporting', ['tenantid' => $tenantid, 'depth' => $previousdepth + 1]);
    }

    /**
     * Helps to create SQL to retrieve users managed by the current manager
     *
     * Example:
     * [$where, $params] = reporting::get_managed_users_select($managerid);
     * $DB->get_records_sql("SELECT * FROM {user} u WHERE $where", $params);
     *
     * @param int $managerid user id of the manager
     * @param string $usertablealias alias of the 'user' table in the main query
     * @param int $withpermissions permissions that the manager must have over these users:
     *      0 to show all managed users;
     *      alternatively you can specify one or more of the following constants:
     *      organisation::PERM_VIEW_REPORTS, organisation::PERM_ALLOCATE_PROGRAMS, organisation::PERM_RECEIVE_NOTIFICATIONS,
     *      if several permissions are specified, the result will be users over whom the manager has
     *      at least one of these permissions;
     *      (if you need both permissions, just use this function twice and combine the results with "AND")
     * @param bool $directonly If set to true, then only users in direct reporting line will be returned
     * (default is to recurse all reporter users direct/no-direct)
     * @return array [$where, $params]
     */
    public static function get_managed_users_select(int $managerid, string $usertablealias = 'u', int $withpermissions = 0,
                                                    bool $directonly = false): array {
        global $DB;
        $r = database::generate_alias();

        $permissionsql = '';
        if ($withpermissions) {
            $p = database::generate_param_name();
            $permissionsql = " AND " . $DB->sql_bitand("{$r}.permissions", ":{$p}") . " <> 0 ";
            $params[$p] = $withpermissions;
        }

        $selfuserid = database::generate_param_name();
        $params[$selfuserid] = $managerid;
        $sql = "EXISTS (SELECT 1
                FROM {tool_organisation_reporting} {$r}
                WHERE {$r}.managerid = :{$selfuserid} AND {$r}.userid = {$usertablealias}.id ".
                ($directonly ? "AND {$r}.isdirect = 1" : '').
                "{$permissionsql} )";
        return [$sql, $params];
    }

    /**
     * Create new ad-hoc task to rebuild reporting line
     *
     * @param int $tenantid
     */
    public static function schedule_reporting_line_reindex(int $tenantid): void {
        // Schedule building reporting line ad-hoc task.
        $task = new build_reporting();
        $task->set_custom_data(['tenantid' => $tenantid]);
        manager::queue_adhoc_task($task, true);
    }
}

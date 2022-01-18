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
 * Class helper
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Suraj Kumar
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use tool_organisation\output\user_with_jobs;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_wp\db;

/**
 * Class format
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class helper {

    /** @var string The constant for double space */
    const  DOUBLE_SPACE = '&nbsp;&nbsp;';

    /**
     * Returns string with help icon and help string.
     *
     * @param string $identifier
     * @param string $component
     * @param mixed $a
     * @param bool $lazyload
     * @return string
     * @throws \coding_exception
     */
    public static function get_string_with_help_icon(string $identifier, string $component = 'moodle',
                                                     $a = null, bool $lazyload = false) : string {
        global $OUTPUT;
        $output = get_string($identifier, $component, $a, $lazyload);
        $output .= $OUTPUT->help_icon($identifier, $component);
        return $output;
    }

    /**
     * Builds a menu for the hierarchical tree
     *
     * @param \stdClass[] $records records with fields: id, parentid, path, pathlevel, name, sortorder
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    public static function get_hierarchical_menu(array $records) : array {
        // Sort records so that the children are always after their parents and each parent's children are sorted by sortorder.
        self::sort_records_hierarchically($records);

        // Create output menu array (names indexed by id), prepend names with spaces to show hierarchy.
        // Group with frameworks as options groups, do not show framework if there is only one.
        return self::build_menu($records, function($r) {
            return true;
        }, function($r) {
            return $r->pathlevel - 2;
        });
    }

    /**
     * Prepends the menu for 'selectgroups' element with an empty option
     *
     * @param array $menu list of options for the 'selectgroups' element
     * @param array $firstoption for example ['' => 'None'] or ['' => 'All'] or ['' => '']
     * @return array new list of options fot the 'selectgroups' element
     */
    public static function prepend_hierarchical_menu(array $menu, array $firstoption) : array {
        if (isset($menu[''])) {
            $r = $menu;
            $r[''] = $firstoption + $menu[''];
        } else {
            $r = ['' => $firstoption] + $menu;
        }
        return $r;
    }

    /**
     * Sort records so that the children are always after their parents and each parent's children are sorted by sortorder
     *
     * @param array $records list of records with properties: path, pathlevel, sortorder
     */
    protected static function sort_records_hierarchically(array &$records) {
        $sortorders = [];
        foreach ($records as &$r) {
            $r->paths = preg_split('|/|', $r->path);
            $sortorders[$r->path] = $r->sortorder;
        }
        $sortorder = function($paths, $level) use ($sortorders) {
            $s = join('/', array_slice($paths, 0, $level + 1));
            return array_key_exists($s, $sortorders) ? (int)$sortorders[$s] : 0;
        };
        usort($records, function($a, $b) use ($sortorder) {
            for ($i = 1; $i < min(count($a->paths), count($b->paths)); $i++) {
                if ($a->paths[$i] !== $b->paths[$i]) {
                    return $sortorder($a->paths, $i) - $sortorder($b->paths, $i);
                }
            }
            return $a->pathlevel - $b->pathlevel;
        });
    }

    /**
     * Create output menu array (names indexed by id), prepend names with spaces to show hierarchy.
     *
     * Group with frameworks as options groups, do not show framework if there is only one.
     *
     * @param array $records list of records with properties name and id
     * @param callable $displayfilter filters which records to display
     * @param callable $indentfunc how much each record name should be indented
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    protected static function build_menu(array $records, callable $displayfilter = null, callable $indentfunc = null) {
        $r = [];
        $options = ['context' => \context_system::instance(), 'escape' => false];
        $frameworks = [];
        foreach ($records as $record) {
            if ($displayfilter !== null && !$displayfilter($record)) {
                continue;
            }
            $fid = (int)preg_split('|/|', $record->path)[1];
            if ($record->pathlevel > 1) {
                $fname = array_key_exists($fid, $frameworks) ? $frameworks[$fid] : $fid;
                $indent = ($indentfunc === null) ? ($record->pathlevel - 2) : $indentfunc($record);
                $r[$fname][$record->id] = str_repeat(self::DOUBLE_SPACE, $indent) .
                    format_string($record->name, true, $options);
            } else {
                $frameworks[$fid] = format_string($record->name, true, $options);
            }
        }
        if (count($r) == 1) {
            // No need to display the framework name if it is the only framework.
            $r = reset($r);
            return ['' => $r];
        }
        return $r;
    }

    /**
     * Builds a menu for the list of departments but only shows the relevant (managed) ones
     *
     * @param \stdClass[] $records records with fields: id, parentid, path, pathlevel, name, sortorder AND ismanaged
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    public static function get_hierarchical_managed_menu(array &$records) {
        // Calculate which records need to be displayed. This will be:
        // - either records that are managed
        // - or records that have one of the [grand]parents AND one of the [grand]children managed
        // - or records that are frameworks that have managed [grand]children
        // - or records that have two or more direct children that are either managed themselves or have managed [grand]children.
        // For each record that needs to be displayed calculate the indent.
        foreach ($records as &$r) {
            $r->manageddirectchildren = [];
            $r->managedparents = false;
            $r->managedchildren = false;
        }
        $fixtree = false;
        foreach ($records as &$record) {
            $paths = preg_split('|/|', $record->path, -1, PREG_SPLIT_NO_EMPTY);
            if (array_diff($paths, array_keys($records)) || ($record->parentid && (empty($records[$record->parentid]) ||
                    $record->path !== $records[$record->parentid]->path . '/' . $record->id))) {
                // The tree has some problems in in. We will automatically fix it in the end
                // but the first render might miss some values.
                $fixtree = true;
                continue;
            }
            if (!empty($record->ismanaged)) {
                // Indicate for all parents that they have managed children.
                for ($i = 0; $i < count($paths) - 1; $i++) {
                    $records[(int)$paths[$i]]->managedchildren = true;
                }
                if (count($paths) > 1) {
                    $parentid = (int)$paths[count($paths) - 2];
                    $records[$parentid]->manageddirectchildren[$record->id] = $record->id;
                }
            }
            // Check if this record has managed parents.
            for ($i = 0; $i < count($paths) - 1; $i++) {
                if (!empty($records[(int)$paths[$i]]->ismanaged)) {
                    $record->managedparents = true;
                }
            }
        }
        foreach ($records as &$r) {
            $r->display = !empty($r->ismanaged) ||
                ($r->managedchildren && $r->managedparents) ||
                ($r->managedchildren && $r->pathlevel == 1) ||
                (count($r->manageddirectchildren) > 1);
        }
        foreach ($records as &$r) {
            $r->indent = 0;
            if ($r->display) {
                $paths = preg_split('|/|', $r->path, -1, PREG_SPLIT_NO_EMPTY);
                for ($i = 1; $i < count($paths) - 1; $i++) {
                    $r->indent += ($records[(int)$paths[$i]]->display) ? 1 : 0;
                }
            }
        }

        if ($fixtree) {
            debugging('Orphaned or inconsistent records found in the hierarchy structure, we will attempt to fix them',
                DEBUG_DEVELOPER);
            (new department_manager())->fix_hierarchy_paths();
        }

        // Build and return the menu.
        self::sort_records_hierarchically($records);
        return self::build_menu($records, function($r) {
            return $r->display;
        }, function($r) {
            return $r->indent;
        });
    }

    /**
     * Rounds timestamp to the date format
     *
     * This method uses exactly the same expression as 'dateselector' element.
     * Both always use the server timezone to avoid showing job start/end date a day early or late
     * because of user timezone not matching the server timezone.
     *
     * @param int $time
     * @return int
     */
    public static function round_time($time) {
        global $CFG;
        if (!$time) {
            return 0;
        }
        $date = getdate($time);
        return make_timestamp($date['year'], $date['mon'], $date['mday'], 0, 0, 0, $CFG->timezone, true);
    }

    /**
     * Store the job start/end time in the format that does not change even if imported into a site with a different timezone
     *
     * @param int $time
     * @return string
     */
    public static function get_job_time_for_export(int $time): string {
        global $CFG;
        return !$time ? '' : userdate($time, '%Y-%m-%d', $CFG->timezone, false, false);
    }

    /**
     * Convert the job start/end time from the export file format back to the timestamp in the current site timezone
     *
     * @param string $date
     * @return int
     */
    public static function get_job_time_for_import(string $date): int {
        global $CFG;
        if (!strlen(trim($date))) {
            return 0;
        }
        if ($date = date_parse($date)) {
            return make_timestamp($date['year'], $date['month'], $date['day'], 0, 0, 0, $CFG->timezone, true);
        }
        return (int)$date;
    }

    /**
     * List of fields to use in job SQL
     *
     * @param string $jobalias
     * @param string $positionalias
     * @param string $departmentalias
     * @return string
     */
    protected static function get_job_sql_fields($jobalias = 'j', $positionalias = 'p', $departmentalias = 'd') {
        $jobfields = array_diff(array_keys(\tool_organisation\job::properties_definition()), ['usermodified']);
        $positionfields = ['id', 'departmentmanager', 'departmentpermissions', 'globalmanager',
            'globalpermissions', 'name', 'path', 'sortorder'];
        $departmentfields = ['id', 'name', 'path', 'sortorder'];
        $fields = [];
        foreach ($jobfields as $field) {
            $fields[] = $jobalias . '.' . $field . ' AS job_' . $field;
        }
        foreach ($positionfields as $field) {
            $fields[] = $positionalias . '.' . $field . ' AS pos_' . $field;
        }
        foreach ($departmentfields as $field) {
            $fields[] = $departmentalias . '.' . $field . ' AS dep_' . $field;
        }
        return join(', ', $fields);
    }

    /**
     * Builds part of SQL to use to filter jobs by tenantid and time
     *
     * Result can be used either in WHERE ... clause or in JOIN job ON ... clause
     *
     * @param int|null $time if null then don't filter based on start/end date
     * @param string $jobalias
     * @param int $tenantid tenant id, by default tenant of the current user
     * @return array array of [$where, $params]
     */
    public static function job_time_and_tenant_select(?int $time, string $jobalias = 'j', int $tenantid = 0) {
        [$wherejobtime, $paramsjobtime] = self::job_time_select($time, $jobalias);
        [$wheretenant, $paramstenant] = \tool_tenant\hierarchy::filter_own_or_sub_entities_sql("{$jobalias}.tenantid", $tenantid);

        return [$wherejobtime . ' AND ' . $wheretenant, $paramsjobtime + $paramstenant];
    }

    /**
     * Builds part of SQL to use to filter jobs by time
     *
     * Result can be used either in WHERE ... clause or in JOIN job ON ... clause
     *
     * @param int|null $time if null then don't filter based on start/end date
     * @param string $jobalias
     * @return array array of [$where, $params]
     */
    public static function job_time_select(?int $time, string $jobalias = 'j') {
        if ($time !== null) {
            $time = $time ?: time();
            $time = self::round_time($time);

            $ptime1 = db::generate_param_name();
            $ptime2 = db::generate_param_name();

            $params = [$ptime1 => $time, $ptime2 => $time];
            $where = "{$jobalias}.startdate <= :{$ptime1}
                AND ({$jobalias}.enddate = 0 OR {$jobalias}.enddate >= :{$ptime2})";
            return [$where, $params];
        }
        return ['1=1', []];
    }

    /**
     * SQL to retrieve user information and their jobs
     *
     * @param string $where
     * @param array $params
     * @param int|null $time
     * @param int $tenantid
     * @return array
     */
    public static function get_users_with_jobs_sql(string $where, array $params = [], ?int $time = 0, int $tenantid = 0) {
        [$tjoin, $twhere, $tparams] = tenancy::get_users_sql('u', $tenantid);

        $userfields = \core_user\fields::for_userpic()->get_sql('u', false, 'user_', 'user_id', false)->selects;

        $jobfields = self::get_job_sql_fields('j', 'p', 'd');

        [$timewhere, $timeparams] = self::job_time_select($time);
        $sql = "SELECT $userfields, $jobfields
          FROM {user} u
          $tjoin
          LEFT JOIN {tool_organisation_job} j ON j.userid = u.id
            AND $timewhere
          LEFT JOIN {tool_organisation_position} p ON p.id = j.positionid
          LEFT JOIN {tool_organisation_department} d ON d.id = j.departmentid
          WHERE $where AND $twhere";

        return [$sql, $params + $tparams + $timeparams];
    }

    /**
     * SQL to retrieve users managed by the given manager
     *
     * @param user_with_jobs $manager
     * @param int $withpermissions
     * @param int $time
     * @param string $fields
     * @return array
     */
    public static function get_managed_users_with_jobs_sql(user_with_jobs $manager, int $withpermissions = 0,
                                                           int $time = 0, string $fields = '') {
        // TODO SP-141 remove this function, move code to functions that use it. The name is confusing!
        list($where, $params) = self::get_managed_users_select($manager, 'u', $withpermissions, $time);
        list($sql, $params) = self::get_users_with_jobs_sql($where, $params);

        if ($fields) {
            $sql = "SELECT $fields FROM ($sql) " . \tool_wp\db::generate_alias();
        }

        return [$sql, $params];
    }

    /**
     * Helps to create SQL to retrieve users managed by the current manager
     *
     * Example:
     * list($where, $params) = helper::get_managed_users_select($manager);
     * $DB->get_records_sql("SELECT * FROM {user} u WHERE $where", $params);
     *
     * @param user_with_jobs $manager
     * @param string $usertablealias
     * @param int $withpermissions
     * @param int $time
     * @param bool $childpositionsonly If set to true, then only users assigned to jobs which are immediate children of the
     *      manager jobs will be returned (default is to recurse all descendants of manager jobs)
     * @return array [$where, $params]
     */
    public static function get_managed_users_select(user_with_jobs $manager, $usertablealias = 'u',
            int $withpermissions = 0, int $time = 0, bool $childpositionsonly = false) : array {

        global $DB;
        $jobs = $manager->get_jobs(true, $withpermissions);
        $queries = [];
        $params = [];
        $j = db::generate_alias();
        $p = db::generate_alias();
        $d = db::generate_alias();
        foreach ($jobs as $job) {
            if ($job->get_position()->is_global_manager($withpermissions)) {
                // If we want child positions only, then only select jobs that are immediate descendents, otherwise match on
                // the path to get all descendents.
                if ($childpositionsonly) {
                    $pid = db::generate_param_name();
                    $queries[] = "{$p}.parentid = :{$pid}";
                    $params[$pid] = $job->get_position()->get('id');
                } else {
                    $ppath = db::generate_param_name();
                    $queries[] = $DB->sql_like("${p}.path", ":$ppath");
                    $params[$ppath] = $job->get_position()->get('path') . '/%';
                }
            }
            if ($job->get_position()->is_department_manager($withpermissions)) {
                $dpath = db::generate_param_name();
                $did = db::generate_param_name();
                $queries[] = "{$d}.id = :$did OR " . $DB->sql_like("{$d}.path", ":$dpath");
                $params[$dpath] = $job->get_department()->get('path') . '/%';
                $params[$did] = $job->get_department()->get('id');
            }
        }
        if (!$queries) {
            return ['1=0', []];
        }

        $selfuserid = db::generate_param_name();
        $params[$selfuserid] = $manager->get('id');
        list($timewhere, $timeparams) = self::job_time_and_tenant_select($time, $j);
        $where = " EXISTS (SELECT 1
            FROM {tool_organisation_job} {$j}
            JOIN {tool_organisation_position} {$p} ON {$p}.id = {$j}.positionid
            JOIN {tool_organisation_department} {$d} ON {$d}.id = {$j}.departmentid
            WHERE {$j}.userid = {$usertablealias}.id
              AND {$j}.userid <> :{$selfuserid}
              AND {$timewhere}
              AND ((" . join(') OR (', $queries) . '))) ';
        return [$where, $params + $timeparams];
    }

    /**
     * Generate SQL where clause/params for retrieving users within a managers department
     *
     * @param user_with_jobs $manager
     * @param bool $includesubdept
     * @param string $usertablealias
     * @param int $minstartdate
     * @param int $tenantid
     * @return array
     */
    public static function get_department_users_select(user_with_jobs $manager, bool $includesubdept = false,
            $usertablealias = 'u', int $minstartdate = 0, int $tenantid = 0) : array {

        $jobs = $manager->get_jobs(false);
        if (count($jobs) == 0) {
            return ['1=0', []];
        }

        $wheres = $params = [];

        foreach ($jobs as $job) {
            list($departmentwhere, $departmentparams) = self::user_is_in_department_select($job->get_department()->get('id'),
                $includesubdept, $usertablealias, $minstartdate, $tenantid);

            $wheres[] = $departmentwhere;
            $params = array_merge($params, $departmentparams);
        }

        return [implode(' OR ', $wheres), $params];
    }

    /**
     * Parses each record returned by get_users_with_jobs_sql() into user, job, position and department records
     *
     * @param \stdClass $record
     * @return array [$userrecord, $jobrecord, $positionrecord, $deprecord]
     */
    public static function unalias_user_job(\stdClass $record) {
        $userrecord = \user_picture::unalias($record, null, 'user_id', 'user_');

        $jobrecord = new \stdClass();
        $positionrecord = new \stdClass();
        $deprecord = new \stdClass();
        foreach ($record as $key => $value) {
            if (preg_match('/^job_(.*)/', $key, $matches)) {
                $jobrecord->{$matches[1]} = $value;
            } else if (preg_match('/^pos_(.*)/', $key, $matches)) {
                $positionrecord->{$matches[1]} = $value;
            } else if (preg_match('/^dep_(.*)/', $key, $matches)) {
                $deprecord->{$matches[1]} = $value;
            }
        }

        return [$userrecord, $jobrecord, $positionrecord, $deprecord];
    }

    /**
     * SQL to retrieve users with jobs relevant to the manager
     */
    public static function get_managed_users_with_relevant_jobs_sql() {
        // TODO SP-141.
    }

    /**
     * Returns a WHERE clause to use in SQL selecting users who have a job in a position
     *
     * This clause can be negated with "NOT"
     *
     * @param int $positionid
     * @param bool $withsubpositions
     * @param string $usertablealias
     * @param int|null $minstartdate
     * @param int $tenantid tenant id, by default tenant of the current user
     * @return array array [$where, $params]
     */
    public static function user_has_position_select(int $positionid, bool $withsubpositions = false,
            string $usertablealias = 'u', ?int $minstartdate = null, int $tenantid = 0) : array {
        global $DB;

        $j = db::generate_alias();
        $params = [];
        list($timewhere, $timeparams) = self::job_time_and_tenant_select(0, $j, $tenantid);
        if ($minstartdate) {
            $ptime = db::generate_param_name();
            $timewhere .= " AND {$j}.startdate >= :{$ptime}";
            $params[$ptime] = self::round_time($minstartdate);
        }

        $params = array_merge($params, $timeparams);

        if ($withsubpositions) {

            $p = db::generate_alias();
            $pathparam = db::generate_param_name();
            $likepath = $DB->sql_like("{$p}.path", ':' . $pathparam);
            $where = " EXISTS (SELECT 1
                                 FROM {tool_organisation_job} {$j}
                                 JOIN {tool_organisation_position} {$p}
                                   ON ({$p}.id = {$j}.positionid)
                                WHERE {$j}.userid = {$usertablealias}.id
                                  AND {$timewhere}
                                  AND {$likepath}) ";

            $pospath = $DB->get_field('tool_organisation_position', 'path', ['id' => $positionid]);

            $params[$pathparam] = $pospath . '%';

        } else {
            $pos = db::generate_param_name();
            $where = " EXISTS (SELECT 1
                                 FROM {tool_organisation_job} $j
                                WHERE $j.userid = {$usertablealias}.id
                                  AND {$timewhere}
                                  AND $j.positionid = :{$pos})";
            $params[$pos] = $positionid;
        }
        return [$where, $params];
    }

    /**
     * Returns a WHERE clause to use in SQL selecting users who have a job in a department
     *
     * This clause can be negated with "NOT"
     *
     * @param int $departmentid
     * @param bool $includesubdept include jobs in subdepartments
     * @param string $usertablealias
     * @param int|null $minstartdate
     * @param int $tenantid tenant id, by default tenant of the current user
     * @return array array [$where, $params]
     */
    public static function user_is_in_department_select(int $departmentid, bool $includesubdept = false,
            string $usertablealias = 'u', ?int $minstartdate = null, int $tenantid = 0) : array {
        global $DB;
        if ($includesubdept) {
            $deppath = $DB->get_field('tool_organisation_department', 'path', ['id' => $departmentid]);
            if (!$deppath) {
                $includesubdept = false;
            }
        }

        $j = db::generate_alias();
        $dep = db::generate_param_name();
        $params = [$dep => $departmentid];
        list($timewhere, $timeparams) = self::job_time_and_tenant_select(0, $j, $tenantid);
        if ($minstartdate) {
            $ptime = db::generate_param_name();
            $timewhere .= " AND {$j}.startdate >= :{$ptime}";
            $params[$ptime] = self::round_time($minstartdate);
        }
        if ($includesubdept) {
            $d = db::generate_alias();
            $pdeppath = db::generate_param_name();
            $likepath = $DB->sql_like("{$d}.path", ':' . $pdeppath);
            $where = "FROM {tool_organisation_job} {$j}
                JOIN {tool_organisation_department} {$d} ON {$d}.id = {$j}.departmentid
                WHERE {$j}.userid = {$usertablealias}.id AND {$timewhere} AND ({$j}.departmentid = :{$dep} OR {$likepath})";
            $params[$pdeppath] = $deppath . '/%';
        } else {
            $where = "FROM {tool_organisation_job} {$j}
                WHERE {$j}.userid = {$usertablealias}.id AND {$timewhere} AND {$j}.departmentid = :{$dep}";
        }
        $where = " EXISTS (SELECT 1 $where )";
        return [$where, $params + $timeparams];
    }

    /**
     * Return SQL clause/params suitable for selecting users with any current job assignments
     *
     * @param string $usertablealias
     * @param int|null $minstarttime
     * @param int $tenantid
     * @return array
     */
    public static function users_with_jobs_sql(string $usertablealias, ?int $minstarttime = null, int $tenantid = 0): array {
        $jobtablealias = db::generate_alias();

        [$jobwhere, $jobparams] = self::job_time_and_tenant_select($minstarttime, $jobtablealias, $tenantid);
        $jobsqlexists = " EXISTS (
            SELECT 1
              FROM {tool_organisation_job} {$jobtablealias}
             WHERE {$jobtablealias}.userid = {$usertablealias}.id
               AND {$jobwhere}
        )";

        return [$jobsqlexists, $jobparams];
    }

    /**
     * Return SQL clause/params suitable for selecting users in position with global/department manager permission
     *
     * Note that the $managerparams properties only have effect when enabled (true), setting them to false has no effect
     * on the returned data
     *
     * @param string $usertablealias
     * @param bool[] $managerparams Options:
     *   - globalmanager: (bool) Get users with global manager permission active in position?
     *            DEFAULT: False
     *   - departmentmanager: (bool) Get users with department manager permission active in position?
     *            DEFAULT: False
     * @param int $tenantid
     * @return array array [$sqlposition, $params + $helperparams]
     */
    public static function users_with_managerpermission_sql(string $usertablealias, array $managerparams = [],
            int $tenantid = 0): array {

        $organisationposition = db::generate_alias();
        $organisationjob = db::generate_alias();

        $defaults = ['globalmanager' => 0, 'departmentmanager' => 0];
        $managerparams = array_intersect_key($managerparams, $defaults) + $defaults;

        // If required keys weren't sent, no sqlposition/params is returned.
        if (empty(array_filter($managerparams))) {
            return ['1=0', []];
        }

        // Retrieve filtered jobs by tenantid and time.
        [$helperwhere, $helperparams] = self::job_time_and_tenant_select(time(), $organisationjob, $tenantid);

        // Get active manager params. If both are enabled then we include both clauses in the returned SQL.
        $activemanagerparams = array_unique($managerparams);
        if (count($activemanagerparams) === 1) {
            $wheremanager = "{$organisationposition}.departmentmanager = :departmentparam OR
             {$organisationposition}.globalmanager = :globalparam";

            $params = [
                'departmentparam' => $managerparams['departmentmanager'],
                'globalparam' => $managerparams['globalmanager'],
            ];
        } else {
            // Only one of the params are enabled, find it and include only that clause in the returned SQL.
            $activemanagerparam = array_search(1, $activemanagerparams, false);

            $wheremanager = "{$organisationposition}.{$activemanagerparam} = :{$activemanagerparam}";
            $params = [$activemanagerparam => $managerparams[$activemanagerparam]];
        }

        $sqlposition = "EXISTS ( SELECT 1
            FROM {tool_organisation_job} {$organisationjob}
            INNER JOIN {tool_organisation_position} {$organisationposition} ON
             {$organisationjob}.positionid = {$organisationposition}.id
            WHERE $helperwhere
            AND {$organisationjob}.userid = {$usertablealias}.id
            AND ($wheremanager)
            AND {$organisationposition}.archived = 0 )";

        return [$sqlposition, $params + $helperparams];
    }

    /**
     * Returns a WHERE clause to use in SQL selecting users who do not have any position in any department
     *
     * This clause can be negated with "NOT"
     *
     * @param string $usertablealias
     * @return array array [$where, $params]
     */
    public static function users_without_jobs_sql(string $usertablealias = 'u') : array {
        $j = db::generate_alias();
        $where = " NOT EXISTS (SELECT 1
                                 FROM {tool_organisation_job} {$j}
                                WHERE {$j}.userid = {$usertablealias}.id)";
        return [$where, []];
    }

    /**
     * Return a SQL subselect to be used by report builder "has current jobs" column and filter.
     *
     * @param string $usertablealias
     * @return string
     */
    public static function get_has_current_jobs_sql(string $usertablealias = 'u') {
        $j = db::generate_alias();
        $now = strtotime('today');
        return "CASE WHEN EXISTS (SELECT 1
                   FROM {tool_organisation_job} {$j}
                  WHERE {$j}.userid = {$usertablealias}.id
                    AND {$j}.startdate <= {$now}
                    AND ({$j}.enddate = 0 OR {$j}.enddate >= {$now}))
                THEN 1 ELSE 0 END";
    }

    /**
     * Returns SQL to find the framework id from department/position path
     *
     * Equivalent to PHP function get_framework_id_from_path().
     *
     * @param string $field the 'path' field (with table alias if necessary)
     * @return string
     */
    public static function get_framework_id_sql(string $field = 'path') {
        global $DB;
        $field2 = $DB->sql_concat($field, "'//'");
        $len = $DB->sql_position("'/'", $DB->sql_substr($field2, 2));
        $sql = $DB->sql_substr($field, 2, $len . " - 1");
        $cast = "(" . $DB->sql_cast_char2int("({$sql})") . ")";
        return "CASE WHEN $field IS NULL THEN NULL ELSE $cast END";
    }

    /**
     * Calculates the framework id from department/position path
     *
     * @param string $path
     * @return int
     */
    public static function get_framework_id_from_path(string $path): int {
        return (int)substr($path, 1, strpos($path . '/', '/', 1));
    }

    /**
     * Helps to create SQL to retrieve direct users managed by the current manager
     *
     * If you are a "global manager", your direct subordinates are those in positions directly underneath your position
     * (but not in their subpositions).
     * If you are a "department manager", your direct subordinates are those in the same department as you
     * (but not in subdepartments).
     *
     * Example:
     * list($where, $params) = helper::get_direct_managed_users_select($manager);
     * $DB->get_records_sql("SELECT * FROM {user} u WHERE $where", $params);
     *
     * @param user_with_jobs $manager
     * @param string $usertablealias
     * @param int $withpermissions
     * @param int $time
     * @return array [$where, $params]
     */
    public static function get_direct_managed_users_select(user_with_jobs $manager, $usertablealias = 'u',
                                                    int $withpermissions = 0, int $time = 0) : array {
        $jobs = $manager->get_jobs(true, $withpermissions);
        $queries = [];
        $params = [];
        $j = db::generate_alias();
        $p = db::generate_alias();
        $d = db::generate_alias();

        foreach ($jobs as $job) {
            if ($job->get_position()->is_global_manager($withpermissions)) {
                $pid = db::generate_param_name();
                $queries[] = "{$p}.parentid = :$pid";
                $params[$pid] = $job->get_position()->get('id');
            }
            if ($job->get_position()->is_department_manager($withpermissions)) {
                $did = db::generate_param_name();
                $j1 = db::generate_alias();
                $p1 = db::generate_alias();
                $queries[] = "{$d}.id = :$did AND NOT EXISTS ".
                    "(SELECT 1 FROM {tool_organisation_job} $j1 join {tool_organisation_position} $p1 ON $p1.id=$j1.positionid ".
                    "WHERE $j1.userid=$j.userid AND $j1.departmentid=$d.id AND $p1.departmentmanager=1)";
                $params[$did] = $job->get_department()->get('id');
            }
        }

        if (!$queries) {
            return ['1=0', []];
        }

        $selfuserid = db::generate_param_name();
        $params[$selfuserid] = $manager->get('id');
        [$timewhere, $timeparams] = self::job_time_and_tenant_select($time, $j);

        $subpossql = implode(') OR (', $queries);
        $where = " EXISTS (SELECT 1
            FROM {tool_organisation_job} {$j}
            JOIN {tool_organisation_position} {$p} ON {$p}.id = {$j}.positionid
            JOIN {tool_organisation_department} {$d} ON {$d}.id = {$j}.departmentid
            WHERE {$j}.userid = {$usertablealias}.id
              AND {$j}.userid <> :{$selfuserid}
              AND {$timewhere}
              AND (($subpossql))
              ) ";

        return [$where, $params + $timeparams];
    }

    /**
     * Returns SQL to get the list of the user's direct managers user ids.
     *
     * If you are a "global manager", your direct subordinates are those in positions directly underneath your position
     * (but not in their subpositions).
     *
     * @param string $usersql SQL snippet that returns id of the subordinate user (from the main SQL query),
     *    for example: "u.id" or ":userid" or even a numeric id
     * @return string
     */
    public static function get_user_direct_managers_sql($usersql = 'u.id'): string {
        global $DB;
        $j1 = db::generate_alias();
        $j2 = db::generate_alias();
        $p1 = db::generate_alias();
        $p2 = db::generate_alias();
        $pathconcat = $DB->sql_concat("{$p2}.path", "'/'", "{$p1}.id");

        return "SELECT {$j2}.userid
                FROM {tool_organisation_job} {$j1}
                JOIN {tool_organisation_position} {$p1} ON {$p1}.id = {$j1}.positionid
                JOIN {tool_organisation_position} {$p2} ON {$pathconcat} = {$p1}.path
                    AND {$p2}.globalmanager = 1
                JOIN {tool_organisation_job} {$j2} ON {$j2}.positionid = {$p2}.id
                WHERE {$j1}.userid = $usersql";
    }

    /**
     * Helps to create SQL to retrieve direct manager users for the current user
     *
     * @param int $userid
     * @param string $usertablealias
     * @return array
     */
    public static function get_user_direct_managers_select(int $userid, $usertablealias = 'u'): array {
        $selfuserid = db::generate_param_name();
        $subselect = self::get_user_direct_managers_sql(":{$selfuserid}");
        $params[$selfuserid] = $userid;
        $p = db::generate_alias();
        $sql = "{$usertablealias}.id in (SELECT $p.userid FROM ($subselect) $p)";

        return [$sql, $params];
    }

    /**
     * Returns SQL to get the list of the user's direct department leaders user ids.
     *
     * If you are a "department leader", your direct subordinates are those in the same department as you
     * (but not in subdepartments).
     *
     * @param string $usersql SQL snippet that returns id of the subordinate user (from the main SQL query),
     *    for example: "u.id" or ":userid" or even a numeric id
     * @return string
     */
    public static function get_user_direct_dptleads_sql($usersql = 'u.id'): string {
        $j1 = db::generate_alias();
        $j2 = db::generate_alias();
        $p1 = db::generate_alias();
        $p2 = db::generate_alias();
        $j3 = db::generate_alias();
        $p3 = db::generate_alias();

        return "SELECT {$j2}.userid
            FROM {tool_organisation_job} {$j1}
            JOIN {tool_organisation_position} {$p1} ON {$p1}.id = {$j1}.positionid
            JOIN {tool_organisation_job} {$j2} ON {$j2}.departmentid = {$j1}.departmentid
            JOIN {tool_organisation_position} {$p2} ON {$p2}.id = {$j2}.positionid AND {$p2}.departmentmanager = 1
            WHERE {$j1}.userid = $usersql
                AND NOT EXISTS (SELECT 1 FROM {tool_organisation_job} {$j3}
                JOIN {tool_organisation_position} {$p3} ON {$j3}.positionid = {$p3}.id
                WHERE {$j3}.userid = {$j1}.userid AND {$j3}.departmentid = {$j1}.departmentid AND {$p3}.departmentmanager = 1)";
    }

    /**
     * Helps to create SQL to retrieve direct manager users for the current user
     *
     * @param int $userid
     * @param string $usertablealias
     * @return array
     */
    public static function get_user_direct_dptleads_select(int $userid, $usertablealias = 'u'): array {
        $selfuserid = db::generate_param_name();
        $subselect = self::get_user_direct_dptleads_sql(":{$selfuserid}");
        $params[$selfuserid] = $userid;
        $p = db::generate_alias();
        $sql = "{$usertablealias}.id in (SELECT $p.userid FROM ($subselect) $p)";

        return [$sql, $params];
    }

    /**
     * Returns 'Edit in shared space' button
     *
     * @param int $tenantid
     * @param \renderer_base $output
     * @param string $urlanchor
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function add_shared_space_button(int $tenantid, \renderer_base $output, string $urlanchor = ''): string {
        $editdetailsstr = get_string('editdetailsinsharedspace', 'tool_tenant');
        $url = new \moodle_url('/admin/tool/organisation/index.php#' . $urlanchor);
        $redirect = new \moodle_url('/admin/tool/tenant/switchtenant.php', ['switchtenantid' => $tenantid,
            'redirecturl' => $url->out_as_local_url(false), 'sesskey' => sesskey()]);
        $buttonparams = ['data-action' => 'editdetailsswitchtenant', 'data-redirect' => $redirect->out(false)];
        return (new \tool_wp\output\page_header_button($editdetailsstr, $buttonparams))->render($output);
    }
}

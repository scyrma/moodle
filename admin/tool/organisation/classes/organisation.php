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
 * Class organisation
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use core\external\persistent_exporter;
use tool_organisation\output\user_with_jobs;
use tool_tenant\hierarchy as tenanthierarchy;
use tool_tenant\tenancy;
use tool_wp\db;
use user_picture;

defined('MOODLE_INTERNAL') || die();

/**
 * Public API for the organisation structure
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class organisation {

    /** @var int Permission to allocate managed users to programs and certifications */
    const PERM_ALLOCATE_PROGRAMS = 1;

    /** @var int Permission to view reports on managed users */
    const PERM_VIEW_REPORTS = 2;

    /** @var int Permission to receive notifications on managed users */
    const PERM_RECEIVE_NOTIFICATIONS = 4;

    /**
     * Returns hardcoded permissions for Department manager with icons.
     *
     * @return array
     */
    public static function get_department_manager_permissions() {
        return array(
            self::PERM_ALLOCATE_PROGRAMS => array(
                'name' => 'organisation:allocateuserstoprogramcertificationsdept',
                'title' => get_string('organisation:allocateuserstoprogramcertificationsdept', 'tool_organisation'),
                'icon' => new \pix_icon('i/course', ''),
            ),
            self::PERM_VIEW_REPORTS => array(
                'name' => 'organisation:viewusersreportdept',
                'title' => get_string('organisation:viewusersreportdept', 'tool_organisation'),
                'icon' => new \pix_icon('bar-chart', '', 'tool_wp'),
            ),
            self::PERM_RECEIVE_NOTIFICATIONS => array(
                'name' => 'organisation:receivenotificationsdept',
                'title' => get_string('organisation:receivenotificationsdept', 'tool_organisation'),
                'icon' => new \pix_icon('i/notifications', ''),
            ),
        );
    }

    /**
     * Returns hardcoded permissions for Global manager with icons.
     *
     * @return array
     */
    public static function get_global_manager_permissions() {
        return array(
            self::PERM_ALLOCATE_PROGRAMS => array(
                'name' => 'organisation:allocateuserstoprogramcertificationsglob',
                'title' => get_string('organisation:allocateuserstoprogramcertificationsglob', 'tool_organisation'),
                'icon' => new \pix_icon('i/course', ''),
            ),
            self::PERM_VIEW_REPORTS => array(
                'name' => 'organisation:viewusersreportglob',
                'title' => get_string('organisation:viewusersreportglob', 'tool_organisation'),
                'icon' => new \pix_icon('bar-chart', '', 'tool_wp'),
            ),
            self::PERM_RECEIVE_NOTIFICATIONS => array(
                'name' => 'organisation:receivenotificationsglob',
                'title' => get_string('organisation:receivenotificationsglob', 'tool_organisation'),
                'icon' => new \pix_icon('i/notifications', ''),
            ),
        );
    }

    /**
     * Returns the list of users who are managers for the given user
     *
     * @param int $userid
     * @param int $withpermissions
     * @return user_with_jobs[]
     */
    public static function get_user_managers(int $userid = 0, int $withpermissions = 0) : array {
        // TODO SP-141 implement.
        return [];
    }

    /**
     * Calculates the key for caching user_with_jobs record
     *
     * @param int $userid
     * @param int $time
     * @return null|string
     */
    protected static function get_cache_key(int $userid, int $time = 0) : ?string {
        global $USER;
        if ($USER->id != $userid) {
            return null;
        }
        $curtime = helper::round_time(time());
        if (!$time || helper::round_time($time) == $curtime) {
            return 'key_' . $userid . '_' . $curtime;
        }
        return null;
    }

    /**
     * Retrieves user information together with a list of their jobs
     *
     * @param int $userid
     * @param int|null $time
     * @param int $strictness
     * @return user_with_jobs
     * @throws \moodle_exception
     */
    public static function get_user_with_jobs(int $userid = 0, ?int $time = 0, int $strictness = IGNORE_MISSING) :? user_with_jobs {
        global $USER;

        // Determine user's tenant.
        $userid = $userid ?: $USER->id;
        $tenantid = tenancy::get_actual_tenant_id($userid);

        if (\tool_tenant\permission::can_access_tenant($tenantid)) {
            $cachekey = self::get_cache_key($userid);
            $user = $cachekey ? \cache::make('tool_organisation', 'myjob')->get($cachekey) : false;
            if ($user === false) {
                $users = self::get_users_with_jobs("u.id = :userid", ['userid' => $userid], $time, $tenantid);
                $user = reset($users) ?: null;
                if ($cachekey) {
                    \cache::make('tool_organisation', 'myjob')->set($cachekey, $user);
                }
            }
        } else {
            // No permission to access this user.
            $user = null;
        }
        if ($strictness == MUST_EXIST && !$user) {
            throw new \moodle_exception('usernotfound', 'tool_organisation');
        }
        return $user;
    }

    /**
     * Returns list of users that are direct managers for the user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_user_direct_managers(int $userid = 0): array {
        global $DB, $USER;
        $userid = $userid ?: $USER->id;
        $useridparam = db::generate_param_name();
        $subselect = helper::get_user_direct_managers_sql(":{$useridparam}");
        $sql = "SELECT u.* FROM {user} u WHERE u.id IN ($subselect)";

        return $DB->get_records_sql($sql, [$useridparam => $userid]);
    }

    /**
     * Returns list of users that are direct department leaders for the user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_user_direct_dptleads(int $userid = 0): array {
        global $DB, $USER;
        $userid = $userid ?: $USER->id;
        $useridparam = db::generate_param_name();
        $subselect = helper::get_user_direct_dptleads_sql(":{$useridparam}");
        $sql = "SELECT u.* FROM {user} u WHERE u.id IN ($subselect)";

        return $DB->get_records_sql($sql, [$useridparam => $userid]);
    }

    /**
     * Returns list of users that are direct managers for the user (both position manager and department leads).
     *
     * @param int $userid
     * @return array
     */
    public static function get_user_all_direct_managers(int $userid = 0): array {
        global $DB, $USER;
        $userid = $userid ?: $USER->id;
        $useridparam1 = db::generate_param_name();
        $useridparam2 = db::generate_param_name();
        $subselect1 = helper::get_user_direct_managers_sql(":{$useridparam1}");
        $subselect2 = helper::get_user_direct_dptleads_sql(":{$useridparam2}");
        $sql = "SELECT u.* FROM {user} u WHERE u.id IN ($subselect1 UNION $subselect2)";

        return $DB->get_records_sql($sql, [$useridparam1 => $userid, $useridparam2 => $userid]);
    }

    /**
     * Retrieves several users with their jobs
     *
     * To get the list of user jobs call:
     * - user_with_jobs::get_jobs() - to retrieve all jobs of this user
     * - user_with_jobs::get_relevant_jobs($manager) - to retrieve only the jobs that are relevant to the manager
     *
     * @param string $where SQL where clause for selecting from user table (user table alias 'u')
     * @param array $params named parameters for SQL where clause
     * @param int|null $time
     * @param int $tenantid
     * @return self[]
     */
    public static function get_users_with_jobs(string $where, array $params = [], ?int $time = 0, int $tenantid = 0) : array {
        global $DB;

        list($sql, $params) = helper::get_users_with_jobs_sql($where, $params, $time, $tenantid);

        $records = $DB->get_recordset_sql($sql, $params);
        $userrecords = [];
        $userjobs = [];

        foreach ($records as $record) {
            list($userrecord, $jobrecord, $positionrecord, $deprecord) = helper::unalias_user_job($record);
            if (!array_key_exists($userrecord->id, $userrecords)) {
                $userrecords[$userrecord->id] = $userrecord;
                $userjobs[$userrecord->id] = [];
            }
            if (!empty($jobrecord->id)) {
                $userjobs[$userrecord->id][] = new \tool_organisation\output\job(
                    new job(0, $jobrecord),
                    ['department' => new department(0, $deprecord), 'position' => new position(0, $positionrecord)]);
            }
        }
        $records->close();
        $users = [];
        foreach ($userrecords as $userrecord) {
            $users[$userrecord->id] = new user_with_jobs($userrecord,
                ['time' => $time, 'jobs' => $userjobs[$userrecord->id], 'fulluserrecord' => null]);
        }
        return $users;
    }

    /**
     * Checks if given user is a manager
     *
     * @param int $userid
     * @param int $withpermissions
     * @return bool
     */
    public static function is_manager(int $userid = 0, int $withpermissions = 0) : bool {
        $user = self::get_user_with_jobs($userid);
        return $user ? $user->is_manager($withpermissions) : false;
    }

    /**
     * Menu to select managed users department for the "Teams" list on the dashboard
     *
     * Note that if one of the managed users has additional job that is not relevant to the manager, the department
     * of this job will also be included in the results
     *
     * This function returns hierarchical menu and may include some departments where there are no jobs
     * but they are necessary for building a tree.
     *
     * @param user_with_jobs $manager
     * @param int $withpermissions
     * @param array $firstoption for example ['' => 'None'] or ['' => 'All'] or ['' => '']
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    public static function get_managed_users_departments_menu(user_with_jobs $manager, int $withpermissions = 0,
                                                              array $firstoption = null) {
        global $DB;
        [$tenantsql, $tenantparams] = tenanthierarchy::filter_own_or_parent_shared_entities_sql("tenantid", "shared=1");
        list($msql, $mparams) = helper::get_managed_users_with_jobs_sql($manager, $withpermissions, 0, 'DISTINCT dep_id');
        $sql = "SELECT d.*, md.dep_id AS ismanaged
            FROM {tool_organisation_department} d
            LEFT JOIN ($msql) md ON md.dep_id = d.id
            WHERE $tenantsql
            ORDER by d.pathlevel, d.sortorder, d.id";
        $records = $DB->get_records_sql($sql, $tenantparams + $mparams);

        $o = helper::get_hierarchical_managed_menu($records);
        if ($firstoption !== null) {
            $o = helper::prepend_hierarchical_menu($o, $firstoption);
        }
        return $o;
    }

    /**
     * Menu to select managed users positions for the "Teams" list on the dashboard
     *
     * Note that if one of the managed users has additional job that is not relevant to the manager, the position
     * of this job will also be included in the results
     *
     * Jobs list is sorted alphabetically and is not hierarchical
     *
     * @param user_with_jobs $manager
     * @param int $withpermissions
     * @param array $firstoption for example ['' => 'None'] or ['' => 'All'] or ['' => '']
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    public static function get_managed_users_positions_menu(user_with_jobs $manager, int $withpermissions = 0,
                                                            array $firstoption = null) {
        global $DB;
        list($sql, $params) = helper::get_managed_users_with_jobs_sql($manager, $withpermissions, 0, 'DISTINCT pos_id, pos_name');
        $records = $DB->get_records_sql_menu($sql, $params);
        $options = ['context' => \context_system::instance()];
        array_walk($records, function(&$name) use ($options) {
            $name = format_string($name, true, $options);
        });
        asort($records);
        // TODO SP-141 group by framework?
        $o = ['' => $records];
        if ($firstoption !== null) {
            $o = helper::prepend_hierarchical_menu($o, $firstoption);
        }
        return $o;
    }

    /**
     * Returns menu of all departments for the current tenant and parent tenants
     *
     * @param array $firstoption for example ['' => 'None'] or ['' => 'All'] or ['' => '']
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    public static function get_all_departments_menu(array $firstoption = null) : array {
        global $DB;

        // Filter by same or parent tenant.
        [$sql, $params] = tenanthierarchy::filter_own_or_parent_shared_entities_sql("tenantid", "shared=1");
        $query = 'SELECT * FROM {tool_organisation_department} WHERE archived = 0 AND ' . $sql;
        $records = $DB->get_records_sql($query, $params);

        $o = helper::get_hierarchical_menu($records);
        if ($firstoption !== null) {
            $o = helper::prepend_hierarchical_menu($o, $firstoption);
        }
        return $o;
    }

    /**
     * Returns menu of all positions for the current tenant and parent tenants
     *
     * @param array $firstoption for example ['' => 'None'] or ['' => 'All'] or ['' => '']
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    public static function get_all_positions_menu(array $firstoption = null) {
        global $DB;

        // Filter by same or parent tenant.
        [$sql, $params] = tenanthierarchy::filter_own_or_parent_shared_entities_sql("tenantid", "shared=1");
        $query = 'SELECT * FROM {tool_organisation_position} WHERE archived = 0 AND ' . $sql;
        $records = $DB->get_records_sql($query, $params);

        $o = helper::get_hierarchical_menu($records);
        if ($firstoption !== null) {
            $o = helper::prepend_hierarchical_menu($o, $firstoption);
        }
        return $o;
    }

    /**
     * Menu to select relevant managed users department for the "Teams" list on the dashboard
     *
     * Note that if one of the managed users has additional job that is not relevant to the manager, the department
     * of this job will NOT be included in the results
     *
     * This function returns hierarchical menu and may include some departments where there are no jobs
     * but they are necessary for building a tree.
     *
     * @param user_with_jobs $manager
     * @param int $withpermissions
     * @param array $firstoption for example ['' => 'None'] or ['' => 'All'] or ['' => '']
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    public static function get_managed_users_relevant_departments_menu(user_with_jobs $manager, int $withpermissions = 0,
                                                                       array $firstoption = null) {
        // TODO SP-141.
        return self::get_managed_users_departments_menu($manager, $withpermissions, $firstoption);
    }

    /**
     * Menu to select relevant managed users positions for the "Teams" list on the dashboard
     *
     * Note that if one of the managed users has additional job that is not relevant to the manager, the position
     * of this job will NOT be included in the results
     *
     * Jobs list is sorted alphabetically and is not hierarchical
     *
     * @param user_with_jobs $manager
     * @param int $withpermissions
     * @param array $firstoption for example ['' => 'None'] or ['' => 'All'] or ['' => '']
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    public static function get_managed_users_relevant_positions_menu(user_with_jobs $manager, int $withpermissions = 0,
                                                                     array $firstoption = null) {
        // TODO SP-141.
        return self::get_managed_users_positions_menu($manager, $withpermissions, $firstoption);
    }

    /**
     * Method to get all users in the organisation that have manager permissions in the different positions.
     *
     * Accepted values for $permission are PERM_ALLOCATE_PROGRAMS, PERM_VIEW_REPORTS or PERM_RECEIVE_NOTIFICATIONS.
     * @param int $withpermission
     * @return array
     */
    public static function get_all_managers(int $withpermission = 0): array {
        global $DB;

        $globperm = $DB->sql_bitand('op.globalpermissions', $withpermission);
        $deptperm = $DB->sql_bitand('op.departmentpermissions', $withpermission);
        [$helperwhere, $helperparams] = helper::job_time_and_tenant_select(time(), 'oj');
        $userfields = \core_user\fields::for_userpic()->including('username')
            ->get_sql('u', false, '', 'userid', false)->selects;
        [$tenantjoin, $tenantwhere, $tenantparams] = tenancy::get_users_sql();

        $sql = "SELECT oj.id, oj.positionid, oj.departmentid, $userfields, op.name as positionname
                FROM {tool_organisation_job} oj
                INNER JOIN {tool_organisation_position} op ON oj.positionid = op.id
                INNER JOIN {user} u ON oj.userid = u.id
                $tenantjoin
                WHERE $helperwhere
                AND $tenantwhere
                AND ((op.departmentmanager = 1 AND $deptperm = :withperm1) OR (op.globalmanager = 1 AND $globperm = :withperm2))
                AND op.archived = 0";
        $params = ['withperm1' => $withpermission, 'withperm2' => $withpermission];
        return $DB->get_records_sql($sql, $params + $helperparams + $tenantparams);
    }
}

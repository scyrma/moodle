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
 * Class organisation
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use core\external\persistent_exporter;
use tool_organisation\output\user_with_jobs;
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
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
            return 'key-' . $userid . '-' . $curtime;
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
        // TODO SP-141 add caching if get_cache_key is not null.
        $userid = $userid ?: $USER->id;
        $users = self::get_users_with_jobs("u.id = :userid", ['userid' => $userid], $time);
        $user = reset($users);
        if ($strictness == MUST_EXIST && !$user) {
            throw new \moodle_exception('usernotfound', 'tool_organisation');
        }
        return $user ? $user : null;

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
     * @return self[]
     */
    public static function get_users_with_jobs(string $where, array $params = [], ?int $time = 0) : array {
        global $DB;

        list($sql, $params) = helper::get_users_with_jobs_sql($where, $params, $time);

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
        list($msql, $mparams) = helper::get_managed_users_with_jobs_sql($manager, $withpermissions, 0, 'DISTINCT dep_id');
        $sql = "SELECT d.*, md.dep_id AS ismanaged
            FROM {tool_organisation_department} d
            LEFT JOIN ($msql) md ON md.dep_id = d.id
            WHERE d.tenantid = :dtenantid
            ORDER by d.pathlevel, d.sortorder, d.id";
        $params = ['dtenantid' => tenancy::get_tenant_id()] + $mparams;
        $records = $DB->get_records_sql($sql, $params);

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
     * Returns menu of all departments for the current tenant
     *
     * @param array $firstoption for example ['' => 'None'] or ['' => 'All'] or ['' => '']
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    public static function get_all_departments_menu(array $firstoption = null) : array {
        global $DB;
        $records = $DB->get_records('tool_organisation_department',
            ['tenantid' => tenancy::get_tenant_id(), 'archived' => 0]);

        $o = helper::get_hierarchical_menu($records);
        if ($firstoption !== null) {
            $o = helper::prepend_hierarchical_menu($o, $firstoption);
        }
        return $o;
    }

    /**
     * Returns menu of all positions for the current tenant
     *
     * @param array $firstoption for example ['' => 'None'] or ['' => 'All'] or ['' => '']
     * @return array Returns array of options that can be used in moodleform 'selectgroups' element
     */
    public static function get_all_positions_menu(array $firstoption = null) {
        global $DB;
        $records = $DB->get_records('tool_organisation_position',
            ['tenantid' => tenancy::get_tenant_id(), 'archived' => 0]);
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
        $userfields = user_picture::fields('u', ['username'], 'userid');
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

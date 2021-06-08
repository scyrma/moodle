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
 * Web services
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_organisation\helper;
use tool_organisation\organisation;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * tool_organisation external function
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_external extends external_api {

    /** @var int Filter value for fullname contains */
    public const FULLNAME_CONTAINS = 1;
    /** @var int Filter value for fullname does not contain */
    public const FULLNAME_NOTCONTAINS = 2;
    /** @var int Filter value for fullname is equal to */
    public const FULLNAME_EQUAL = 3;
    /** @var int Filter value for fullname starts with */
    public const FULLNAME_STARTSWITH = 4;
    /** @var int Filter value for fullname ends with */
    public const FULLNAME_ENDSWITH = 5;
    /** @var int Filter value for fullname is empty */
    public const FULLNAME_EMPTY = 6;
    /** @var int Filter value for fullname is not empty */
    public const FULLNAME_NOTEMPTY = 7;

    /**
     * Parameters for the 'tool_organisation_department_move' WS
     * @return external_function_parameters
     */
    public static function department_move_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Id of the department to move', VALUE_REQUIRED),
            'parentid' => new external_value(PARAM_INT, 'New parent', VALUE_DEFAULT, 0),
            'beforeid' => new external_value(PARAM_INT, 'Id of the department before which this department should appear',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * WS 'tool_organisation_department_move' that moves a department
     *
     * @param int $id
     * @param int $parentid
     * @param int $beforeid
     */
    public static function department_move($id, $parentid, $beforeid) {
        $params = self::validate_parameters(self::department_move_parameters(), [
            'id' => $id,
            'parentid' => $parentid,
            'beforeid' => $beforeid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        $manager = new \tool_organisation\department_manager();
        $department = $manager->get_department($id);
        \tool_organisation\permission::require_can_edit_department($department);
        $manager->move($params['id'], $params['parentid'], $params['beforeid']);
    }

    /**
     * Return structure for the 'tool_organisation_department_move' WS
     * @return null
     */
    public static function department_move_returns() {
        return null;
    }

    /**
     * Parameters for the 'tool_organisation_position_move' WS
     * @return external_function_parameters
     */
    public static function position_move_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Id of the position to move', VALUE_REQUIRED),
            'parentid' => new external_value(PARAM_INT, 'New parent', VALUE_DEFAULT, 0),
            'beforeid' => new external_value(PARAM_INT, 'Id of the position before which this position should appear',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * WS 'tool_organisation_position_move' that moves a position
     *
     * @param int $id
     * @param int $parentid
     * @param int $beforeid
     */
    public static function position_move($id, $parentid, $beforeid) {
        $params = self::validate_parameters(self::position_move_parameters(), [
            'id' => $id,
            'parentid' => $parentid,
            'beforeid' => $beforeid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        $manager = new \tool_organisation\position_manager();
        $position = $manager->get_position($params['id']);
        \tool_organisation\permission::require_can_edit_position($position);
        $manager->move($params['id'], $params['parentid'], $params['beforeid']);
    }

    /**
     * Return structure for the 'tool_organisation_position_move' WS
     * @return null
     */
    public static function position_move_returns() {
        return null;
    }

    /**
     * Parameters for the 'tool_organisation_position_delete' WS
     * @return external_function_parameters
     */
    public static function position_delete_parameters() {
        return new external_function_parameters(
            ['id' => new external_value(PARAM_INT, 'Id of the position to delete', VALUE_REQUIRED)]
        );
    }

    /**
     * WS 'tool_organisation_position_delete' that deletes a position
     *
     * @param int $id
     * @throws Exception
     */
    public static function position_delete(int $id) {
        $params = self::validate_parameters(self::position_delete_parameters(),
            ['id' => $id]);
        $id = $params['id'];

        $context = context_system::instance();
        self::validate_context($context);
        $manager = new \tool_organisation\position_manager();
        $position = $manager->get_position($params['id']);
        \tool_organisation\permission::require_can_edit_position($position);

        $manager->delete_position($id);
    }

    /**
     * Return structure for the 'tool_organisation_position_delete' WS
     * @return null
     */
    public static function position_delete_returns() {
        return null;
    }

    /**
     * Parameters for the 'tool_organisation_department_delete' WS
     * @return external_function_parameters
     */
    public static function department_delete_parameters() {
        return new external_function_parameters(
            ['id' => new external_value(PARAM_INT, 'Id of the department to delete', VALUE_REQUIRED)]
        );
    }

    /**
     * WS 'tool_organisation_department_delete' that deletes a department
     *
     * @param int $id
     */
    public static function department_delete(int $id) {
        $params = self::validate_parameters(self::department_delete_parameters(),
            ['id' => $id]);
        $id = $params['id'];

        $context = context_system::instance();
        self::validate_context($context);
        $manager = new \tool_organisation\department_manager();
        $department = $manager->get_department($params['id']);
        \tool_organisation\permission::require_can_edit_department($department);
        $manager->delete_department($id);
    }

    /**
     * Return structure for the 'tool_organisation_department_delete' WS
     * @return null
     */
    public static function department_delete_returns() {
        return null;
    }

    /**
     * Parameters for the 'tool_organisation_job_delete' WS
     * @return external_function_parameters
     */
    public static function job_delete_parameters() {
        return new external_function_parameters(
            ['id' => new external_value(PARAM_INT, 'Id of the Job to delete', VALUE_REQUIRED)]
        );
    }

    /**
     * WS 'tool_organisation_job_delete' that deletes a job
     *
     * @param int $id
     */
    public static function job_delete(int $id) {

        $params = self::validate_parameters(self::job_delete_parameters(),
            ['id' => $id]);
        $id = $params['id'];

        $context = context_system::instance();
        self::validate_context($context);
        $manager = new \tool_organisation\job_manager();
        $job = $manager->get_job(['id' => $id]);
        \tool_organisation\permission::require_can_edit_job($job);
        $manager->delete_job($id);
    }

    /**
     * Return structure for the 'tool_organisation_job_delete' WS
     * @return null
     */
    public static function job_delete_returns() {
        return null;
    }

    /**
     * Parameters for the 'tool_organisation_create_departments' WS
     * @return external_function_parameters
     */
    public static function create_departments_parameters() {
        return new external_function_parameters([
            'departments' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Department name', VALUE_REQUIRED),
                    'parent' => new external_value(PARAM_RAW,
                        'Parent department idnumber or idnumber of a framework, empty value for creating a new framework',
                        VALUE_DEFAULT, null),
                    'idnumber' => new external_value(PARAM_RAW, 'IDNUMBER', VALUE_OPTIONAL),
                    'description' => new external_value(PARAM_RAW, 'Description for new department', VALUE_OPTIONAL),
                    'descriptionformat' => new external_value(PARAM_INT, 'Description format', VALUE_DEFAULT, 0),
                ])
            )
        ]);
    }

    /**
     * WS 'tool_organisation_create_departments' that creates departments.
     *
     * @param array $departments
     */
    public static function create_departments($departments) {
        $params = self::validate_parameters(self::create_departments_parameters(), ['departments' => $departments]);
        $context = context_system::instance();
        self::validate_context($context);
        $manager = new \tool_organisation\department_manager();
        $result = [];
        $warnings = [];
        foreach ($params['departments'] as $d) {
            $parent = null;
            if (!empty($d['parent'])) {
                $parentconditions = ['idnumber' => $d['parent'], 'tenantid' => \tool_tenant\tenancy::get_tenant_id()];
                $parent = \tool_organisation\department::get_record($parentconditions);
                if ($parent) {
                    $d['parentid'] = $parent->get('id');
                } else {
                    $item = !empty($d['idnumber']) ? $d['idnumber'] : $d['name'];
                    $warnings[] = [
                        'item' => $item,
                        'warningcode' => 'errorparentnotfound',
                        'message' => get_string('errorparentnotfound', 'tool_organisation')
                    ];
                    continue;
                }
            }
            try {
                unset($d['parent']);
                \tool_organisation\permission::require_can_create_department($parent);
                $department = $manager->create_department((object)$d);
                $result[] = [
                    'id' => $department->get('id'),
                    'name' => $department->get('name'),
                    'idnumber' => $department->get('idnumber')
                ];
            } catch (Exception $e) {
                $item = !empty($d['idnumber']) ? $d['idnumber'] : $d['name'];
                $warnings[] = [
                    'item' => $item,
                    'warningcode' => 'errorcreatingdepartment',
                    'message' => get_string('errorcreatingdepartment', 'tool_organisation')
                ];
            }
        }
        return ['result' => $result, 'warnings' => $warnings];
    }

    /**
     * Return structure for the 'tool_create_departments' WS
     * @return \external_single_structure
     */
    public static function create_departments_returns() {
        return new external_single_structure([
            'result' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'ID of created department'),
                    'name' => new external_value(PARAM_TEXT, 'Name of created department'),
                    'idnumber' => new external_value(PARAM_RAW, 'IDNUMBER of created department'),
                ])
            ),
            'warnings' => new external_warnings()
        ]);
    }

    /**
     * Parameters for the 'tool_organisation_create_positions' WS
     * @return external_function_parameters
     */
    public static function create_positions_parameters() {
        return new external_function_parameters([
            'positions' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Position name', VALUE_REQUIRED),
                    'parent' => new external_value(PARAM_RAW,
                        'Parent position idnumber or idnumber of a framework, empty value for creating a new framework',
                        VALUE_DEFAULT, null),
                    'idnumber' => new external_value(PARAM_RAW, 'IDNUMBER', VALUE_OPTIONAL),
                    'description' => new external_value(PARAM_RAW, 'Description for new position', VALUE_OPTIONAL),
                    'descriptionformat' => new external_value(PARAM_INT, 'Description format', VALUE_DEFAULT, 0),
                    'departmentmanager' => new external_value(PARAM_BOOL,
                        'True if this position is a department lead', VALUE_OPTIONAL),
                    'globalmanager' => new external_value(PARAM_BOOL, 'True if this position is a manager', VALUE_OPTIONAL),
                    'departmentpermissions' => new external_single_structure([
                        'allocateprograms' => new external_value(
                            PARAM_BOOL, 'True if this position can allocate users on programs', VALUE_OPTIONAL),
                        'viewreports' => new external_value(PARAM_BOOL, 'True if this position can view reports', VALUE_OPTIONAL),
                        'receivenotifications' => new external_value(
                            PARAM_BOOL, 'True if this position will receive notifications', VALUE_OPTIONAL),
                    ], 'Department permissions for this position', VALUE_OPTIONAL),
                    'globalpermissions' => new external_single_structure([
                        'allocateprograms' => new external_value(
                            PARAM_BOOL, 'True if this position can allocate users on programs', VALUE_OPTIONAL),
                        'viewreports' => new external_value(PARAM_BOOL, 'True if this position can view reports', VALUE_OPTIONAL),
                        'receivenotifications' => new external_value(
                            PARAM_BOOL, 'True if this position will receive notifications', VALUE_OPTIONAL),
                    ], 'Global permissions for this position', VALUE_OPTIONAL)
                ])
            )
        ]);
    }

    /**
     * WS 'tool_organisation_create_positions' that creates positions.
     *
     * @param array $positions
     */
    public static function create_positions($positions) {
        $params = self::validate_parameters(self::create_positions_parameters(), ['positions' => $positions]);
        $context = context_system::instance();
        self::validate_context($context);
        \tool_organisation\permission::require_can_create_department();
        $manager = new \tool_organisation\position_manager();
        $warnings = [];
        $result = [];
        foreach ($params['positions'] as $p) {
            $parent = null;
            if (!empty($p['parent'])) {
                $parentconditions = ['idnumber' => $p['parent'], 'tenantid' => \tool_tenant\tenancy::get_tenant_id()];
                $parent = \tool_organisation\position::get_record($parentconditions);
                if ($parent) {
                    $p['parentid'] = $parent->get('id');
                } else {
                    $item = !empty($p['idnumber']) ? $p['idnumber'] : $p['name'];
                    $warnings[] = [
                        'item' => $item,
                        'warningcode' => 'errorparentnotfound',
                        'message' => get_string('errorparentnotfound', 'tool_organisation')
                    ];
                    continue;
                }
            }
            if (!empty($p['globalpermissions'])) {
                $p['globalpermissions'] = self::sum_permissions($p['globalpermissions']);
            }
            if (!empty($p['departmentpermissions'])) {
                $p['departmentpermissions'] = self::sum_permissions($p['departmentpermissions']);
            }
            try {
                \tool_organisation\permission::require_can_create_position($parent);
                unset($p['parent']);
                $position = $manager->create_position((object)$p);
                $result[] = [
                    'id' => $position->get('id'),
                    'name' => $position->get('name'),
                    'idnumber' => $position->get('idnumber')
                ];
            } catch (Exception $e) {
                $item = !empty($p['idnumber']) ? $p['idnumber'] : $p['name'];
                $warnings[] = [
                    'item' => $item,
                    'warningcode' => 'errorcreatingposition',
                    'message' => get_string('errorcreatingposition', 'tool_organisation')
                ];
            }
        }
        return ['result' => $result, 'warnings' => $warnings];
    }

    /**
     * Return structure for the 'tool_create_positions' WS
     * @return \external_single_structure
     */
    public static function create_positions_returns() {
        return new external_single_structure([
            'result' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'ID of created position'),
                    'name' => new external_value(PARAM_TEXT, 'Name of created position'),
                    'idnumber' => new external_value(PARAM_RAW, 'IDNUMBER of created position'),
                ])
            ),
            'warnings' => new external_warnings()
        ]);
    }

    /**
     * Parameters for the 'tool_organisation_is_jobs_tab_available' WS.
     *
     * @return \external_function_parameters
     */
    public static function is_jobs_tab_available_parameters() {
        return new \external_function_parameters([]);
    }

    /**
     * Check if job assignments tab is available to logged user (deprecated).
     *
     * @return null
     */
    public static function is_jobs_tab_available() {
        $context = context_system::instance();
        self::validate_context($context);
        return \tool_organisation\permission::can_view_jobs() &&
            \tool_organisation\job_manager::is_tab_available();
    }

    /**
     * Return structure for the 'tool_organisation_is_jobs_tab_available' WS.
     *
     * @return \external_value
     */
    public static function is_jobs_tab_available_returns() {
        return new \external_value(PARAM_BOOL, 'True if tab is available, false otherwise.');
    }

    /**
     * Marking the method as deprecated.
     *
     * @return bool
     */
    public static function is_jobs_tab_available_is_deprecated() {
        return true;
    }

    /**
     * Sum permissions.
     *
     * @param array $permissions
     * @return int
     */
    public static function sum_permissions($permissions) : int {
        $sum = 0;
        if ($permissions['allocateprograms']) {
            $sum += \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS;
        }
        if ($permissions['viewreports']) {
            $sum += \tool_organisation\organisation::PERM_VIEW_REPORTS;
        }
        if ($permissions['receivenotifications']) {
            $sum += \tool_organisation\organisation::PERM_RECEIVE_NOTIFICATIONS;
        }
        return $sum;
    }

    /**
     * Parameters for the 'tool_organisation_get_managed_users' WS
     * @return external_function_parameters
     */
    public static function get_managed_users_parameters() {
        return new external_function_parameters(
            array (
                'departmentid'  => new external_value(PARAM_INT, 'Departmend ID', VALUE_DEFAULT, 0),
                'includesubdepts'  => new external_value(PARAM_INT, 'Include subdepartments', VALUE_DEFAULT, 0),
                'positionid'  => new external_value(PARAM_INT, 'Position iD', VALUE_DEFAULT, 0),
                'includesubpos'  => new external_value(PARAM_INT, 'Include subpositions', VALUE_DEFAULT, 0),
                'fullnametype'  => new external_value(PARAM_INT, 'Filter type for fullname', VALUE_DEFAULT, 0),
                'fullname'  => new external_value(PARAM_TEXT, 'Fullname to search for', VALUE_DEFAULT, ''),
                'limitfrom'  => new external_value(PARAM_INT, 'limitfrom (integer) sql limit from', VALUE_DEFAULT, 0),
                'limitnumber'  => new external_value(PARAM_INT, 'limitnumber (integer) maximum number of returned users',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * WS 'tool_organisation_get_managed_users' that returns the list of managed users
     *
     * @param int $departmentid
     * @param int $includesubdepts
     * @param int $positionid
     * @param int $includesubpos
     * @param int $fullnametype Filter types are defined in get_teams_tab_filters method.
     * @param string $fullname
     * @param int $limitfrom
     * @param int $limitnumber
     * @return mixed
     */
    public static function get_managed_users(int $departmentid = 0, int $includesubdepts = 0, int $positionid = 0,
                                             int $includesubpos = 0, int $fullnametype = 0, string $fullname = '',
                                             int $limitfrom = 0, int $limitnumber = 0) {
        global $DB, $PAGE, $USER;

        // Parameter validation.
        $params = self::validate_parameters(self::get_managed_users_parameters(), [
            'departmentid' => $departmentid,
            'includesubdepts' => $includesubdepts,
            'positionid' => $positionid,
            'includesubpos' => $includesubpos,
            'fullnametype' => $fullnametype,
            'fullname' => $fullname,
            'limitfrom' => $limitfrom,
            'limitnumber' => $limitnumber,
        ]);
        $departmentid = $params['departmentid'];
        $includesubdepts = $params['includesubdepts'];
        $positionid = $params['positionid'];
        $includesubpos = $params['includesubpos'];
        $fullnametype = $params['fullnametype'];
        $fullname = $params['fullname'];
        $limitfrom = $params['limitfrom'];
        $limitnumber = $params['limitnumber'];

        $context = context_system::instance();
        self::validate_context($context);

        $manager = organisation::get_user_with_jobs();

        // Get current user information and jobs.
        $user = core_user::get_user($USER->id);
        $currentuser = [];
        $currentuser['id'] = (int)$user->id;
        $currentuser['fullname'] = fullname($user);
        $userpicture = new \user_picture($user);
        $userpicture->size = 1;
        $currentuser['profileimageurl'] = $userpicture->get_url($PAGE)->out(false);
        $currentuser['ismanager'] = (int)$manager->is_manager();
        $currentuser['isdepartmentmanager'] = (int)$manager->is_department_manager();
        $currentuser['isglobalmanager'] = (int)$manager->is_global_manager();
        $result['user'] = $currentuser;

        $alldepartments = $DB->get_records('tool_organisation_department');
        $allpositions = $DB->get_records('tool_organisation_position');

        $jobs = $manager->get_jobs();
        $exportedjobs = [];
        foreach ($jobs as $job) {
            $a['jobid'] = $job->get('id');
            $a['positionid'] = $job->get('positionid');
            $a['position_name'] = format_string($allpositions[$job->get('positionid')]->name);
            $a['departmentid'] = $job->get('departmentid');
            $a['department_name'] = format_string($alldepartments[$job->get('departmentid')]->name);
            $a['startdate'] = $job->get('startdate');
            $a['enddate'] = $job->get('enddate');
            $exportedjobs[] = $a;
        }
        $result['user']['jobs'] = $exportedjobs;

        // Get managed users information and jobs.
        [$where, $params] = helper::get_managed_users_select($manager);

        $wheres[] = $where;

        if (!empty($departmentid)) {
            [$wheredep, $paramsdep] = helper::user_is_in_department_select($departmentid, $includesubdepts, 'u');
            $wheres[] = $wheredep;
            $params = array_merge($params, $paramsdep);
        }

        if (!empty($positionid)) {
            [$wherepos, $paramspos] = helper::user_has_position_select($positionid, $includesubpos, 'u');
            $wheres[] = $wherepos;
            $params = array_merge($params, $paramspos);
        }

        // Fullname search.
        if (!empty($fullnametype)) {
            $field  = $DB->sql_fullname('u.firstname', 'u.lastname');
            $name = db::generate_param_name();
            $value = $fullname;

            switch ($fullnametype) {
                case self::FULLNAME_CONTAINS: // Contains.
                    $res = $DB->sql_like($field, ":$name", false, false);
                    $value = $DB->sql_like_escape($value);
                    $paramsfullname[$name] = "%$value%";
                    break;
                case self::FULLNAME_NOTCONTAINS: // Does not contain.
                    $res = $DB->sql_like($field, ":$name", false, false, true);
                    $value = $DB->sql_like_escape($value);
                    $paramsfullname[$name] = "%$value%";
                    break;
                case self::FULLNAME_EQUAL: // Equal to.
                    $res = $DB->sql_equal($field, ":$name", false, false);
                    $paramsfullname[$name] = "$value";
                    break;
                case self::FULLNAME_STARTSWITH: // Starts with.
                    $res = $DB->sql_like($field, ":$name", false, false);
                    $value = $DB->sql_like_escape($value);
                    $paramsfullname[$name] = "$value%";
                    break;
                case self::FULLNAME_ENDSWITH: // Ends with.
                    $res = $DB->sql_like($field, ":$name", false, false);
                    $value = $DB->sql_like_escape($value);
                    $paramsfullname[$name] = "%$value";
                    break;
                case self::FULLNAME_EMPTY: // Empty.
                    $res = "$field = :$name";
                    $paramsfullname[$name] = '';
                    break;
                case self::FULLNAME_NOTEMPTY: // Not empty.
                    $res = "$field != :$name";
                    $paramsfullname[$name] = '';
                    break;
                default:
                    // Filter configuration is invalid. Ignore the filter.
                    $res = '';
                    $paramsfullname = [];
            }

            $wheres[] = $res;
            $params = array_merge($params, $paramsfullname);
        }

        $wheresql = '(' . implode(' AND ', $wheres) . ')';

        $users = $DB->get_records_sql("SELECT u.* FROM {user} u WHERE " . $wheresql, $params, $limitfrom, $limitnumber);
        $totalcount = $DB->count_records_sql("SELECT COUNT(u.id) FROM {user} u WHERE " . $wheresql, $params);

        $managedusers = [];
        foreach ($users as $user) {
            $userjobs = organisation::get_user_with_jobs($user->id);
            if (!$userjobs) {
                continue;
            }

            $record = [];
            $record['id'] = $user->id;
            $record['fullname'] = fullname($user);
            $userpicture = new \user_picture($user);
            $userpicture->size = 1;
            $record['profileimageurl'] = $userpicture->get_url($PAGE)->out(false);
            $record['ismanager'] = (int)$userjobs->is_manager();
            $record['isdepartmentmanager'] = (int)$userjobs->is_department_manager();
            $record['isglobalmanager'] = (int)$userjobs->is_global_manager();

            $jobs = $userjobs->get_jobs();
            $exportedjobs = [];
            foreach ($jobs as $job) {
                $a['jobid'] = $job->get('id');
                $a['positionid'] = $job->get('positionid');
                $a['position_name'] = format_string($allpositions[$job->get('positionid')]->name);
                $a['departmentid'] = $job->get('departmentid');
                $a['department_name'] = format_string($alldepartments[$job->get('departmentid')]->name);
                $a['startdate'] = $job->get('startdate');
                $a['enddate'] = $job->get('enddate');

                $position = new \tool_organisation\position(0, $allpositions[$job->get('positionid')]);
                $a['ismanager'] = (int)$position->is_manager();
                $a['isdepartmentmanager'] = (int)$position->is_department_manager();

                $exportedjobs[] = $a;
            }
            $record['jobs'] = $exportedjobs;

            // Check if it has any expired certifications.
            $hasexpiredcertifications = false;
            $userallocations = \tool_certification\certification_user::get_records(['userid' => $user->id]);
            foreach ($userallocations as $allocation) {
                // One allocation can have status 'Completed' and 'Suspended' at the same time.
                $statuses = \tool_certification\api::get_user_allocation_status($allocation->get('certificationid'), $user->id);

                foreach ($statuses as $status) {
                    if ($status['status'] == 'cert_user_status_expired') {
                        $hasexpiredcertifications = true;
                        // We already found one and we can exit both loops.
                        break 2;
                    }
                }
            }
            $record['hasexpiredcertifications'] = (int)$hasexpiredcertifications;

            $managedusers[] = $record;
        }
        $result['managedusers'] = $managedusers;

        $result['totalcount'] = $totalcount;

        return $result;
    }

    /**
     * Return structure for the 'tool_organisation_get_managed_users' WS
     * @return null
     */
    public static function get_managed_users_returns() {
        return new external_single_structure([
            'user' => new external_single_structure([
                'id' => new external_value(PARAM_INT, ''),
                'fullname' => new external_value(PARAM_TEXT, ''),
                'profileimageurl' => new external_value(PARAM_TEXT, ''),
                'ismanager' => new external_value(PARAM_INT, ''),
                'isdepartmentmanager' => new external_value(PARAM_INT, ''),
                'isglobalmanager' => new external_value(PARAM_INT, ''),
                'jobs' => new external_multiple_structure(
                    new external_single_structure([
                        'jobid' => new external_value(PARAM_INT, ''),
                        'positionid' => new external_value(PARAM_INT, ''),
                        'position_name' => new external_value(PARAM_TEXT, ''),
                        'departmentid' => new external_value(PARAM_INT, ''),
                        'department_name' => new external_value(PARAM_TEXT, ''),
                        'startdate' => new external_value(PARAM_INT, ''),
                        'enddate' => new external_value(PARAM_INT, ''),
                    ])
                )
            ]),
            'managedusers' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, ''),
                    'fullname' => new external_value(PARAM_TEXT, ''),
                    'profileimageurl' => new external_value(PARAM_TEXT, ''),
                    'ismanager' => new external_value(PARAM_INT, ''),
                    'isdepartmentmanager' => new external_value(PARAM_INT, ''),
                    'isglobalmanager' => new external_value(PARAM_INT, ''),
                    'hasexpiredcertifications' => new external_value(PARAM_INT, ''),
                    'jobs' => new external_multiple_structure(
                        new external_single_structure([
                            'jobid' => new external_value(PARAM_INT, ''),
                            'positionid' => new external_value(PARAM_INT, ''),
                            'position_name' => new external_value(PARAM_TEXT, ''),
                            'departmentid' => new external_value(PARAM_INT, ''),
                            'department_name' => new external_value(PARAM_TEXT, ''),
                            'startdate' => new external_value(PARAM_INT, ''),
                            'enddate' => new external_value(PARAM_INT, ''),
                            'ismanager' => new external_value(PARAM_INT, ''),
                            'isdepartmentmanager' => new external_value(PARAM_INT, ''),
                        ])
                    )
                ])
            ),
            'totalcount' => new external_value(PARAM_INT, ''),
        ]);
    }

    /**
     * Parameters for the 'tool_organisation_get_teams_tab_filters' WS.
     *
     * @return \external_function_parameters
     */
    public static function get_teams_tab_filters_parameters() {
        return new \external_function_parameters([]);
    }

    /**
     * Returns filters for the Teams tab.
     *
     * @return null
     */
    public static function get_teams_tab_filters() {
        $context = context_system::instance();
        self::validate_context($context);

        $departments = [];
        $alldepartments = \tool_organisation\department::get_records_select('', null, '', 'id,name,parentid');
        foreach ($alldepartments as $department) {
            $dep['id'] = $department->get('id');
            $dep['name'] = $department->get_formatted_name();
            $dep['parentid'] = $department->get('parentid') ?? 0;
            $departments[] = $dep;
        }

        $positions = [];
        $allpositions = \tool_organisation\position::get_records_select('', null, '', 'id,name,parentid');
        foreach ($allpositions as $position) {
            $pos['id'] = $position->get('id');
            $pos['name'] = $position->get_formatted_name();
            $pos['parentid'] = $position->get('parentid') ?? 0;
            $positions[] = $pos;
        }

        $options = [
            ['id' => self::FULLNAME_CONTAINS, 'identifier' => 'contains', 'component' => 'filters'],
            ['id' => self::FULLNAME_NOTCONTAINS, 'identifier' => 'doesnotcontain', 'component' => 'filters'],
            ['id' => self::FULLNAME_EQUAL, 'identifier' => 'isequalto', 'component' => 'filters'],
            ['id' => self::FULLNAME_STARTSWITH, 'identifier' => 'startswith', 'component' => 'filters'],
            ['id' => self::FULLNAME_ENDSWITH, 'identifier' => 'endswith', 'component' => 'filters'],
            ['id' => self::FULLNAME_EMPTY, 'identifier' => 'isempty', 'component' => 'filters'],
            ['id' => self::FULLNAME_NOTEMPTY, 'identifier' => 'isnotempty', 'component' => 'tool_reportbuilder'],
        ];

        $result = [];

        $result['departments'] = $departments;
        $result['positions'] = $positions;
        $result['fullname_filters'] = $options;

        return $result;

    }

    /**
     * Return structure for the 'tool_organisation_get_teams_tab_filters' WS.
     *
     * @return external_single_structure
     */
    public static function get_teams_tab_filters_returns() {
        return new external_single_structure([
            'departments' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, ''),
                    'parentid' => new external_value(PARAM_INT, ''),
                    'name' => new external_value(PARAM_TEXT, ''),
                ])
            ),
            'positions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, ''),
                    'parentid' => new external_value(PARAM_INT, ''),
                    'name' => new external_value(PARAM_TEXT, ''),
                ])
            ),
            'fullname_filters' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, ''),
                    'identifier' => new external_value(PARAM_TEXT, ''),
                    'component' => new external_value(PARAM_TEXT, ''),
                ])
            ),
        ]);
    }
}

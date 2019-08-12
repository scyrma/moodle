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
 * Web services
 *
 * @package     tool_organisation
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * tool_organisation external function
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_organisation_external extends external_api {

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
                        'True if this position is a department manager', VALUE_OPTIONAL),
                    'globalmanager' => new external_value(PARAM_BOOL, 'True if this position is a global manager', VALUE_OPTIONAL),
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
     * Check if job assignments tab is available to logged user.
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
     * Sum permissions.
     *
     * @param array $permissions
     * @return int
     */
    private static function sum_permissions($permissions) : int {
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
}

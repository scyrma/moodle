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
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * tool_tenant external function
 *
 * @package     tool_tenant
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_tenant_external extends external_api {

    /**
     * Parameters for the 'tool_tenant_change_sortorder' WS
     * @return external_function_parameters
     */
    public static function change_sortorder_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Id of the tenant to move', VALUE_REQUIRED),
            'beforeid' => new external_value(PARAM_INT, 'Id of the tenant before which this tenant should appear',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * WS 'tool_tenant_change_sortorder' that moves a tenant before another tenant
     *
     * @param int $id
     * @param int $beforeid
     */
    public static function change_sortorder($id, $beforeid) {
        $params = self::validate_parameters(self::change_sortorder_parameters(), [
            'id' => $id,
            'beforeid' => $beforeid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        \tool_tenant\permission::require_can_move_tenant($params['id']);
        (new \tool_tenant\manager())->change_sortorder($params['id'], $params['beforeid']);
    }

    /**
     * Return structure for the 'tool_tenant_change_sortorder' WS
     * @return null
     */
    public static function change_sortorder_returns() {
        return null;
    }

    /**
     * Parameters for the 'tool_tenant_get_tenants' WS
     * @return external_function_parameters
     */
    public static function get_tenants_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * WS 'tool_tenant_get_tenants' that updates tenant data.
     */
    public static function get_tenants() {
        $context = context_system::instance();
        self::validate_context($context);
        \tool_tenant\permission::require_can_view_tenants_list();
        $manager = new \tool_tenant\manager();
        return array_map(function($t) {
            return (object) [
              'id'         => $t->get('id'),
              'name'       => $t->get('name'),
              'sitename'   => $t->get('sitename'),
              'idnumber'   => $t->get('idnumber'),
              'isdefault'  => $t->get('isdefault'),
              'categoryid' => $t->get('categoryid'),
            ];
        }, $manager->get_tenants());
    }

    /**
     * Return structure for the 'tool_tenant_get_tenants' WS
     * @return external_multiple_structure
     */
    public static function get_tenants_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'ID', VALUE_REQUIRED),
                'name' => new external_value(PARAM_TEXT, 'Tenant name', VALUE_REQUIRED),
                'sitename' => new external_value(PARAM_TEXT, 'Site name', VALUE_REQUIRED),
                'idnumber' => new external_value(PARAM_RAW, 'IDNUMBER', VALUE_OPTIONAL),
                'isdefault' => new external_value(PARAM_INT, 'Default tenant', VALUE_DEFAULT, 0),
                'categoryid' => new external_value(PARAM_INT, 'Category ID for new tenant', VALUE_OPTIONAL),
            ])
        );
    }

    /**
     * Parameters for the 'tool_tenant_allocate_users' WS.
     *
     * @return external_function_parameters
     */
    public static function allocate_users_parameters() {
        return new external_function_parameters([
            'allocations' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, ''),
                    'tenantid' => new external_value(PARAM_INT, ''),
                ])
            )
        ]);
    }

    /**
     * WS 'tool_tenant_allocate_users' that allocates users to tenants.
     *
     * @param array $allocations
     */
    public static function allocate_users($allocations) {
        $params = self::validate_parameters(self::allocate_users_parameters(), ['allocations' => $allocations]);

        $context = context_system::instance();
        self::validate_context($context);
        \tool_tenant\permission::require_can_move_users_between_tenants();
        $manager = new \tool_tenant\manager();
        $fails = 0;
        foreach ($params['allocations'] as $a) {
            try {
                $manager->allocate_user($a['userid'], $a['tenantid'], 'tool_tenant', 'manual');
            } catch (Exception $e) {
                $fails++;
            }
        }
        return ['successcount' => count($params['allocations']) - $fails, 'failcount' => $fails];
    }

    /**
     * Return structure for the 'tool_tenant_allocate_users' WS
     * @return null
     */
    public static function allocate_users_returns() {
        return new external_single_structure([
            'successcount' => new external_value(PARAM_INT, 'The total of users of suspendend users'),
            'failcount' => new external_value(PARAM_INT, 'The total of users not suspended users')
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function suspend_users_parameters() {
        return new external_function_parameters(
            ['userids' => new external_multiple_structure(new external_value(core_user::get_property_type('id'), 'user ID'))]
        );
    }

    /**
     * Suspend users
     *
     * @throws moodle_exception
     * @param array $userids
     * @return null
     */
    public static function suspend_users($userids) {
        global $CFG, $DB;
        require_once($CFG->dirroot."/user/lib.php");

        $context = context_system::instance();
        self::validate_context($context);
        \tool_tenant\permission::require_can_update_users(); // Loose check first for security reasons.

        $params = self::validate_parameters(self::suspend_users_parameters(), ['userids' => $userids]);

        $fails = 0;
        foreach ($params['userids'] as $userid) {
            try {
                $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
                \tool_tenant\permission::require_can_suspend_user($user);
                $user->suspended = 1;
                user_update_user($user, false, true);
            } catch (moodle_exception $exception) {
                $fails++;
            }
        }

        return ['successcount' => count($params['userids']) - $fails, 'failcount' => $fails];
    }

    /**
     * Returns description of method result value
     *
     * @return null
     */
    public static function suspend_users_returns() {
        return new \external_single_structure([
            'successcount' => new external_value(PARAM_INT, 'The total of users of suspendend users'),
            'failcount' => new external_value(PARAM_INT, 'The total of users not suspended users')
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function unsuspend_users_parameters() {
        return new external_function_parameters(
            ['userids' => new external_multiple_structure(new external_value(core_user::get_property_type('id'), 'user ID'))]
        );
    }

    /**
     * Unsuspend users
     *
     * @throws moodle_exception
     * @param array $userids
     * @return null
     */
    public static function unsuspend_users($userids) {
        global $CFG, $DB;
        require_once($CFG->dirroot."/user/lib.php");

        $context = context_system::instance();
        self::validate_context($context);
        \tool_tenant\permission::require_can_update_users(); // Loose check first for security reasons.

        $params = self::validate_parameters(self::unsuspend_users_parameters(), ['userids' => $userids]);

        $fails = 0;
        foreach ($params['userids'] as $userid) {
            try {
                $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
                \tool_tenant\permission::require_can_unsuspend_user($user);
                $user->suspended = 0;
                user_update_user($user, false, true);
            } catch (moodle_exception $exception) {
                $fails++;
            }
        }
        return ['successcount' => count($params['userids']) - $fails, 'failcount' => $fails];
    }

    /**
     * Returns description of method result value
     *
     * @return null
     */
    public static function unsuspend_users_returns() {
        return new \external_single_structure([
            'successcount' => new external_value(PARAM_INT, 'The total of users of unsuspendend users'),
            'failcount' => new external_value(PARAM_INT, 'The total of users not unsuspended users')
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function delete_users_parameters() {
        return new external_function_parameters(
            ['userids' => new external_multiple_structure(new external_value(core_user::get_property_type('id'), 'user ID'))]
        );
    }

    /**
     * Delete users
     *
     * @throws moodle_exception
     * @param array $userids
     */
    public static function delete_users($userids) {
        global $CFG, $DB;
        require_once($CFG->dirroot."/user/lib.php");

        $context = context_system::instance();
        self::validate_context($context);
        \tool_tenant\permission::require_can_delete_users(); // Loose check first for security reasons.

        $params = self::validate_parameters(self::delete_users_parameters(), ['userids' => $userids]);

        $fails = 0;
        foreach ($params['userids'] as $userid) {
            try {
                $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
                \tool_tenant\permission::require_can_delete_user($user);
                user_delete_user($user);
            } catch (moodle_exception $exception) {
                $fails++;
            }
        }
        return ['successcount' => count($params['userids']) - $fails, 'failcount' => $fails];
    }

    /**
     * Returns description of method result value
     *
     * @return null
     */
    public static function delete_users_returns() {
        return new \external_single_structure([
            'successcount' => new external_value(PARAM_INT, 'The total of users of deleted users'),
            'failcount' => new external_value(PARAM_INT, 'The total of users not deleted users')
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function assign_tenant_admin_roles_parameters() {
        return new external_function_parameters(
            [
                'userids' => new external_multiple_structure(new external_value(core_user::get_property_type('id'), 'user ID')),
                'tenantid' => new external_value(PARAM_INT, 'Tenant ID')
            ]
        );
    }

    /**
     * Assigns the 'Tenant administrator' role in given tenantid to given array of userids
     *
     * @param array $userids
     * @param int $tenantid
     */
    public static function assign_tenant_admin_roles(array $userids, int $tenantid) {
        $params = self::validate_parameters(self::assign_tenant_admin_roles_parameters(),
            ['userids' => $userids, 'tenantid' => $tenantid]);

        // Ensure the current user is allowed to run this function.
        $context = context_system::instance();
        self::validate_context($context);
        \tool_tenant\permission::require_can_assign_tenant_admin($params['tenantid']);

        $userids = [];
        // Make sure specified users exist, belong to the same tenant and are not already admins.
        foreach ($params['userids'] as $userid) {
            if (\tool_tenant\tenancy::get_tenant_id($userid) == $params['tenantid'] &&
                    !\tool_tenant\manager::is_tenant_admin($params['tenantid'], $userid)) {
                $userids[] = $userid;
            }
        }

        $tmanager = new \tool_tenant\manager();
        $tmanager->assign_tenant_admin_roles($userids, $params['tenantid']);

        return ['successcount' => count($userids), 'failcount' => count($params['userids']) - count($userids)];
    }

    /**
     * Returns description of method result value
     *
     * @return null
     */
    public static function assign_tenant_admin_roles_returns() {
        return new \external_single_structure([
            'successcount' => new external_value(PARAM_INT, 'The total of users the role was assigned to'),
            'failcount' => new external_value(PARAM_INT, 'The total of users the role was not assigned to')
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function unassign_tenant_admin_roles_parameters() {
        return new external_function_parameters(
            [
                'userids' => new external_multiple_structure(new external_value(core_user::get_property_type('id'), 'user ID')),
                'tenantid' => new external_value(PARAM_INT, 'Tenant ID')
            ]
        );
    }

    /**
     * Assigns the 'Tenant administrator' role in given tenantid to given array of userids
     *
     * @param array $userids
     * @param int $tenantid
     */
    public static function unassign_tenant_admin_roles(array $userids, int $tenantid) {
        $params = self::validate_parameters(self::unassign_tenant_admin_roles_parameters(),
            ['userids' => $userids, 'tenantid' => $tenantid]);

        // Ensure the current user is allowed to run this function.
        $context = context_system::instance();
        self::validate_context($context);
        \tool_tenant\permission::require_can_assign_tenant_admin($params['tenantid']);

        $userids = [];
        // Make sure specified users exist, belong to the same tenant and are admins.
        foreach ($params['userids'] as $userid) {
            if (\tool_tenant\tenancy::get_tenant_id($userid) == $params['tenantid'] &&
                    \tool_tenant\manager::is_tenant_admin($params['tenantid'], $userid)) {
                $userids[] = $userid;
            }
        }

        $tmanager = new \tool_tenant\manager();
        $tmanager->unassign_tenant_admin_roles($userids, $params['tenantid']);

        return ['successcount' => count($userids), 'failcount' => count($params['userids']) - count($userids)];
    }

    /**
     * Returns description of method result value
     *
     * @return null
     */
    public static function unassign_tenant_admin_roles_returns() {
        return new \external_single_structure([
            'successcount' => new external_value(PARAM_INT, 'The total of users the role was unassigned to'),
            'failcount' => new external_value(PARAM_INT, 'The total of users the role was not unassigned to')
        ]);
    }
}

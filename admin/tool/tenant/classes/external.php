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
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * tool_tenant external function
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        require_capability('tool/tenant:manage', $context);
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
        if (!has_any_capability(['tool/tenant:manage', 'tool/tenant:allocate'], $context)) {
            throw new moodle_exception('errorcannotgettenants', 'tool_tenant');
        }
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
        require_capability('tool/tenant:allocate', $context);
        $manager = new \tool_tenant\manager();
        $result = ['result' => true];
        foreach ($params['allocations'] as $a) {
            try {
                $manager->allocate_user($a['userid'], $a['tenantid'], 'tool_tenant', 'manual');
            } catch (Exception $e) {
                $result['warnings'][] = [
                    'item' => $a['userid'],
                    'warningcode' => 'couldnotallocatetotenant',
                    'message' => get_string('couldnnotallocate', (object)$a)
                ];
            }
        }
        return $result;
    }

    /**
     * Return structure for the 'tool_tenant_allocate_users' WS
     * @return null
     */
    public static function allocate_users_returns() {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL),
            'warnings' => new external_warnings()
        ]);
    }
}

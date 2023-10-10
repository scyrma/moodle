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
 * Class position
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

use core\output\inplace_editable;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/organisation/lib.php');

/**
 * Class position
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class position extends hierarchy {

    /** The table name. */
    const TABLE = 'tool_organisation_position';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {

        $positonspecificproperties = array(
            'departmentmanager' => array(
                'type' => PARAM_INT,
                'description' => 'Department manager flag for position',
                'default' => 0,
            ),
            'globalmanager' => array(
                'type' => PARAM_INT,
                'description' => 'Manager flag for position',
                'default' => 0,
            ),
            'departmentpermissions' => array(
                'type' => PARAM_INT,
                'description' => 'Combined integer value (Binary AND) for department-manager permissions',
                'default' => 0,
            ),
            'globalpermissions' => array(
                'type' => PARAM_INT,
                'description' => 'Combined integer value (Binary AND) for global-manager permissions',
                'default' => 0,
            ),
        );

        return parent::define_properties() + $positonspecificproperties;
    }


    /**
     * Inplace editable object for a name
     * @return inplace_editable
     */
    public function get_editable_name() : inplace_editable {
        return new \core\output\inplace_editable(
            'tool_organisation',
            'position_name',
            $this->get('id'),
            permission::can_edit_position($this),
            $this->get_formatted_name(),
            $this->get('name'),
            get_string('editpositionname', 'tool_organisation'),
            get_string('newnamefor', 'tool_organisation', $this->get_formatted_name())
        );
    }

    /**
     * Get node roles permissions
     * @return array|null
     */
    public function get_node_roles_permissions() {
        global $OUTPUT;
        $rolespermissions = [];
        if ($this->get('globalmanager')) {
            $permissions = [];
            $globalpermissions = organisation::get_global_manager_permissions();
            $globalpermissionsindex = $this->get('globalpermissions');
            foreach ($globalpermissions as $index => $globalpermission) {
                    $permissions[] = [
                        'title' => $globalpermission['title'],
                        'icon' => $OUTPUT->render($globalpermission['icon']),
                        'set' => ($globalpermissionsindex & $index) ? true : false,
                    ];
            }
            $rolespermissions[] = [
                'roletitle' => get_string('globalmanager', 'tool_organisation'),
                'permissions' => $permissions,
                'extraclass' => 'perm-global-manager',
                'permissiontype' => 'globalmanager',
            ];
        }
        if ($this->get('departmentmanager')) {
            $permissions = [];
            $departmentpermissions = organisation::get_department_manager_permissions();
            $deptpermissionsindex = $this->get('departmentpermissions');
            foreach ($departmentpermissions as $index => $departmentpermission) {
                $permissions[] = [
                    'title' => $departmentpermission['title'],
                    'icon' => $OUTPUT->render($departmentpermission['icon']),
                    'set' => ($deptpermissionsindex & $index) ? true : false,
                ];
            }
            $rolespermissions[] = [
                'roletitle' => get_string('departmentmanager', 'tool_organisation'),
                'permissions' => $permissions,
                'extraclass' => 'perm-department-manager',
                'permissiontype' => 'departmentmanager',
            ];
        }
        array_walk($rolespermissions, function(&$roleperm) {
            array_walk($roleperm['permissions'], function(&$perm) {
                $perm['title'] = get_string($perm['set'] ? 'withpermission' : 'withoutpermission',
                    'tool_organisation', $perm['title']);
            });
        });
        return $rolespermissions;
    }

    /**
     * Is this a position of a department manager
     *
     * @param int $withpermissions
     * @return bool
     */
    public function is_department_manager(int $withpermissions = 0) : bool {
        if (!$this->get('departmentmanager')) {
            return false;
        }
        return !$withpermissions || ($this->get('departmentpermissions') & $withpermissions);
    }

    /**
     * Is this a position of a global manager
     *
     * @param int $withpermissions
     * @return bool
     */
    public function is_global_manager(int $withpermissions = 0) : bool {
        if (!$this->get('globalmanager')) {
            return false;
        }
        return !$withpermissions || ($this->get('globalpermissions') & $withpermissions);
    }

    /**
     * Is this a position of a manager
     *
     * @param int $withpermissions
     * @return bool
     */
    public function is_manager(int $withpermissions = 0) : bool {
        return $this->is_department_manager($withpermissions) || $this->is_global_manager($withpermissions);
    }
}

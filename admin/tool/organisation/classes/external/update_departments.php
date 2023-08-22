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

/**
 * 'tool_organisation_update_departments' WebService
 *
 * @package    tool_organisation
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use moodle_exception;
use tool_organisation\department;
use tool_tenant\tenancy;

/**
 * update_departments external class
 *
 * @package     tool_organisation
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class update_departments extends external_api {
    /**
     * Parameters for 'tool_organisation_update_departments' WebService
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'departments' => new external_multiple_structure(
                new external_single_structure([
                    'idnumber' => new external_value(PARAM_RAW, 'Department idnumber', VALUE_REQUIRED),
                    'name' => new external_value(PARAM_TEXT, 'Department name', VALUE_OPTIONAL),
                    'parent' => new external_value(PARAM_RAW,
                        'New parent department idnumber (in case of moving)',
                        VALUE_OPTIONAL),
                    'tenant' => new external_value(PARAM_RAW, 'Tenant idnumber where the department is updated', VALUE_DEFAULT, ''),
                    'description' => new external_value(PARAM_RAW, 'Description for department', VALUE_OPTIONAL),
                    'descriptionformat' => new external_value(PARAM_INT, 'Description format', VALUE_OPTIONAL),
                ])
            )
        ]);
    }

    /**
     * Update departments
     *
     * @param array $departments
     * @return array
     */
    public static function execute(array $departments): array {
        $result = [];
        $warnings = [];
        $params = self::validate_parameters(self::execute_parameters(), ['departments' => $departments]);

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        $originaltenantid = tenancy::get_tenant_id();

        $tenantmanager = new \tool_tenant\manager();
        $manager = new \tool_organisation\department_manager();

        foreach ($params['departments'] as $data) {
            $idnumber = $data['idnumber'];
            $newparentid = null;
            unset($data['idnumber']);
            try {
                if (strlen($data['tenant'] ?? '')) {
                    $tenant = $tenantmanager->get_by_idnumber($data['tenant'], MUST_EXIST);
                    \tool_tenant\permission::require_can_access_tenant($tenant->get('id'));
                    tenancy::set_switched_tenant_id($tenant->get('id'));
                }
                if (!$department = department::get_by_idnumber($idnumber)) {
                    throw new moodle_exception('departmentnotfound', 'tool_organisation');
                }
                if (!\tool_organisation\permission::can_edit_department_in_its_tenant($department)) {
                    throw new moodle_exception('departmentnotfound', 'tool_organisation');
                }
                if (isset($data['parent'])) {
                    if ($parent = department::get_by_idnumber_in_framework($data['parent'],
                            $department->get_framework_id(), $department->get('tenantid'))) {
                        $newparentid = $parent->get('id');
                        unset($data['parent']);
                    } else {
                        throw new moodle_exception('errorparentnotfounddepartment', 'tool_organisation');
                    }
                }
                if (isset($newparentid)) {
                    $manager->move($department->get('id'), $newparentid);
                }
                $newdepartment = $manager->update_department($department->get('id'), (object)$data);
                $result[] = [
                    'id' => $newdepartment->get('id'),
                    'idnumber' => $newdepartment->get('idnumber')
                ];
            } catch (\Exception $e) {
                $warnings[] = [
                    'item' => $idnumber,
                    'warningcode' => $e instanceof moodle_exception ? $e->errorcode : $e->getCode(),
                    'message' => $e->getMessage()
                ];
            }
            tenancy::set_switched_tenant_id($originaltenantid);
        }
        return [
            'result' => $result,
            'warnings' => $warnings
        ];
    }

    /**
     * Return structure for 'tool_organisation_update_departments' WebService
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Department id'),
                    'idnumber' => new external_value(PARAM_RAW, 'Department idnumber'),
                ])
            ),
            'warnings' => new external_warnings()
        ]);
    }
}

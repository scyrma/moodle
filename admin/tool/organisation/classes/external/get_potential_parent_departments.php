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
 * 'tool_organisation_get_potential_parent_departments' WS
 *
 * @package    tool_organisation
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\external;

use context_system;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use tool_organisation\department_manager;
use tool_organisation\permission;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * get_potential_parent_departments external class
 *
 * @package     tool_organisation
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_potential_parent_departments extends external_api {
    /**
     * Parameters for the 'tool_organisation_get_potential_parent_department' WS
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_NOTAGS, 'Search string', VALUE_REQUIRED),
            'departmentid' => new external_value(PARAM_INT, 'Department id when the department is being edited', VALUE_REQUIRED),
            'frameworkid' => new external_value(PARAM_INT, 'Framework id when adding a new department', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get potential parent department selector.
     *
     * @param string $search
     * @param int $departmentid
     * @param int $frameworkid
     * @return array
     */
    public static function execute(string $search, int $departmentid, int $frameworkid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['search' => $search, 'departmentid' => $departmentid, 'frameworkid' => $frameworkid]);
        $search = $params['search'];
        $departmentid = $params['departmentid'];
        $frameworkid = $params['frameworkid'];

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);

        $manager = new department_manager();
        $department = $framework = null;
        if ($departmentid === 0) {
            if ($frameworkid === 0) {
                throw new \invalid_parameter_exception('frameworkid or departmentid is required');
            }
            permission::require_can_create_department();
            $framework = $manager->get_department($frameworkid);
            // Checks if user can edit department in the tenant of framework.
            permission::require_can_edit_department($framework);
        } else {
            $department = $manager->get_department($departmentid);
            permission::require_can_edit_department($department);
        }

        return $manager->get_potential_parents($search, $department, $framework);
    }

    /**
     * Update job return structure
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'ID of the certification'),
            'name' => new external_value(PARAM_TEXT, 'The fullname of the department'),
            'path' => new external_value(PARAM_TEXT, 'The path of the department'),
        ]));
    }
}

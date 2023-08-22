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

namespace tool_tenant\external;

use context_system;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use tool_tenant\manager;
use tool_tenant\permission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/externallib.php");

/**
 * Class create_tenant
 *
 * @package     tool_tenant
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * TODO: this class has to be re-written when full hierarchy is introduced.
 */
class create_tenant extends external_api {

    /**
     * Describes the parameters for create new tenant.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'tenant' => new external_single_structure(
                    [
                        'name' => new external_value( PARAM_RAW,
                            'Tenant name', VALUE_REQUIRED
                        ),
                        'sitename' => new external_value(PARAM_TEXT,
                            'Site name (leave empty to use the default site name)', VALUE_DEFAULT, ''
                        ),
                        'siteshortname' => new external_value(PARAM_TEXT,
                            'Site short name (leave empty to use the default site name)', VALUE_DEFAULT, ''
                        ),
                        'idnumber' => new external_value(PARAM_RAW,
                            'ID number (used to match with external systems and in Upload user tool)', VALUE_DEFAULT, ''
                        ),
                        'useloginurlid' => new external_value(PARAM_BOOL,
                            'Allow to use tenant id as login URL', VALUE_DEFAULT, true
                        ),
                        'useloginurlidnumber' => new external_value(PARAM_BOOL,
                            'Allow to use tenant ID number as login URL', VALUE_DEFAULT, false
                        ),
                        'showinloginselector' => new external_value(PARAM_BOOL,
                            'Show this tenant in the login selector', VALUE_DEFAULT, true
                        ),
                        'categoryid' => new external_value(PARAM_INT,
                            'Course category id', VALUE_DEFAULT, 0
                        ),
                        'autocreatecategory' => new external_value(PARAM_BOOL,
                            'Create new course category for this tenant', VALUE_DEFAULT, false
                        )
                    ]
                )
            ]
        );
    }

    /**
     * Create new tenant
     *
     * @param array $tenant
     * @return array
     */
    public static function execute(array $tenant): array {
        global $DB;

        $context = context_system::instance();
        self::validate_context($context);

        // Check if can create new tenant.
        permission::require_can_create_tenant();

        // Validate parameters.
        $params = external_api::validate_parameters(self::execute_parameters(), ['tenant' => $tenant]);

        // Tenant name must not be blank.
        if (empty(trim($params['tenant']['name']))) {
            throw new \moodle_exception('missingparam', 'error', '', 'name');
        }

        // If autocreatecategory is true and categoryid was provided, throw an exception.
        if ($params['tenant']['categoryid'] && $params['tenant']['autocreatecategory']) {
            throw new \moodle_exception('errornewcategorytenant', 'tool_tenant');
        }

        // Check if autocreatecategory was provided.
        if ($params['tenant']['autocreatecategory']) {
            // Check if category with this name does not exist.
            if ($DB->record_exists('course_categories', ['name' => $params['tenant']['name'], 'parent' => 0])) {
                throw new \moodle_exception('categorynameexistws', 'tool_tenant', '', $params['tenant']['name']);
            }

            // Create a new tenant category.
            $coursecat = \core_course_category::create(['name' => $params['tenant']['name']]);
            $params['tenant']['categoryid'] = $coursecat->id;
        }

        // Check if categoryid was provided.
        if ($params['tenant']['categoryid']) {
            // Check if category id is assigned to another tenant.
            if (!manager::can_change_category(0, $params['tenant']['categoryid'])) {
                throw new \moodle_exception('categorytaken', 'tool_tenant');
            }

            // Check if category with this id exist and is in top level.
            if (!$DB->get_record('course_categories', ['id' => $params['tenant']['categoryid'], 'parent' => 0])) {
                throw new \moodle_exception('categorynotfound', 'tool_tenant');
            }
        }

        // If idnumber was not provided then useloginurlidnumber = false.
        if (!$params['tenant']['idnumber'] || !$params['tenant']['useloginurlidnumber']) {
            $params['tenant']['useloginurlidnumber'] = false;
        }

        // If useloginurlid and useloginurlidnumber were not provided then showinloginselector = false.
        if (!$params['tenant']['useloginurlid'] && !$params['tenant']['useloginurlidnumber']) {
            $params['tenant']['showinloginselector'] = false;
        }

        $manager = new manager();
        $tenants = $manager->get_tenants_without_shared();
        $last = end($tenants);

        $newtenantdata = [
            'name' => $params['tenant']['name'],
            'sitename' => $params['tenant']['sitename'],
            'siteshortname' => $params['tenant']['siteshortname'],
            'idnumber' => $params['tenant']['idnumber'],
            'useloginurlid' => $params['tenant']['useloginurlid'],
            'useloginurlidnumber' => $params['tenant']['useloginurlidnumber'],
            'showinloginselector' => $params['tenant']['showinloginselector'],
            'categoryid' => $params['tenant']['categoryid'],
            'sortorder' => $last ? ($last->get('sortorder') + 1) : 0,
        ];

        $newtenant = $manager->create_tenant((object) $newtenantdata);

        return ['tenantid' => $newtenant->get('id')];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'tenantid' => new external_value(PARAM_INT, 'Tenant id')]);
    }
}

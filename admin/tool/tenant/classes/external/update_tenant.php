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
 * Class update_tenant
 *
 * @package     tool_tenant
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * TODO: this class has to be re-written when full hierarchy is introduced.
 */
class update_tenant extends external_api {

    /**
     * Describes the parameters for update tenant.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'tenant' => new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT,
                            'Tenant id to be updated', VALUE_REQUIRED
                        ),
                        'name' => new external_value(PARAM_RAW,
                            'Tenant name', VALUE_OPTIONAL
                        ),
                        'sitename' => new external_value(PARAM_TEXT,
                            'Site name (leave empty to use the default site name)', VALUE_OPTIONAL
                        ),
                        'siteshortname' => new external_value(PARAM_TEXT,
                            'Site short name (leave empty to use the default site name)', VALUE_OPTIONAL
                        ),
                        'idnumber' => new external_value(PARAM_RAW,
                            'ID number (used to match with external systems and in Upload user tool)', VALUE_OPTIONAL
                        ),
                        'useloginurlid' => new external_value(PARAM_BOOL,
                            'Allow to use tenant id as login URL', VALUE_OPTIONAL
                        ),
                        'useloginurlidnumber' => new external_value(PARAM_BOOL,
                            'Allow to use tenant ID number as login URL', VALUE_OPTIONAL
                        ),
                        'showinloginselector' => new external_value(PARAM_BOOL,
                            'Show this tenant in the login selector', VALUE_OPTIONAL
                        ),
                        'categoryid' => new external_value(PARAM_INT,
                            'Course category id', VALUE_OPTIONAL
                        ),
                        'autocreatecategory' => new external_value(PARAM_BOOL,
                            'Create new course category for this tenant', VALUE_OPTIONAL
                        )
                    ]
                ),
            ]
        );
    }

    /**
     * Update tenant
     *
     * @param array $tenant
     */
    public static function execute(array $tenant) {
        global $DB;

        $context = context_system::instance();
        self::validate_context($context);
        $manager = new manager();

        // Validate parameters.
        $params = external_api::validate_parameters(self::execute_parameters(), ['tenant' => $tenant]);

        // Check if can edit tenant.
        permission::require_can_edit_tenant($params['tenant']['id']);

        // If autocreatecategory is true and categoryid was provided, throw an exception.
        if (!empty($params['tenant']['categoryid']) && !empty($params['tenant']['autocreatecategory'])) {
            throw new \moodle_exception('errornewcategorytenant', 'tool_tenant');
        }

        // Get tenant instance.
        $tenantinstance = $manager->get_tenant($params['tenant']['id']);

        // Check if autocreatecategory was provided.
        if (isset($params['tenant']['autocreatecategory']) && !empty($params['tenant']['autocreatecategory'])) {

            // If tenant name is updated we get it otherwise we retrieve current name saved.
            $tenantname = empty(trim($params['tenant']['name'])) ? $tenantinstance->get('name') : $params['tenant']['name'];

            // Check if category with this name does not exist.
            if ($DB->record_exists('course_categories', ['name' => $tenantname, 'parent' => 0])) {
                throw new \moodle_exception('categorynameexistws', 'tool_tenant', '', $tenantname);
            }

            // Create a new tenant category.
            $coursecat = \core_course_category::create(['name' => $tenantname]);
            $params['tenant']['categoryid'] = $coursecat->id;
        }

        // Check if categoryid was provided.
        if (isset($params['tenant']['categoryid']) && !empty($params['tenant']['categoryid'])) {
            // Check if category id is assigned to another tenant.
            if (!manager::can_change_category($params['tenant']['id'], $params['tenant']['categoryid'])) {
                throw new \moodle_exception('categorytaken', 'tool_tenant');
            }

            // Check if category with this id exist and is in top level.
            if (!$DB->get_record('course_categories', ['id' => $params['tenant']['categoryid'], 'parent' => 0])) {
                throw new \moodle_exception('categorynotfound', 'tool_tenant');
            }
        }

        // Check if was provided a valid params and add to the update tenant data.
        $tenantupdateddata['id'] = $params['tenant']['id'];
        foreach ($params['tenant'] as $paramname => $paramdata) {
            if ($paramname !== 'id' && isset($params['tenant'][$paramname])
                && !empty(trim($params['tenant'][$paramname])) || is_bool($params['tenant'][$paramname])) {
                $tenantupdateddata[$paramname] = $paramdata;
            }
        }

        // If not valid idnumber was provided or was saved, set 'useloginurlidnumber' = false.
        if (isset($tenantupdateddata['useloginurlidnumber']) &&
            (!isset($tenantupdateddata['idnumber']) && empty($tenantinstance->get('idnumber')))) {
            $tenantupdateddata['useloginurlidnumber'] = false;
        }

        // Create an array with current url values to check if showinloginselector can be enabled.
        $urlvalues = [
            'useloginurlid' => $tenantinstance->get('useloginurlid'),
            'useloginurlidnumber' => $tenantinstance->get('useloginurlidnumber')
        ];

        if (isset($tenantupdateddata['useloginurlid'])
            && $tenantupdateddata['useloginurlid'] !== $urlvalues['useloginurlid']) {
                $urlvalues['useloginurlid'] = $tenantupdateddata['useloginurlid'];
        }

        if (isset($tenantupdateddata['useloginurlidnumber'])
            && $tenantupdateddata['useloginurlidnumber'] !== $urlvalues['useloginurlidnumber']) {
                $urlvalues['useloginurlidnumber'] = $tenantupdateddata['useloginurlidnumber'];
        }

        // If useloginurlid and useloginurlidnumber are disabled and showinloginselector is enabled
        // we need to force disable showinloginselector.
        if (!in_array(true, $urlvalues) &&
            ($tenantinstance->get('showinloginselector') || $tenantupdateddata['showinloginselector'])) {
                $tenantupdateddata['showinloginselector'] = false;
        }

        $manager->update_tenant($params['tenant']['id'], (object) $tenantupdateddata);
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return null
     */
    public static function execute_returns() {
        return null;
    }
}

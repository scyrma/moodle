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
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use tool_tenant\tenant;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/externallib.php");

/**
 * Class get_tenant_login_info
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_tenant_login_info extends external_api {

    /**
     * Describes the parameters for getting tenant login info.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'url' => new external_value(PARAM_URL, 'Tenant URL', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get tenant login info
     *
     * Note: No need to validate context because login is not required for this WS.
     *
     * @param string $url
     * @return array
     */
    public static function execute(string $url): array {
        global $SITE;

        [
            'url' => $url,
        ] = self::validate_parameters(self::execute_parameters(), [
            'url' => $url,
        ]);

        $manager = new \tool_tenant\manager();

        // The $url variable contains the tenant login url. We need to get the tenantid/tenant param value passed in this url.
        // For example: http://localhost/dev/?tenantid=2 or http://localhost/dev/?tenant=defaultenant.
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);
        if (isset($params['tenantid'])) {
            $tenantid = clean_param($params['tenantid'], PARAM_INT);
            $tenant = tenant::get_record(['id' => $tenantid]);
        } else if (isset($params['tenant'])) {
            $tenantidnumber = clean_param($params['tenant'], PARAM_RAW);
            $tenant = tenant::get_record(['idnumber' => $tenantidnumber]);
        } else {
            throw new invalid_parameter_exception(get_string('invalidurl', 'error'));
        }

        // Check if tenant exists and if the login URL type is enabled in the tenant settings.
        if (!$tenant
            || (isset($params['tenantid']) && empty($tenant->get('useloginurlid')))
            || (isset($params['tenant']) && empty($tenant->get('useloginurlidnumber')))) {
            throw new invalid_parameter_exception(get_string('invalidurl', 'error'));
        }

        $tenantname = $tenant->get('sitename');
        // Default sitename is used if tenant sitename is not defined.
        if (empty($tenantname)) {
            $tenantname = format_string($SITE->fullname, true,
                ['context' => context_system::instance()->id, 'escape' => false]);
        }

        $url = $manager->get_tenant_file_url($tenant->get('id'), 'tenantselectorlogo') ??
            $manager->get_tenant_file_url($tenant->get('id'), 'loginlogo');
        $logourl = !is_null($url) ? $url->out(false) : '';

        // Tenant CSS config.
        $cssconfig = (array) json_decode($tenant->get('cssconfig') ?? '');
        $cssconfigitems = array_filter($cssconfig, static function($key) {
            return strpos($key, 'mform_isexpanded') !== 0;
        }, ARRAY_FILTER_USE_KEY);

        $cssconfig = array_map(static function ($key, $value) {
            return [
                'name' => $key,
                'value' => $value,
            ];
        }, array_keys($cssconfigitems), $cssconfigitems);

        return [
            'name' => external_format_string($tenantname, context_system::instance()),
            'logourl' => $logourl,
            'cssconfig' => $cssconfig,
        ];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'name' => new external_value(PARAM_TEXT, 'The site name for this tenant'),
            'logourl' => new external_value(PARAM_URL, 'The logo url of the tenant'),
            'cssconfig' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Name of CSS property'),
                'value' => new external_value(PARAM_RAW, 'Value of the CSS property')
            ]))
        ]);
    }
}

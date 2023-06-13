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

namespace tool_tenant;

/**
 * Class mod_url
 *
 * This implements methods to add tenant parameters to the url activity.
 *
 * @package     tool_tenant
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 David Castro <david.castro@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class mod_url {

    /**
     * Gets tenant related variable options.
     * @param \stdClass $config URL plugin config
     * @param array $options Reference to instance option list
     * @return void
     */
    public static function url_get_variable_options($config, &$options) {
        if (!empty($config->tenantdata)) {
            $options[get_string('tenant', 'tool_tenant')] = [
                'tenantid' => get_string('modurl:tenantid', 'tool_tenant'),
                'tenantidnumber' => get_string('modurl:tenantidnumber', 'tool_tenant'),
            ];
        }
    }

    /**
     * Gets tenant related variable values.
     * @param \stdClass $config URL plugin config
     * @param array $values Reference to instance value list
     * @return void
     */
    public static function url_get_variable_values($config, &$values) {
        if (!empty($config->tenantdata)) {
            $tenantid = tenancy::get_tenant_id();
            $idnumber = tenancy::get_tenants()[$tenantid]->idnumber;
            $values['tenantid'] = $tenantid;
            if (!empty($idnumber)) {
                $values['tenantidnumber'] = $idnumber;
            }
        }
    }
}

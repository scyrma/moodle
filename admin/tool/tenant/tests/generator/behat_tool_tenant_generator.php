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
 * Behat data generator for tool_tenant
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Behat data generator for tool_tenant
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_tenant_generator extends behat_generator_base {

    /**
     * @var tool_tenant_generator
     */
    protected $componentdatagenerator;

    /**
     * Get a list of the entities that can be created for this component.
     *
     * See {@see behat_core_generator::get_creatable_entities} for an example.
     *
     * @return array entity name => information about how to generate.
     */
    protected function get_creatable_entities(): array {
        return [
            'tenants' => [
                'datagenerator' => 'tenant',
                'required' => ['name'],
                'switchids' => ['category' => 'categoryid'],
            ],
            'users' => [
                'datagenerator' => 'user',
                'required' => ['username'],
                'switchids' => ['tenant' => 'tenantid'],
            ],
        ];
    }

    /**
     * Look up the id of a tenant from its name
     *
     * @param string $tenantname
     * @return int corresponding id.
     */
    protected function get_tenant_id(string $tenantname): int {
        global $DB;

        if (!$id = $DB->get_field('tool_tenant', 'id', array('name' => $tenantname))) {
            throw new Exception('The specified tenant with name "' . $tenantname . '" does not exist');
        }
        return $id;
    }

    /**
     * If password is not set it uses the username.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_user($data) {
        if (!isset($data['password'])) {
            $data['password'] = $data['username'];
        }
        return $data;
    }
}

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
 * Class tool_tenant
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\tool_wp\mapper;

use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_wp\export_import_mapper_base;

/**
 * Class tool_tenant
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_tenant extends export_import_mapper_base {

    /**
     * Initialises mapper and registers all potential notices
     */
    protected function initialise(): void {
        $this->register_potential_error(self::NOTFOUND, [
            self::ERROR_CONFLICTHEADER => get_string('migrationmappingerror', 'tool_tenant'),
            self::ERROR_LOG => function(array $identifier): string {
                return get_string('migrationmappingerrorlog', 'tool_tenant', $this->get_identifier_for_display($identifier, true));
            },
            self::ERROR_IDENTIFIER => static function(array $identifier, bool $usequotes = false) {
                return self::display_identifier_idnumber_name($identifier, 'idnumber', 'name', $usequotes);
            },
        ]);
    }

    /**
     * Returns the array of properties of an entity that can be used to find this entity during import
     *
     * This function is used when the entity itself is not included in the export
     *
     * @param int $id
     * @return array|null
     */
    public function get_mapping_data_for_workplace_export(int $id): ?array {
        // TODO do not return anything if the export is for the current tenant.
        global $DB;
        $obj = $DB->get_record('tool_tenant', ['id' => $id], 'id, idnumber, name, archived');
        return $obj ? (array)$obj : null;
    }

    /**
     * Allows to locate the existing entity that is available to the current user by default identifier
     *
     * @param string $identifier the default identifier used by the entity (normally shortname/idnumber/name)
     * @param int $tenantid strictly inside the given tenant (for entities that can be inside tenants)
     * @return int|null the id of the entity or null if not found or not available
     */
    public function locate_mapping_default(string $identifier, ?int $tenantid = null): ?int {
        return $this->locate_mapping(['idnumber' => $identifier]);
    }

    /**
     * Allows to locate the existing entity that is available to the current user
     *
     * @param array $identifier array or known entity's attributes, for example:
     *     ['idnumber' => 'OLDID', 'shortname' => 'OLDNAME', 'id' => 'OLDID', 'tenantid' => 1]
     * @return int|null
     */
    public function locate_mapping(array $identifier): ?int {
        global $DB;
        if (!permission::can_switch_tenant()) {
            return tenancy::get_tenant_id();
        } else if (!empty($identifier['tenantid'])) {
            // This is a fixed-tenant import.
            $params = ['id' => $identifier['tenantid']];
        } else if (!empty($identifier['idnumber'])) {
            $params = ['idnumber' => $identifier['idnumber']];
        }
        if (!empty($params) && ($id = $DB->get_field('tool_tenant', 'id', $params))) {
            return $id;
        }
        return null;
    }
}

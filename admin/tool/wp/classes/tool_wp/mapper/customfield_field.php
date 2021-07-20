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
 * Class customfield
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\mapper;

use tool_wp\export_import_mapper_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Allows to map custom fields during export and search for existing custom fields during import
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class customfield_field extends export_import_mapper_base {
    /**
     * Initialises mapper and registers all potential notices
     */
    protected function initialise(): void {
        $this->register_potential_error(self::NOTFOUND, [
            self::ERROR_CONFLICTHEADER => get_string('errorcustomfieldnotfound', 'tool_wp'),
            self::ERROR_LOG => function(array $identifier): string {
                return get_string('errorcustomfieldnotfounddetail', 'tool_wp', $this->get_identifier_for_display($identifier));
            },
            self::ERROR_IDENTIFIER => static function(array $identifier) {
                return !empty($identifier['shortname']) ? s($identifier['shortname']) : "[{$identifier['id']}]";
            },
        ]);
    }

    /**
     * Returns an array of customfield properties that can be used to find it during import
     *
     * This function is used when the entity itself is not included in the export
     *
     * @param int $id
     * @return array|null
     */
    public function get_mapping_data_for_workplace_export(int $id): ?array {
        global $DB;

        $sql = '
            SELECT f.id, f.shortname, f.type, c.component, c.area, c.itemid
            FROM {customfield_field} f
            JOIN {customfield_category} c
            ON f.categoryid = c.id
            WHERE f.id = :id
        ';
        $data = $DB->get_record_sql($sql, ['id' => $id]);

        return $data ? (array) $data : null;
    }

    /**
     * Locate an existing entity, that is available to the current user, by default identifier
     *
     * @param string $identifier the default identifier used by the entity (normally name/idnumber)
     * @param int $tenantid strictly inside the given tenant (for entities that can be inside tenants)
     * @return int|null the id of the entity or null if not found or not available
     */
    public function locate_mapping_default(string $identifier, ?int $tenantid = null): ?int {

        if ($cfid = $this->locate_mapping(['shortname' => $identifier])) {
            return $cfid;
        }

        return null;
    }

    /**
     * Locate an existing entity that is available to the current user
     *
     * @param array $identifier array or known entity's attributes, for example:
     *     ['name' => 'OLDID', 'idnumber' => 'OLDNAME']
     * @return int|null
     */
    public function locate_mapping(array $identifier): ?int {
        global $DB;

        if (!empty($identifier['shortname']) && !empty($identifier['component']) && !empty($identifier['area'])) {
            $sql = '
                SELECT f.*
                FROM {customfield_field} f
                JOIN {customfield_category} c
                ON f.categoryid = c.id
                WHERE f.shortname = :shortname AND f.type = :type
                    AND c.component = :component AND c.area = :area AND c.itemid = :itemid
            ';
            $params = [
                'shortname' => $identifier['shortname'],
                'type' => $identifier['type'],
                'component' => $identifier['component'],
                'area' => $identifier['area'],
                'itemid' => $identifier['itemid'] ?? 0,
            ];

            if ($cf = $DB->get_record_sql($sql, $params)) {
                return $cf->id;
            }
        }

        return null;
    }
}

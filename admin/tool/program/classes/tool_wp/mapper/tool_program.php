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
 * Class program
 *
 * @package     tool_program
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\tool_wp\mapper;

use tool_tenant\hierarchy;
use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_wp\export_import_mapper_base;
use tool_wp\local\exportimport\forms\import_conflict_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Mapper class for exporting/importing programs
 *
 * @package     tool_program
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program extends export_import_mapper_base {

    /**
     * Initialises mapper and registers all potential notices
     */
    protected function initialise(): void {
        $this->register_potential_error(self::NOTFOUND, [
            self::ERROR_CONFLICTHEADER => get_string('errorsomeprogramsdontexist', 'tool_program'),
            self::ERROR_LOG => function(array $identifier) {
                return get_string('mappingerrorprogramnotfound', 'tool_program',
                        $this->get_identifier_for_display($identifier, true));
            },
            self::ERROR_IDENTIFIER => static function(array $identifier, bool $usequotes = false) {
                return self::display_identifier_idnumber_name($identifier, 'idnumber', 'fullname', $usequotes);
            },
        ]);

        $this->register_potential_notice('namemapping', [
            self::NOTICE_LOG => get_string('mappingnoticenoidnumber', 'tool_program')
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
        global $DB;
        $obj = $DB->get_record(\tool_program\persistent\program::TABLE,
            ['id' => $id], 'id, idnumber, fullname, tenantid');
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
        return $this->locate_mapping(['idnumber' => $identifier, 'tenantid' => $tenantid]);
    }

    /**
     * Allows to locate the existing entity that is available to the current user
     *
     * @param array $identifier array or known entity's attributes, for example:
     *     ['idnumber' => 'OLDID', 'shortname' => 'OLDNAME', 'id' => 'OLDID', 'tenantid' => 'OLDTENANTID']
     * @return int|null
     */
    public function locate_mapping(array $identifier): ?int {
        global $DB;
        if (permission::can_switch_tenant() && array_key_exists('tenantid', $identifier)) {
            $tenantid = $this->get_mapping('tool_tenant', $identifier['tenantid'], IGNORE_MISSING);
        }
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1', $tenantid ?? 0);

        if (!empty($identifier['idnumber'])) {
            $id = $DB->get_field_select(\tool_program\persistent\program::TABLE,
                'id', $sql.' AND idnumber=:idnumber', $params + ['idnumber' => $identifier['idnumber']]);
            if ($id) {
                return $id;
            }
        } else if (!empty($identifier['fullname'])) {
            $id = $DB->get_field_select(\tool_program\persistent\program::TABLE,
                'id', $sql.' AND fullname=:fullname', $params + ['fullname' => $identifier['fullname']]);
            if ($id) {
                // Log a notice that mapping by name is not recommended.
                $this->log_mapping_notice('namemapping', $identifier);
                return $id;
            }
        }
        return null;
    }

    /**
     * Add options to conflict form
     *
     * To retrieve QuickForm:
     *   $mform = $form->get_quick_form();
     * To add form validation:
     *   $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_conflict_form $form
     * @param array $importedentities
     * @param array $identifiers
     * @param bool $addskipaction
     */
    public function add_to_conflict_form(import_conflict_form $form, array $importedentities,
                                         array $identifiers, bool $addskipaction = true): void {
        parent::add_to_conflict_form($form, $importedentities, $identifiers);
    }
}

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
 * Class tool_organisation_position
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_wp\mapper;

use tool_organisation\position;
use tool_organisation\position_manager;
use tool_tenant\permission;
use tool_tenant\tenancy;
use tool_wp\export_import_mapper_base;
use tool_wp\local\exportimport\forms\import_conflict_form;

/**
 * Class tool_organisation_position
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_position extends export_import_mapper_base {

    /**
     * Initialises mapper and registers all potential notices
     */
    protected function initialise(): void {
        $this->register_potential_error(self::NOTFOUND, [
            self::ERROR_CONFLICTHEADER => get_string('somepositionsdonotexist', 'tool_organisation'),
            self::ERROR_LOG => function(array $identifier) {
                return get_string('mappingerrorposnotfound', 'tool_organisation',
                        $this->get_identifier_for_display($identifier));
            },
            self::ERROR_CONFLICTSOLUTION => function(array $values, bool $forform) {
                if ($values['action'] === 'create') {
                    if ($forform) {
                        return 'Create in framework...'; // TODO string.
                    } else {
                        return 'Create in framework '.$values['frmid']; // TODO string, resolve name.
                    }
                }
                return null;
            },
            self::ERROR_IDENTIFIER => static function(array $identifier) {
                return self::display_identifier_idnumber_name($identifier);
            },
        ]);

        $this->register_potential_notice('namemapping', [
            self::NOTICE_LOG => get_string('mappingnoticenoposidnumber', 'tool_organisation')
        ]);

        $this->register_potential_notice('positioncreated', [
            self::NOTICE_LOG => function(array $identifier, array $additionaldata) {
                return "Position '".s($identifier['name'])."' was created"; // TODO string, link.
            }
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
        $obj = $DB->get_record('tool_organisation_position', ['id' => $id], 'id, idnumber, name');
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
     *     ['idnumber' => 'OLDID', 'shortname' => 'OLDNAME', 'id' => 'OLDID', 'tenantid' => 1]
     * @return int|null
     */
    public function locate_mapping(array $identifier): ?int {
        global $DB;
        $params = [];
        if (!permission::can_switch_tenant()) {
            $params['tenantid'] = tenancy::get_tenant_id();
        } else if (array_key_exists('tenantid', $identifier)) {
            $params['tenantid'] = $this->get_mapping('tool_tenant', $identifier['tenantid'], IGNORE_MISSING);
        }
        if (empty($params['tenantid'])) {
            // Tenantid must always be set, until we implement cross-tenant org structures.
            $params['tenantid'] = tenancy::get_tenant_id();
        }

        $id = null;
        if (!empty($identifier['idnumber'])) {
            $id = $DB->get_field('tool_organisation_position',
                'id', $params + ['idnumber' => $identifier['idnumber']]);
        } else if (!empty($identifier['name'])) {
            $id = $DB->get_field('tool_organisation_position',
                'id', $params + ['name' => $identifier['name']], IGNORE_MULTIPLE);
            if ($id) {
                // Log a notice that mapping by name is not recommended.
                $this->log_mapping_notice('namemapping', $identifier);
            }
        }

        if (!$id) {
            $id = $this->create_missing_if_needed($params + $identifier);
        }
        return $id ?: null;
    }

    /**
     * When conflict resolution settings allow and position does not exist, create a new one
     *
     * @param array $identifier
     * @return int|null
     */
    protected function create_missing_if_needed(array $identifier): ?int {
        global $DB;
        if ($this->get_conflict_resolution_setting('action', 'skip') !== 'create') {
            return null;
        }
        $frmid = $this->get_conflict_resolution_setting('frmid');
        $frmexist = $DB->record_exists('tool_organisation_position', ['tenantid' => $identifier['tenantid'],
            'id' => $frmid, 'parentid' => null]);
        if (!$frmexist) {
            return null;
        }
        if ($this->can_fully_apply_conflict_resolutions()) {
            $data = ['parentid' => $frmid] +
                array_intersect_key($identifier, ['idnumber' => '', 'name' => '']);
            $position = (new position_manager())->create_position((object)$data, false);
            $this->log_mapping_notice('positioncreated', $identifier);
            return $position->get('id');
        } else {
            return -1;
        }
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
        global $DB;
        parent::add_to_conflict_form($form, $importedentities, $identifiers);

        $frms = $DB->get_records_menu(position::TABLE,
            ['archived' => 0, 'parentid' => null, 'tenantid' => $this->get_import_tenant_id()], 'sortorder', 'id, name');
        if ($frms) {
            $mform = $form->get_quick_form();
            $key = $this->get_conflict_form_element_name();
            $mform->addElement('radio', $key, '',
                $this->get_conflict_solution(['action' => 'create']), 'create');
            $frmid = $this->get_conflict_form_element_name('frmid');
            $mform->addElement('select', $frmid, get_string('positionframeworks', 'tool_organisation'),
                array_map('format_string', $frms))->setHiddenLabel(true);
            $mform->hideIf($frmid, $key, 'ne', 'create');
        }
    }
}

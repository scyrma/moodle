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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Customfields importer
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\importer;

use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\wp_imported_entity;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/lib.php');

/**
 * Importer class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class customfields extends importer_base {

    /** @var string Component name to import */
    public const COMPONENT = 'component';
    /** @var string Area name to import. */
    public const AREA = 'area';
    /** @var string Item id of the custom fields, usually 0. */
    public const ITEMID = 'itemid';
    /** @var string Instance id (program id, whose fields we are importing). */
    public const INSTANCEID = 'instanceid';
    /** @var string Mapper to use for instance id. */
    public const INSTANCEMAPPER = 'instancemapper';

    /**
     * Importer format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_WORKPLACE;
    }

    /**
     * Importer name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('customfields', 'customfield');
    }

    /**
     * Does this importer "work" only within one tenant?
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return false;
    }

    /**
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        // Additional permission checks should be performed during actual export/import calls because we would need
        // to know component/area/itemid at this point.
        return $this->is_chained_entrypoint();
    }

    /**
     * Called when an instance of the importer is created
     *
     * Must register all entities, potential errors and potential notices by calling
     * $this->register_entity()
     * $this->register_potential_errors()
     * $this->register_potential_notices()
     */
    protected function initialise() {
        $this->register_entity('customfield_data', [
            self::ENTITY_DEPENDENCIES => [],
            self::ENTITY_LOGSUCCESS => function(int $id, array $importerdetails) {
                // Do not log anything on success, from user point of view the custom fields are just like other fields
                // for example 'name' or 'description', they don't need a separate record in the log.
                return null;
            },
            self::ENTITY_LOGERROR => function(array $importerdetails) {
                // Even though we don't log success we still need to log errors.
                $value = format_string($importerdetails['value']);
                return get_string('errorcustomfielddoesnotexist', 'tool_wp', $value);
            },
            self::ENTITY_NAMEPLURAL => get_string('customfields', 'customfield'),
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                // What parameters to use when importing individual entity as part of some other import.
                // May be called from programs and tenants import.

                if (empty($settings['component']) || empty($settings['area']) || empty($settings['instanceid'])
                        || empty($settings['instancemapper'])) {
                    throw new \coding_exception(
                        'Component, area, instancemapper and instanceid are required to export custom fields, itemid is optional.');
                }
                return [
                        self::COMPONENT => $settings['component'],
                        self::AREA => $settings['area'],
                        self::ITEMID => $settings['itemid'] ?? 0,
                        self::INSTANCEID => $settings['instanceid'],
                        self::INSTANCEMAPPER => $settings['instancemapper'],
                    ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $logdetails, int $id) {
                return format_string($logdetails['value']);
            },
        ]);
    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importiscompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importiscompleted): string {
        return '';
    }

    /**
     * Add settings to the import form (Step 3. Options, "what to import")
     *
     * To retrive QuickForm:
     * $mform = $form->get_quick_form()
     * To add validation use:
     * $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_settings_form $form
     */
    public function add_to_options_form(import_settings_form $form): void {
        return;
    }

    /**
     * Summary of entities included in the workplace file (human-readable), displayed in the "Step 2 General settings"
     *
     * Returns array of strings, where each string will be displayed as a separate 'static' element
     * in the form.
     *
     * @return array
     */
    public function get_export_file_content_for_overview_page(): array {
        return [];
    }

    /**
     * Perform the import
     *
     * @param string $entity
     * @return void
     */
    public function perform_import(string $entity): void {
        if ($entity === 'customfield_data') {
            $this->import_customfields();
        }
    }

    /**
     * Import customfields
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function import_customfields(): void {
        $data = $this->get_import_settings();

        $cfs = $this->get_entities_in_workplace_export_file('customfield_data', function(array $entity) use ($data) {
            if ($entity['component'] == $data[self::COMPONENT] &&
                $entity['area'] == $data[self::AREA] &&
                $entity['itemid'] == $data[self::ITEMID] &&
                $entity['instanceid'] == $data[self::INSTANCEID]) {
                return true;
            }
            return false;
        });

        foreach ($cfs as $customfield) {
            $customfield
                ->add_mapping('fieldid', 'customfield_field')
                ->add_mapping('instanceid', $this->get_import_setting(self::INSTANCEMAPPER))
                ->add_mapping('contextid', 'context')
                ->exclude_fields(['component', 'area', 'itemid'])
                ->set_validation_callback(function($data, wp_imported_entity $caller) {

                    $this->add_details_to_log(['value' => $data['value']]);
                    // TODO check permission to update custom field using get_handler can_edit.

                })
                ->set_import_callback(function($data, wp_imported_entity $entity) {

                    $datacontroller = \core_customfield\data_controller::create(0, (object)$data);
                    $datacontroller->save();
                    return $datacontroller->get('id');
                })
                ->import($this);
        }
    }

    /**
     * Add the importer error to the conflict resolution form (Step 5. Conflicts)
     *
     * To retrieve QuickForm:
     * $mform = $form->get_quick_form();
     * To add validation use:
     * $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_conflict_form $form
     * @param string $importedentity
     * @param string $errorcode code of an error that this importer raises using $this->add_error_to_log()
     * @param array $detailsarray array of arrays: for each occurrence of error stores the details that were logged
     *     in add_details_to_log()
     * @param bool $addskipaction  can be used by overridding methods when calling parent
     */
    public function add_to_conflict_form(import_conflict_form $form, string $importedentity,
                                         string $errorcode, array $detailsarray, bool $addskipaction = true): void {

        parent::add_to_conflict_form($form, $importedentity, $errorcode, $detailsarray, true);
    }
}

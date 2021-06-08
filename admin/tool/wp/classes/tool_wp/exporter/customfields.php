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
 * Courses exporter
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\exporter;

use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;

defined('MOODLE_INTERNAL') || die;

/**
 * Exporter class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class customfields extends exporter_base {

    /** @var string Component name to export. */
    public const COMPONENT = 'component';
    /** @var string Area name to export. */
    public const AREA = 'area';
    /** @var string Itemid to export. */
    public const ITEMID = 'itemid';
    /** @var string Itemid to export. */
    public const INSTANCEID = 'instanceid';

    /**
     * Exporter format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_WORKPLACE;
    }

    /**
     * Exporter name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('customfields', 'customfield');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return '';
    }

    /**
     * Initialise the class, register entities that can be exported from other places
     */
    protected function initialise() {
        $this->register_entity('customfield_data', [
            self::ENTITY_INDIVIDUALEXPORT => function(array $ids, array $settings) {
                // What parameters to use when exporting individual entity as part of some other export.
                // May be called from programs and certifications export.

                if (empty($settings['component']) || empty($settings['area']) || empty($settings['instanceid'])) {
                    throw new \coding_exception(
                        'Component, area and instanceid are required to export custom fields, itemid is optional.');
                }
                return [
                        self::COMPONENT => $settings['component'],
                        self::AREA => $settings['area'],
                        self::ITEMID => $settings['itemid'] ?? 0,
                        self::INSTANCEID => $settings['instanceid'],
                    ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return format_string($record['name']);
            },
        ]);
    }

    /**
     * Does this exporter "work" only within one tenant?
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return false;
    }

    /**
     * Allows to mark exporter as not available
     *
     * @return bool
     */
    public function is_available(): bool {
        // Custom fields can only be used as chained exporter.
        return $this->is_chained_entrypoint();
    }

    /**
     * Add elements to the export form
     *
     * To retrieve QuickForm:
     *   $mform = $form->get_quick_form();
     * To add form validation:
     *   $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param export_settings_form $form
     */
    public function add_to_options_form(export_settings_form $form): void {
        // Custom fields can only be used as chained exporter. No options form.
        return;
    }

    /**
     * Summary of the export settings for the review step and also for the report page
     *
     * @param bool $exportcompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $exportcompleted): string {
        // Custom fields can only be used as chained exporter. No summary.
        return '';
    }

    /**
     * Returns the list of entities that will be exported
     *
     * @param string $entityname
     * @return array array where each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW
     *     callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        // Custom fields can only be used as chained exporter. No instances.
        return [];
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {

        $cfdatas = $this->get_customfields_to_export();
        foreach ($cfdatas as $cfdata) {
            $export = $this->prepare_data_for_workplace_export('customfield_data', (array)$cfdata)
                ->exclude_fields(['timecreated', 'timemodified', 'usermodified'])
                ->add_mappings('fieldid', 'customfield_field')
                ->add_mappings('contextid', 'context');

            $export->export();

        }
    }

    /**
     * Returns list of custom fields selected to export
     *
     * @return array
     */
    public function get_customfields_to_export(): array {
        $data = $this->get_export_settings();

        $handler = \core_customfield\handler::get_handler($data[self::COMPONENT], $data[self::AREA], $data[self::ITEMID]);
        $fields = $handler->get_instance_data($data[self::INSTANCEID]);

        $datafields = [];
        foreach ($fields as $field) {
            $record = $field->to_record();
            if (empty($record->id)) {
                continue;
            }
            $record->component = $data[self::COMPONENT];
            $record->area = $data[self::AREA];
            $record->itemid = $data[self::ITEMID];
            $datafields[] = $record;
        }

        return $datafields;
    }
}

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

namespace tool_wp\tool_wp\importer;

use core_reportbuilder\local\audiences\base;
use core_reportbuilder\local\helpers\report as reporthelper;
use core_reportbuilder\local\helpers\schedule as schedule_helper;
use core_reportbuilder\local\models\audience;
use core_reportbuilder\local\models\column;
use core_reportbuilder\local\models\filter;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\schedule;
use core_reportbuilder\manager;
use core_reportbuilder\permission;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\wp_imported_entities;
use tool_wp\local\exportimport\wp_imported_entity;
use tool_wp\reportbuilder\local\exportimport\audience_mapping_helper;
use tool_wp\reportbuilder\local\exportimport\condition_mapping_helper;

/**
 * Report builder custom reports importer
 *
 * @package     tool_wp
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @author      2022 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reports extends importer_base {

    /** @var string Element for including import of report definitions. */
    const IMPORT_CONTENT = 'import_content';

    /** @var string Element for including import of report audiences. */
    const IMPORT_AUDIENCES = 'import_audiences';

    /** @var string Element for including import of report schedules. */
    const IMPORT_SCHEDULES = 'import_schedules';

    /** @var string Element for selecting what to import. */
    const IMPORT_INSTANCES = 'import_instances';

    /** @var string  */
    const IMPORT_INSTANCES_ALL = 'all';

    /** @var string  */
    const IMPORT_INSTANCES_SELECTED = 'selected';

    /** @var string Element for limiting reports to import. */
    const IMPORT_SELECT_REPORTS = 'select_reports';

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
        return get_string('customreports', 'core_reportbuilder');
    }

    /**
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_create_report();
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
        $this->register_entity(report::TABLE, [
            self::ENTITY_DEPENDENCIES => ['tool_tenant'],
            self::ENTITY_INDIVIDUALIMPORT => static function(array $ids, array $settings): array {
                $defaults = [
                    self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_SELECTED,
                    self::IMPORT_CONTENT => 1,
                    self::IMPORT_AUDIENCES => 1,
                    self::IMPORT_SCHEDULES => 1,
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::IMPORT_SELECT_REPORTS => $ids,
                ];
            },
            self::ENTITY_LOGSUCCESS => static function(int $id, array $details) {
                $a = (object) [
                    'name' => format_string($details['name']),
                    'url' => (new \moodle_url('/reportbuilder/edit.php', ['id' => $id]))->out(),
                    'columncount' => $details['columncount'],
                    'conditioncount' => $details['conditioncount'],
                    'filtercount' => $details['filtercount'],
                ];

                return get_string('importreportlogsuccess', 'tool_wp', $a);
            },
            self::ENTITY_LOGERROR => static function(array $details) {
                return get_string('importreportlogerror', 'tool_wp', format_string($details['name']));
            },
            self::ENTITY_NAMEPLURAL => $this->get_name(),
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return format_string($record['name']);
            },
        ]);

        $this->register_potential_error(report::TABLE, 'invalidsource', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidreportsource', 'tool_wp'),
            self::ERROR_LOG => get_string('importlogerrorinvalidreportsource', 'tool_wp'),
        ]);

        $this->register_potential_error(report::TABLE, 'invalidtype', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidreporttype', 'tool_wp'),
            self::ERROR_LOG => get_string('importlogerrorinvalidreporttype', 'tool_wp'),
        ]);

        // Audiences.
        $this->register_entity(audience::TABLE, [
            self::ENTITY_DEPENDENCIES => [report::TABLE],
            self::ENTITY_LOGSUCCESS => static function (int $id, array $details) {
                return get_string('importaudiencelogsuccess', 'tool_wp');
            },
            self::ENTITY_LOGERROR => static function (array $details) {
                return get_string('importaudiencelogerror', 'tool_wp');
            },
            self::ENTITY_NAMEPLURAL => get_string('migrationreportaudiences', 'tool_wp'),
        ]);

        $this->register_potential_error(audience::TABLE, 'invalidaudience', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidaudience', 'tool_wp'),
            self::ERROR_LOG => get_string('importlogerrorinvalidaudience', 'tool_wp'),
        ]);

        // Schedules.
        $this->register_entity(schedule::TABLE, [
            self::ENTITY_DEPENDENCIES => [audience::TABLE, 'user'],
            self::ENTITY_LOGSUCCESS => static function (int $id, array $details) {
                return get_string('importschedulelogsuccess', 'tool_wp');
            },
            self::ENTITY_LOGERROR => static function (array $details) {
                return get_string('importschedulelogerror', 'tool_wp');
            },
            self::ENTITY_NAMEPLURAL => get_string('migrationreportschedules', 'tool_wp'),
        ]);

        $this->register_potential_error(schedule::TABLE, 'invalidformat', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidscheduleformat', 'tool_wp'),
            self::ERROR_LOG => get_string('importlogerrorinvalidscheduleformat', 'tool_wp'),
        ]);
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
        $mform = $form->get_quick_form();
        $mform->addElement('header', 'headerconfig', get_string('content', 'tool_wp'));

        // This field is always set, it's here for informational purposes only.
        $mform->addElement('advcheckbox', self::IMPORT_CONTENT,
            new \lang_string('migrationreportdefinition', 'tool_wp'));
        $mform->addHelpButton(self::IMPORT_CONTENT, 'migrationreportdefinition', 'tool_wp');
        $mform->setDefault(self::IMPORT_CONTENT, 1);
        $form->freeze_at(self::IMPORT_CONTENT, 1);

        // Whether report audience records should be imported (where available).
        $audiencespresent = $this->get_entities_in_workplace_export_file(audience::TABLE)->count() > 0;

        $mform->addElement('advcheckbox', self::IMPORT_AUDIENCES, new \lang_string('migrationreportaudiences', 'tool_wp'));
        $mform->setType(self::IMPORT_AUDIENCES, PARAM_INT);
        $mform->setDefault(self::IMPORT_AUDIENCES, 1);
        if (!$audiencespresent) {
            $form->freeze_at(self::IMPORT_AUDIENCES, 0);
        }

        // Whether report schedule records should be imported (where available).
        $schedulespresent = $this->get_entities_in_workplace_export_file(schedule::TABLE)->count() > 0;

        $mform->addElement('advcheckbox', self::IMPORT_SCHEDULES, new \lang_string('migrationreportschedules', 'tool_wp'));
        $mform->setType(self::IMPORT_SCHEDULES, PARAM_INT);
        $mform->setDefault(self::IMPORT_SCHEDULES, 1);
        if (!$audiencespresent || !$schedulespresent) {
            $form->freeze_at(self::IMPORT_SCHEDULES, 0);
        }
        $mform->hideIf(self::IMPORT_SCHEDULES, self::IMPORT_AUDIENCES, 'neq', 1);

        // Report selection.
        $mform->addElement('header', 'headerinstances', get_string('instances', 'tool_wp'));

        $mform->addElement('radio', self::IMPORT_INSTANCES, null,
            new \lang_string('importselectallreports', 'tool_wp'), self::IMPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::IMPORT_INSTANCES, null,
            new \lang_string('importselectlimitreports', 'tool_wp'), self::IMPORT_INSTANCES_SELECTED);
        $mform->setType(self::IMPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_INSTANCES, self::IMPORT_INSTANCES_ALL);

        // Create elements to allow user to limit which reports to import, sorted by name.
        $reportselect = array_map('format_string',
            $this->get_entities_in_workplace_export_file(report::TABLE)->get_menu('name'));
        \core_collator::asort($reportselect);

        $mform->addElement('autocomplete', self::IMPORT_SELECT_REPORTS,
            new \lang_string('customreports', 'core_reportbuilder'),
            $reportselect, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECT_REPORTS, PARAM_INT);
        $mform->hideIf(self::IMPORT_SELECT_REPORTS, self::IMPORT_INSTANCES, 'ne', self::IMPORT_INSTANCES_SELECTED);

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importiscompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importiscompleted): string {
        global $OUTPUT;

        $data = $this->get_import_settings();

        $strs = get_strings(['migrationreportdefinition', 'migrationreportaudiences', 'migrationreportschedules'], 'tool_wp');
        $settings = [
            ['name' => $strs->migrationreportdefinition, 'value' => !empty($data[self::IMPORT_CONTENT])],
            ['name' => $strs->migrationreportaudiences, 'value' => !empty($data[self::IMPORT_AUDIENCES])],
        ];

        if (!empty($data[self::IMPORT_AUDIENCES])) {
            $settings[] = [
                'name' => $strs->migrationreportschedules,
                'value' => !empty($data[self::IMPORT_SCHEDULES])
            ];
        }

        return $OUTPUT->render_from_template('tool_wp/exportimport_summary', ['settings' => $settings]);
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
        $overview = [];

        // Reports.
        if ($reportcount = $this->get_entities_in_workplace_export_file(report::TABLE)->count()) {
            $overview[] = $this->get_entity_display_name_plural(report::TABLE) .
                get_string('entitiescountpostfix', 'tool_wp', $reportcount);
        }

        // Audiences.
        $audiencecount = $this->get_entities_in_workplace_export_file(audience::TABLE)->count();
        if ($audiencecount > 0) {
            $overview[] = $this->get_entity_display_name_plural(audience::TABLE) .
                get_string('entitiescountpostfix', 'tool_wp', $audiencecount);
        }

        // Schedules.
        $schedulecount = $this->get_entities_in_workplace_export_file(schedule::TABLE)->count();
        if ($schedulecount > 0) {
            $overview[] = $this->get_entity_display_name_plural(schedule::TABLE) .
                get_string('entitiescountpostfix', 'tool_wp', $schedulecount);
        }

        return $overview;
    }

    /**
     * Import form element validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        // Ensure user has selected something to import.
        if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_SELECTED && empty($data[self::IMPORT_SELECT_REPORTS])) {
            $errors[self::IMPORT_SELECT_REPORTS] = new \lang_string('required');
        }

        return $errors;
    }

    /**
     * Validates that the audience can be imported
     *
     * @param array $data
     * @param wp_imported_entity $entity
     * @return void
     */
    public function audience_validation_callback(array $data, wp_imported_entity $entity): void {
        $audienceinstance = base::instance(0,
            (object)array_intersect_key($data, audience::properties_definition()));

        // Ensure instance is valid and current user can add it.
        if (!$audienceinstance || !$audienceinstance->user_can_add()) {
            $this->add_error_to_log('invalidaudience');
        }
    }

    /**
     * Helper method intended to be used for creating audience instances
     *
     * @param array $data
     * @param wp_imported_entity $entity
     * @return int id of imported audience
     */
    public function audience_import_callback(array $data, wp_imported_entity $entity): int {
        $audienceinstance = base::instance(0,
            (object) array_intersect_key($data, audience::properties_definition()));

        // Allow each audience to get field mapping for the import.
        audience_mapping_helper::get_importer_mapping($audienceinstance, $this);

        $persistent = $audienceinstance->get_persistent();
        $persistent->save();

        return $persistent->get('id');
    }

    /**
     * Get audiences from the export file for the given report
     *
     * @param int $reportid
     * @return \tool_wp\local\exportimport\wp_imported_entities
     */
    protected function get_audiences_for_report(int $reportid) {
        return $this->get_entities_in_workplace_export_file(audience::TABLE,
            static function(array $entity) use ($reportid): bool {
                return $entity['reportid'] == $reportid;
            });
    }

    /**
     * Get schedules from the export file for the given report
     *
     * @param int $reportid
     * @return wp_imported_entities
     */
    protected function get_schedules_for_report(int $reportid): wp_imported_entities {
        return $this->get_entities_in_workplace_export_file(schedule::TABLE,
            static function(array $entity) use ($reportid): bool {
                return $entity['reportid'] == $reportid;
            });
    }

    /**
     * Validates that the schedule can be imported
     *
     * @param array $data
     * @param wp_imported_entity $entity
     */
    public function schedule_validation_callback(array $data, wp_imported_entity $entity): void {
        // Raise an error if the schedule dataformat doesn't exist.
        $dataformatwriter = 'dataformat_' . $data['format'] . '\writer';
        if (!class_exists($dataformatwriter)) {
            $this->add_error_to_log('invalidformat');
        }
    }

    /**
     * Import a schedule
     *
     * @param array $data
     * @param wp_imported_entity $entity
     * @return int id of the imported scheudle
     */
    public function schedule_import_callback(array $data, wp_imported_entity $entity): int {
        $schedulerecord = array_intersect_key($data, schedule::properties_definition());

        // Reconstruct recipient audiences, mapping to those we've just imported.
        $audiences = (array)json_decode($schedulerecord['audiences']);
        $audiences = array_map(function (int $audience): int {
            return $this->get_mapping(audience::TABLE, $audience, IGNORE_MISSING) ?? -1;
        }, $audiences);

        // Apply some sensible defaults, the schedule should be disabled by default with reset "last sent".
        $schedulerecord = array_merge($schedulerecord, [
            'enabled' => 0,
            'lastsenton' => -1,
            'audiences' => json_encode($audiences),
        ]);

        return schedule_helper::create_schedule((object)$schedulerecord)->get('id');
    }

    /**
     * Validates that report can be imported
     *
     * @param array $data
     * @param wp_imported_entity $entity
     * @return void
     */
    public function report_validation_callback(array $data, wp_imported_entity $entity): void {
        $this->add_details_to_log([
            'name' => $data['name'],
            'source' => $data['source'],
        ]);

        // Ensure that imported report source is valid.
        if (!manager::report_source_exists($data['source'])) {
            $this->add_error_to_log('invalidsource');
        }

        // We can only import custom reports.
        if ($data['type'] != \core_reportbuilder\local\report\base::TYPE_CUSTOM_REPORT) {
            $this->add_error_to_log('invalidtype');
        }
    }

    /**
     * Import one report, its columns, filters and conditions
     *
     * @param array $data
     * @param wp_imported_entity $entity
     * @return bool|mixed|null
     */
    public function report_import_callback(array $data, wp_imported_entity $entity) {
        // We need to re-check user can still create reports and hasn't gone over any configured limits.
        permission::require_can_create_report();

        $excludefields = ['timecreated', 'timemodified', 'usermodified'];
        $reportpersistent = reporthelper::create_report((object)$data, false);
        $reportid = $reportpersistent->get('id');

        // Nested columns.
        $columns = $entity->get_nested_entities(column::TABLE, $excludefields);
        foreach ($columns as $column) {
            $columnrecord = ['reportid' => $reportid] + array_intersect_key($column, column::properties_definition());
            (new column(0, (object)$columnrecord))->create();
        }
        $this->add_details_to_log(['columncount' => count($columns)]);

        // Nested filters and conditions.
        $conditioncount = 0;
        $filters = $entity->get_nested_entities(filter::TABLE, $excludefields);
        foreach ($filters as $filter) {
            $conditionrecord = ['reportid' => $reportid] + array_intersect_key($filter, filter::properties_definition());
            (new filter(0, (object)$conditionrecord))->create();
            $conditioncount += (int)(!empty($filter['iscondition']));
        }
        $filtercount = count($filters) - $conditioncount;
        $this->add_details_to_log(['filtercount' => $filtercount]);

        // Loop over all conditions we added to the report again and allow them to get field mappings.
        $report = \core_reportbuilder\manager::get_report_from_persistent($reportpersistent);
        if ($activeconditions = $report->get_active_conditions()) {
            $values = $report->get_condition_values();
            foreach ($activeconditions as $condition) {
                condition_mapping_helper::get_importer_mapping($condition, $this, $values);
            }
            $report->set_condition_values($values);
        }
        $this->add_details_to_log(['conditioncount' => $conditioncount]);

        // Map the original report ID to the new one.
        $this->set_mapping(report::TABLE, $entity->get_original_id(), $reportid);

        return $reportid;
    }

    /**
     * Perform the import
     *
     * @param string $entityname
     * @return void
     */
    public function perform_import(string $entityname): void {
        // Only import report entities; schedules and audiences will be imported as part of them.
        if (strcmp($entityname, report::TABLE) !== 0) {
            return;
        }

        $settingimportinstances = $this->get_import_setting(self::IMPORT_INSTANCES);
        if (!in_array($settingimportinstances, [self::IMPORT_INSTANCES_ALL, self::IMPORT_INSTANCES_SELECTED])) {
            return;
        }

        // We don't want the automatic persistent fields.
        $excludefields = ['timecreated', 'timemodified', 'usermodified'];

        // Set filter if we are importing specific reports (rather than all of them).
        $selectedreports = $this->get_import_setting(self::IMPORT_SELECT_REPORTS);

        $reports = $this->get_entities_in_workplace_export_file(report::TABLE,
            function(array $entity) use ($selectedreports, $settingimportinstances) {
                return ($settingimportinstances === self::IMPORT_INSTANCES_ALL || in_array($entity['id'], $selectedreports));
            });

        foreach ($reports as $report) {
            $report
                ->exclude_fields($excludefields)
                ->add_mapping('itemid', 'tool_tenant')
                ->set_validation_callback([$this, 'report_validation_callback'])
                ->set_import_callback([$this, 'report_import_callback'])
                ->import($this);

            if ($report->get_new_id() && $this->get_import_setting(self::IMPORT_AUDIENCES)) {
                // Import audiences.
                foreach ($this->get_audiences_for_report($report->get_original_id()) as $audience) {
                    $audience
                        ->exclude_fields($excludefields)
                        ->add_mapping('reportid', report::TABLE)
                        ->set_validation_callback([$this, 'audience_validation_callback'])
                        ->set_import_callback([$this, 'audience_import_callback'])
                        ->import($this);
                }

                // Import schedules. Schedules can only be imported if audiences are imported.
                if ($this->get_import_setting(self::IMPORT_SCHEDULES)) {
                    foreach ($this->get_schedules_for_report($report->get_original_id()) as $schedule) {
                        $schedule
                            ->exclude_fields($excludefields)
                            ->add_mapping('reportid', report::TABLE)
                            ->set_validation_callback([$this, 'schedule_validation_callback'])
                            ->set_import_callback([$this, 'schedule_import_callback'])
                            ->import($this);
                    }
                }
            }
        }
    }
}

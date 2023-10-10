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
 * Report builder custom reports importer
 *
 * @package     tool_reportbuilder
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_wp\importer;

use tool_reportbuilder\audience_base;
use tool_reportbuilder\constants;
use tool_reportbuilder\datasource;
use tool_reportbuilder\filter_base;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\local\helpers\schedules as schedule_helper;
use tool_reportbuilder\local\models\audiences;
use tool_reportbuilder\local\models\reportbuilder_conditions as condition;
use tool_reportbuilder\local\models\schedule;
use tool_reportbuilder\local\report\reportbuilder_filter as filter;
use tool_reportbuilder\reportbuilder_column as column;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\wp_imported_entity;

/**
 * Importer class
 *
 * @package     tool_reportbuilder
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class customreports extends importer_base {

    /** @var string Entity name for legacy audience records. */
    private const AUDIENCE_ENTITY_LEGACY = 'tool_reportbuilder_audience';

    /** @var string Entity name for legacy schedule records. */
    private const SCHEDULE_ENTITY_LEGACY = 'tool_reportbuilder_scheduled';

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
        return get_string('customreports', 'tool_reportbuilder');
    }

    /**
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_create(false);
    }

    /**
     * Returns source replacements.
     *
     * For backwards compatibility using different source classes.
     *
     * @return array of $oldclass => $newclass
     */
    private function get_source_replacements(): array {
        return [
            'tool_certificate\tool_reportbuilder\datasources\issues' =>
                'tool_reportbuilder\tool_reportbuilder\datasources\report_tool_certificate_issues',
            'tool_certificate\tool_reportbuilder\datasources\certificates' =>
                'tool_reportbuilder\tool_reportbuilder\datasources\report_tool_certificate_templates'
        ];
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
        $this->register_entity(reportbuilder::TABLE, [
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
                    'url' => (new \moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $id]))->out(),
                    'columncount' => $details['columncount'],
                    'conditioncount' => $details['conditioncount'],
                    'filtercount' => $details['filtercount'],
                ];

                return get_string('importlogsuccess', 'tool_reportbuilder', $a);
            },
            self::ENTITY_LOGERROR => static function(array $details) {
                return get_string('importlogerror', 'tool_reportbuilder', format_string($details['name']));
            },
            self::ENTITY_NAMEPLURAL => $this->get_name(),
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return format_string($record['name']);
            },
        ]);

        $this->register_potential_error(reportbuilder::TABLE, 'invalidsource', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidsource', 'tool_reportbuilder'),
            self::ERROR_LOG => get_string('importlogerrorinvalidsource', 'tool_reportbuilder'),
        ]);

        $this->register_potential_error(reportbuilder::TABLE, 'invalidtype', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidtype', 'tool_reportbuilder'),
            self::ERROR_LOG => get_string('importlogerrorinvalidtype', 'tool_reportbuilder'),
        ]);

        // Audiences.
        $this->register_entity(audiences::TABLE, [
            self::ENTITY_DEPENDENCIES => [reportbuilder::TABLE],
            self::ENTITY_LOGSUCCESS => static function (int $id, array $details) {
                return get_string('importaudiencelogsuccess', 'tool_reportbuilder');
            },
            self::ENTITY_LOGERROR => static function (array $details) {
                return get_string('importaudiencelogerror', 'tool_reportbuilder');
            },
            self::ENTITY_NAMEPLURAL => get_string('audiences', 'tool_reportbuilder'),
        ]);

        $this->register_potential_error(audiences::TABLE, 'invalidaudience', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidaudience', 'tool_reportbuilder'),
            self::ERROR_LOG => get_string('importlogerrorinvalidaudience', 'tool_reportbuilder'),
        ]);

        // Audiences (legacy).
        $this->register_entity(self::AUDIENCE_ENTITY_LEGACY, [
            self::ENTITY_DEPENDENCIES => ['tool_organisation_department', 'tool_organisation_position', reportbuilder::TABLE],
            self::ENTITY_LOGSUCCESS => static function (int $id, array $details) {
                return get_string('importaudiencelogsuccess', 'tool_reportbuilder');
            },
            self::ENTITY_LOGERROR => static function (array $details) {
                return get_string('importaudiencelogerror', 'tool_reportbuilder');
            },
            self::ENTITY_NAMEPLURAL => get_string('audiences', 'tool_reportbuilder'),
        ]);

        // Schedules.
        $this->register_entity(schedule::TABLE, [
            self::ENTITY_DEPENDENCIES => [audiences::TABLE, 'user'],
            self::ENTITY_LOGSUCCESS => static function (int $id, array $details) {
                return get_string('importschedulelogsuccess', 'tool_reportbuilder');
            },
            self::ENTITY_LOGERROR => static function (array $details) {
                return get_string('importschedulelogerror', 'tool_reportbuilder');
            },
            self::ENTITY_NAMEPLURAL => get_string('schedules', 'tool_reportbuilder'),
        ]);

        $this->register_potential_error(schedule::TABLE, 'invalidformat', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidformat', 'tool_reportbuilder'),
            self::ERROR_LOG => get_string('importlogerrorinvalidformat', 'tool_reportbuilder'),
        ]);

        // Schedules (legacy).
        $this->register_entity(self::SCHEDULE_ENTITY_LEGACY, [
            self::ENTITY_DEPENDENCIES => ['tool_organisation_department', 'tool_organisation_position', 'user'],
            self::ENTITY_LOGSUCCESS => static function (int $id, array $details) {
                return get_string('importschedulelogsuccess', 'tool_reportbuilder');
            },
            self::ENTITY_LOGERROR => static function (array $details) {
                return get_string('importschedulelogerror', 'tool_reportbuilder');
            },
            self::ENTITY_NAMEPLURAL => get_string('schedules', 'tool_reportbuilder'),
        ]);

        $this->register_potential_error(self::SCHEDULE_ENTITY_LEGACY, 'invalidformat', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidformat', 'tool_reportbuilder'),
            self::ERROR_LOG => get_string('importlogerrorinvalidformat', 'tool_reportbuilder'),
        ]);

        $this->register_potential_error(self::SCHEDULE_ENTITY_LEGACY, 'legacyemails', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorlegacyemails', 'tool_reportbuilder'),
            self::ERROR_LOG => get_string('importlogerrorlegacyemails', 'tool_reportbuilder'),
            self::ERROR_CONFLICTSOLUTION => static function(array $settings, bool $forform): ?string {
                if (strcmp($settings['action'], 'import') === 0) {
                    return get_string('importlogerrorlegacyemailsimport', 'tool_reportbuilder');
                }
                return null;
            },
        ]);

        $this->register_potential_notice(self::SCHEDULE_ENTITY_LEGACY, 'legacyemailslog', [
            self::NOTICE_LOG => static function(array $details, array $noticedetails): string {
                return get_string('importlogerrorlegacyemailslog', 'tool_reportbuilder', (object) [
                    'docslink' => get_docs_url('Report_builder#Upgrading_audience_and_schedules_prior_to_3.11'),
                ]);
            },
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
            new \lang_string('importexportreportdefinition', 'tool_reportbuilder'));
        $mform->addHelpButton(self::IMPORT_CONTENT, 'importexportreportdefinition', 'tool_reportbuilder');
        $mform->setDefault(self::IMPORT_CONTENT, 1);
        $form->freeze_at(self::IMPORT_CONTENT, 1);

        // Whether report audience records should be imported (where available) - note support for legacy audiences.
        $audiencespresent = $this->get_entities_in_workplace_export_file(self::AUDIENCE_ENTITY_LEGACY)->count() > 0 ||
            $this->get_entities_in_workplace_export_file(audiences::TABLE)->count() > 0;

        $mform->addElement('advcheckbox', self::IMPORT_AUDIENCES, new \lang_string('audiences', 'tool_reportbuilder'));
        $mform->setType(self::IMPORT_AUDIENCES, PARAM_INT);
        $mform->setDefault(self::IMPORT_AUDIENCES, 1);
        if (!$audiencespresent) {
            $form->freeze_at(self::IMPORT_AUDIENCES, 0);
        }

        // Whether report schedule records should be imported (where available) - note support for legacy schedules.
        $schedulespresent = $this->get_entities_in_workplace_export_file(self::SCHEDULE_ENTITY_LEGACY)->count() > 0 ||
            $this->get_entities_in_workplace_export_file(schedule::TABLE)->count() > 0;

        $mform->addElement('advcheckbox', self::IMPORT_SCHEDULES, new \lang_string('schedules', 'tool_reportbuilder'));
        $mform->setType(self::IMPORT_SCHEDULES, PARAM_INT);
        $mform->setDefault(self::IMPORT_SCHEDULES, 1);
        if (!$audiencespresent || !$schedulespresent) {
            $form->freeze_at(self::IMPORT_SCHEDULES, 0);
        }
        $mform->hideIf(self::IMPORT_SCHEDULES, self::IMPORT_AUDIENCES, 'neq', 1);

        // Report selection.
        $mform->addElement('header', 'headerinstances', get_string('instances', 'tool_wp'));

        $mform->addElement('radio', self::IMPORT_INSTANCES, null,
            new \lang_string('importselectall', 'tool_reportbuilder'), self::IMPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::IMPORT_INSTANCES, null,
            new \lang_string('importselectlimit', 'tool_reportbuilder'), self::IMPORT_INSTANCES_SELECTED);
        $mform->setType(self::IMPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_INSTANCES, self::IMPORT_INSTANCES_ALL);

        // Create elements to allow user to limit which reports to import, sorted by name.
        $reportselect = array_map('format_string',
            $this->get_entities_in_workplace_export_file(reportbuilder::TABLE)->get_menu('name'));
        \core_collator::asort($reportselect);

        $mform->addElement('autocomplete', self::IMPORT_SELECT_REPORTS, new \lang_string('customreports', 'tool_reportbuilder'),
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

        $strs = get_strings(['importexportreportdefinition', 'audiences', 'schedules'], 'tool_reportbuilder');
        $settings = [
            ['name' => $strs->importexportreportdefinition, 'value' => !empty($data[self::IMPORT_CONTENT])],
            ['name' => $strs->audiences, 'value' => !empty($data[self::IMPORT_AUDIENCES])],
        ];

        if (!empty($data[self::IMPORT_AUDIENCES])) {
            $settings[] = [
                'name' => $strs->schedules,
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
        if ($reportcount = $this->get_entities_in_workplace_export_file(reportbuilder::TABLE)->count()) {
            $overview[] = $this->get_entity_display_name_plural(reportbuilder::TABLE) .
                get_string('entitiescountpostfix', 'tool_wp', $reportcount);
        }

        // Audiences (note support for legacy audiences).
        if (0 === ($audiencecount = $this->get_entities_in_workplace_export_file(self::AUDIENCE_ENTITY_LEGACY)->count())) {
            $audiencecount = $this->get_entities_in_workplace_export_file(audiences::TABLE)->count();
        }
        if ($audiencecount > 0) {
            $overview[] = $this->get_entity_display_name_plural(audiences::TABLE) .
                get_string('entitiescountpostfix', 'tool_wp', $audiencecount);
        }

        // Schedules (note support for legacy schedules).
        if (0 === ($schedulecount = $this->get_entities_in_workplace_export_file(self::SCHEDULE_ENTITY_LEGACY)->count())) {
            $schedulecount = $this->get_entities_in_workplace_export_file(schedule::TABLE)->count();
        }
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
     * Add importer errors to conflict resolution form
     *
     * @param import_conflict_form $form
     * @param string $importedentity
     * @param string $errorcode
     * @param array $details
     * @param bool $addskipaction
     */
    public function add_to_conflict_form(import_conflict_form $form, string $importedentity, string $errorcode, array $details,
            bool $addskipaction = true): void {

        parent::add_to_conflict_form($form, $importedentity, $errorcode, $details, $addskipaction);

        $mform = $form->get_quick_form();

        if (strcmp($importedentity, self::SCHEDULE_ENTITY_LEGACY) === 0 && strcmp($errorcode, 'legacyemails') === 0) {
            $elementname = $this->get_conflict_form_element_name($importedentity, $errorcode);

            $mform->addElement('radio', $elementname, null,
                $this->get_conflict_solution($importedentity, $errorcode, ['action' => 'import']), 'import');
            $mform->setType($elementname, PARAM_ALPHANUMEXT);
            $mform->setDefault($elementname, 'import');
        }
    }

    /**
     * Helper method intended to be used for creating audience instances, to be used for both legacy and current types
     *
     * @param array $data
     * @param bool $audiencemapping Whether the audience should perform import mapping
     * @return int
     */
    private function import_audience_callback(array $data, bool $audiencemapping = true): int {
        $audienceinstance = audience_base::instance(0,
            (object) array_intersect_key($data, audiences::properties_definition()));

        // Allow each audience to get field mapping for the import.
        if ($audiencemapping) {
            $audienceinstance->get_importer_mapping($this);
        }

        $persistent = $audienceinstance->get_persistent();
        $persistent->save();

        return $persistent->get('id');
    }

    /**
     * Perform the import
     *
     * @param string $entityname
     * @return void
     */
    public function perform_import(string $entityname): void {
        // Only import report entities; schedules and audiences will be imported as part of them.
        if (strcmp($entityname, reportbuilder::TABLE) !== 0) {
            return;
        }

        $settingimportall = $this->get_import_setting(self::IMPORT_INSTANCES);
        if ($settingimportall !== null) {

            // We don't want the automatic persistent fields.
            $excludefields = ['timecreated', 'timemodified', 'usermodified'];

            // Set filter if we are importing specific reports (rather than all of them).
            $filter = $settingimportall === self::IMPORT_INSTANCES_ALL ?
                [] : $this->get_import_setting(self::IMPORT_SELECT_REPORTS);

            $reports = $this->get_entities_in_workplace_export_file(reportbuilder::TABLE,
                function(array $entity) use ($filter, $settingimportall) {
                    return ($settingimportall === self::IMPORT_INSTANCES_ALL || in_array($entity['id'], $filter));
                });

            foreach ($reports as $report) {
                $report
                    // Note that 'shortname' is no longer present in the report persistent, so should be excluded.
                    ->exclude_fields(array_merge($excludefields, ['idnumber', 'shortname']))
                    ->add_mapping('tenantid', 'tool_tenant')
                    ->set_validation_callback(function(array $data, wp_imported_entity $entity): void {
                        $this->add_details_to_log([
                            'name' => $data['name'],
                            'source' => $data['source'],
                        ]);

                        // Ensure that imported report source is valid.
                        if (!manager::report_source_valid($data['source'], datasource::class)) {
                            // Accept replacement sources. Update them later.
                            if (!in_array($data['source'], array_keys($this->get_source_replacements()))) {
                                $this->add_error_to_log('invalidsource');
                            }
                        }

                        // We can only import custom reports.
                        if ($data['type'] != constants::TYPE_DATASOURCE) {
                            $this->add_error_to_log('invalidtype');
                        }
                    })
                    ->set_import_callback(function(array $data, wp_imported_entity $entity) use ($excludefields): int {
                        global $PAGE;

                        // We need to re-check user can still create reports and hasn't gone over any configured limits.
                        permission::require_can_create();

                        // Update replacements datasources.
                        $data['source'] = $this->get_source_replacements()[$data['source']] ?? $data['source'];

                        $reportpersistent = manager::save_report($data, false);
                        $report = manager::get_report($reportpersistent->get('id'));

                        // Nested columns.
                        $columns = $entity->get_nested_entities(column::TABLE, $excludefields);
                        foreach ($columns as $column) {
                            $columnrecord = array_intersect_key($column, column::properties_definition());
                            (new column(0, (object) $columnrecord))
                                ->set('reportid', $report->get_id())
                                ->create();
                        }
                        $this->add_details_to_log(['columncount' => count($columns)]);

                        // Nested conditions.
                        $conditions = $entity->get_nested_entities(condition::TABLE, $excludefields);
                        foreach ($conditions as $condition) {
                            $conditionrecord = array_intersect_key($condition, condition::properties_definition());
                            (new condition(0, (object) $conditionrecord))
                                ->set('reportid', $report->get_id())
                                ->create();
                        }

                        // Loop over all conditions we added to the report again and allow them to get field mappings.
                        $allconditions = $report->get_conditions();
                        $conditionshelper = new conditions_helper($report);

                        $conditions = $conditionshelper::get_conditions($report->get_active_conditions(), $allconditions,
                            $PAGE->get_renderer('tool_reportbuilder'));

                        foreach ($conditions as $key => $condition) {
                            $conditioninstance = filter_base::create($condition->classname, $allconditions[$key], $condition->id,
                                $condition->heading, $condition->default);

                            // We need to populate the condition values from the current report.
                            $conditionsvalues = $conditionshelper->get_condition_values($conditioninstance->get_name());
                            $conditioninstance->set_values($conditionsvalues);

                            $conditionsvaluesmapped = $conditioninstance->get_importer_mapping($this);
                            if ($conditionsvaluesmapped) {
                                $conditionshelper->merge_condition_values($conditionsvaluesmapped);
                            }
                        }

                        $this->add_details_to_log(['conditioncount' => count($conditions)]);

                        // Nested filters.
                        $filters = $entity->get_nested_entities(filter::TABLE, $excludefields);
                        foreach ($filters as $filter) {
                            $filterrecord = array_intersect_key($filter, filter::properties_definition());
                            (new filter(0, (object) $filterrecord))
                                ->set('reportid', $report->get_id())
                                ->create();
                        }
                        $this->add_details_to_log(['filtercount' => count($filters)]);

                        // Map the original report ID to the new one.
                        $this->set_mapping(reportbuilder::TABLE, $entity->get_original_id(), $reportpersistent->get('id'));

                        return $reportpersistent->get('id');
                    })
                    ->import($this);

                // Helper for getting entities linked to the current report.
                $entityreportfilter = static function(array $entity) use ($report): bool {
                    return $entity['reportid'] == $report->get_original_id();
                };

                // Check if audiences were included in import configuration - note support for legacy audiences.
                if ($this->get_import_setting(self::IMPORT_AUDIENCES) && $report->get_new_id()) {

                    // Legacy audience entity types.
                    $audiences = $this->get_entities_in_workplace_export_file(self::AUDIENCE_ENTITY_LEGACY, $entityreportfilter);
                    foreach ($audiences as $audience) {
                        $audience
                            ->exclude_fields($excludefields)
                            ->add_mapping('reportid', reportbuilder::TABLE)
                            ->add_mapping('departmentid', 'tool_organisation_department')
                            ->add_mapping('positionid', 'tool_organisation_position')
                            ->set_import_callback(function(array $data, wp_imported_entity $entity): int {
                                $config = [
                                    'department' => [
                                        'id' => $data['departmentid'],
                                        'withsubdepartments' => $data['subdepartments'],
                                    ],
                                    'position' => [
                                        'id' => $data['positionid'],
                                        'withsubpositions' => $data['subpositions'],
                                    ],
                                ];

                                $data = array_merge($data, [
                                    'classname' => \tool_organisation\tool_reportbuilder\audiences\job::class,
                                    'configdata' => json_encode($config),
                                ]);

                                return $this->import_audience_callback($data);
                            })
                            ->import($this);
                    }

                    // Current audience entity types (non-legacy).
                    $audiences = $this->get_entities_in_workplace_export_file(audiences::TABLE, $entityreportfilter);
                    foreach ($audiences as $audience) {
                        $audience
                            ->exclude_fields($excludefields)
                            ->add_mapping('reportid', reportbuilder::TABLE)
                            ->set_validation_callback(function(array $data, wp_imported_entity $entity): void {
                                $audienceinstance = audience_base::instance(0,
                                    (object) array_intersect_key($data, audiences::properties_definition()));

                                // Ensure instance is valid and current user can add it.
                                if (!$audienceinstance || !$audienceinstance->user_can_add()) {
                                    $this->add_error_to_log('invalidaudience');
                                }
                            })
                            ->set_import_callback(function(array $data, wp_imported_entity $entity): int {
                                return $this->import_audience_callback($data);
                            })
                            ->import($this);
                    }
                }

                // Check if schedules were included in import configuration - note support for legacy schedules.
                if ($this->get_import_setting(self::IMPORT_AUDIENCES) && $this->get_import_setting(self::IMPORT_SCHEDULES) &&
                        $report->get_new_id()) {

                    // Legacy schedule entity types.
                    $schedules = $this->get_entities_in_workplace_export_file(self::SCHEDULE_ENTITY_LEGACY, $entityreportfilter);
                    foreach ($schedules as $schedule) {
                        $schedule
                            ->exclude_fields($excludefields)
                            ->add_mapping('reportid', reportbuilder::TABLE)
                            ->add_mapping('usercreated', 'user')
                            ->add_mapping('departmentid', 'tool_organisation_department')
                            ->add_mapping('positionid', 'tool_organisation_position')
                            ->set_validation_callback(function($data, wp_imported_entity $entity) {
                                // Raise an error if the schedule dataformat doesn't exist.
                                $dataformatwriter = 'dataformat_' . $data['format'] . '\writer';
                                if (!class_exists($dataformatwriter)) {
                                    $this->add_error_to_log('invalidformat');
                                }

                                // Raise an error if recipient emails are present (no longer supported).
                                $recipients = json_decode($data['recipients']);
                                if (!empty($recipients->emails)) {
                                    $this->add_error_to_log('legacyemails');
                                }
                            })
                            ->set_import_callback(function($data, wp_imported_entity $entity) {
                                $audiences = [];

                                // Create matching "Job" audience for the department, with "Any" position.
                                if ($data['departmentid'] > 0) {
                                    $audience = [
                                        'reportid' => $data['reportid'],
                                        'usercreated' => $data['usercreated'],
                                        'classname' => \tool_organisation\tool_reportbuilder\audiences\job::class,
                                        'configdata' => json_encode([
                                            'department' => ['id' => $data['departmentid']],
                                            'position' => ['id' => 0],
                                        ]),
                                    ];

                                    $audiences[] = $this->import_audience_callback($audience, false);
                                }

                                // Create matching "Job" audience for the position, with "Any" department.
                                if ($data['positionid'] > 0) {
                                    $audience = [
                                        'reportid' => $data['reportid'],
                                        'usercreated' => $data['usercreated'],
                                        'classname' => \tool_organisation\tool_reportbuilder\audiences\job::class,
                                        'configdata' => json_encode([
                                            'position' => ['id' => $data['positionid']],
                                            'department' => ['id' => 0],
                                        ]),
                                    ];

                                    $audiences[] = $this->import_audience_callback($audience, false);
                                }

                                // Create matching "Manual" audience for manually added user recipients.
                                $users = $entity->get_nested_entities('users');
                                if (!empty($users)) {
                                    $users = array_map(function(array $user): int {
                                        return $this->get_mapping('user', $user['userid'], IGNORE_MISSING) ?? -1;
                                    }, $users);

                                    $audience = [
                                        'reportid' => $data['reportid'],
                                        'usercreated' => $data['usercreated'],
                                        'classname' => \tool_reportbuilder\tool_reportbuilder\audiences\manual::class,
                                        'configdata' => json_encode(['users' => $users]),
                                    ];

                                    $audiences[] = $this->import_audience_callback($audience, false);
                                }

                                // If user chose to import without legacy emails, add some helpful logging.
                                $recipients = json_decode($data['recipients']);
                                $legacyemailsresolution = $this->get_conflict_resolution_setting(self::SCHEDULE_ENTITY_LEGACY,
                                    'legacyemails', 'action');
                                if (!empty($recipients->emails) && strcmp($legacyemailsresolution, 'import') === 0) {
                                    $this->add_notice_to_log('legacyemailslog');
                                }

                                // Remove old data from the schedule, replace with the new audiences we've created.
                                unset($data['departmentid'], $data['positionid'], $data['recipients']);

                                // Apply some sensible defaults, the schedule should be disabled by default with reset "last sent".
                                $schedulerecord = array_merge($data, [
                                    'enabled' => 0,
                                    'lastsenton' => -1,
                                    'audiences' => json_encode($audiences),
                                ]);

                                return schedule_helper::add_schedule((object) $schedulerecord)->get('id');
                            })
                            ->import($this);
                    }

                    // Current schedule entity types (non-legacy).
                    $schedules = $this->get_entities_in_workplace_export_file(schedule::TABLE, $entityreportfilter);
                    foreach ($schedules as $schedule) {
                        $schedule
                            ->exclude_fields($excludefields)
                            ->add_mapping('reportid', reportbuilder::TABLE)
                            ->add_mapping('usercreated', 'user')
                            ->set_validation_callback(function($data, wp_imported_entity $entity) {
                                // Raise an error if the schedule dataformat doesn't exist.
                                $dataformatwriter = 'dataformat_' . $data['format'] . '\writer';
                                if (!class_exists($dataformatwriter)) {
                                    $this->add_error_to_log('invalidformat');
                                }
                            })
                            ->set_import_callback(function($data, wp_imported_entity $entity) {
                                $schedulerecord = array_intersect_key($data, schedule::properties_definition());

                                // Reconstruct recipient audiences, mapping to those we've just imported.
                                $audiences = (array) json_decode($schedulerecord['audiences']);
                                $audiences = array_map(function(int $audience): int {
                                    return $this->get_mapping(audiences::TABLE, $audience, IGNORE_MISSING) ?? -1;
                                }, $audiences);

                                // Apply some sensible defaults, the schedule should be disabled by default with reset "last sent".
                                $schedulerecord = array_merge($schedulerecord, [
                                    'enabled' => 0,
                                    'lastsenton' => -1,
                                    'audiences' => json_encode($audiences),
                                ]);

                                return schedule_helper::add_schedule((object) $schedulerecord)->get('id');
                            })
                            ->import($this);
                    }
                }
            }
        }
    }
}

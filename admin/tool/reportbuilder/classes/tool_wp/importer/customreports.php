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
 * Report builder custom reports importer
 *
 * @package     tool_reportbuilder
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_wp\importer;

use tool_reportbuilder\constants;
use tool_reportbuilder\datasource;
use tool_reportbuilder\filter_base;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\local\helpers\audience as audience_helper;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\local\helpers\schedules as schedule_helper;
use tool_reportbuilder\local\models\audience;
use tool_reportbuilder\local\models\reportbuilder_conditions as condition;
use tool_reportbuilder\local\models\schedules as schedule;
use tool_reportbuilder\local\report\reportbuilder_filter as filter;
use tool_reportbuilder\reportbuilder_column as column;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\wp_imported_entity;

/**
 * Importer class
 *
 * @package     tool_reportbuilder
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class customreports extends importer_base {

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
        $this->register_entity(audience::TABLE, [
            self::ENTITY_DEPENDENCIES => ['tool_organisation_department', 'tool_organisation_position'],
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
            self::ENTITY_DEPENDENCIES => ['tool_organisation_department', 'tool_organisation_position', 'user'],
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

        $this->register_potential_error(schedule::TABLE, 'norecipients', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrornorecipients', 'tool_reportbuilder'),
            self::ERROR_LOG => get_string('importlogerrornorecipients', 'tool_reportbuilder'),
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

        // Whether report audience records should be imported (where available).
        $mform->addElement('advcheckbox', self::IMPORT_AUDIENCES, new \lang_string('audiences', 'tool_reportbuilder'));
        $mform->setType(self::IMPORT_AUDIENCES, PARAM_INT);
        $mform->setDefault(self::IMPORT_AUDIENCES, 1);
        if ($this->get_entities_in_workplace_export_file(audience::TABLE)->count() == 0) {
            $form->freeze_at(self::IMPORT_AUDIENCES, 0);
        }

        // Whether report schedule records should be imported (where available).
        $mform->addElement('advcheckbox', self::IMPORT_SCHEDULES, new \lang_string('schedules', 'tool_reportbuilder'));
        $mform->setType(self::IMPORT_SCHEDULES, PARAM_INT);
        $mform->setDefault(self::IMPORT_SCHEDULES, 1);
        if ($this->get_entities_in_workplace_export_file(schedule::TABLE)->count() == 0) {
            $form->freeze_at(self::IMPORT_SCHEDULES, 0);
        }

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
            ['name' => $strs->schedules, 'value' => !empty($data[self::IMPORT_SCHEDULES])],
        ];

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

        // Audiences.
        if ($audiencecount = $this->get_entities_in_workplace_export_file(audience::TABLE)->count()) {
            $overview[] = $this->get_entity_display_name_plural(audience::TABLE) .
                get_string('entitiescountpostfix', 'tool_wp', $audiencecount);
        }

        // Schedules.
        if ($schedulecount = $this->get_entities_in_workplace_export_file(schedule::TABLE)->count()) {
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

                // Check if audiences were included in import configuration.
                if ($this->get_import_setting(self::IMPORT_AUDIENCES) && $report->get_new_id()) {
                    $audiences = $this->get_entities_in_workplace_export_file(audience::TABLE,
                        function(array $entity) use ($report) {
                            return $entity['reportid'] == $report->get_original_id();
                        });

                    foreach ($audiences as $audience) {
                        $audience
                            ->exclude_fields($excludefields)
                            ->add_mapping('reportid', reportbuilder::TABLE)
                            ->add_mapping('departmentid', 'tool_organisation_department')
                            ->add_mapping('positionid', 'tool_organisation_position')
                            ->set_import_callback(function($data, wp_imported_entity $entity) {
                                $audiencerecord = array_intersect_key($data, audience::properties_definition());

                                return audience_helper::create_record((object) $audiencerecord)->get('id');
                            })
                            ->import($this);
                    }
                }

                // Check if schedules were included in import configuration.
                if ($this->get_import_setting(self::IMPORT_SCHEDULES) && $report->get_new_id()) {
                    $schedules = $this->get_entities_in_workplace_export_file(schedule::TABLE,
                        function(array $entity) use ($report) {
                            return $entity['reportid'] == $report->get_original_id();
                        });

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

                                // Ensure we have some schedule recipients.
                                $recipients = json_decode($data['recipients']);
                                if (empty($data['departmentid']) && empty($data['positionid']) && empty($recipients->users)
                                        && empty($recipients->emails)) {

                                    $this->add_error_to_log('norecipients');
                                }

                                // Ensure all our custom user recipients are mapped.
                                $users = $entity->get_nested_entities('users');
                                foreach ($users as $user) {
                                    $this->get_mapping('user', $user['userid']);
                                }
                            })
                            ->set_import_callback(function($data, wp_imported_entity $entity) {
                                $schedulerecord = array_intersect_key($data, schedule::properties_definition());

                                // Reconstruct any custom user recipients.
                                $recipients = json_decode($schedulerecord['recipients']);
                                $recipients->users = [];

                                $users = $entity->get_nested_entities('users');
                                foreach ($users as $user) {
                                    $recipients->users[] = $this->get_mapping('user', $user['userid']);
                                }

                                // Apply some sensible defaults, the schedule should be disabled by default with reset "last sent".
                                $schedulerecord = array_merge($schedulerecord, [
                                    'enabled' => 0,
                                    'lastsenton' => -1,
                                    'recipients' => json_encode($recipients),
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

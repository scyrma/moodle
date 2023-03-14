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

namespace tool_wp\tool_wp\exporter;

use core_reportbuilder\local\models\audience;
use core_reportbuilder\local\models\column;
use core_reportbuilder\local\models\filter;
use core_reportbuilder\local\models\schedule;
use core_reportbuilder\permission;
use core_reportbuilder\local\models\report as reportbuilder;
use tool_tenant\tenancy;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\local\exportimport\helper;
use tool_wp\reportbuilder\local\exportimport\audience_mapping_helper;
use tool_wp\reportbuilder\local\exportimport\condition_mapping_helper;

/**
 * Report builder custom reports exporter
 *
 * @package     tool_wp
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @author      2022 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reports extends exporter_base {

    /** @var string Element for including export of report definitions. */
    const EXPORT_CONTENT = 'export_content';

    /** @var string Element for including export of report audiences. */
    const EXPORT_AUDIENCES = 'export_audiences';

    /** @var string Element for including export of report schedules. */
    const EXPORT_SCHEDULES = 'export_schedules';

    /** @var string Element for selecting what to export. */
    const EXPORT_INSTANCES = 'export_instances';

    /** @var string Element for selecting what to export. */
    const EXPORT_INSTANCES_ALL = 'all';

    /** @var string Element for selecting what to export. */
    const EXPORT_INSTANCES_SELECTED = 'selected';

    /** @var string Element for limiting reports to export. */
    const EXPORT_SELECT_REPORTS = 'select_reports';

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
        return get_string('customreports', 'core_reportbuilder');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exporterdescriptionreports', 'tool_wp');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/report_builder', 'theme')->out(false);
    }

    /**
     * Allows to mark exporter as not available, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_create_report();
    }

    /**
     * Called when an instance of the importer is created
     *
     * Must register all entities that can be called from other exporter as chained exports
     * {@see register_entity()}
     */
    public function initialise() {
        $this->register_entity(reportbuilder::TABLE, [
            self::ENTITY_INDIVIDUALEXPORT => static function(array $ids, array $settings): array {
                $defaults = [
                    self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_SELECTED,
                    self::EXPORT_CONTENT => 1,
                    self::EXPORT_AUDIENCES => 1,
                    self::EXPORT_SCHEDULES => 1,
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::EXPORT_SELECT_REPORTS => $ids,
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return format_string($record['name']);
            },
        ]);
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
        $mform = $form->get_quick_form();
        $mform->addElement('header', 'headerconfig', get_string('content', 'tool_wp'));

        // This field is always set, it's here for informational purposes only.
        $mform->addElement('advcheckbox', self::EXPORT_CONTENT,
            new \lang_string('migrationreportdefinition', 'tool_wp'));
        $mform->addHelpButton(self::EXPORT_CONTENT, 'migrationreportdefinition', 'tool_wp');
        $mform->setDefault(self::EXPORT_CONTENT, 1);
        $form->freeze_at(self::EXPORT_CONTENT, 1);

        // Whether report audience records should be exported.
        $mform->addElement('advcheckbox', self::EXPORT_AUDIENCES,
            new \lang_string('migrationreportaudiences', 'tool_wp'));
        $mform->setType(self::EXPORT_AUDIENCES, PARAM_INT);
        $mform->setDefault(self::EXPORT_AUDIENCES, 1);

        // Whether report schedule records should be exported.
        $mform->addElement('advcheckbox', self::EXPORT_SCHEDULES, new \lang_string('migrationreportschedules', 'tool_wp'));
        $mform->setType(self::EXPORT_SCHEDULES, PARAM_INT);
        $mform->setDefault(self::EXPORT_SCHEDULES, 1);
        $mform->hideIf(self::EXPORT_SCHEDULES, self::EXPORT_AUDIENCES, 'neq', 1);

        // Report selection.
        $mform->addElement('header', 'headerinstances', get_string('instances', 'tool_wp'));

        $mform->addElement('radio', self::EXPORT_INSTANCES, null,
            new \lang_string('exportselectallreports', 'tool_wp'), self::EXPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null,
            new \lang_string('exportselectlimitreports', 'tool_wp'), self::EXPORT_INSTANCES_SELECTED);
        $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_ALL);

        // Create elements to allow user to limit which reports to export.
        $reportselect = array_map(static function(reportbuilder $report): string {
            return format_string($report->get('name'));
        }, $this->get_custom_reports());

        $mform->addElement('autocomplete', self::EXPORT_SELECT_REPORTS, new \lang_string('customreports', 'core_reportbuilder'),
            $reportselect, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::EXPORT_SELECT_REPORTS, PARAM_INT);
        $mform->hideIf(self::EXPORT_SELECT_REPORTS, self::EXPORT_INSTANCES, 'ne', self::EXPORT_INSTANCES_SELECTED);

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Summary of the export settings for the review step and also for the report page
     *
     * @param bool $exportcompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $exportcompleted): string {
        global $OUTPUT;

        $data = $this->get_export_settings();

        $strs = get_strings(['migrationreportdefinition', 'migrationreportaudiences', 'migrationreportschedules'],
            'tool_wp');
        $settings = [
            ['name' => $strs->migrationreportdefinition, 'value' => !empty($data[self::EXPORT_CONTENT])],
            ['name' => $strs->migrationreportaudiences, 'value' => !empty($data[self::EXPORT_AUDIENCES])],
        ];

        if (!empty($data[self::EXPORT_AUDIENCES])) {
            $settings[] = [
                'name' => $strs->migrationreportschedules,
                'value' => !empty($data[self::EXPORT_SCHEDULES])
            ];
        }

        return $OUTPUT->render_from_template('tool_wp/exportimport_summary', ['settings' => $settings]);
    }

    /**
     * Returns the list of entities that will be exported
     *
     * Implement also {@see get_summary_for_review_step()}
     *
     * @param string $entityname
     * @return array array where each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW
     *     callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        // Only review report entities; schedules and audiences will be imported as part of them.
        if (strcmp($entityname, reportbuilder::TABLE) === 0) {
            $data = $this->get_export_settings();

            $filter = $data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_ALL ?
                [] : $data[self::EXPORT_SELECT_REPORTS];

            return helper::persistents_to_array($this->get_custom_reports($filter));
        }

        return [];
    }

    /**
     * Export form element validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        // Ensure user has selected something to export.
        if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_SELECTED
                && empty($data[self::EXPORT_SELECT_REPORTS])) {
            $errors[self::EXPORT_SELECT_REPORTS] = new \lang_string('required');
        }

        return $errors;
    }

    /**
     * Export one report including columns, filters, conditions
     *
     * @param reportbuilder $reportbuilder
     */
    protected function export_report(reportbuilder $reportbuilder): void {
        /** @var \core_reportbuilder\datasource $report */
        $report = \core_reportbuilder\manager::get_report_from_persistent($reportbuilder);
        $excludefields = ['timecreated', 'timemodified', 'usercreated', 'usermodified'];

        // Prepare report export, add nested entities for columns, filters and conditions.
        $activecolumns = $activefilters = [];
        foreach ($report->get_active_columns() as $column) {
            $activecolumns[] = (array)$column->get_persistent()->to_record();
        }
        foreach (array_merge($report->get_active_filters(), $report->get_active_conditions()) as $filter) {
            $activefilters[] = (array)$filter->get_persistent()->to_record();
        }
        $export = $this->prepare_data_for_workplace_export(reportbuilder::TABLE, (array)$reportbuilder->to_record())
            ->add_nested_entities(column::TABLE, $activecolumns, $excludefields)
            ->add_nested_entities(filter::TABLE, $activefilters, $excludefields)
            ->exclude_fields($excludefields)
            ->add_mappings('itemid', 'tool_tenant');

        // Allow each condition to add field mapping to the export.
        $values = $report->get_condition_values();
        foreach ($report->get_active_conditions() as $condition) {
            condition_mapping_helper::add_exporter_mapping($condition, $this, $values);
        }

        $export->export();
    }

    /**
     * Exports one audience
     *
     * @param audience $audience
     */
    protected function export_audience(audience $audience): void {
        $audiencerecord = $audience->to_record();
        $excludefields = ['timecreated', 'timemodified', 'usercreated', 'usermodified'];

        // Allow each audience to add field mapping to the export.
        if ($audienceinstance = \core_reportbuilder\local\audiences\base::instance(0, $audiencerecord)) {
            audience_mapping_helper::add_exporter_mapping($audienceinstance, $this);
        }

        $this->prepare_data_for_workplace_export(audience::TABLE, (array) $audiencerecord)
            ->exclude_fields($excludefields)
            ->export();
    }

    /**
     * Export one schedule
     *
     * @param schedule $schedule
     */
    protected function export_schedule(schedule $schedule): void {
        $schedulerecord = $schedule->to_record();
        $excludefields = ['timecreated', 'timemodified', 'usercreated', 'usermodified'];

        $this->prepare_data_for_workplace_export(schedule::TABLE, (array) $schedulerecord)
            ->exclude_fields($excludefields)
            ->export();
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        global $DB;

        $data = $this->get_export_settings();
        if (!in_array($data[self::EXPORT_INSTANCES], [self::EXPORT_INSTANCES_ALL, self::EXPORT_INSTANCES_SELECTED])) {
            return;
        }

        // Set filter if we are exporting specific reports (rather than all of them).
        $filter = $data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_ALL ? [] : $data[self::EXPORT_SELECT_REPORTS];
        $reportids = [];
        foreach ($this->get_custom_reports($filter) as $reportbuilder) {
            $this->export_report($reportbuilder);
            $reportids[] = $reportbuilder->get('id');
        }

        // Check if audiences and schedules were included in export configuration.
        if (!empty($data[self::EXPORT_AUDIENCES]) && count($reportids)) {
            [$select, $params] = $DB->get_in_or_equal($reportids, SQL_PARAMS_NAMED);
            foreach (audience::get_records_select("reportid {$select}", $params) as $audience) {
                $this->export_audience($audience);
            }

            if (!empty($data[self::EXPORT_SCHEDULES])) {
                foreach (schedule::get_records_select("reportid {$select}", $params) as $schedule) {
                    $this->export_schedule($schedule);
                }
            }
        }
    }

    /**
     * Return array of custom reports, optionally filtered by specific ID's
     *
     * @param int[] $filter
     * @return reportbuilder[]
     */
    private function get_custom_reports(array $filter = []): array {
        global $DB;

        $select = 'component = :component AND itemid = :tenantid AND type = :type';
        $params = [
            'component' => 'tool_tenant',
            'tenantid' => $this->get_export_tenant_id() ?: tenancy::get_tenant_id(),
            'type' => \core_reportbuilder\local\report\base::TYPE_CUSTOM_REPORT,
        ];

        // Add filter ID's to select and params if specified.
        if (!empty($filter)) {
            list($reportselect, $reportparams) = $DB->get_in_or_equal($filter, SQL_PARAMS_NAMED, 'id');
            $select .= " AND id {$reportselect}";
            $params = array_merge($params, $reportparams);
        }

        return reportbuilder::get_records_select($select, $params, 'name');
    }
}

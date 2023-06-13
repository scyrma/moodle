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
 * Report builder custom reports exporter
 *
 * @package     tool_reportbuilder
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_wp\exporter;

use tool_reportbuilder\audience_base;
use tool_reportbuilder\constants;
use tool_reportbuilder\filter_base;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\local\models\audiences;
use tool_reportbuilder\local\models\reportbuilder_conditions as condition;
use tool_reportbuilder\local\models\schedule;
use tool_reportbuilder\local\report\reportbuilder_filter as filter;
use tool_reportbuilder\reportbuilder_column as column;
use tool_tenant\tenancy;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\local\exportimport\helper;

/**
 * Exporter class
 *
 * @package     tool_reportbuilder
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class customreports extends exporter_base {

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
        return get_string('customreportslegacy', 'tool_reportbuilder');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('customreportsdesc', 'tool_reportbuilder');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('icon_legacy', 'tool_reportbuilder')->out(false);
    }

    /**
     * Allows to mark exporter as not available, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_create(true);
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
            new \lang_string('importexportreportdefinition', 'tool_reportbuilder'));
        $mform->addHelpButton(self::EXPORT_CONTENT, 'importexportreportdefinition', 'tool_reportbuilder');
        $mform->setDefault(self::EXPORT_CONTENT, 1);
        $form->freeze_at(self::EXPORT_CONTENT, 1);

        // Whether report audience records should be exported.
        $mform->addElement('advcheckbox', self::EXPORT_AUDIENCES, new \lang_string('audiences', 'tool_reportbuilder'));
        $mform->setType(self::EXPORT_AUDIENCES, PARAM_INT);
        $mform->setDefault(self::EXPORT_AUDIENCES, 1);

        // Whether report schedule records should be exported.
        $mform->addElement('advcheckbox', self::EXPORT_SCHEDULES, new \lang_string('schedules', 'tool_reportbuilder'));
        $mform->setType(self::EXPORT_SCHEDULES, PARAM_INT);
        $mform->setDefault(self::EXPORT_SCHEDULES, 1);
        $mform->hideIf(self::EXPORT_SCHEDULES, self::EXPORT_AUDIENCES, 'neq', 1);

        // Report selection.
        $mform->addElement('header', 'headerinstances', get_string('instances', 'tool_wp'));

        $mform->addElement('radio', self::EXPORT_INSTANCES, null,
            new \lang_string('exportselectall', 'tool_reportbuilder'), self::EXPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null,
            new \lang_string('exportselectlimit', 'tool_reportbuilder'), self::EXPORT_INSTANCES_SELECTED);
        $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_ALL);

        // Create elements to allow user to limit which reports to export.
        $reportselect = array_map(static function(reportbuilder $report): string {
            return format_string($report->get('name'));
        }, $this->get_custom_reports());

        $mform->addElement('autocomplete', self::EXPORT_SELECT_REPORTS,
            new \lang_string('customreportslegacy', 'tool_reportbuilder'),
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

        $strs = get_strings(['importexportreportdefinition', 'audiences', 'schedules'], 'tool_reportbuilder');
        $settings = [
            ['name' => $strs->importexportreportdefinition, 'value' => !empty($data[self::EXPORT_CONTENT])],
            ['name' => $strs->audiences, 'value' => !empty($data[self::EXPORT_AUDIENCES])],
        ];

        if (!empty($data[self::EXPORT_AUDIENCES])) {
            $settings[] = [
                'name' => $strs->schedules,
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
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        global $DB, $PAGE;

        $data = $this->get_export_settings();
        if (!empty($data[self::EXPORT_INSTANCES])) {

            $output = $PAGE->get_renderer('tool_reportbuilder');

            // We don't want the automatic persistent fields.
            $excludefields = ['timecreated', 'timemodified', 'usermodified'];

            // Set filter if we are exporting specific reports (rather than all of them).
            $filter = $data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_ALL ? [] : $data[self::EXPORT_SELECT_REPORTS];
            $reportids = [];

            foreach ($this->get_custom_reports($filter) as $reportbuilder) {
                $report = manager::get_report($reportbuilder->get('id'));

                // Prepare report export.
                $export = $this->prepare_data_for_workplace_export(reportbuilder::TABLE, (array) $reportbuilder->to_record())
                    ->add_nested_entities(column::TABLE, helper::persistents_to_array($report->get_active_columns()),
                        $excludefields)
                    ->add_nested_entities(filter::TABLE, helper::persistents_to_array($report->get_active_filters()),
                        $excludefields)
                    ->exclude_fields($excludefields)
                    ->add_mappings('tenantid', 'tool_tenant');

                // Report conditions need to be handled separetely.
                $activeconditions = $report->get_active_conditions();
                $export->add_nested_entities(condition::TABLE, helper::persistents_to_array($activeconditions), $excludefields);

                // Allow each condition to add field mapping to the export.
                $allconditions = $report->get_conditions();
                $conditionshelper = new conditions_helper($report);

                $conditions = $conditionshelper::get_conditions($activeconditions, $allconditions, $output);
                foreach ($conditions as $key => $condition) {
                    $conditioninstance = filter_base::create($condition->classname, $allconditions[$key], $condition->id,
                        $condition->heading, $condition->default);

                    // We need to populate the condition values from the current report.
                    $conditionsvalues = $conditionshelper->get_condition_values($conditioninstance->get_name());
                    $conditioninstance->set_values($conditionsvalues);

                    $conditioninstance->add_exporter_mapping($this);
                }

                // Store the list of exported reports, for later use.
                $reportids[] = $report->get_id();

                $export->export();
            }

            // Check if audiences were included in export configuration.
            if (!empty($data[self::EXPORT_AUDIENCES]) && count($reportids) > 0) {
                list($select, $params) = $DB->get_in_or_equal($reportids, SQL_PARAMS_NAMED);
                $audiences = audiences::get_records_select("reportid {$select}", $params);

                foreach ($audiences as $audience) {
                    $audiencerecord = $audience->to_record();

                    // Allow each audience to add field mapping to the export.
                    if ($audienceinstance = audience_base::instance(0, $audiencerecord)) {
                        $audienceinstance->add_exporter_mapping($this);
                    }

                    $this->prepare_data_for_workplace_export(audiences::TABLE, (array) $audiencerecord)
                        ->export();
                }
            }

            // Check if schedules were included in export configuration.
            if (!empty($data[self::EXPORT_AUDIENCES]) && !empty($data[self::EXPORT_SCHEDULES]) && (count($reportids) > 0)) {
                list($select, $params) = $DB->get_in_or_equal($reportids, SQL_PARAMS_NAMED);
                $schedules = schedule::get_records_select("reportid {$select}", $params);

                foreach ($schedules as $schedule) {
                    $schedulerecord = $schedule->to_record();

                    $this->prepare_data_for_workplace_export(schedule::TABLE, (array) $schedulerecord)
                        ->add_mappings('usercreated', 'user')
                        ->export();
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

        $select = 'tenantid = :tenantid AND type = :type';
        $params = [
            'tenantid' => $this->get_export_tenant_id() ?: tenancy::get_tenant_id(),
            'type' => constants::TYPE_DATASOURCE,
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

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
 * File containing tests for import/export classes
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_reportbuilder\manager;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\local\models\audience;
use tool_reportbuilder\local\models\reportbuilder_conditions as condition;
use tool_reportbuilder\local\models\schedules as schedule;
use tool_reportbuilder\local\report\reportbuilder_filter as filter;
use tool_reportbuilder\reportbuilder_column as column;
use tool_reportbuilder\test\mock_report3 as mock_report_conditions_filters;
use tool_reportbuilder\tool_wp\exporter\customreports as exporter;
use tool_reportbuilder\tool_wp\importer\customreports as importer;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\wp_imported_entity;

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\tool_wp\exporter\customreports
 * @covers      \tool_reportbuilder\tool_wp\importer\customreports
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_export_import_testcase extends advanced_testcase {

    /** @var string $importfixture */
    protected $importfixture = __DIR__ . '/fixtures/custom-reports-export.zip';

    /** @var string $importfixtureinvalid */
    protected $importfixtureinvalid = __DIR__ . '/fixtures/custom-reports-export-invalid.zip';

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
    }

    /**
     * Test exporting single report
     *
     * @return void
     */
    public function test_export_single_report() {
        $report1 = $this->get_plugin_generator()->create_report(['source' => mock_report_conditions_filters::class,
            'name' => 'Report 1']);
        $report2 = $this->get_plugin_generator()->create_report(['source' => mock_report_conditions_filters::class,
            'name' => 'Report 2']);

        // Create audience/schedule records for the first report.
        $audience = $this->get_plugin_generator()->create_audience(['reportid' => $report1->get_id()]);
        $schedule = $this->get_plugin_generator()->create_schedule(['reportid' => $report1->get_id()]);

        // Create a new export containing only the first report.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_SELECTED,
            exporter::EXPORT_SELECT_REPORTS => [$report1->get_id()],
            exporter::EXPORT_AUDIENCES => 1,
            exporter::EXPORT_SCHEDULES => 1,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have the first report in the import.
        $reports = $importer->get_entities_in_workplace_export_file(reportbuilder::TABLE);
        $this->assertCount(1, $reports);

        /** @var wp_imported_entity $entity */
        $entity = iterator_to_array($reports, false)[0];
        $this->assertEquals($report1->get_reportname(), $entity->get_raw_field('name'));

        // Nested columns.
        $columns = $entity->get_nested_entities(column::TABLE);
        $this->assertCount(2, $columns);

        // Nested conditions.
        $conditions = $entity->get_nested_entities(condition::TABLE);
        $this->assertCount(1, $conditions);

        // Nested filters.
        $filters = $entity->get_nested_entities(filter::TABLE);
        $this->assertCount(1, $filters);

        // Audience entities.
        $audienceentities = $importer->get_entities_in_workplace_export_file(audience::TABLE);
        $this->assertCount(1, $audienceentities);
        $this->assertEquals($audience->get('id'), $audienceentities->current()->get_original_id());

        // Schedule entities.
        $scheduleentities = $importer->get_entities_in_workplace_export_file(schedule::TABLE);
        $this->assertCount(1, $scheduleentities);
        $this->assertEquals($schedule->get('id'), $scheduleentities->current()->get_original_id());
    }

    /**
     * Test exporting all reports
     *
     * @return void
     */
    public function test_export_all_reports() {
        $report1 = $this->get_plugin_generator()->create_report(['source' => mock_report_conditions_filters::class,
            'name' => 'Report 1']);
        $report2 = $this->get_plugin_generator()->create_report(['source' => mock_report_conditions_filters::class,
            'name' => 'Report 2']);

        // Create a new export of all reports.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have both reports in the import.
        $reports = $importer->get_entities_in_workplace_export_file(reportbuilder::TABLE, null, function(array $entity) {
            return $entity['name'];
        });
        $this->assertCount(2, $reports);

        $reportnames = array_map(function(wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($reports, false));

        $this->assertEquals([$report1->get_reportname(), $report2->get_reportname()], $reportnames);
    }

    /**
     * Test exporting when there is nothing to export
     *
     * @return void
     */
    public function test_export_no_reports() {
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
            exporter::EXPORT_AUDIENCES => 1,
            exporter::EXPORT_SCHEDULES => 1,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // There shouldn't be any content.
        $this->assertEmpty($importer->get_entities_in_workplace_export_file(reportbuilder::TABLE));
        $this->assertEmpty($importer->get_entities_in_workplace_export_file(audience::TABLE));
        $this->assertEmpty($importer->get_entities_in_workplace_export_file(schedule::TABLE));
    }

    /**
     * Test importing single report from an export file
     *
     * @return void
     */
    public function test_import_single_report() {
        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records());

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_SELECTED,
            // Original ID of the "UK Users" report from the test fixture.
            importer::IMPORT_SELECT_REPORTS => [129],
            // The report has a schedule that won't match position/user entities.
            importer::IMPORT_SCHEDULES => 1,
            helper::get_setting_name_for_conflict_form('tool_organisation_position', 'action') => 'skip',
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form(schedule::TABLE, 'norecipients', 'action') => 'skip',
        ]);

        $reports = reportbuilder::get_records();
        $this->assertCount(1, $reports);

        $reportid = reset($reports)->get('id');

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertEqualsCanonicalizing([
            'Some positions do not exist',
            'Some users do not exist',
            'Missing or invalid schedule recipients',
        ], array_column($conflicts, 0));

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(2, $logs);

        // First log should be successful report import.
        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[0];

        $reporturl = (new \moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $reportid]))->out();
        $this->assertEquals("Created new report '<a href=\"{$reporturl}\">UK Users</a>' with 3 columns, 1 conditions and 2 filters",
            $detail);
        $this->assertEmpty($errors);
        $this->assertEmpty($notices);

        // Second log should be failure to import schedule.
        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[1];

        $this->assertEquals('Couldn\'t import schedule record', $detail);
        $this->assertEqualsCanonicalizing([
            'Could not find user \'paul\' (\'paul@mail.internal\') in current tenant',
            'A position Office manager was not found',
            'Missing or invalid schedule recipients',
        ], $errors);
        $this->assertEmpty($notices);

        // Get the report we just imported, confirm it's idnumber/uniqid doesn't match the value from the test fixture.
        $report = manager::get_report($reportid);
        $this->assertNotEquals('5eb2aebc58de3', $report->get_report_uniqid());

        // Columns.
        $columns = $report->get_active_columns();
        $this->assertCount(3, $columns);

        $columnidentifiers = array_map(function(column $column) {
            return $column->get_unique_identifier();
        }, $columns);
        $this->assertEqualsCanonicalizing(['user:firstname', 'user:lastname', 'user:country'], $columnidentifiers);

        // Conditions.
        $conditions = $report->get_active_conditions();
        $this->assertCount(1, $conditions);

        $conditionidentifiers = array_map(function(condition $condition) {
            return $condition->get_unique_identifier();
        }, $conditions);
        $this->assertEqualsCanonicalizing(['user:country'], $conditionidentifiers);

        // Filters.
        $filters = $report->get_active_filters();
        $this->assertCount(2, $filters);

        $filteridentifiers = array_map(function(filter $filter) {
            return $filter->get_unique_identifier();
        }, $filters);
        $this->assertEqualsCanonicalizing(['user:firstname', 'user:lastname'], $filteridentifiers);
    }

    /**
     * Test importing single report from an export file with mapped entities
     *
     * @return void
     */
    public function test_import_single_report_mapped_entities() {
        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records());

        // Create entities to match those referred to in the test fixture.
        $this->getDataGenerator()->create_user(['username' => 'paul']);
        $this->get_organisation_generator()->create_department(['name' => 'Sub-Garden']);
        $this->get_organisation_generator()->create_position(['name' => 'Franchise manager']);
        $this->get_organisation_generator()->create_position(['name' => 'Office manager']);

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_SELECTED,
            // Original ID of the "Course completion" report from the test fixture.
            importer::IMPORT_SELECT_REPORTS => [138],
            importer::IMPORT_AUDIENCES => 1,
            importer::IMPORT_SCHEDULES => 1,
        ]);

        $reports = reportbuilder::get_records();
        $this->assertCount(1, $reports);

        $reportid = reset($reports)->get('id');

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertEmpty($conflicts);

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(4, $logs);

        $reporturl = (new \moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $reportid]))->out();
        $this->assertEquals([
            "Created new report '<a href=\"{$reporturl}\">Course completion</a>' with 4 columns, 0 conditions and 2 filters",
            'Audience record imported',
            'Audience record imported',
            'Schedule record imported'
        ], array_column($logs, 'detail'));

        // Audience records.
        $audiences = audience::get_records(['reportid' => $reportid]);
        $this->assertCount(2, $audiences);

        // Schedule records.
        $schedules = schedule::get_records(['reportid' => $reportid]);
        $this->assertCount(1, $schedules);
    }

    /**
     * Test importing invalid reports from an export file
     */
    public function test_import_invalid_reports() {
        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixtureinvalid, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            helper::get_importer_setting_name_for_conflict_form(reportbuilder::TABLE, 'invalidsource', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form(reportbuilder::TABLE, 'invalidtype', 'action') => 'skip',
        ]);

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertCount(2, $conflicts);
        $this->assertEquals(['Missing or invalid report source', 'Invalid report type'], array_column($conflicts, 0));

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(2, $logs);

        // First log should be invalid report type.
        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[0];
        $this->assertEquals('Couldn\'t import report \'UK Users\'', $detail);
        $this->assertEquals(['Invalid report type'], $errors);
        $this->assertEmpty($notices);

        // First log should be invalid report source.
        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[1];
        $this->assertEquals('Couldn\'t import report \'Course completion\'', $detail);
        $this->assertEquals(['Missing or invalid report source'], $errors);
        $this->assertEmpty($notices);
    }

    /**
     * Test importing all reports from an export file into the current tenant
     *
     * @return void
     */
    public function test_import_all_reports_current_tenant() {
        $tenantid = tenancy::get_default_tenant_id();

        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records(['tenantid' => $tenantid]));

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        $reports = reportbuilder::get_records(['tenantid' => $tenantid], 'name');
        $this->assertCount(2, $reports);

        $reportnames = array_map(function(reportbuilder $report) {
            return $report->get('name');
        }, $reports);

        $this->assertEquals(['Course completion', 'UK Users'], $reportnames);

        $this->assertEmpty($this->get_workplace_generator()->get_import_conflict_review($importid));
    }

    /**
     * Test importing all reports from an export file into another tenant
     *
     * @return void
     */
    public function test_import_all_reports_different_tenant() {
        $tenantid = $this->get_tenant_generator()->create_tenant()->id;

        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records(['tenantid' => $tenantid]));

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            'tenantid' => $tenantid,
        ]);

        $reports = reportbuilder::get_records(['tenantid' => $tenantid], 'name');
        $this->assertCount(2, $reports);

        $reportnames = array_map(function(reportbuilder $report) {
            return $report->get('name');
        }, $reports);

        $this->assertEquals(['Course completion', 'UK Users'], $reportnames);

        $this->assertEmpty($this->get_workplace_generator()->get_import_conflict_review($importid));
    }

    /**
     * Test importing with invalid settings
     *
     * @return void
     */
    public function test_import_invalid_settings() {
        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records());

        // Set to import specific reports, but don't provide them.
        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_SELECTED,
            importer::IMPORT_SELECT_REPORTS => [],
        ]);

        // Nothing was imported.
        $this->assertEquals(0, reportbuilder::count_records());

        $this->assertEmpty($this->get_workplace_generator()->get_import_conflict_review($importid));
    }

    /**
     * Test condition field mapping during export/import
     */
    public function test_export_import_field_mapping(): void {
        $course = $this->getDataGenerator()->create_course();

        $report = $this->get_plugin_generator()->create_report([
            'source' => tool_reportbuilder\tool_reportbuilder\datasources\report_course_enrolment_completion::class,
        ]);

        $this->get_plugin_generator()->add_condition($report, 'course:courseselector');
        (new \tool_reportbuilder\local\helpers\conditions($report))
            ->merge_condition_values(['course:courseselector_op' => [$course->id]]);

        // Export our report.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_SELECTED,
            exporter::EXPORT_SELECT_REPORTS => [$report->get_id()],
        ]);

        // Now delete the original course, and create a new one with the same name.
        $originalid = $course->id;
        $originalname = $course->shortname;
        delete_course($course, false);

        $newcourse = $this->getDataGenerator()->create_course(['shortname' => $originalname]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the course mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('course', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported condition field should be mapped to the new course.
        $reports = reportbuilder::get_records([], 'id');
        $newreport = manager::get_report(end($reports)->get('id'));

        $conditionvalues = (new \tool_reportbuilder\local\helpers\conditions($newreport))
            ->get_condition_values('course:courseselector');
        $this->assertEquals([$newcourse->id], reset($conditionvalues));
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_plugin_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Get organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_organisation_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Get Workplace generator
     *
     * @return tool_wp_generator
     */
    protected function get_workplace_generator(): tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }
}

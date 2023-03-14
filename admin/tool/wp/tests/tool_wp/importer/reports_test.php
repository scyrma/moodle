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

use advanced_testcase;
use core_reportbuilder\local\models\audience;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\schedule;
use core_reportbuilder\manager;
use tool_organisation_generator;
use tool_tenant_generator;
use \tool_wp\tool_wp\exporter\reports as exporter;
use \tool_wp\tool_wp\importer\reports as importer;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\helper;
use tool_wp_generator;

/**
 * Tests for custom reports import
 *
 * @package     tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\importer\reports
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reports_test extends advanced_testcase {

    /** @var string $importfixture */
    protected $importfixture = __DIR__ . '/../../fixtures/custom-reports-export.zip';

    /** @var string $importfixtureinvalid */
    protected $importfixtureinvalid = __DIR__ . '/../../fixtures/custom-reports-export-invalid.zip';

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
    }

    /**
     * Test importing single report from an export file
     *
     * @return void
     */
    public function test_import_single_report() {
        // Sanity test.
        $this->assertEquals(0, report::count_records());

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_SELECTED,
            // Original ID of the "UK Users" report from the test fixture.
            importer::IMPORT_SELECT_REPORTS => [156],
            // The report has a schedule that won't match position/user entities.
            importer::IMPORT_SCHEDULES => 1,
            helper::get_setting_name_for_conflict_form('tool_organisation_position', 'action') => 'skip',
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
        ]);

        $reports = report::get_records();
        $this->assertCount(1, $reports);

        $reportid = reset($reports)->get('id');

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertEqualsCanonicalizing([
            'Some positions do not exist',
            'Some users do not exist',
        ], array_column($conflicts, 0));

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(1, $logs);

        // First log should be successful report import.
        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[0];

        $reporturl = (new \moodle_url('/reportbuilder/edit.php', ['id' => $reportid]))->out();
        $this->assertEquals(
            "Created new report '<a href=\"{$reporturl}\">UK Users</a>' with 3 columns, 1 conditions and 2 filters",
            $detail);
        $this->assertEmpty($errors);
        $this->assertEmpty($notices);

        // Get the report we just imported.
        $report = manager::get_report_from_id($reportid);

        // Columns.
        $columns = $report->get_active_columns();
        $this->assertCount(3, $columns);

        $columnidentifiers = array_map(function(\core_reportbuilder\local\report\column $column) {
            return $column->get_unique_identifier();
        }, $columns);
        $this->assertEqualsCanonicalizing(['user:firstname', 'user:lastname', 'user:country'], $columnidentifiers);

        // Conditions.
        $conditions = $report->get_active_conditions();
        $this->assertCount(1, $conditions);

        $conditionidentifiers = array_map(function(\core_reportbuilder\local\report\filter $condition) {
            return $condition->get_unique_identifier();
        }, $conditions);
        $this->assertEqualsCanonicalizing(['user:country'], $conditionidentifiers);

        // Filters.
        $filters = $report->get_active_filters();
        $this->assertCount(2, $filters);

        $filteridentifiers = array_map(function(\core_reportbuilder\local\report\filter $filter) {
            return $filter->get_unique_identifier();
        }, $filters);
        $this->assertEqualsCanonicalizing(['user:firstname', 'user:lastname'], $filteridentifiers);
    }

    /**
     * Test importing single report from an export file with mapped entities and the old audience types.
     *
     * @return void
     */
    public function test_import_single_report_mapped_entities() {
        // Sanity test.
        $this->assertEquals(0, report::count_records());

        // Create entities to match those referred to in the test fixture.
        $this->getDataGenerator()->create_user(['username' => 'paul']);
        $this->get_organisation_generator()->create_department(['name' => 'Sub-Garden']);
        $this->get_organisation_generator()->create_position(['name' => 'Franchise manager']);
        $this->get_organisation_generator()->create_position(['name' => 'Office manager']);

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_SELECTED,
            // Original ID of the "Course completion" report from the test fixture.
            importer::IMPORT_SELECT_REPORTS => [157],
            importer::IMPORT_AUDIENCES => 1,
            importer::IMPORT_SCHEDULES => 1,
        ]);

        $reports = report::get_records();
        $this->assertCount(1, $reports);

        $reportid = reset($reports)->get('id');

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertEmpty($conflicts);

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(5, $logs);

        $reporturl = (new \moodle_url('/reportbuilder/edit.php', ['id' => $reportid]))->out();
        $this->assertEquals([
            "Created new report '<a href=\"{$reporturl}\">Course completion</a>' with 4 columns, 1 conditions and 2 filters",
            'Audience record imported',
            'Audience record imported',
            'Audience record imported',
            'Schedule record imported'
        ], array_column($logs, 'detail'));

        // Audience records.
        $audiences = audience::get_records(['reportid' => $reportid]);
        $this->assertCount(3, $audiences);

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
            helper::get_importer_setting_name_for_conflict_form(report::TABLE, 'invalidsource', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form(report::TABLE, 'invalidtype', 'action') => 'skip',
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
        $this->assertEquals(0, report::count_records(['itemid' => $tenantid]));

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        $reports = report::get_records(['itemid' => $tenantid], 'name');
        $this->assertCount(2, $reports);

        $reportnames = array_map(function(report $report) {
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
        $this->assertEquals(0, report::count_records(['itemid' => $tenantid]));

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            'tenantid' => $tenantid,
        ]);

        $reports = report::get_records(['itemid' => $tenantid], 'name');
        $this->assertCount(2, $reports);

        $reportnames = array_map(function(report $report) {
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
        $this->assertEquals(0, report::count_records());

        // Set to import specific reports, but don't provide them.
        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_SELECTED,
            importer::IMPORT_SELECT_REPORTS => [],
        ]);

        // Nothing was imported.
        $this->assertEquals(0, report::count_records());

        $this->assertEmpty($this->get_workplace_generator()->get_import_conflict_review($importid));
    }

    /**
     * Test condition field mapping during export/import
     */
    public function test_export_import_field_mapping(): void {
        $course = $this->getDataGenerator()->create_course();

        $report = $this->get_tenant_generator()->create_report([
            'source' => \core_course\reportbuilder\datasource\participants::class,
            'name' => 'R1',
        ]);

        $this->get_core_reportbuilder_generator()->create_condition(
            ['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:courseselector']);
        $reportobj = \core_reportbuilder\manager::get_report_from_persistent($report);
        $reportobj->set_condition_values(['course:courseselector_values' => [$course->id]]);

        // Export our report.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_SELECTED,
            exporter::EXPORT_SELECT_REPORTS => [$report->get('id')],
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
        $reports = report::get_records([], 'id');
        $newreport = \core_reportbuilder\manager::get_report_from_persistent(end($reports));

        $conditionvalues = $newreport->get_condition_values();
        $this->assertEquals([$newcourse->id], reset($conditionvalues));
    }

    /**
     * Get report builder generator
     *
     * @return \core_reportbuilder_generator
     */
    protected function get_core_reportbuilder_generator(): \core_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
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

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

use advanced_testcase;
use core_reportbuilder\local\models\audience;
use core_reportbuilder\local\models\column;
use core_reportbuilder\local\models\filter;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\schedule;
use core_reportbuilder\reportbuilder\audience\manual;
use tool_tenant_generator;
use \tool_wp\tool_wp\exporter\reports as exporter;
use \tool_wp\tool_wp\importer\reports as importer;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\wp_imported_entity;
use tool_wp_generator;
use tool_wp\test\mock_report3 as mock_report_conditions_filters;

/**
 * Tests for custom reports export
 *
 * @package     tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\exporter\reports
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class reports_test extends advanced_testcase {

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
        $report1 = $this->get_tenant_generator()->create_report(['source' => mock_report_conditions_filters::class,
            'name' => 'Report 1']);
        $report2 = $this->get_tenant_generator()->create_report(['source' => mock_report_conditions_filters::class,
            'name' => 'Report 2']);

        // Create audience/schedule records for the first report.
        $audience = $this->get_core_reportbuilder_generator()->create_audience([
            'reportid' => $report1->get('id'),
            'classname' => manual::class,
            'configdata' => ['users' => [2]],
        ]);
        $schedule = $this->get_core_reportbuilder_generator()->create_schedule(['reportid' => $report1->get('id'), 'name' => 'S1']);

        // Create a new export containing only the first report.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_SELECTED,
            exporter::EXPORT_SELECT_REPORTS => [$report1->get('id')],
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
        $reports = $importer->get_entities_in_workplace_export_file(report::TABLE);
        $this->assertCount(1, $reports);

        /** @var wp_imported_entity $entity */
        $entity = iterator_to_array($reports, false)[0];
        $this->assertEquals($report1->get('name'), $entity->get_raw_field('name'));

        // Nested columns.
        $columns = $entity->get_nested_entities(column::TABLE);
        $this->assertCount(2, $columns);

        // Nested filters and conditions.
        $filters = $entity->get_nested_entities(filter::TABLE);
        $this->assertCount(4, $filters); // There is two filter and two conditions.

        // Audience entities.
        $audienceentities = $importer->get_entities_in_workplace_export_file(audience::TABLE);
        $this->assertCount(1, $audienceentities);
        $this->assertEquals($audience->get_persistent()->get('id'), $audienceentities->current()->get_original_id());

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
        $report1 = $this->get_tenant_generator()->create_report(['source' => mock_report_conditions_filters::class,
            'name' => 'Report 1']);
        $report2 = $this->get_tenant_generator()->create_report(['source' => mock_report_conditions_filters::class,
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
        $reports = $importer->get_entities_in_workplace_export_file(report::TABLE, null, function(array $entity) {
            return $entity['name'];
        });
        $this->assertCount(2, $reports);

        $reportnames = array_map(function(wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($reports, false));

        $this->assertEquals([$report1->get('name'), $report2->get('name')], $reportnames);
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
        $this->assertEmpty($importer->get_entities_in_workplace_export_file(report::TABLE));
        $this->assertEmpty($importer->get_entities_in_workplace_export_file(audience::TABLE));
        $this->assertEmpty($importer->get_entities_in_workplace_export_file(schedule::TABLE));
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

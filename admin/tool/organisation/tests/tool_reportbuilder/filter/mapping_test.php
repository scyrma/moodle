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
 * File containing tests for report condition class field mapping during export/import
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_reportbuilder\filter;

use tool_reportbuilder\manager;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\tool_wp\exporter\customreports as exporter;
use tool_reportbuilder\tool_wp\importer\customreports as importer;

/**
 * Test class
 *
 * TODO WP-3102 this test has to be split into tests for individual classes
 *
 * @package     tool_organisation
 * @group       tool_organisation
 * @category    test
 * @covers      \tool_organisation\tool_reportbuilder\filter\department_select
 * @covers      \tool_organisation\tool_reportbuilder\filter\job_department
 * @covers      \tool_organisation\tool_reportbuilder\filter\position_select
 * @covers      \tool_organisation\tool_reportbuilder\filter\job_position
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class mapping_test extends \advanced_testcase {

    /**
     * Data provider to return department conditions for this plugin (used by user report list)
     *
     * @return string[]
     */
    public function department_condition_provider(): array {
        return [
            ['tool_organisation_jobs:department'],
            ['user:jobdepartment'],
        ];
    }

    /**
     * Test that report department condition instance adds field mappings during export/import
     *
     * @param string $conditionkey
     *
     * @dataProvider department_condition_provider
     */
    public function test_report_department_condition_mapping(string $conditionkey): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $department = $this->get_plugin_generator()->create_department(['name' => 'My department']);

        $report = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
        ]);

        $this->get_report_generator()->add_condition($report, $conditionkey);
        (new \tool_reportbuilder\local\helpers\conditions($report))
            ->merge_condition_values([$conditionkey => $department->id]);

        // Export our report.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_SELECTED,
            exporter::EXPORT_SELECT_REPORTS => [$report->get_id()],
        ]);

        // Now delete the original department, and create a new one with the same name.
        $originalid = $department->id;
        $originalname = $department->name;

        (new \tool_organisation\department_manager)->delete_department($originalid);

        $newdepartment = $this->get_plugin_generator()->create_department(['name' => $originalname]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('tool_organisation_department', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported condition field should be mapped to the new course.
        $reports = reportbuilder::get_records([], 'id');
        $newreport = manager::get_report(end($reports)->get('id'));

        $conditionvalues = (new \tool_reportbuilder\local\helpers\conditions($newreport))
            ->get_condition_values($conditionkey);
        $this->assertEquals($newdepartment->id, reset($conditionvalues));
    }

    /**
     * Data provider to return position conditions for this plugin (used by user report list)
     *
     * @return string[]
     */
    public function position_condition_provider(): array {
        return [
            ['tool_organisation_jobs:position'],
            ['user:jobposition'],
        ];
    }

    /**
     * Test that report position condition instance adds field mappings during export/import
     *
     * @param string $conditionkey
     *
     * @dataProvider position_condition_provider
     */
    public function test_report_position_condition_mapping(string $conditionkey): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $position = $this->get_plugin_generator()->create_position(['name' => 'My position']);

        $report = $this->get_report_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
        ]);

        $this->get_report_generator()->add_condition($report, $conditionkey);
        (new \tool_reportbuilder\local\helpers\conditions($report))
            ->merge_condition_values([$conditionkey => $position->id]);

        // Export our report.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_SELECTED,
            exporter::EXPORT_SELECT_REPORTS => [$report->get_id()],
        ]);

        // Now delete the original position, and create a new one with the same name.
        $originalid = $position->id;
        $originalname = $position->name;

        (new \tool_organisation\position_manager)->delete_position($originalid);

        $newposition = $this->get_plugin_generator()->create_position(['name' => $originalname]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('tool_organisation_position', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported condition field should be mapped to the new course.
        $reports = reportbuilder::get_records([], 'id');
        $newreport = manager::get_report(end($reports)->get('id'));

        $conditionvalues = (new \tool_reportbuilder\local\helpers\conditions($newreport))
            ->get_condition_values($conditionkey);
        $this->assertEquals($newposition->id, reset($conditionvalues));
    }

    /**
     * Return plugin generator
     *
     * @return \tool_organisation_generator
     */
    protected function get_plugin_generator(): \tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Return report builder generator
     *
     * @return \tool_reportbuilder_generator
     */
    protected function get_report_generator(): \tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Return Workplace generator
     *
     * @return \tool_wp_generator
     */
    protected function get_workplace_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }
}

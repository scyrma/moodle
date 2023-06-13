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

namespace tool_reportbuilder\task;

use advanced_testcase;
use tool_reportbuilder_generator;

/**
 * Test convert_all_possible_reports task
 *
 * @package   tool_reportbuilder
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @covers    \tool_reportbuilder\task\convert_all_possible_reports
 * @author    2022 Roberto Bravo <roberto.bravo@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class convert_all_possible_reports_test extends advanced_testcase {

    /**
     * Test convert_all_reports
     *
     * @return void
     */
    public function test_convert_all_reports(): void {
        global $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();

        /** @var tool_reportbuilder_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('tool_reportbuilder');

        // Create report with a column that can be converted.
        $report = $generator->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion::class,
            'adddefault' => 0,
            'name' => 'convertible',
        ]);
        $reportid = $report->get_id();
        $report = \tool_reportbuilder\manager::get_report($reportid);
        // Add a column that can be converted.
        $generator->add_column($report, 'course:category');

        // Create report with a column that can not be converted.
        $report = $generator->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_course_enrolments::class,
            'adddefault' => 0,
            'name' => 'non-convertible',
        ]);
        $reportid = $report->get_id();
        $report = \tool_reportbuilder\manager::get_report($reportid);
        // Add a column that can not be converted.
        $generator->add_column($report, 'course_completion:requiredgrade');

        (new convert_all_possible_reports())->execute();

        // Get all custom reports, it must be only one report that could not be converted.
        $oldreports = $DB->get_records('tool_reportbuilder', ['type' => \tool_reportbuilder\constants::TYPE_DATASOURCE]);
        $this->assertCount(1, $oldreports);
        $this->assertEquals('non-convertible', reset($oldreports)->name);

        $newreports = $DB->get_records('reportbuilder_report',
            ['type' => \core_reportbuilder\local\report\base::TYPE_CUSTOM_REPORT]);
        $this->assertCount(1, $newreports);
        $this->assertEquals('convertible', reset($newreports)->name);
    }

    /**
     * Test convert_all_reports checking permission and limits
     *
     * @return void
     */
    public function test_convert_all_reports_checking_permission(): void {
        global $DB, $CFG;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();

        /** @var tool_reportbuilder_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('tool_reportbuilder');

        // Create report 1 with a column that can be converted.
        $report = $generator->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion::class,
            'adddefault' => 0,
            'name' => 'convertible 1',
        ]);
        $reportid = $report->get_id();
        $report = \tool_reportbuilder\manager::get_report($reportid);
        // Add a column that can be converted.
        $generator->add_column($report, 'course:category');

        // Create report 2 with a column that can be converted.
        $report = $generator->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion::class,
            'adddefault' => 0,
            'name' => 'convertible 2',
        ]);
        $reportid = $report->get_id();
        $report = \tool_reportbuilder\manager::get_report($reportid);
        // Add a column that can be converted.
        $generator->add_column($report, 'course:fullname');

        // Create report with a column that can not be converted.
        $report = $generator->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_course_enrolments::class,
            'adddefault' => 0,
            'name' => 'non-convertible',
        ]);
        $reportid = $report->get_id();
        $report = \tool_reportbuilder\manager::get_report($reportid);
        // Add a column that can not be converted.
        $generator->add_column($report, 'course_completion:requiredgrade');

        // Disable create custom reports.
        $CFG->enablecustomreports = false;

        (new convert_all_possible_reports())->execute();

        // Nothing happen, all old reports is keeping and no new are converted.
        $oldreports = $DB->get_records('tool_reportbuilder', ['type' => \tool_reportbuilder\constants::TYPE_DATASOURCE]);
        $newreports = $DB->get_records('reportbuilder_report',
            ['type' => \core_reportbuilder\local\report\base::TYPE_CUSTOM_REPORT]);

        $this->assertCount(3, $oldreports);
        $this->assertCount(0, $newreports);

        // Enable create custom reports but set the site limit report = 1.
        $CFG->enablecustomreports = true;
        $CFG->customreportslimit = 1;

        (new convert_all_possible_reports())->execute();

        // Only one report was converted.
        $oldreports = $DB->get_records('tool_reportbuilder', ['type' => \tool_reportbuilder\constants::TYPE_DATASOURCE]);
        $newreports = $DB->get_records('reportbuilder_report',
            ['type' => \core_reportbuilder\local\report\base::TYPE_CUSTOM_REPORT]);

        $this->assertCount(2, $oldreports);
        $this->assertCount(1, $newreports);

        // Enable create custom reports but set the tenant limit report = 1.
        $CFG->customreportslimit = 0;
        $CFG->tool_tenant_customreportslimit = 1;

        (new convert_all_possible_reports())->execute();

        // Nothing happen, only one report was converted, rest keep as old report.
        $oldreports = $DB->get_records('tool_reportbuilder', ['type' => \tool_reportbuilder\constants::TYPE_DATASOURCE]);
        $newreports = $DB->get_records('reportbuilder_report',
            ['type' => \core_reportbuilder\local\report\base::TYPE_CUSTOM_REPORT]);

        $this->assertCount(2, $oldreports);
        $this->assertCount(1, $newreports);

        // Set customreportslimit = 0 (not limit) and tool_tenant_customreportslimit = 3.
        $CFG->customreportslimit = 0;
        $CFG->tool_tenant_customreportslimit = 3;

        (new convert_all_possible_reports())->execute();

        // Latest report was converted, only non-convertible keep as an old report.
        $oldreports = $DB->get_records('tool_reportbuilder', ['type' => \tool_reportbuilder\constants::TYPE_DATASOURCE]);
        $newreports = $DB->get_records('reportbuilder_report',
            ['type' => \core_reportbuilder\local\report\base::TYPE_CUSTOM_REPORT]);

        $this->assertCount(1, $oldreports);
        $this->assertCount(2, $newreports);
    }
}

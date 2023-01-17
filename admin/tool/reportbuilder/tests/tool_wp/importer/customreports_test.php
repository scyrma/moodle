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

declare(strict_types=1);

namespace tool_reportbuilder\tool_wp\importer;

use advanced_testcase;
use context_system;
use core_reportbuilder\local\models\report;
use moodle_url;
use tool_reportbuilder_generator;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\tool_wp\importer\customreports as importer;
use tool_wp_generator;

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\tool_wp\importer\customreports
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class customreports_test extends advanced_testcase {

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // This is needed because we have a failing conversion on the fixture.
        $this->preventResetByRollback();
    }

    /**
     * Test importing both reports from the export file and try to convert them to core reportbuilder
     */
    public function test_import_reports(): void {
        $importfixture = __DIR__ . '/../../fixtures/custom-reports-export-conversion.zip';

        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records());
        $this->assertEquals(0, report::count_records());

        $importid = $this->get_workplace_generator()->perform_import_from_file($importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        $toolrbreports = reportbuilder::get_records();
        $corerbreports = report::get_records();

        // Import has created 2 tool reportbuilder reports but deleted one of them (the one that has been converted).
        $this->assertCount(1, $toolrbreports);
        $toolrbreport = reset($toolrbreports);
        $this->assertEquals('My Course enrolments (fails conversion)', $toolrbreport->get('name'));

        // Check that only one report has been converted to core report builder.
        $this->assertCount(1, $corerbreports);
        $corerbreport = reset($corerbreports);
        $this->assertEquals('My users list report', $corerbreport->get('name'));

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(4, $logs);

        $this->assertStringStartsWith('Created new report', $logs[0]['detail']);

        $str = get_string('importlogconversionsuccess', 'tool_reportbuilder', [
            'name' => 'My users list report',
        ]);

        $this->assertEquals($str, $logs[1]['detail']);

        $this->assertStringStartsWith('Created new report', $logs[2]['detail']);

        $str = get_string('importlogconversionfailure', 'tool_reportbuilder', [
            'name' => 'My Course enrolments (fails conversion)',
            'message' => 'Column \'course_completion:requiredgrade\' can not be converted',
            'url' => (new moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $toolrbreport->get('id')]))->out(),
        ]);
        $this->assertEquals($str, $logs[3]['detail']);
    }

    /**
     * Test importing some reports from the export file and try to convert them to core reportbuilder checking permission
     */
    public function test_import_reports_checking_permission(): void {
        global $DB;
        $importfixture = __DIR__ . '/../../fixtures/custom-reports-export-conversion.zip';

        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records());
        $this->assertEquals(0, report::count_records());

        // Create user who can add reports in tool_reportbuilder but cannot add reports in core_reportbuilder.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder')->assign_edit_capability((int)$user->id);
        $this->setUser($user);
        $this->get_workplace_generator()->perform_import_from_file($importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // None report was converted.
        $this->assertCount(2, reportbuilder::get_records());
        $this->assertCount(0, report::get_records());

        // Set as admin user and empty tool_reportbuilder/core_rb tables.
        $this->setAdminUser();
        $DB->delete_records(reportbuilder::TABLE, []);
        $DB->delete_records(report::TABLE, []);
        $this->get_workplace_generator()->perform_import_from_file($importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Only the valid report was converted.
        $this->assertCount(1, reportbuilder::get_records());
        $this->assertCount(1, report::get_records());
    }

    /**
     * Test importing both reports from the export file and try to convert them to core reportbuilder using a tenants export
     */
    public function test_import_tenant_reports(): void {
        $importfixture = __DIR__ . '/../../fixtures/tenants-export-convert.zip';

        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records());
        $this->assertEquals(0, report::count_records());

        $importid = $this->get_workplace_generator()->perform_import_from_file($importfixture, [
            \tool_tenant\tool_wp\importer\tenants::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            \tool_tenant\tool_wp\importer\tenants::IMPORT_DESTINATION =>
                \tool_tenant\tool_wp\importer\tenants::IMPORT_DESTINATION_NEW,
            \tool_tenant\tool_wp\importer\tenants::IMPORT_REPORTS => 1,
        ]);

        $toolrbreports = reportbuilder::get_records();
        $corerbreports = report::get_records();

        // Import has created 2 tool reportbuilder reports but deleted one of them (the one that has been converted).
        $this->assertCount(1, $toolrbreports);
        $toolrbreport = reset($toolrbreports);
        $this->assertEquals('My Course enrolments (fails conversion)', $toolrbreport->get('name'));

        // Check that only one report has been converted to core report builder.
        $this->assertCount(1, $corerbreports);
        $corerbreport = reset($corerbreports);
        $this->assertEquals('My users list report', $corerbreport->get('name'));

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(5, $logs);

        $this->assertStringStartsWith('Imported tenant', $logs[0]['detail']);

        $this->assertStringStartsWith('Created new report', $logs[1]['detail']);

        $str = get_string('importlogconversionsuccess', 'tool_reportbuilder', [
            'name' => 'My users list report',
        ]);
        $this->assertEquals($str, $logs[2]['detail']);

        $this->assertStringStartsWith('Created new report', $logs[3]['detail']);

        $str = get_string('importlogconversionfailure', 'tool_reportbuilder', [
            'name' => 'My Course enrolments (fails conversion)',
            'message' => 'Column \'course_completion:requiredgrade\' can not be converted',
            'url' => (new moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $toolrbreport->get('id')]))->out(),
        ]);
        $this->assertEquals($str, $logs[4]['detail']);
    }

    /**
     * Test importing some reports from the export file and try to convert them to core reportbuilder using a tenants export
     * and checking permission
     */
    public function test_import_tenant_reports_checking_permission(): void {
        global $CFG, $DB;
        $importfixturepermission = __DIR__ . '/../../fixtures/tenants-export-convert_permission.zip';

        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records());
        $this->assertEquals(0, report::count_records());

        // Set tenant custom report limit = 1.
        $CFG->tool_tenant_customreportslimit = 1;
        $this->get_workplace_generator()->perform_import_from_file($importfixturepermission, [
            \tool_tenant\tool_wp\importer\tenants::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            \tool_tenant\tool_wp\importer\tenants::IMPORT_DESTINATION =>
                \tool_tenant\tool_wp\importer\tenants::IMPORT_DESTINATION_NEW,
            \tool_tenant\tool_wp\importer\tenants::IMPORT_REPORTS => 1,
        ]);

        // Only one report was converted.
        $this->assertCount(2, reportbuilder::get_records());
        $this->assertCount(1, report::get_records());

        // Set tenant custom report limit = 2, empty tool_reportbuilder/core_rb tables.
        $CFG->tool_tenant_customreportslimit = 2;
        $DB->delete_records(reportbuilder::TABLE, []);
        $DB->delete_records(report::TABLE, []);

        $this->get_workplace_generator()->perform_import_from_file($importfixturepermission, [
            \tool_tenant\tool_wp\importer\tenants::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            \tool_tenant\tool_wp\importer\tenants::IMPORT_DESTINATION =>
                \tool_tenant\tool_wp\importer\tenants::IMPORT_DESTINATION_NEW,
            \tool_tenant\tool_wp\importer\tenants::IMPORT_REPORTS => 1,
        ]);

        // After increment tenant report limit, two reports was converted.
        $this->assertCount(1, reportbuilder::get_records());
        $this->assertCount(2, report::get_records());
    }

    /**
     * Test importing both reports from the export file and try to convert them to core reportbuilder using a site export
     */
    public function test_import_site_reports(): void {
        $importfixture = __DIR__ . '/../../fixtures/site-export-convert.zip';

        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records());
        $this->assertEquals(0, report::count_records());

        $importid = $this->get_workplace_generator()->perform_import_from_file($importfixture, [
            \tool_wp\tool_wp\importer\site::IMPORT_REPORTS => 1,
            \tool_wp\tool_wp\importer\site::IMPORT_CERTIFICATES => 0,
            \tool_wp\tool_wp\importer\site::IMPORT_ORGANISATION => 0,
            \tool_wp\tool_wp\importer\site::IMPORT_RULES => 0,
            \tool_wp\tool_wp\importer\site::IMPORT_COHORTS => 0,
        ]);

        $toolrbreports = reportbuilder::get_records();
        $corerbreports = report::get_records();

        // Import has created 2 tool reportbuilder reports but deleted one of them (the one that has been converted).
        $this->assertCount(1, $toolrbreports);
        $toolrbreport = reset($toolrbreports);
        $this->assertEquals('My Course enrolments (fails conversion)', $toolrbreport->get('name'));

        // Check that only one report has been converted to core report builder.
        $this->assertCount(1, $corerbreports);
        $corerbreport = reset($corerbreports);
        $this->assertEquals('My users list report', $corerbreport->get('name'));

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(6, $logs);

        $this->assertEquals('Imported site', $logs[0]['detail']);

        $this->assertStringStartsWith('Imported tenant', $logs[1]['detail']);

        $this->assertStringStartsWith('Created new report', $logs[2]['detail']);

        $str = get_string('importlogconversionsuccess', 'tool_reportbuilder', [
            'name' => 'My users list report',
        ]);
        $this->assertEquals($str, $logs[3]['detail']);

        $this->assertStringStartsWith('Created new report', $logs[4]['detail']);

        $str = get_string('importlogconversionfailure', 'tool_reportbuilder', [
            'name' => 'My Course enrolments (fails conversion)',
            'message' => 'Column \'course_completion:requiredgrade\' can not be converted',
            'url' => (new moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $toolrbreport->get('id')]))->out(),
        ]);
        $this->assertEquals($str, $logs[5]['detail']);
    }

    /**
     * Test importing some reports from the export file and try to convert them to core reportbuilder using a site export
     * and checking permission
     */
    public function test_import_site_reports_checking_permission(): void {
        global $DB, $CFG;
        $importfixturepermission = __DIR__ . '/../../fixtures/site-export-convert_permission.zip';

        // Sanity test.
        $this->assertEquals(0, reportbuilder::count_records());
        $this->assertEquals(0, report::count_records());

        // Set site custom report limit = 1.
        $CFG->customreportslimit = 1;
        $this->get_workplace_generator()->perform_import_from_file($importfixturepermission, [
            \tool_wp\tool_wp\importer\site::IMPORT_REPORTS => 1,
            \tool_wp\tool_wp\importer\site::IMPORT_CERTIFICATES => 0,
            \tool_wp\tool_wp\importer\site::IMPORT_ORGANISATION => 0,
            \tool_wp\tool_wp\importer\site::IMPORT_RULES => 0,
            \tool_wp\tool_wp\importer\site::IMPORT_COHORTS => 0,
        ]);

        // Only one report was converted.
        $this->assertCount(2, reportbuilder::get_records());
        $this->assertCount(1, report::get_records());

        // Set site custom report limit = 2, empty tool_reportbuilder/core_rb tables.
        $CFG->customreportslimit = 2;
        $DB->delete_records(reportbuilder::TABLE, []);
        $DB->delete_records(report::TABLE, []);

        $this->get_workplace_generator()->perform_import_from_file($importfixturepermission, [
            \tool_wp\tool_wp\importer\site::IMPORT_REPORTS => 1,
            \tool_wp\tool_wp\importer\site::IMPORT_CERTIFICATES => 0,
            \tool_wp\tool_wp\importer\site::IMPORT_ORGANISATION => 0,
            \tool_wp\tool_wp\importer\site::IMPORT_RULES => 0,
            \tool_wp\tool_wp\importer\site::IMPORT_COHORTS => 0,
        ]);

        // After increment site report limit, two reports was converted.
        $this->assertCount(1, reportbuilder::get_records());
        $this->assertCount(2, report::get_records());
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
     * Get Workplace generator
     *
     * @return tool_wp_generator
     */
    protected function get_workplace_generator(): tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }
}

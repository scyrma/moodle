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
 * Download report tests.
 *
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use advanced_testcase;
use coding_exception;
use context_system;
use core_text;
use html_writer;
use moodle_exception;
use testable_report_exporter;
use tool_reportbuilder_generator;
use tool_tenant_generator;
use tool_reportbuilder\test\mock_system_report;

/**
 * Class tool_reportbuilder_download_testcase
 *
 * @package    tool_reportbuilder
 * @covers     \tool_reportbuilder\output\report_exporter
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class download_test extends advanced_testcase {

    /**
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/reportbuilder/tests/fixtures/testable_report_exporter.php");
    }

    /**
     * Test download the report in cvs format
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_download_cvs() {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the report_users_list datasource with default columns/conditions.
        $report = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ]);
        $reportid = $report->get_id();

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 1']);
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->get_reportbuilder_generator()->assign_edit_capability($user1->id);
        $this->setUser($user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('csv', true);
        $actual = array_filter(explode("\n", $content));
        $expected = ['"Full name","Email address",City/town,Country', '"User Lastname 1",username1@example.com,,'];
        $this->assertEquals($expected, preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $actual));

        // Test with users from other tenant.
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 2']);
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $exporter = new testable_report_exporter($reportid);
        $content = $exporter->download('csv', true);
        $actual = array_filter(explode("\n", $content));
        $expected = ['"Full name","Email address",City/town,Country', '"User Lastname 1",username1@example.com,,'];
        $this->assertEquals($expected, preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $actual));

        // Test export with a filter.
        for ($i = 2; $i < 5; $i++) {
            $user = $this->getDataGenerator()->create_user([
                    'firstname' => 'User',
                    'lastname' => 'Lastname ' . $i,
                    'country' => 'FR']
            );
            $this->get_tenant_generator()->allocate_user($user->id, $tenant1->id);
        }

        $this->get_reportbuilder_generator()->add_filter($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{"user:country_op":"1","user:country":"FR"}', $user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('csv', true);
        $actual = array_filter(explode("\n", $content));
        $expected = ['"Full name","Email address",City/town,Country',
            '"User Lastname 2",username3@example.com,,France',
            '"User Lastname 3",username4@example.com,,France',
            '"User Lastname 4",username5@example.com,,France'];
        $this->assertEquals($expected, preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $actual));

        // Test export with a condition.
        $this->get_reportbuilder_generator()->add_condition($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{}', $user1->id);
        $DB->set_field('tool_reportbuilder', 'conditions', '{"user:country_op":"1","user:country":"FR"}', ['id' => $reportid]);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('csv', true);
        $actual = array_filter(explode("\n", $content));
        $this->assertEquals($expected, preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $actual));
    }

    /**
     * Test download the report in xlsx format
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_download_xlsx() {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the report_users_list datasource with default columns/conditions.
        $report = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ]);
        $reportid = $report->get_id();

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 1']);
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->get_reportbuilder_generator()->assign_edit_capability($user1->id);
        $this->setUser($user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('excel', true);

        $excelcells = $this->get_excel($content);
        $this->assertEquals('Full name', $excelcells[0][0]);
        $this->assertEquals('User Lastname 1', $excelcells[1][0]);

        // Test with users from other tenant.
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 2']);
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $exporter = new testable_report_exporter($reportid);
        $content = $exporter->download('excel', true);
        $excelcells = $this->get_excel($content);
        $this->assertEquals('Full name', $excelcells[0][0]);
        $this->assertEquals('User Lastname 1', $excelcells[1][0]);

        // Test export with a filter.
        for ($i = 2; $i < 5; $i++) {
            $user = $this->getDataGenerator()->create_user([
                    'firstname' => 'User',
                    'lastname' => 'Lastname ' . $i,
                    'country' => 'FR']
            );
            $this->get_tenant_generator()->allocate_user($user->id, $tenant1->id);
        }

        $this->get_reportbuilder_generator()->add_filter($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{"user:country_op":"1","user:country":"FR"}', $user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('excel', true);
        $excelcells = $this->get_excel($content);
        $this->assertEquals('Full name', $excelcells[0][0]);
        $this->assertEquals('User Lastname 2', $excelcells[1][0]);
        $this->assertEquals('User Lastname 3', $excelcells[2][0]);
        $this->assertEquals('User Lastname 4', $excelcells[3][0]);

        // Test export with a condition.
        $this->get_reportbuilder_generator()->add_condition($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{}', $user1->id);
        $DB->set_field('tool_reportbuilder', 'conditions', '{"user:country_op":"1","user:country":"FR"}', ['id' => $reportid]);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('excel', true);
        $excelcells = $this->get_excel($content);
        $this->assertEquals('Full name', $excelcells[0][0]);
        $this->assertEquals('User Lastname 2', $excelcells[1][0]);
        $this->assertEquals('User Lastname 3', $excelcells[2][0]);
        $this->assertEquals('User Lastname 4', $excelcells[3][0]);
    }

    /**
     * Test download the report in json format
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_download_json() {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the report_users_list datasource with default columns/conditions.
        $report = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ]);
        $reportid = $report->get_id();

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 1']);
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->get_reportbuilder_generator()->assign_edit_capability($user1->id);
        $this->setUser($user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $content = json_decode($exporter->download('json'), true);
        $this->assertEquals([[['fullname' => 'User Lastname 1',
            'emailaddress' => 'username1@example.com',
            'citytown' => '', 'country' => '']]], $content);

        // Test with users from other tenant.
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 2']);
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $exporter = new testable_report_exporter($reportid);
        $content = json_decode($exporter->download('json'), true);
        $this->assertEquals([[['fullname' => 'User Lastname 1', 'emailaddress' => 'username1@example.com',
            'citytown' => '', 'country' => '']]], $content);

        // Test export with a filter.
        for ($i = 2; $i < 5; $i++) {
            $user = $this->getDataGenerator()->create_user([
                'firstname' => 'User',
                'lastname' => 'Lastname ' . $i,
                'country' => 'FR']
            );
            $this->get_tenant_generator()->allocate_user($user->id, $tenant1->id);
        }

        $this->get_reportbuilder_generator()->add_filter($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{"user:country_op":"1","user:country":"FR"}', $user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $content = json_decode($exporter->download('json'), true);
        $expected = [[
            ['fullname' => 'User Lastname 2', 'emailaddress' => 'username3@example.com', 'citytown' => '', 'country' => 'France'],
            ['fullname' => 'User Lastname 3', 'emailaddress' => 'username4@example.com', 'citytown' => '', 'country' => 'France'],
            ['fullname' => 'User Lastname 4', 'emailaddress' => 'username5@example.com', 'citytown' => '', 'country' => 'France'],
        ]];
        $this->assertEquals($expected, $content);

        // Test export with a condition.
        $this->get_reportbuilder_generator()->add_condition($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{}', $user1->id);
        $DB->set_field('tool_reportbuilder', 'conditions', '{"user:country_op":"1","user:country":"FR"}', ['id' => $reportid]);

        $exporter = new testable_report_exporter($reportid, false);
        $content = json_decode($exporter->download('json'), true);
        $expected = [[
            ['fullname' => 'User Lastname 2', 'emailaddress' => 'username3@example.com', 'citytown' => '', 'country' => 'France'],
            ['fullname' => 'User Lastname 3', 'emailaddress' => 'username4@example.com', 'citytown' => '', 'country' => 'France'],
            ['fullname' => 'User Lastname 4', 'emailaddress' => 'username5@example.com', 'citytown' => '', 'country' => 'France'],
        ]];
        $this->assertEquals($expected, $content);
    }

    /**
     * Test download the report in html format
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_download_html() {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the report_users_list datasource with default columns/conditions.
        $report = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ]);
        $reportid = $report->get_id();

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 1']);
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->get_reportbuilder_generator()->assign_edit_capability($user1->id);
        $this->setUser($user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('html', true);
        $expectedrows = [
            ['Full name', 'Email address', 'City/town', 'Country'],
            ['User Lastname 1', 'username1@example.com', '', '']
        ];
        $this->assertEquals($this->get_html_table($expectedrows), $content);

        // Test with users from other tenant.
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 2']);
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $exporter = new testable_report_exporter($reportid);
        $content = $exporter->download('html', true);
        $expectedrows = [
            ['Full name', 'Email address', 'City/town', 'Country'],
            ['User Lastname 1', 'username1@example.com', '', '']
        ];
        $this->assertEquals($this->get_html_table($expectedrows), $content);

        // Test export with a filter.
        for ($i = 2; $i < 5; $i++) {
            $user = $this->getDataGenerator()->create_user([
                    'firstname' => 'User',
                    'lastname' => 'Lastname ' . $i,
                    'country' => 'FR']
            );
            $this->get_tenant_generator()->allocate_user($user->id, $tenant1->id);
        }

        $this->get_reportbuilder_generator()->add_filter($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{"user:country_op":"1","user:country":"FR"}', $user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('html', true);
        $expectedrows = [['Full name', 'Email address', 'City/town', 'Country'],
            ['User Lastname 2', 'username3@example.com', '', 'France'],
            ['User Lastname 3', 'username4@example.com', '', 'France'],
            ['User Lastname 4', 'username5@example.com', '', 'France']];
        $this->assertEquals($this->get_html_table($expectedrows), $content);

        // Test export with a condition.
        $this->get_reportbuilder_generator()->add_condition($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{}', $user1->id);
        $DB->set_field('tool_reportbuilder', 'conditions', '{"user:country_op":"1","user:country":"FR"}', ['id' => $reportid]);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('html', true);
        $this->assertEquals($this->get_html_table($expectedrows), $content);
    }

    /**
     * Test download the report in ods format
     *
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_download_ods() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the report_users_list datasource with default columns/conditions.
        $report = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ]);
        $reportid = $report->get_id();

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 1']);
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->get_reportbuilder_generator()->assign_edit_capability($user1->id);
        $this->setUser($user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('ods', true);

        $expectedrows = ['Full name', 'User Lastname 1'];
        $this->assertEquals($expectedrows, $this->get_ods_rows_content($content));

        // Test with users from other tenant.
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 2']);
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $exporter = new testable_report_exporter($reportid);
        $content = $exporter->download('ods', true);
        $expectedrows = ['Full name', 'User Lastname 1'];
        $this->assertEquals($expectedrows, $this->get_ods_rows_content($content));

        // Test export with a filter.
        for ($i = 2; $i < 5; $i++) {
            $user = $this->getDataGenerator()->create_user([
                    'firstname' => 'User',
                    'lastname' => 'Lastname ' . $i,
                    'country' => 'FR']
            );
            $this->get_tenant_generator()->allocate_user($user->id, $tenant1->id);
        }

        $this->get_reportbuilder_generator()->add_filter($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{"user:country_op":"1","user:country":"FR"}', $user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('ods', true);
        $expectedrows = ['Full name', 'User Lastname 2', 'User Lastname 3', 'User Lastname 4'];
        $this->assertEquals($expectedrows, $this->get_ods_rows_content($content));

        // Test export with a condition.
        $this->get_reportbuilder_generator()->add_condition($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{}', $user1->id);
        $DB->set_field('tool_reportbuilder', 'conditions', '{"user:country_op":"1","user:country":"FR"}', ['id' => $reportid]);

        $exporter = new testable_report_exporter($reportid, false);
        $content = $exporter->download('ods', true);
        $expectedrows = ['Full name', 'User Lastname 2', 'User Lastname 3', 'User Lastname 4'];
        $this->assertEquals($expectedrows, $this->get_ods_rows_content($content));
    }

    /**
     * Test download the report in pdf format
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_download_pdf() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the report_users_list datasource with default columns/conditions.
        $report = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ]);
        $reportid = $report->get_id();

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 1']);
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->get_reportbuilder_generator()->assign_edit_capability($user1->id);
        $this->setUser($user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $filecontents = $exporter->download('pdf', true);

        // No, this isn't testing the filesize, it's testing the length of the string.
        $filesize = core_text::strlen($filecontents);
        $this->assertGreaterThan(30000, $filesize);

        // Test with users from other tenant.
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 2']);
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $exporter = new testable_report_exporter($reportid);
        $filecontents = $exporter->download('pdf', true);
        $filesize = core_text::strlen($filecontents);
        $this->assertGreaterThan(30000, $filesize);

        // Test export with a filter.
        for ($i = 2; $i < 5; $i++) {
            $user = $this->getDataGenerator()->create_user([
                    'firstname' => 'User',
                    'lastname' => 'Lastname ' . $i,
                    'country' => 'FR']
            );
            $this->get_tenant_generator()->allocate_user($user->id, $tenant1->id);
        }

        $this->get_reportbuilder_generator()->add_filter($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{"user:country_op":"1","user:country":"FR"}', $user1->id);

        $exporter = new testable_report_exporter($reportid, false);
        $filecontents = $exporter->download('pdf', true);
        $filesize = core_text::strlen($filecontents);
        $this->assertGreaterThan(30000, $filesize);

        // Test export with a condition.
        $this->get_reportbuilder_generator()->add_condition($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{}', $user1->id);
        $DB->set_field('tool_reportbuilder', 'conditions', '{"user:country_op":"1","user:country":"FR"}', ['id' => $reportid]);

        $exporter = new testable_report_exporter($reportid, false);
        $filecontents = $exporter->download('pdf', true);
        $filesize = core_text::strlen($filecontents);
        $this->assertGreaterThan(30000, $filesize);
    }

    /**
     * Creates tenant and assigns user.
     * @return void
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function test_can_download() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $this->setUser($user1);

        // Can't download without report 'can_view' permissions.
        $report1 = \tool_reportbuilder\system_report_factory::create(mock_system_report::class);
        $this->assertFalse($report1->can_download());

        // Assign necessary permissions and check again.
        assign_capability('tool/reportbuilder:read', CAP_ALLOW, $roleid, context_system::instance()->id);
        role_assign($roleid, $user1->id, context_system::instance()->id);
        $this->assertTrue($report1->can_download());

        // Can't download if report is not downloadable.
        $report1->set_downloadable(false);
        $this->assertFalse($report1->can_download());

        $report1->set_downloadable(true);

        // We need to create a system report in a different tenant.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $user2 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant1->id]);
        // System reports are created in the current user tenant, so we need to switch to user2.
        $this->setUser($user2);
        $report2 = \tool_reportbuilder\system_report_factory::create(mock_system_report::class);
        // Switch again to user1.
        $this->setUser($user1);
        // User1 (default tenant) can't download report2 (tenant1) because they are not in the same tenant.
        $this->assertFalse($report2->can_download());
    }

    /**
     * Get an Excel object to check the content
     *
     * @param string $content
     * @return array two-dimensional array with cell values
     */
    private function get_excel(string $content) {
        $file = tempnam(sys_get_temp_dir(), 'excel_');
        $handle = fopen($file, "w");
        fwrite($handle, $content);
        /** @var \Box\Spout\Reader\XLSX\Reader $reader */
        $reader = \Box\Spout\Reader\Common\Creator\ReaderFactory::createFromType(\Box\Spout\Common\Type::XLSX);
        $reader->open($file);

        /** @var \Box\Spout\Reader\XLSX\Sheet[] $sheets */
        $sheets = $reader->getSheetIterator();
        $rowscellsvalues = [];
        foreach ($sheets as $sheet) {
            /** @var \Box\Spout\Common\Entity\Row[] $rows */
            $rows = $sheet->getRowIterator();
            foreach ($rows as $row) {
                $thisvalues = [];
                foreach ($row->getCells() as $cell) {
                    $thisvalues[] = $cell->getValue();
                }
                $rowscellsvalues[] = $thisvalues;
            }
        }

        return $rowscellsvalues;
    }

    /**
     * Get ods rows from binary content
     * @param string $content
     * @return array
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
     */
    private function get_ods_rows_content($content) {
        $reader = \Box\Spout\Reader\Common\Creator\ReaderFactory::createFromType(\Box\Spout\Common\Type::ODS);
        $file = tempnam(sys_get_temp_dir(), 'ods_');
        $handle = fopen($file, "w");
        fwrite($handle, $content);
        $reader->open($file);
        /** @var \Box\Spout\Reader\ODS\Sheet[] $sheets */
        $sheets = $reader->getSheetIterator();
        $rowscellsvalues = [];
        foreach ($sheets as $sheet) {
            /** @var \Box\Spout\Common\Entity\Row[] $rows */
            $rows = $sheet->getRowIterator();
            foreach ($rows as $row) {
                $rowscellsvalues[] = $row->getCellAtIndex(0);
            }
        }

        return $rowscellsvalues;
    }

    /**
     * Get the expected HTML table
     *
     * @param array $expectedrows
     * @return string
     */
    private function get_html_table(array $expectedrows): string {
        $firstrow = true;

        $body = '';
        foreach ($expectedrows as $expectedcolumns) {
            $body .= '<tr>';
            $celltagname = $firstrow ? 'th' : 'td';
            foreach ($expectedcolumns as $column) {
                $body .= html_writer::tag($celltagname, $column);
            }
            $body .= '</tr>';
            $firstrow = false;
        }
        return '<!DOCTYPE html><html><head><meta charset="UTF-8" /><title>test</title><style>
html, body {
    margin: 0;
    padding: 0;
    font-family: sans-serif;
    font-size: 13px;
    background: #eee;
}
th {
    border: solid 1px #999;
    background: #eee;
}
td {
    border: solid 1px #999;
    background: #fff;
}
tr:hover td {
    background: #eef;
}
table {
    border-collapse: collapse;
    border-spacing: 0pt;
    width: 80%;
    margin: auto;
}
</style>
</head>
<body><table border=1 cellspacing=0 cellpadding=3>' . $body . '</table></body></html>';
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_reportbuilder_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Returns the tenant generator
     * @return tool_tenant_generator
     * @throws coding_exception
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}

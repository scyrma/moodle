<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Download report tests.
 *
 * @package    tool_reportbuilder
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/tablelib.php');
require_once($CFG->libdir . '/odslib.class.php');
require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

/**
 * Class tool_reportbuilder_download_testcase
 *
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_download_testcase extends advanced_testcase {

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
        $this->assertEquals([['User Lastname 1', 'username1@example.com', '', '']], $content);

        // Test with users from other tenant.
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 2']);
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $exporter = new testable_report_exporter($reportid);
        $content = json_decode($exporter->download('json'), true);
        $this->assertEquals([['User Lastname 1', 'username1@example.com', '', '']], $content);

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
        $expected = [
            ['User Lastname 2', 'username3@example.com', '', 'France'],
            ['User Lastname 3', 'username4@example.com', '', 'France'],
            ['User Lastname 4', 'username5@example.com', '', 'France'],
        ];
        $this->assertEquals($expected, $content);

        // Test export with a condition.
        $this->get_reportbuilder_generator()->add_condition($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{}', $user1->id);
        $DB->set_field('tool_reportbuilder', 'conditions', '{"user:country_op":"1","user:country":"FR"}', ['id' => $reportid]);

        $exporter = new testable_report_exporter($reportid, false);
        $content = json_decode($exporter->download('json'));
        $expected = [
            ['User Lastname 2', 'username3@example.com', '', 'France'],
            ['User Lastname 3', 'username4@example.com', '', 'France'],
            ['User Lastname 4', 'username5@example.com', '', 'France'],
        ];
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
        $filesize = core_text::strlen($filecontents);
        $this->assertTrue($filesize > 30000 && $filesize < 70000);

        // Test with users from other tenant.
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'Lastname 2']);
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $exporter = new testable_report_exporter($reportid);
        $filecontents = $exporter->download('pdf', true);
        $filesize = core_text::strlen($filecontents);
        $this->assertTrue($filesize > 30000 && $filesize < 70000);

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
        $this->assertTrue($filesize > 30000 && $filesize < 70000);

        // Test export with a condition.
        $this->get_reportbuilder_generator()->add_condition($report, 'user:country');
        set_user_preference('filters_report_' . $reportid, '{}', $user1->id);
        $DB->set_field('tool_reportbuilder', 'conditions', '{"user:country_op":"1","user:country":"FR"}', ['id' => $reportid]);

        $exporter = new testable_report_exporter($reportid, false);
        $filecontents = $exporter->download('pdf', true);
        $filesize = core_text::strlen($filecontents);
        $this->assertTrue($filesize > 30000 && $filesize < 70000);
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

        /** @var Box\Spout\Reader\XLSX\Sheet[] $sheets */
        $sheets = $reader->getSheetIterator();
        $rowscellsvalues = [];
        foreach ($sheets as $sheet) {
            /** @var Box\Spout\Common\Entity\Row[] $rows */
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
        /** @var Box\Spout\Reader\ODS\Sheet[] $sheets */
        $sheets = $reader->getSheetIterator();
        $rowscellsvalues = [];
        foreach ($sheets as $sheet) {
            /** @var Box\Spout\Common\Entity\Row[] $rows */
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
        $body = '';
        foreach ($expectedrows as $expectedcolumns) {
            $body .= '<tr>';
            if (!is_array($expectedcolumns)) {
                $expectedcolumns = [$expectedcolumns];
            }
            foreach ($expectedcolumns as $column) {
                $body .= '<td>' . $column. '</td>';
            }
            $body .= '</tr>';
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
<body>' . $body . '</body></html>';
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

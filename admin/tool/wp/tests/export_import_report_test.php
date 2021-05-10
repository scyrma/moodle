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
 * File containing tests for import/export list reports
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

use tool_reportbuilder\system_report_factory;
use tool_tenant\manager;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\local\exportimport\exports_list_report
 * @covers      \tool_wp\local\exportimport\imports_list_report
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_report_testcase extends \advanced_testcase {

    /** @var \stdClass[] $tenants */
    protected $tenants = [];

    /** @var \stdClass[] $users */
    protected $users = [];

    /**
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/reportbuilder/tests/fixtures/testable_report_exporter.php");
    }

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();

        list($tenant1, ) = $this->get_tenant_generator()->create_tenant_and_users(0);
        list($tenant2, $tenantusers) = $this->get_tenant_generator()->create_tenant_and_users(2);

        // Make first user admin of the second tenant.
        (new manager())->assign_tenant_admin_roles([$tenantusers[0]->id], $tenant2->id);

        $this->tenants = [$tenant1, $tenant2];
        $this->users = array_merge([get_admin()], $tenantusers);
    }

    /**
     * Data provider for users and the exports that they should see in the report
     *
     * @return array[]
     */
    public function exports_list_report_provider(): array {
        return [
            // Admin can see all the exports.
            [0, ['Programs', 'Organisation structure jobs', 'Organisation structure frameworks']],
            // Tenant admin can see all exports in their own tenant.
            [1, ['Programs', 'Organisation structure jobs']],
            // Tenant user can see their own export.
            [2, ['Programs']],
        ];
    }

    /**
     * Test users view the correct list of exports given their current capabilities
     *
     * @param int $userindex
     * @param array $expectedexporters
     *
     * @dataProvider exports_list_report_provider
     */
    public function test_exports_list_report(int $userindex, array $expectedexporters): void {
        $this->setUser($this->users[$userindex]);

        // Create three export records (tenant1/admin, tenant2/tenantadmin, tenant2/user).
        (new export_persistent(0, (object) [
            'tenantid' => $this->tenants[0]->id,
            'createdby' => $this->users[0]->id,
            'exporter' => \tool_organisation\tool_wp\exporter\orgstructure::class,
        ]))->create();
        (new export_persistent(0, (object) [
            'tenantid' => $this->tenants[1]->id,
            'createdby' => $this->users[1]->id,
            'exporter' => \tool_organisation\tool_wp\exporter\jobs::class,
        ]))->create();
        (new export_persistent(0, (object) [
            'tenantid' => $this->tenants[1]->id,
            'createdby' => $this->users[2]->id,
            'exporter' => \tool_program\tool_wp\exporter\programs::class,
        ]))->create();

        // Exporter value is in third element of each row, use to assert which exports given user should see.
        $rows = $this->get_report_exporter(exports_list_report::class)->get_table_rows();
        $this->assertEqualsCanonicalizing($expectedexporters, array_column($rows, 2));
    }

    /**
     * Data provider for users and the imports that they should see in the report
     *
     * @return array[]
     */
    public function imports_list_report_provider(): array {
        return [
            // Admin can see all the imports.
            [0, ['Programs', 'Organisation structure jobs', 'Organisation structure frameworks']],
            // Tenant admin can see all imports in their own tenant.
            [1, ['Programs', 'Organisation structure jobs']],
            // Tenant user can see their own import.
            [2, ['Programs']],
        ];
    }

    /**
     * Test users view the correct list of imports given their current capabilities
     *
     * @param int $userindex
     * @param array $expectedimporters
     *
     * @dataProvider imports_list_report_provider
     */
    public function test_imports_list_report(int $userindex, array $expectedimporters): void {
        $this->setUser($this->users[$userindex]);

        // Create three import records (tenant1/admin, tenant2/tenantadmin, tenant2/user).
        (new import_persistent(0, (object) [
            'tenantid' => $this->tenants[0]->id,
            'createdby' => $this->users[0]->id,
            'importer' => \tool_organisation\tool_wp\importer\orgstructure::class,
            'status' => 1,
        ]))->create();
        (new import_persistent(0, (object) [
            'tenantid' => $this->tenants[1]->id,
            'createdby' => $this->users[1]->id,
            'importer' => \tool_organisation\tool_wp\importer\jobs::class,
            'status' => 1,
        ]))->create();
        (new import_persistent(0, (object) [
            'tenantid' => $this->tenants[1]->id,
            'createdby' => $this->users[2]->id,
            'importer' => \tool_program\tool_wp\importer\programs::class,
            'status' => 1,
        ]))->create();

        // Importer value is in third element of each row, use to assert which imports given user should see.
        $rows = $this->get_report_exporter(imports_list_report::class)->get_table_rows();
        $this->assertEqualsCanonicalizing($expectedimporters, array_column($rows, 2));
    }

    /**
     * Helper method to retrieve a system report instance, which we can test the output of
     *
     * @param string $reportclass
     * @return \testable_report_exporter
     */
    protected function get_report_exporter(string $reportclass): \testable_report_exporter {
        $report = system_report_factory::create($reportclass);

        return new \testable_report_exporter($report->get_id());
    }

    /**
     * Return tenant generator
     *
     * @return \tool_tenant_generator
     */
    protected function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}

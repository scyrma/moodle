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

namespace tool_wp\reportbuilder\local\systemreports;

use advanced_testcase;
use context_system;
use stdClass;
use core_reportbuilder\testable_system_report_table;
use tool_tenant_generator;
use tool_tenant\manager;
use tool_wp\local\exportimport\import_persistent;

/**
 * Test for imports system report
 *
 * @package     tool_wp
 * @covers      \tool_wp\reportbuilder\local\systemreports\imports
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class imports_test extends advanced_testcase {

    /** @var stdClass[] $tenants */
    protected $tenants = [];

    /** @var stdClass[] $users */
    protected $users = [];

    /**
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/reportbuilder/tests/fixtures/testable_system_report_table.php");
    }

    /**
     * Test setup
     */
    public function setUp(): void {
        global $DB;

        $this->resetAfterTest();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        [$tenant1] = $generator->create_tenant_and_users(0);
        [$tenant2, $tenant2users] = $generator->create_tenant_and_users(2);

        // Make first user admin of the second tenant.
        (new manager())->assign_tenant_admin_roles([$tenant2users[0]->id], $tenant2->id);

        $this->tenants = [$tenant1, $tenant2];
        $this->users = array_merge([get_admin()], $tenant2users);

        // Allow all users to use export import.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('tool/wp:useexportimport', CAP_ALLOW, $userrole, context_system::instance()->id);
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
     * Test users view the correct list of exports given their current capabilities
     *
     * @param int $userindex
     * @param array $expectedexporters
     *
     * @dataProvider imports_list_report_provider
     */
    public function test_exports_list_report(int $userindex, array $expectedexporters): void {
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

        // Exporter value is in third element of each row, use to assert which exports given user should see.
        $rows = $this->get_report_table_rows();
        $this->assertEqualsCanonicalizing($expectedexporters, array_column($rows, 'importer'));
    }

    /**
     * Helper method to create the report, and return it's rows
     *
     * @return array
     */
    private function get_report_table_rows(): array {
        $report = \core_reportbuilder\manager::create_report_persistent((object) [
            'type' => imports::TYPE_SYSTEM_REPORT,
            'source' => imports::class,
        ]);

        return testable_system_report_table::create($report->get('id'), [])->get_table_rows();
    }
}

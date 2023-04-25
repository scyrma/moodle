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

namespace tool_organisation\tool_reportbuilder\audiences;

use advanced_testcase;
use tool_reportbuilder\permission;
use tool_reportbuilder\test\mock_report;
use tool_reportbuilder_generator;
use tool_tenant\tenancy;

/**
 * Tests for the manager audience.
 *
 * @covers     \tool_organisation\tool_reportbuilder\audiences\manager
 * @package    tool_organisation
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager_test extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Test get_title()
     */
    public function test_get_title(): void {
        $report = $this->get_generator()->create_report([
            'source' => mock_report::class,
        ]);
        $audience = manager::create($report->get_id(), []);
        $this->assertNotEmpty($audience->get_title());
    }

    /**
     * Check that audience can be converted to the core reportbuilder
     */
    public function test_convert_to_core_reportbuilder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantgenerator->create_tenant(); // Make site multi-tenant.
        $this->get_generator()->audience_test_convert_to_core_reportbuilder(manager::class, $this);
    }

    /**
     * Provider for test_convert_to_core_reportbuilder_config
     *
     * @return array[]
     */
    public function get_audience_settings() {
        return [
            'All managers' => ["anymanager", [1, 2]],
            'Global managers' => ["globalmanager", [1]],
            'Department managers' => ["departmentmanager", [2]],
        ];
    }

    /**
     * Check that audience can be converted to the core reportbuilder and creates the correct configdata
     *
     * @dataProvider get_audience_settings
     * @param string $permissions
     * @param array $expectedusers
     */
    public function test_convert_to_core_reportbuilder_config(string $permissions, array $expectedusers): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $pos1 = $generator->create_position(['tenantid' => tenancy::get_default_tenant_id(), 'globalmanager' => 1]);
        $pos2 = $generator->create_position(['tenantid' => tenancy::get_default_tenant_id(), 'departmentmanager' => 1]);
        $pos3 = $generator->create_position(['tenantid' => tenancy::get_default_tenant_id()]);
        $dep1 = $generator->create_department(['tenantid' => tenancy::get_default_tenant_id()]);

        $users = [];
        $users[1] = $this->getDataGenerator()->create_user(); // Global manager.
        $users[2] = $this->getDataGenerator()->create_user(); // Department manager.
        $users[3] = $this->getDataGenerator()->create_user(); // Not a manager.

        $generator->assign_job((object)['userid' => $users[1]->id, 'positionid' => $pos1->id, 'departmentid' => $dep1->id]);
        $generator->assign_job((object)['userid' => $users[2]->id, 'positionid' => $pos2->id, 'departmentid' => $dep1->id]);

        // Create report with an audience and convert it.
        $report = $this->get_generator()->create_report([
            'source' => \tool_program\tool_reportbuilder\datasources\report_programs::class,
        ]);
        manager::create($report->get_id(), ["permissions" => $permissions]);

        $newid = $report->convert(false);

        // Make sure expected users can view both original and converted reports.
        $origreport = \tool_reportbuilder\manager::get_report($report->get_id());
        $newreport = \core_reportbuilder\manager::get_report_from_id($newid)->get_report_persistent();
        foreach ($users as $idx => $user) {
            $this->setUser($user);
            $this->assertEquals(in_array($idx, $expectedusers), permission::can_view($origreport));
            $this->assertEquals(in_array($idx, $expectedusers), \core_reportbuilder\permission::can_view_report($newreport));
        }
    }
}

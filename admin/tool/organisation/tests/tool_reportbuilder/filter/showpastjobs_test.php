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

namespace tool_organisation\tool_reportbuilder\filter;

use tool_organisation_generator;
use tool_reportbuilder_generator;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Tests for conversion of showpastjobs filter
 *
 * @package   tool_organisation
 * @group     tool_organisation
 * @category  test
 * @covers    \tool_organisation\tool_reportbuilder\filter\showpastjobs
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class showpastjobs_test extends \core_reportbuilder_testcase {

    /**
     * Load required classes
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/reportbuilder/tests/fixtures/testable_report_exporter.php");
    }

    /**
     * Get Report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    public function get_plugin_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Provider for showpastjobs conversion test
     *
     * @return array[]
     */
    public function convert_provider(): array {
        return [
            'show past jobs' => [1, ['A', 'Admin', 'B']],
            'don\'t show past jobs' => [0, ['A']],
        ];
    }

    /**
     * Test for conversion of showpastjobs condition
     *
     * @dataProvider convert_provider
     * @param int $operator
     * @param array $expected
     * @return void
     */
    public function test_convert(int $operator, array $expected): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $tenantid = tenancy::get_default_tenant_id();
        /** @var tool_organisation_generator $orggenerator */
        $orggenerator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $generator = $this->get_plugin_generator();
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'A']);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'B']);

        $dep1 = $orggenerator->create_department(['name' => 'D1', 'tenantid' => $tenantid]);
        $dep2 = $orggenerator->create_department(['name' => 'D2', 'tenantid' => $tenantid]);
        $dep3 = $orggenerator->create_department(['name' => 'D3', 'parentid' => $dep1->id, 'tenantid' => $tenantid]);
        $pf1 = $orggenerator->create_position(['tenantid' => $tenantid]);
        $pa = $orggenerator->create_position(['parentid' => $pf1->id, 'globalmanager' => 1, 'globalpermissions' => 3]);
        $orggenerator->assign_job(['userid' => $user1->id, 'positionid' => $pa->id, 'departmentid' => $dep1->id,
            'startdate' => time() - WEEKSECS, 'enddate' => time() - DAYSECS]);
        $orggenerator->assign_job(['userid' => $user1->id, 'positionid' => $pa->id, 'departmentid' => $dep2->id]);
        $orggenerator->assign_job(['userid' => $user2->id, 'positionid' => $pa->id, 'departmentid' => $dep3->id,
            'startdate' => time() - 2 * DAYSECS, 'enddate' => time() - DAYSECS]);

        $report = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => $tenantid,
                'adddefault' => 0,
            ]);
        $reportid = $report->get_id();
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $generator->add_column($report, 'user:firstname');
        $generator->add_condition($report, 'tool_organisation_jobs:showpastjobs');
        $DB->set_field('tool_reportbuilder', 'conditions',
            json_encode(['tool_organisation_jobs:showpastjobs' => $operator]),
            ['id' => $reportid]);

        $exporter = new \testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing($expected, array_column($rows, 0));

        $report = \tool_reportbuilder\manager::get_report($reportid);
        $newreportid = $report->convert();

        $newrows = $this->get_custom_report_content($newreportid);
        $this->assertEqualsCanonicalizing($expected, array_column($newrows, 'c0_firstname'));
    }
}

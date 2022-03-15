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
 * File containing tests for helper class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\helper;
use tool_reportbuilder\test\mock_report;

/**
 * Class tool_reportbuilder_helper_testcase
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @category  test
 * @covers    \tool_reportbuilder\helper
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_helper_testcase extends advanced_testcase {

    /** @var \tool_reportbuilder\reportbuilder[] $reports */
    protected $reports;

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void {
        $this->resetAfterTest();

        // Create a couple of reports.
        for ($i = 0; $i < 2; $i++) {
            $report = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);
            $this->reports[$i] = $report->get_persistent();
        }
    }

    /**
     * Test get_reports_select with a user with permission to view all reports
     *
     * @return void
     */
    public function test_get_reports_select_admin() {
        $this->setAdminUser();

        $expected = [
            $this->reports[0]->get('id') => format_string($this->reports[0]->get('name')),
            $this->reports[1]->get('id') => format_string($this->reports[1]->get('name')),
        ];

        $this->assertEquals($expected, helper::get_reports_select());
    }

    /**
     * Test get_reports_select with a user without access to view any reports
     *
     * @return void
     */
    public function test_get_reports_select_no_access() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertEmpty(helper::get_reports_select());
    }

    public function test_get_reports_select_with_audience() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Assign user a job.
        list($position, $department) = $this->get_organisation_generator()->create_position_and_department();
        $this->get_organisation_generator()->assign_job([
            'userid' => $user->id,
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        // Add that job to a report's audience.
        $this->get_plugin_generator()->create_audience([
            'reportid' => $this->reports[0]->get('id'),
            'positionid' => $position->id,
            'departmentid' => $department->id,
        ]);

        $expected = [
            $this->reports[0]->get('id') => format_string($this->reports[0]->get('name')),
        ];
        $this->assertEquals($expected, helper::get_reports_select());
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
     * Get organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_organisation_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }
}

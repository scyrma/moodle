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
 * File containing tests for helper class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use advanced_testcase;
use tool_reportbuilder_generator;
use tool_reportbuilder\test\mock_report;
use tool_reportbuilder\tool_reportbuilder\audiences\manual;

/**
 * Class tool_reportbuilder_helper_testcase
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @category  test
 * @covers    \tool_reportbuilder\helper
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class helper_test extends advanced_testcase {

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

        manual::create($this->reports[0]->get('id'), ['users' => [$user->id]]);

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
}

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

namespace tool_reportbuilder\local\filter;

use tool_reportbuilder_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Tests for conversion of course selector filter
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @category  test
 * @covers    \tool_reportbuilder\local\filter\course_selector
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_selector_test extends \core_reportbuilder_testcase {

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
     * Test for conversion of course selector condition
     */
    public function test_convert(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->get_plugin_generator();
        $user = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'A']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'B']);
        $course3 = $this->getDataGenerator()->create_course(['fullname' => 'C']);
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);
        $this->getDataGenerator()->enrol_user($user->id, $course3->id);
        $report = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_course_enrolments::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
                'adddefault' => 0,
            ]);
        $reportid = $report->get_id();
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $generator->add_column($report, 'course:fullname');
        $generator->add_condition($report, 'course:courseselector');
        $DB->set_field('tool_reportbuilder', 'conditions',
            json_encode(['course:courseselector_op' => [$course1->id, $course3->id]]),
            ['id' => $reportid]);

        $exporter = new \testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(['A', 'C'], array_column($rows, 0));

        $report = \tool_reportbuilder\manager::get_report($reportid);
        $newreportid = $report->convert();

        $newrows = $this->get_custom_report_content($newreportid);
        $this->assertEqualsCanonicalizing(['A', 'C'], array_column($newrows, 'c0_fullname'));
    }
}

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

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use advanced_testcase;
use testable_report_exporter;
use tool_reportbuilder_generator;
use tool_reportbuilder\report_base;

/**
 * Test class for "Course enrolments" datasource
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\tool_reportbuilder\datasources\report_course_enrolments
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_course_enrolments_test extends advanced_testcase {

    /**
     * Load required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/reportbuilder/tests/fixtures/testable_report_exporter.php");
    }

    /**
     * Test default report data
     */
    public function test_default_report(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // Ensure test users are named such that they are returned in a predictable order in the report (alphabetically).
        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Iceman']);
        $user2 = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Maverick']);

        $exporter = new testable_report_exporter($this->create_report()->get_id());
        $rows = $exporter->get_table_rows();
        $this->assertCount(2, $rows);

        $row = $rows[0];
        $this->assertStringContainsString($course->fullname, $row[0]);
        $this->assertStringContainsString(fullname($user1, true), $row[1]);
        $this->assertEquals('Manual enrolments', $row[2]);
        $this->assertMatchesRegularExpression('/\d+\/\d+\/\d+/', $row[3]);
        $this->assertEmpty($row[4]);

        $row = $rows[1];
        $this->assertStringContainsString($course->fullname, $row[0]);
        $this->assertStringContainsString(fullname($user2, true), $row[1]);
        $this->assertEquals('Manual enrolments', $row[2]);
        $this->assertMatchesRegularExpression('/\d+\/\d+\/\d+/', $row[3]);
        $this->assertEmpty($row[4]);
    }

    /**
     * Test default report data with user enrolled multiple times in single course
     */
    public function test_default_report_multiple_enrolments_in_course(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $enroltimestart = time();
        $strdateformat = get_string('strftimedatefullshort', 'langconfig');

        // Enrol manually from tomorrow until the following day, plus self enrolment from now.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', $enroltimestart + DAYSECS,
            $enroltimestart + (DAYSECS * 2));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'self', $enroltimestart);

        $exporter = new testable_report_exporter($this->create_report()->get_id());
        $rows = $exporter->get_table_rows();
        $this->assertCount(2, $rows);

        $row = $rows[0];
        $this->assertStringContainsString($course->fullname, $row[0]);
        $this->assertStringContainsString(fullname($user, true), $row[1]);
        $this->assertEquals('Manual enrolments', $row[2]);
        $this->assertEquals(userdate($enroltimestart + DAYSECS, $strdateformat), $row[3]);
        $this->assertEquals(userdate($enroltimestart + (DAYSECS * 2), $strdateformat), $row[4]);

        $row = $rows[1];
        $this->assertStringContainsString($course->fullname, $row[0]);
        $this->assertStringContainsString(fullname($user, true), $row[1]);
        $this->assertEquals('Self enrolment (Student)', $row[2]);
        $this->assertEquals(userdate($enroltimestart, $strdateformat), $row[3]);
        $this->assertEmpty($row[4]);
    }

    /**
     * Stress testing of aggregation methods
     *
     * @coversNothing
     */
    public function test_stress_aggregation(): void {
        $this->resetAfterTest();

        $reportid = $this->create_report(false)->get_id();

        // Make sure there's some data to report on - required for test.
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->get_plugin_generator()->add_all_available_columns_to_report($reportid);
        $this->get_plugin_generator()->datasource_stress_test_aggregation($reportid, $this);
    }

    /**
     * Stress testing of conditions
     *
     * @coversNothing
     */
    public function test_stress_conditions(): void {
        $this->resetAfterTest();

        $reportid = $this->create_report(false)->get_id();

        $this->get_plugin_generator()->add_all_available_columns_to_report($reportid);
        $this->get_plugin_generator()->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing of filters
     *
     * @coversNothing
     */
    public function test_stress_filters(): void {
        $this->resetAfterTest();

        $reportid = $this->create_report(false)->get_id();

        $this->get_plugin_generator()->add_all_available_columns_to_report($reportid);
        $this->get_plugin_generator()->datasource_stress_test_filters($reportid, $this);
    }

    /**
     * Helper method to create a report instance
     *
     * @param bool $adddefault
     * @return report_base
     */
    protected function create_report(bool $adddefault = true): report_base {
        return $this->get_plugin_generator()->create_report([
            'source' => report_course_enrolments::class,
            'adddefault' => (int)$adddefault,
        ]);
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

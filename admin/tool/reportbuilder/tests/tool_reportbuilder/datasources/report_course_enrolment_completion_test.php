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
use completion_completion;
use completion_criteria;
use grade_item;
use stdClass;
use testable_report_exporter;
use tool_reportbuilder_generator;
use tool_reportbuilder\report_base;

/**
 * Test class for "Course participants" datasource
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\tool_reportbuilder\datasources\report_course_enrolment_completion
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_course_enrolment_completion_test extends advanced_testcase {

    /** @var stdClass test course  */
    protected $course;

    /** @var stdClass first test user  */
    protected $user1;

    /** @var stdClass second test user  */
    protected $user2;

    /**
     * Load required libraries
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

        // Enable completion in our test course.
        set_config('enablecompletion', 1);
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        // Enrol first test user in course and mark completed.
        $this->user1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student',
            ['firstname' => 'Arthur']);
        (new completion_completion(['userid' => $this->user1->id, 'course' => $this->course->id]))->mark_complete();

        // Second test user, hasn't completed the course.
        $this->user2 = $this->getDataGenerator()->create_and_enrol($this->course, 'student',
            ['firstname' => 'Brendan']);
    }

    /**
     * Test default report data
     */
    public function test_default_report(): void {
        $exporter = new testable_report_exporter($this->create_report()->get_id());
        $rows = $exporter->get_table_rows();

        // First row is first user who has completed the test course.
        $row = $rows[0];
        $this->assertStringContainsString($this->course->fullname, $row[0]);
        $this->assertStringContainsString(fullname($this->user1, true), $row[1]);
        $this->assertEquals(get_string('yes'), $row[2]);

        // Second row is the second user who hasn't completed the test course.
        $row = $rows[1];
        $this->assertStringContainsString($this->course->fullname, $row[0]);
        $this->assertStringContainsString(fullname($this->user2, true), $row[1]);
        $this->assertEquals(get_string('no'), $row[2]);
    }

    /**
     * Stress testing of aggregation methods
     *
     * @coversNothing
     */
    public function test_stress_aggregation(): void {
        $reportid = $this->create_report(false)->get_id();

        $generator = $this->get_plugin_generator();
        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_aggregation($reportid, $this);
    }

    /**
     * Stress testing of conditions
     *
     * @coversNothing
     */
    public function test_stress_conditions(): void {
        $reportid = $this->create_report(false)->get_id();

        $generator = $this->get_plugin_generator();
        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing of filters
     */
    public function test_stress_filters(): void {
        $reportid = $this->create_report(false)->get_id();

        $generator = $this->get_plugin_generator();
        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_filters($reportid, $this);
    }

    /**
     * Grade columns
     */
    public function test_grades_columns(): void {
        // Setup course completion criteria.
        $cc = completion_criteria::factory(['criteriatype' => COMPLETION_CRITERIA_TYPE_GRADE]);
        $data = (object)['id' => $this->course->id, 'criteria_grade' => 1, 'criteria_grade_value' => 50];
        $cc->update_config($data);
        // Assign grades to users.
        $this->assign_course_grade();
        // Create a report.
        $reportbase = $this->create_report(true);
        $reportid = $reportbase->get_id();
        $generator = $this->get_plugin_generator();
        // Add the grades columns to report.
        $generator->add_column($reportbase, 'course_completion:grade');
        $generator->add_column($reportbase, 'course_completion:requiredgrade');

        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();

        // Check the report data for user1, check course grade, completion and required grade.
        $this->assertStringContainsString($this->course->fullname, $rows[0][0]);
        $this->assertStringContainsString(fullname($this->user1, true), $rows[0][1]);
        $this->assertEquals('Yes', $rows[0][2]);
        $this->assertEquals('60.00', $rows[0][3]);
        $this->assertEquals('50.00', $rows[0][4]);

        // Check the report data for user2, check course grade, completion and required grade.
        $this->assertStringContainsString($this->course->fullname, $rows[1][0]);
        $this->assertStringContainsString(fullname($this->user2, true), $rows[1][1]);
        $this->assertEquals('No', $rows[1][2]);
        $this->assertEquals('40.00', $rows[1][3]);
        $this->assertEquals('50.00', $rows[1][4]);
    }

    /**
     * Helper method to create a report instance
     *
     * @param bool $adddefault
     * @return report_base
     */
    protected function create_report(bool $adddefault = true) : report_base {
        return $this->get_plugin_generator()->create_report([
            'source' => report_course_enrolment_completion::class,
            'adddefault' => (int)$adddefault,
        ]);
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_plugin_generator() : tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Assign course grades
     */
    protected function assign_course_grade(): void {
        // Setup course grade.
        $courseitem = grade_item::fetch_course_item($this->course->id);
        // Award grades to users in course.
        $courseitem->update_final_grade($this->user1->id, 60);
        $courseitem->update_final_grade($this->user2->id, 40);
    }
}

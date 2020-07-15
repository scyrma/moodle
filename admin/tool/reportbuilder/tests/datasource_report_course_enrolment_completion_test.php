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
 * File containing tests for external report class
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\report_base;
use tool_reportbuilder\tool_reportbuilder\datasources\report_course_enrolment_completion;

global $CFG;
require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\tool_reportbuilder\datasources\report_course_enrolment_completion
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_datasource_report_course_enrolment_completion_testcase extends advanced_testcase {

    /** @var stdClass test course  */
    protected $course;

    /** @var stdClass first test user  */
    protected $user1;

    /** @var stdClass second test user  */
    protected $user2;

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp() : void {
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
     *
     * @return void
     */
    public function test_default_report() : void {
        $exporter = new testable_report_exporter($this->create_report()->get_id());
        $rows = $exporter->get_table_rows();

        $dateformat = get_string('strftimedatefullshort', 'langconfig');
        $date = userdate(time(), $dateformat);

        // First row is first user who has completed the test course.
        $row = $rows[0];
        $this->assertEquals($this->course->fullname, $row[0]);
        $this->assertContains(fullname($this->user1, true), $row[1]);
        $this->assertEquals($date, $row[2]);
        $this->assertEquals(get_string('yes'), $row[3]);

        // Second row is the second user who hasn't completed the test course.
        $row = $rows[1];
        $this->assertEquals($this->course->fullname, $row[0]);
        $this->assertContains(fullname($this->user2, true), $row[1]);
        $this->assertEquals($date, $row[2]);
        $this->assertEquals(get_string('no'), $row[3]);
    }

    /**
     * Stress testing of aggregation methods
     *
     * @coversNothing
     */
    public function test_stress_aggregation() : void {
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
    public function test_stress_conditions() : void {
        $reportid = $this->create_report(false)->get_id();

        $generator = $this->get_plugin_generator();
        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing of filters
     *
     * @return void
     */
    public function test_stress_filters() : void {
        $reportid = $this->create_report(false)->get_id();

        $generator = $this->get_plugin_generator();
        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_filters($reportid, $this);
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
}
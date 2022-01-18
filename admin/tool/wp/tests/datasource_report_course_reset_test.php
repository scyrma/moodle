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
 * File containing tests for report_course_reset datasource
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

use advanced_testcase;
use completion_info;
use component_generator_base;
use testable_report_exporter;
use tool_reportbuilder_generator;
use tool_tenant\tenancy;
use tool_tenant_generator;

/**
 * Tests for the datasource report_course_reset
 *
 * @package     tool_wp
 * @covers      \tool_wp\tool_reportbuilder\datasources\report_course_reset
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class datasource_report_course_reset_test extends advanced_testcase {

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator|component_generator_base
     */
    protected function get_reportbuilder_generator(): tool_reportbuilder_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Returns the tenant generator
     *
     * @return tool_tenant_generator|component_generator_base
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Set up for the test
     */
    protected function set_up_for_report(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $this->setUp();
        $this->resetAfterTest();

        $users = [];

        // Create one user and allocate them to the default tenant.
        $user1 = self::getDataGenerator()->create_user()->id;
        $defaulttenantid = tenancy::get_default_tenant_id();
        $this->get_tenant_generator()->allocate_user($user1, $defaulttenantid);

        // Add a course that supports completion.
        $course1 = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $this->completion = new completion_info($course1);

        // Enrol a user in the course.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user1, $course1->id, $studentrole->id);

        $choice = $this->getDataGenerator()->create_module('choice', array('course' => $course1->id),
            array('completion' => 1));
        $choicewithoptions = choice_get_choice($choice->id);
        $optionids = array_keys($choicewithoptions->option);
        $cm = get_coursemodule_from_instance('choice', $choice->id);
        choice_user_submit_response($optionids[2], $choice, $user1, $course1, $cm);
        $this->completion->update_state($cm, COMPLETION_COMPLETE, $user1);

        $users[] = $user1;

        // Reset the course for this user.
        $creset = new \tool_wp\course_reset_api($course1->id, $user1);
        $resetparams = [
            'programid' => 33,
            'certificationid' => 44,
            'reason' => 'Testing course reset :)'
        ];
        $creset->reset_course($resetparams);

        $this->assertEquals(1, $DB->count_records('tool_wp_course_reset', ['courseid' => $course1->id, 'userid' => $user1]));

        // First user should have capability to edit reports.
        $this->get_reportbuilder_generator()->assign_edit_capability($users[0]);

        return $users;
    }

    /**
     * System capabilities and manager permissions
     */
    public function test_permissions(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();

        // Create a report from the report_course_reset datasource with default columns/conditions.
        $class = \tool_wp\tool_reportbuilder\datasources\report_course_reset::class;
        $reportid = $generator->create_report(['source' => $class])->get_id();

        // User 0 can see the only course in the default tenant.
        self::setUser($users[0]);

        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertCount(1, $rows);
    }

    /**
     * Create a report
     *
     * @param int $tenantid
     * @param bool $adddefault
     * @return int
     */
    protected function create_report(int $tenantid, bool $adddefault): int {
        return $this->get_reportbuilder_generator()->create_report([
            'source' => \tool_wp\tool_reportbuilder\datasources\report_course_reset::class,
            'tenantid' => $tenantid,
            'adddefault' => (int)$adddefault
        ])->get_id();
    }

    /**
     * Stress testing - add all available columns, try all possible aggregation methods.
     *
     * @coversNothing
     */
    public function test_stress_aggregation(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();
        self::setUser($users[0]);

        // Create a report from the report_course_reset datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_aggregation($reportid, $this);
    }

    /**
     * Stress testing - add all available conditions.
     *
     * @coversNothing
     */
    public function test_stress_conditions(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();
        self::setUser($users[0]);

        // Create a report from the report_course_reset datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing - add all available filters.
     *
     * @coversNothing
     */
    public function test_stress_filters(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();
        self::setUser($users[0]);

        // Create a report from the report_course_reset datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_filters($reportid, $this);
    }
}

<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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
 * File containing tests for report_appointments datasource
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_tenant\tenancy;
use mod_appointment\tool_reportbuilder\datasources\report_appointments;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the datasource report_appointments
 *
 * @package     mod_appointment
 * @covers      \mod_appointment\tool_reportbuilder\datasources\report_appointments
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_appointment_datasource_report_appointments_testcase extends advanced_testcase {

    /**
     * This method is called after the last test of this test class is run.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void {
        \mod_appointment\customfield\appointment_handler::create()->delete_all();
    }

    /**
     * Get generator.
     *
     * @return mod_appointment_generator|component_generator_base
     * @throws coding_exception
     */
    protected function get_generator(): mod_appointment_generator {
        return self::getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator|component_generator_base
     * @throws coding_exception
     */
    protected function get_reportbuilder_generator(): tool_reportbuilder_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator|component_generator_base
     * @throws coding_exception
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Set up for the test
     */
    protected function set_up_for_report(): array {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $users = [];
        $this->resetAfterTest();

        $coursecategory = self::getDataGenerator()->create_category(['name' => 'tenant category']);
        $coursecategory2 = self::getDataGenerator()->create_category(['parent' => $coursecategory->id]);
        $tenant1 = $this->get_tenant_generator()->create_tenant(['categoryid' => $coursecategory->id]);
        $course = self::getDataGenerator()->create_course(['category' => $coursecategory2->id]);
        $student = self::getDataGenerator()->create_and_enrol($course);
        $this->get_tenant_generator()->allocate_user($student->id, $tenant1->id);

        // Add appointment to course.
        $appointment = self::getDataGenerator()->create_module('appointment', ['course' => $course->id]);

        // Add session.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + 3600;
        $session = $this->get_generator()->create_session(['appointment' => $appointment->id, 'capacity' => 1], [], [$date]);

        // Add second session.
        $date = new stdClass();
        $date->timestart = strtotime('+2 day');
        $date->timefinish = $date->timestart + 3600;
        $session2 = $this->get_generator()->create_session(['appointment' => $appointment->id, 'capacity' => 1], [], [$date]);

        appointment_user_signup($session, $appointment, $course, MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $student->id);

        appointment_user_signup($session2, $appointment, $course, MOD_APPOINTMENT_BOTH,
            MOD_APPOINTMENT_STATUS_BOOKED, $student->id);

        $users[] = $student->id;

        // First user should have capability to edit reports. This is only necessary because Report builder stress tests require.
        // An "editing capability" to add aggregation methods, and this should be fixed in Report builder itself.
        $this->get_reportbuilder_generator()->assign_edit_capability($users[0]);

        return $users;
    }

    /**
     * System capabilities and manager permissions
     */
    public function test_permissions(): void {
        $users = $this->set_up_for_report();

        // Create a report from the report_appointments datasource with default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), true);

        self::setUser($users[0]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertCount(2, $rows);
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
            'source' => report_appointments::class,
            'tenantid' => $tenantid,
            'adddefault' => (int) $adddefault
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

        // Create a report from the report_appointments datasource without default columns/conditions.
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

        // Create a report from the report_appointments datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing - add all available filters.
     */
    public function test_stress_filters(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();
        self::setUser($users[0]);

        // Create a report from the report_appointments datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_filters($reportid, $this);
    }
}

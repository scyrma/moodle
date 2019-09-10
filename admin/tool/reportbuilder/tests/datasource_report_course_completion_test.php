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
 * File containing tests for report_course_completion class.
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the datasource report_course_completion
 *
 * @package     tool_reportbuilder
 * @covers \tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_datasource_report_course_completion_testcase extends advanced_testcase {

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }

    /**
     * Returns the tenant generator
     * @return tool_tenant_generator
     * @throws coding_exception
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Set up for the
     */
    protected function set_up_for_report() {
        global $CFG;
        require_once($CFG->dirroot.'/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        parent::setUp();
        $this->resetAfterTest();

        // Enable completion before creating modules, otherwise the completion data is not written in DB.
        $CFG->enablecompletion = true;

        // Enable datastore.
        set_config('enabled', 1, 'tool_datastore');

        // Create a course.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);

        // Create one user and allocate them to the default tenant.
        $user = $this->getDataGenerator()->create_user()->id;
        $this->get_tenant_generator()->allocate_user($user, \tool_tenant\tenancy::get_default_tenant_id());
        $this->setUser($user); // TODO WP-708 - remove.
        $ccompletion = new completion_completion(array('course' => $course->id, 'userid' => $user));
        $ccompletion->mark_complete();

        // Create five more users and allocated them to the new tenant.
        $othertenantid = $this->get_tenant_generator()->create_tenant()->id;
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(
                ['firstname' => 'User', 'lastname' => $i + 1, 'username' => 'user' . $i])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], $othertenantid);
            $this->getDataGenerator()->enrol_user($users[$i], $course->id);
            $this->setUser($users[$i]); // TODO WP-708 - remove.
            $ccompletion = new completion_completion(array('course' => $course->id, 'userid' => $users[$i]));
            $ccompletion->mark_complete();
        }

        // First user should have capability to edit reports.
        $this->get_generator()->assign_edit_capability($users[0]);
        // Second user should have capability to view reports.
        $this->get_generator()->assign_read_capability($users[1]);
        // Third user is a manager that has "view reports" permission over users four and five.
        $this->get_generator()->assign_job_with_report_permissions($users[2], [$users[3], $users[4]]);

        return $users;
    }

    /**
     * Create a report
     *
     * @param int $tenantid
     * @param bool $adddefault
     * @return int
     */
    protected function create_report(int $tenantid, bool $adddefault) : int {
        return $this->get_generator()->create_report(
            ['source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion::class,
                'tenantid' => $tenantid,
                'adddefault' => (int)$adddefault])->get_id();
    }

    /**
     * System capabilities and manager permissions
     */
    public function test_permissions() {
        $users = $this->set_up_for_report();

        // Create a report from the report_users_list datasource with default columns/conditions.
        $reportid = $this->create_report(\tool_tenant\tenancy::get_tenant_id($users[0]), true);

        // Execute report for different users.

        // Admin can only see their tenant with 1 user.
        $this->setAdminUser();
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEquals(1, count($rows));

        // User 0 and user 1 can see all five user in their tenant (but can not see users in other tenants).
        $this->setUser($users[0]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEquals(5, count($rows));

        $this->setUser($users[1]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEquals(5, count($rows));

        // User 2 can see only his team (users 3 and 4).
        $this->setUser($users[2]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEquals(2, count($rows));
    }

    /**
     * Stress testing - add all available columns, try all possible aggregation methods.
     */
    public function test_stress_aggregation() {
        $users = $this->set_up_for_report();
        $generator = $this->get_generator();
        $this->setUser($users[0]);

        // Create a report from the report_users_list datasource without default columns/conditions.
        $reportid = $this->create_report(\tool_tenant\tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_aggregation($reportid, $this);
    }

    /**
     * Stress testing - add all available conditions.
     */
    public function test_stress_conditions() {
        $users = $this->set_up_for_report();
        $generator = $this->get_generator();
        $this->setUser($users[0]);

        // Create a report from the report_users_list datasource without default columns/conditions.
        $reportid = $this->create_report(\tool_tenant\tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing - add all available filters.
     */
    public function test_stress_filters() {
        $users = $this->set_up_for_report();
        $generator = $this->get_generator();
        $this->setUser($users[0]);

        // Create a report from the report_users_list datasource without default columns/conditions.
        $reportid = $this->create_report(\tool_tenant\tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_filters($reportid, $this);
    }
}
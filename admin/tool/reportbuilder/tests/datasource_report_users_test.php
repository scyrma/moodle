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
 * File containing tests for tool_reportbuilder_datasources_testcase class.
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

/**
 * Tests for the datasource report_users_list
 *
 * @package     tool_reportbuilder
 * @covers      \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_datasource_report_users_testcase extends advanced_testcase {

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
     * Set up for the test
     */
    protected function set_up_for_report() {
        global $DB;

        $this->resetAfterTest();

        // Define user profile fields.
        $categoryid = $DB->insert_record('user_info_category', ['name' => 't']);
        $DB->insert_record('user_info_field',
            ['shortname' => 'field1', 'name' => 'Name1', 'categoryid' => $categoryid, 'datatype' => 'textarea']);
        $DB->insert_record('user_info_field',
            ['shortname' => 'field2', 'name' => 'Name2', 'categoryid' => $categoryid, 'datatype' => 'text']);
        $DB->insert_record('user_info_field',
            ['shortname' => 'field3', 'name' => 'Name3', 'categoryid' => $categoryid, 'datatype' => 'datetime']);
        $DB->insert_record('user_info_field',
            ['shortname' => 'field4', 'name' => 'Name4', 'categoryid' => $categoryid, 'datatype' => 'checkbox']);
        $DB->insert_record('user_info_field',
            ['shortname' => 'field5', 'name' => 'Name5', 'categoryid' => $categoryid,
                'param1' => "a\nb\nc", 'datatype' => 'menu']);

        // Create one user and allocate them to the default tenant.
        $user = $this->getDataGenerator()->create_user(['firstname' => 'John', 'lastname' => 'Smith'])->id;
        $this->get_tenant_generator()->allocate_user($user, \tool_tenant\tenancy::get_default_tenant_id());

        // Create five more users and allocated them to the new tenant.
        $othertenantid = $this->get_tenant_generator()->create_tenant()->id;
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], $othertenantid);
        }

        return $users;
    }

    /**
     * System capabilities and manager permissions
     */
    public function test_permissions() {
        $users = $this->set_up_for_report();

        // Create a report from the report_users_list datasource with default columns/conditions.
        $reportid = $this->create_report(\tool_tenant\tenancy::get_tenant_id($users[0]), true);

        // Admin user sees only the two users in their tenant.
        $this->setAdminUser();
        $exporter = new testable_report_exporter($reportid);
        $this->assertEqualsCanonicalizing(['Admin User', 'John Smith'],
            array_column($exporter->get_table_rows(), 0));

        // User 0 can see all five users in their tenant (but can not see users in other tenants).
        $this->setUser($users[0]);
        $exporter = new testable_report_exporter($reportid);
        $this->assertEqualsCanonicalizing(['User 1', 'User 2', 'User 3', 'User 4', 'User 5'],
            array_column($exporter->get_table_rows(), 0));
    }

    /**
     * Create a report
     *
     * @param int $tenantid
     * @param bool $adddefault
     * @return int
     */
    protected function create_report(int $tenantid, bool $adddefault) : int {
        global $CFG;
        require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/reportbuilder/tests/fixtures/mock_report_users_list.php');
        // TODO: replace the mock_report_users_list with \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list
        // (as it was initially) when the tenant column in the user entity is not optional.
        return $this->get_generator()->create_report(
            ['source' => mock_report_users_list::class,
                'tenantid' => $tenantid,
                'adddefault' => (int)$adddefault])->get_id();
    }

    /**
     * Stress testing - add all available columns, try all possible aggregation methods.
     *
     * @coversNothing
     */
    public function test_stress_aggregation() {
        $users = $this->set_up_for_report();
        $reportid = $this->create_report(\tool_tenant\tenancy::get_tenant_id($users[0]), false);

        $generator = $this->get_generator();
        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_aggregation($reportid, $this);
    }

    /**
     * Stress testing - add all available conditions.
     *
     * @coversNothing
     */
    public function test_stress_conditions() {
        $users = $this->set_up_for_report();
        $reportid = $this->create_report(\tool_tenant\tenancy::get_tenant_id($users[0]), false);

        $generator = $this->get_generator();
        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing - add all available filters.
     */
    public function test_stress_filters() {
        $users = $this->set_up_for_report();
        $reportid = $this->create_report(\tool_tenant\tenancy::get_tenant_id($users[0]), false);

        $generator = $this->get_generator();
        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_filters($reportid, $this);
    }

    /**
     * Shared reports only display users from the current tenant and below
     */
    public function test_shared_report() {
        $this->resetAfterTest();

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->get_tenant_generator()->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->get_tenant_generator()->create_tenant_and_users(2);

        $this->get_generator()->assign_edit_capability($users1[0]->id);
        $this->setUser($users1[0]);

        // Create a report inside a tenant. User from this tenant will be able to see only their users.
        $report = $this->get_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
            'tenantid' => $tenant1->id,
            'adddefault' => (int)false,
        ]);

        $this->get_generator()->add_column($report, 'user:lastname');
        $this->get_generator()->add_column($report, 'user:email');

        $exporter = new testable_report_exporter($report->get_id());
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column($users1, 'lastname'), array_column($rows, 0));
        $this->assertEqualsCanonicalizing(array_column($users1, 'email'), array_column($rows, 1));

        // Create a report inside a tenant. User from this tenant will be able to see only their users.
        $report = $this->get_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
            'tenantid' => $sharedspaceid,
            'adddefault' => (int)false,
        ]);

        $this->get_generator()->add_column($report, 'user:lastname');
        $this->get_generator()->add_column($report, 'user:email');

        $exporter = new testable_report_exporter($report->get_id());
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column($users1, 'lastname'), array_column($rows, 0));
        $this->assertEqualsCanonicalizing(array_column($users1, 'email'), array_column($rows, 1));

        // Create a report in shared space. Admin will be able to see all users.
        $this->setAdminUser();
        \tool_tenant\tenancy::set_switched_tenant_id($sharedspaceid);
        $report = $this->get_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
            'tenantid' => $sharedspaceid,
            'adddefault' => (int)false,
            'shared' => true,
        ]);

        $this->get_generator()->add_column($report, 'user:lastname');
        $this->get_generator()->add_column($report, 'user:email');

        $exporter = new testable_report_exporter($report->get_id());
        $rows = $exporter->get_table_rows();
        $users = array_merge([get_admin()], $users1, $users2);
        $this->assertEqualsCanonicalizing(array_column($users, 'lastname'), array_column($rows, 0));
        $this->assertEqualsCanonicalizing(array_column($users, 'email'), array_column($rows, 1));
    }
}

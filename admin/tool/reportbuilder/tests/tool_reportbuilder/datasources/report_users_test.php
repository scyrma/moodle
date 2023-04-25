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
 * File containing tests for tool_reportbuilder_datasources_testcase class.
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use coding_exception;
use core_reportbuilder_testcase;
use mock_report_users_list;
use testable_report_exporter;
use tool_reportbuilder\external;
use tool_reportbuilder_generator;
use tool_tenant_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Tests for the datasource report_users_list
 *
 * @package     tool_reportbuilder
 * @covers      \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_users_test extends core_reportbuilder_testcase {

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

    /**
     * Check that each column / filter / condition can be converted to the core reportbuilder
     * @return void
     */
    public function test_convert_to_core_reportbuilder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->get_tenant_generator()->create_tenant(); // Make site multi-tenant.
        $this->get_generator()->datasource_test_convert_to_core_reportbuilder(report_users_list::class, $this);
    }

    /**
     * Check that sorted columns will be converted to the core reportbuilder
     */
    public function test_convert_to_core_reportbuilder_sorting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_user(['firstname' => 'Marie', 'lastname' => 'One', 'country' => 'AU']);
        $this->getDataGenerator()->create_user(['firstname' => 'Carlos', 'lastname' => 'Two', 'country' => 'AU']);
        $this->getDataGenerator()->create_user(['firstname' => 'Anne', 'lastname' => 'Three', 'country' => 'ES']);
        $this->getDataGenerator()->create_user(['firstname' => 'David', 'lastname' => 'Four', 'country' => 'VE']);
        $this->getDataGenerator()->create_user(['firstname' => 'Hector', 'lastname' => 'Five', 'country' => 'ES']);

        $report = $this->get_generator()->create_report([
            'source' => report_users_list::class,
            'adddefault' => (int)false,
        ]);
        $reportid = $report->get_id();

        // Add columns to report.
        $this->get_generator()->add_column($report, 'user:country');
        $this->get_generator()->add_column($report, 'user:firstname');
        $this->get_generator()->add_column($report, 'user:lastname');

        // Get report sortable columns.
        $columns = external::get_report_sortable_columns($reportid);
        $columns = external::clean_returnvalue(external::get_report_sortable_columns_returns(), $columns);
        $columns = $columns['columnsinuse'];

        // Enable sorting on the column country.
        $country = external::toggle_report_sorting_column($reportid, $columns[0]['id']);
        $country = external::clean_returnvalue(external::toggle_report_sorting_column_returns(), $country);
        $this->assertEquals(1, $country['newcolumnstate']);
        $this->assertEquals(SORT_ASC, $columns[0]['sortdirection']);

        // Assert new report return same sorted rows (country ASC).
        $newreport = $report->convert(false);
        $newreportvalues = array_map('array_values', $this->get_custom_report_content($newreport));
        $countries = array_map(function($r) {
            return $r[0];
        }, $newreportvalues);
        $this->assertEquals(['', 'Australia', 'Australia', 'Spain', 'Spain', 'Venezuela (Bolivarian Republic of)'], $countries);

        // Disable sorting on the column country.
        $country = external::toggle_report_sorting_column($reportid, $columns[0]['id']);
        $country = external::clean_returnvalue(external::toggle_report_sorting_column_returns(), $country);
        $this->assertEquals(0, $country['newcolumnstate']);

        // Enable sorting on the column firstname and change default direction.
        $firstname = external::toggle_report_sorting_column($reportid, $columns[1]['id']);
        $firstname = external::clean_returnvalue(external::toggle_report_sorting_column_returns(), $firstname);
        $this->assertEquals(1, $firstname['newcolumnstate']);

        // Set sorting by firstname DESC.
        $fndirection = external::toggle_column_sorting_direction($reportid, $columns[1]['id']);
        $fndirection = external::clean_returnvalue(external::toggle_column_sorting_direction_returns(), $fndirection);
        $this->assertEquals(SORT_DESC, $fndirection['newcolumnstate']);

        // Assert new report return same sorted rows (firstname DESC).
        $newreport = $report->convert(false);
        $newreportvalues = array_map('array_values', $this->get_custom_report_content($newreport));
        $users = array_map(function($r) {
            return $r[1];
        }, $newreportvalues);
        $this->assertEquals(['Marie', 'Hector', 'David', 'Carlos', 'Anne', 'Admin'], $users);

        // Change sorting by firstname ASC.
        $fndirection = external::toggle_column_sorting_direction($reportid, $columns[1]['id']);
        $fndirection = external::clean_returnvalue(external::toggle_column_sorting_direction_returns(), $fndirection);
        $this->assertEquals(SORT_ASC, $fndirection['newcolumnstate']);

        // Assert new report return same sorted rows (firstname ASC).
        $newreport = $report->convert(false);
        $newreportvalues = array_map('array_values', $this->get_custom_report_content($newreport));
        $users = array_map(function($r) {
            return $r[1];
        }, $newreportvalues);
        $this->assertEquals(['Admin', 'Anne', 'Carlos', 'David', 'Hector', 'Marie'], $users);
    }

    /**
     * Check that each profile field column/filter/condition can be converted to the core reportbuilder
     */
    public function test_convert_to_core_reportbuilder_profile_fields(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create user profile fields available in report_users_list datasource.
        $categoryid = $DB->insert_record('user_info_category', ['name' => 't']);
        $pftextarea = $DB->insert_record('user_info_field',
            ['shortname' => 'field1', 'name' => 'Name1', 'categoryid' => $categoryid, 'datatype' => 'textarea', 'visible' => 2]);
        $pftext = $DB->insert_record('user_info_field',
            ['shortname' => 'field2', 'name' => 'Name2', 'categoryid' => $categoryid, 'datatype' => 'text', 'visible' => 2]);
        $pfdatetime = $DB->insert_record('user_info_field',
            ['shortname' => 'field3', 'name' => 'Name3', 'categoryid' => $categoryid, 'datatype' => 'datetime', 'visible' => 2]);
        $pfcheckbox = $DB->insert_record('user_info_field',
            ['shortname' => 'field4', 'name' => 'Name4', 'categoryid' => $categoryid, 'datatype' => 'checkbox', 'visible' => 2]);
        $pfmenu = $DB->insert_record('user_info_field',
            ['shortname' => 'field5', 'name' => 'Name5', 'categoryid' => $categoryid,
                'param1' => "a\nb\nc", 'datatype' => 'menu', 'visible' => 2]);

        // Create a report.
        $report = $this->get_generator()->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
        ]);

        // Add profile fields as report columns.
        $this->get_generator()->add_column($report, "user:profilefield_{$pftextarea}");
        $this->get_generator()->add_column($report, "user:profilefield_{$pftext}");
        $this->get_generator()->add_column($report, "user:profilefield_{$pfdatetime}");
        $this->get_generator()->add_column($report, "user:profilefield_{$pfcheckbox}");
        $this->get_generator()->add_column($report, "user:profilefield_{$pfmenu}");

        // Add profile fields as report filters.
        $this->get_generator()->add_filter($report, "user:profilefield_field1_{$pftextarea}");
        $this->get_generator()->add_filter($report, "user:profilefield_field2_{$pftext}");
        $this->get_generator()->add_filter($report, "user:profilefield_field3_{$pfdatetime}");
        $this->get_generator()->add_filter($report, "user:profilefield_field4_{$pfcheckbox}");
        $this->get_generator()->add_filter($report, "user:profilefield_field5_{$pfmenu}");

        // Add profile fields as report conditions.
        $this->get_generator()->add_condition($report, "user:profilefield_field1_{$pftextarea}");
        $this->get_generator()->add_condition($report, "user:profilefield_field2_{$pftext}");
        $this->get_generator()->add_condition($report, "user:profilefield_field3_{$pfdatetime}");
        $this->get_generator()->add_condition($report, "user:profilefield_field4_{$pfcheckbox}");
        $this->get_generator()->add_condition($report, "user:profilefield_field5_{$pfmenu}");

        $this->get_generator()->datasource_test_convert_to_core_reportbuilder(report_users_list::class, $this);
    }
}

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
 * File containing tests for report_certifications datasource
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\tool_reportbuilder\datasources\report_certifications;
use tool_reportbuilder\local\helpers\aggregation;
use tool_reportbuilder\local\helpers\columns;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the datasource report_certifications
 *
 * @package    tool_certification
 * @covers     \tool_certification\tool_reportbuilder\datasources\report_certifications
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_datasource_report_certifications_testcase extends advanced_testcase {

    /**
     * Get report builder generator
     *
     * @return tool_certification_generator|component_generator_base
     * @throws coding_exception
     */
    protected function get_generator(): tool_certification_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_certification');
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
     * Returns the tenant generator
     *
     * @return tool_tenant_generator|component_generator_base
     * @throws coding_exception
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Returns the customfield generator
     *
     * @return core_customfield_generator|component_generator_base
     * @throws coding_exception
     */
    protected function get_customfield_generator(): core_customfield_generator {
        return self::getDataGenerator()->get_plugin_generator('core_customfield');
    }

    /**
     * Get program generator
     *
     * @return tool_program_generator|component_generator_base
     */
    public function get_program_generator(): tool_program_generator {
        return self::getDataGenerator()->get_plugin_generator('tool_program');
    }

    /**
     * This method is called after the last test of this test class is run.
     *
     * @return void
     */
    public static function tearDownAfterClass() {
        \tool_certification\customfield\certification_handler::create()->delete_all();
    }

    /**
     * Set up for the test
     */
    protected function set_up_for_report(): array {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $this->setUp();
        $this->resetAfterTest();

        $users = [];

        // Define one customfield.
        $params = [
            'component' => 'tool_certification',
            'area' => 'certification',
            'itemid' => 0,
            'contextid' => context_system::instance()->id
        ];
        $category = $this->get_customfield_generator()->create_category($params);
        $this->get_customfield_generator()->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld1']);

        // Create one user and one certification and allocate them to the default tenant.
        $user1 = self::getDataGenerator()->create_user()->id;
        $defaulttenantid = tenancy::get_default_tenant_id();
        $this->get_tenant_generator()->allocate_user($user1, $defaulttenantid);
        $params = ['tenantid' => $defaulttenantid, 'customfield_fld1' => 'Hello customfield'];
        $this->get_generator()->generate_certification($params);
        $users[] = $user1;

        // Create one more user and five more certifications and allocate them to the new tenant.
        $othertenantid = $this->get_tenant_generator()->create_tenant()->id;
        $params = ['tenantid' => $othertenantid];
        for ($i = 0; $i < 5; $i++) {
            $this->get_generator()->generate_certification($params)->get('id');
        }
        $user2 = self::getDataGenerator()->create_user()->id;
        $this->get_tenant_generator()->allocate_user($user2, $othertenantid);
        $users[] = $user2;

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

        // Create a report from the report_certifications datasource with default columns/conditions.
        $reportid = $generator->create_report(['source' => report_certifications::class])->get_id();

        // Execute report for different users.

        // User 0 can see the only certification in the default tenant (but can not see certification in other tenants).
        self::setUser($users[0]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertCount(1, $rows);

        // User 1 can see all five certifications in its tenant (but can not see certifications in other tenants).
        self::setUser($users[1]);
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertCount(5, $rows);
    }

    /**
     * Convert tags column HTML content into array of plaintext values
     *
     * @param string $tags
     * @return string[]
     */
    private static function extract_tags(string $tags) : array {
        preg_match_all('/<span[^>]*>([^<]*)<\/span>/', $tags, $matches);

        return $matches[1];
    }

    /**
     * Test certification tag aggregation
     *
     * @return void
     */
    public function test_tag_groupconcat_aggregation() : void {
        global $DB;

        // Not available in MSSQL.
        if (strcasecmp($DB->get_dbfamily(), 'mssql') === 0) {
            $this->markTestSkipped('MSSQL cannot aggregate columns containing sub-queries');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->get_generator()->generate_certification(['fullname' => 'Cert 1', 'certification_tags' => ['hello', 'moon']]);
        $this->get_generator()->generate_certification(['fullname' => 'Cert 2', 'certification_tags' => ['hello', 'world']]);

        // Set up the report, containing a single column (tags).
        $report = $this->get_reportbuilder_generator()->create_report([
            'source' => report_certifications::class,
            'adddefault' => 0,
        ]);

        $column = columns::add_column_from_key($report, 'tool_certification:tags');

        // Setting aggregation method to groupconcat should return a single row containing all tags.
        aggregation::set_aggregation($column->get('id'), 'groupconcat');

        $rows = (new testable_report_exporter($report->get_id()))->get_table_rows();
        $this->assertCount(1, $rows);

        $tags = self::extract_tags(reset($rows)[0]);
        $this->assertEqualsCanonicalizing(['hello', 'moon', 'hello', 'world'], $tags);
    }

    /**
     * Test certification distinct tag aggregation
     *
     * @return void
     */
    public function test_tag_groupconcatdistinct_aggregation() : void {
        global $DB;

        // Not available in MSSQL.
        if (strcasecmp($DB->get_dbfamily(), 'mssql') === 0) {
            $this->markTestSkipped('MSSQL cannot aggregate columns containing sub-queries');
        } else if (!\tool_reportbuilder\local\aggregate\groupconcatdistinct::is_compatible(null)) {
            $this->markTestSkipped($DB->get_dbfamily() . ' does not currently support group concat distinct');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->get_generator()->generate_certification(['fullname' => 'Cert 1', 'certification_tags' => ['hello', 'moon']]);
        $this->get_generator()->generate_certification(['fullname' => 'Cert 2', 'certification_tags' => ['hello', 'world']]);

        // Set up the report, containing a single column (tags).
        $report = $this->get_reportbuilder_generator()->create_report([
            'source' => report_certifications::class,
            'adddefault' => 0,
        ]);

        $column = columns::add_column_from_key($report, 'tool_certification:tags');

        // Setting aggregation method to groupconcatdistinct should return a single row containing unique tags.
        aggregation::set_aggregation($column->get('id'), 'groupconcatdistinct');

        $rows = (new testable_report_exporter($report->get_id()))->get_table_rows();
        $this->assertCount(1, $rows);

        $tags = self::extract_tags(reset($rows)[0]);
        $this->assertEqualsCanonicalizing(['hello', 'moon', 'world'], $tags);
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
            'source' => report_certifications::class,
            'tenantid' => $tenantid,
            'adddefault' => (int) $adddefault
        ])->get_id();
    }

    /**
     * Stress testing - add all available columns, try all possible aggregation methods.
     */
    public function test_stress_aggregation(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();
        self::setUser($users[0]);

        // Create a report from the report_certifications datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_aggregation($reportid, $this);
    }

    /**
     * Stress testing - add all available conditions.
     */
    public function test_stress_conditions(): void {
        $users = $this->set_up_for_report();
        $generator = $this->get_reportbuilder_generator();
        self::setUser($users[0]);

        // Create a report from the report_certifications datasource without default columns/conditions.
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

        // Create a report from the report_certifications datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $generator->add_all_available_columns_to_report($reportid);
        $generator->datasource_stress_test_filters($reportid, $this);
    }

    /**
     * Shared certifications only display users from the current tenant and below
     */
    public function test_shared_certifications() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $this->resetAfterTest();

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->get_tenant_generator()->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->get_tenant_generator()->create_tenant_and_users(2);
        $program = $this->get_program_generator()->generate_program((object)[
            'tenantid' => $sharedspaceid, 'fullname' => 'Sharedprogram']);
        $certification = $this->get_generator()->generate_certification([
            'tenantid' => $sharedspaceid, 'fullname' => 'Sharedcertification', 'program' => $program->get('id')]);
        $certification2 = $this->get_generator()->generate_certification([
            'tenantid' => $sharedspaceid, 'fullname' => 'NOTvisible', 'program' => $program->get('id')]);
        $DB->update_record('tool_certification', (object)['shared' => 0, 'id' => $certification2->get('id')]);

        $this->get_generator()->allocate_users_to_certification($certification->get('id'),
            array_column(array_merge($users1, $users2), 'id'));
        $this->get_reportbuilder_generator()->assign_edit_capability($users1[0]->id);

        // Create a report inside a tenant. User from this tenant will be able to see one shared certification with
        // only their users.
        $this->setUser($users1[0]);
        $reportid = $this->create_report(tenancy::get_tenant_id($users1[0]->id), false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->get_reportbuilder_generator()->add_column($report, 'tool_certification:fullname');
        $this->get_reportbuilder_generator()->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column($users1, 'username'), array_column($rows, 1));
        $this->assertEquals(['Sharedcertification'], array_unique(array_column($rows, 0)));

        // Create a report in shared space. Admin will be able to see both certifications and all users.
        $this->setAdminUser();
        tenancy::set_switched_tenant_id($sharedspaceid);
        $reportid = $this->create_report($sharedspaceid, false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->get_reportbuilder_generator()->add_column($report, 'tool_certification:fullname');
        $this->get_reportbuilder_generator()->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column(array_merge($users1, $users2), 'username'),
            array_filter(array_column($rows, 1)));
        $this->assertEqualsCanonicalizing(['NOTvisible', 'Sharedcertification'], array_unique(array_column($rows, 0)));
    }

    /**
     * Shared certifications only display users from the current tenant and below
     */
    public function test_certifications_on_shared_programs() {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        $this->resetAfterTest();

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->get_tenant_generator()->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->get_tenant_generator()->create_tenant_and_users(2);
        $program = $this->get_program_generator()->generate_program((object)[
            'tenantid' => $sharedspaceid, 'fullname' => 'Sharedprogram']);
        $certification = $this->get_generator()->generate_certification([
            'tenantid' => $tenant1->id, 'fullname' => 'Normalcertification', 'program' => $program->get('id')]);

        $this->get_generator()->allocate_users_to_certification($certification->get('id'),
            array_column($users1, 'id'));
        $this->get_reportbuilder_generator()->assign_edit_capability($users1[0]->id);

        // Create a report inside a tenant. User from this tenant will be able to see one shared certification.
        $this->setUser($users1[0]);
        $reportid = $this->create_report(tenancy::get_tenant_id($users1[0]->id), false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->get_reportbuilder_generator()->add_column($report, 'tool_certification:fullname');
        $this->get_reportbuilder_generator()->add_column($report, 'tool_program:fullname');
        $this->get_reportbuilder_generator()->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column($users1, 'username'), array_column($rows, 2));
        $this->assertEquals(['Normalcertification'], array_unique(array_column($rows, 0)));
        $this->assertEquals(['Sharedprogram'], array_unique(array_column($rows, 1)));
    }
}

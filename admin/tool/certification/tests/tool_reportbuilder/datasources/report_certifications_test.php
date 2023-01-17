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

namespace tool_certification\tool_reportbuilder\datasources;

use advanced_testcase;
use context_system;
use core_customfield_generator;
use testable_report_exporter;
use tool_certification_generator;
use tool_program_generator;
use tool_reportbuilder_generator;
use tool_tenant_generator;
use tool_reportbuilder\local\helpers\aggregation;
use tool_reportbuilder\local\helpers\columns;
use tool_tenant\tenancy;

/**
 * Tests for the datasource report_certifications
 *
 * @package    tool_certification
 * @covers     \tool_certification\tool_reportbuilder\datasources\report_certifications
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_certifications_test extends advanced_testcase {

    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var tool_reportbuilder_generator */
    protected $rbgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var core_customfield_generator */
    protected $cfgenerator;

    /**
     * This method is called after the last test of this test class is run.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void {
        \tool_certification\customfield\certification_handler::create()->delete_all();
        \tool_program\customfield\program_handler::create()->delete_all();
    }

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->rbgenerator = self::getDataGenerator()->get_plugin_generator('tool_reportbuilder');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->cfgenerator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $this->resetAfterTest();
        \tool_certification\customfield\certification_handler::create()->delete_all();
        \tool_program\customfield\program_handler::create()->delete_all();
    }

    /**
     * Set up for the test
     */
    protected function set_up_for_report(): array {
        global $CFG;
        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

        $users = [];

        // Define one customfield.
        $params = [
            'component' => 'tool_certification',
            'area' => 'certification',
            'itemid' => 0,
            'contextid' => context_system::instance()->id,
        ];
        $category = $this->cfgenerator->create_category($params);
        $this->cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld1']);

        // Create one user and one certification and allocate them to the default tenant.
        $user1 = self::getDataGenerator()->create_user()->id;
        $defaulttenantid = tenancy::get_default_tenant_id();
        $this->tenantgenerator->allocate_user($user1, $defaulttenantid);
        $params = ['tenantid' => $defaulttenantid, 'customfield_fld1' => 'Hello customfield'];
        $this->generator->generate_certification($params);
        $users[] = $user1;

        // Create one more user and five more certifications and allocate them to the new tenant.
        $othertenantid = $this->tenantgenerator->create_tenant()->id;
        $params = ['tenantid' => $othertenantid];
        for ($i = 0; $i < 5; $i++) {
            $this->generator->generate_certification($params)->get('id');
        }
        $user2 = self::getDataGenerator()->create_user()->id;
        $this->tenantgenerator->allocate_user($user2, $othertenantid);
        $users[] = $user2;

        // First user should have capability to edit reports.
        $this->rbgenerator->assign_edit_capability($users[0]);

        return $users;
    }

    /**
     * System capabilities and manager permissions
     */
    public function test_permissions(): void {
        $users = $this->set_up_for_report();

        // Create a report from the report_certifications datasource with default columns/conditions.
        $reportid = $this->rbgenerator->create_report(['source' => report_certifications::class])->get_id();

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
    private static function extract_tags(string $tags): array {
        preg_match_all('/<span[^>]*>([^<]*)<\/span>/', $tags, $matches);

        return $matches[1];
    }

    /**
     * Test certification tag aggregation
     */
    public function test_tag_groupconcat_aggregation(): void {
        global $DB;

        // Not available in MSSQL.
        if (strcasecmp($DB->get_dbfamily(), 'mssql') === 0) {
            $this->markTestSkipped('MSSQL cannot aggregate columns containing sub-queries');
        }

        $this->setAdminUser();

        $this->generator->generate_certification(['fullname' => 'Cert 1', 'certification_tags' => ['hello', 'moon']]);
        $this->generator->generate_certification(['fullname' => 'Cert 2', 'certification_tags' => ['hello', 'world']]);

        // Set up the report, containing a single column (tags).
        $report = $this->rbgenerator->create_report([
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
     */
    public function test_tag_groupconcatdistinct_aggregation(): void {
        global $DB;

        // Not available in MSSQL.
        if (strcasecmp($DB->get_dbfamily(), 'mssql') === 0) {
            $this->markTestSkipped('MSSQL cannot aggregate columns containing sub-queries');
        } else if (!\tool_reportbuilder\local\aggregate\groupconcatdistinct::is_compatible(null)) {
            $this->markTestSkipped($DB->get_dbfamily() . ' does not currently support group concat distinct');
        }

        $this->setAdminUser();

        $this->generator->generate_certification(['fullname' => 'Cert 1', 'certification_tags' => ['hello', 'moon']]);
        $this->generator->generate_certification(['fullname' => 'Cert 2', 'certification_tags' => ['hello', 'world']]);

        // Set up the report, containing a single column (tags).
        $report = $this->rbgenerator->create_report([
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
        return $this->rbgenerator->create_report([
            'source' => report_certifications::class,
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
        self::setUser($users[0]);

        // Create a report from the report_certifications datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $this->rbgenerator->add_all_available_columns_to_report($reportid);
        $this->rbgenerator->datasource_stress_test_aggregation($reportid, $this);
    }

    /**
     * Stress testing - add all available conditions.
     *
     * @coversNothing
     */
    public function test_stress_conditions(): void {
        $users = $this->set_up_for_report();
        self::setUser($users[0]);

        // Create a report from the report_certifications datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $this->rbgenerator->add_all_available_columns_to_report($reportid);
        $this->rbgenerator->datasource_stress_test_conditions($reportid, $this);
    }

    /**
     * Stress testing - add all available filters.
     *
     * @coversNothing
     */
    public function test_stress_filters(): void {
        $users = $this->set_up_for_report();
        self::setUser($users[0]);

        // Create a report from the report_certifications datasource without default columns/conditions.
        $reportid = $this->create_report(tenancy::get_tenant_id($users[0]), false);

        $this->rbgenerator->add_all_available_columns_to_report($reportid);
        $this->rbgenerator->datasource_stress_test_filters($reportid, $this);
    }

    /**
     * Shared certifications only display users from the current tenant and below
     */
    public function test_shared_certifications() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $program = $this->programgenerator->generate_program((object)[
            'tenantid' => $sharedspaceid, 'fullname' => 'Sharedprogram']);
        $certification = $this->generator->generate_certification([
            'tenantid' => $sharedspaceid, 'fullname' => 'Sharedcertification', 'program' => $program->get('id')]);
        $certification2 = $this->generator->generate_certification([
            'tenantid' => $sharedspaceid, 'fullname' => 'NOTvisible', 'program' => $program->get('id')]);
        $DB->update_record('tool_certification', (object)['shared' => 0, 'id' => $certification2->get('id')]);

        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column(array_merge($users1, $users2), 'id'));
        $this->rbgenerator->assign_edit_capability($users1[0]->id);

        // Create a report inside a tenant. User from this tenant will be able to see one shared certification with
        // only their users.
        $this->setUser($users1[0]);
        $reportid = $this->create_report(tenancy::get_tenant_id($users1[0]->id), false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->rbgenerator->add_column($report, 'tool_certification:fullname');
        $this->rbgenerator->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column($users1, 'username'), array_column($rows, 1));
        $this->assertEquals(['Sharedcertification'], array_unique(array_column($rows, 0)));

        // Create a report in shared space. Admin will be able to see both certifications and all users.
        $this->setAdminUser();
        tenancy::set_switched_tenant_id($sharedspaceid);
        $reportid = $this->create_report($sharedspaceid, false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->rbgenerator->add_column($report, 'tool_certification:fullname');
        $this->rbgenerator->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column(array_merge($users1, $users2), 'username'),
            array_filter(array_column($rows, 1)));
        $this->assertEqualsCanonicalizing(['NOTvisible', 'Sharedcertification'], array_unique(array_column($rows, 0)));
    }

    /**
     * Shared certifications only display users from the current tenant and below
     */
    public function test_certifications_on_shared_programs(): void {
        global $CFG;
        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant1, $users1] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $program = $this->programgenerator->generate_program((object)[
            'tenantid' => $sharedspaceid, 'fullname' => 'Sharedprogram']);
        $certification = $this->generator->generate_certification([
            'tenantid' => $tenant1->id, 'fullname' => 'Normalcertification', 'program' => $program->get('id')]);

        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column($users1, 'id'));
        $this->rbgenerator->assign_edit_capability($users1[0]->id);

        // Create a report inside a tenant. User from this tenant will be able to see one shared certification.
        $this->setUser($users1[0]);
        $reportid = $this->create_report(tenancy::get_tenant_id($users1[0]->id), false);
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $this->rbgenerator->add_column($report, 'tool_certification:fullname');
        $this->rbgenerator->add_column($report, 'tool_program:fullname');
        $this->rbgenerator->add_column($report, 'user:username');
        $exporter = new testable_report_exporter($reportid);
        $rows = $exporter->get_table_rows();
        $this->assertEqualsCanonicalizing(array_column($users1, 'username'), array_column($rows, 2));
        $this->assertEquals(['Normalcertification'], array_unique(array_column($rows, 0)));
        $this->assertEquals(['Sharedprogram'], array_unique(array_column($rows, 1)));
    }

    /**
     * Check that each column / filter / condition can be converted to the core reportbuilder
     * @return void
     */
    public function test_convert_to_core_reportbuilder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->tenantgenerator->create_tenant(); // Make site multi-tenant.
        $this->rbgenerator->datasource_test_convert_to_core_reportbuilder(report_certifications::class, $this);
    }

    /**
     * Check that each custom field column/filter/condition can be converted to the core reportbuilder
     */
    public function test_convert_to_core_reportbuilder_custom_fields(): void {
        $this->setAdminUser();
        $this->tenantgenerator->create_tenant(); // Make site multi-tenant.

        // Create certification custom fields available in report_users_list datasource.
        $params = [
            'component' => 'tool_certification',
            'area' => 'certification',
            'itemid' => 0,
            'name' => 'Certification custom field',
            'contextid' => context_system::instance()->id
        ];
        $category = $this->cfgenerator->create_category($params);
        $categoryid = $category->get('id');

        $cftextarea = $this->cfgenerator->create_field(['shortname' => 'field1', 'name' => 'Name1',
                'type' => 'textarea', 'categoryid' => $categoryid]);
        $cftextareaid = $cftextarea->get('id');

        $cftext = $this->cfgenerator->create_field(['shortname' => 'field2', 'name' => 'Name2',
            'type' => 'text', 'categoryid' => $categoryid]);
        $cftextid = $cftext->get('id');

        $cfdatetime = $this->cfgenerator->create_field(['shortname' => 'field3', 'name' => 'Name3',
            'type' => 'date', 'categoryid' => $categoryid]);
        $cfdatetimeid = $cfdatetime->get('id');

        $cfcheckbox = $this->cfgenerator->create_field(['shortname' => 'field4', 'name' => 'Name4',
            'type' => 'checkbox', 'categoryid' => $categoryid]);
        $cfcheckboxid = $cfcheckbox->get('id');

        $cfmenu = $this->cfgenerator->create_field(['shortname' => 'field5', 'name' => 'Name5',
            'param1' => "a\nb\nc", 'type' => 'select', 'categoryid' => $categoryid]);
        $cfmenuid = $cfmenu->get('id');

        // Create a report.
        $report = $this->rbgenerator->create_report([
            'source' => report_certifications::class,
        ]);

        // Add certification custom fields as report columns.
        $this->rbgenerator->add_column($report, "tool_certification:tcid{$cftextareaid}");
        $this->rbgenerator->add_column($report, "tool_certification:tcid{$cftextid}");
        $this->rbgenerator->add_column($report, "tool_certification:tcid{$cfdatetimeid}");
        $this->rbgenerator->add_column($report, "tool_certification:tcid{$cfcheckboxid}");
        $this->rbgenerator->add_column($report, "tool_certification:tcid{$cfmenuid}");

        // Add certification custom fields as report filters.
        $this->rbgenerator->add_filter($report, "tool_certification:tcid{$cftextareaid}");
        $this->rbgenerator->add_filter($report, "tool_certification:tcid{$cftextid}");
        $this->rbgenerator->add_filter($report, "tool_certification:tcid{$cfdatetimeid}");
        $this->rbgenerator->add_filter($report, "tool_certification:tcid{$cfcheckboxid}");
        $this->rbgenerator->add_filter($report, "tool_certification:tcid{$cfmenuid}");

        // Add certification custom fields as report conditions.
        $this->rbgenerator->add_condition($report, "tool_certification:tcid{$cftextareaid}");
        $this->rbgenerator->add_condition($report, "tool_certification:tcid{$cftextid}");
        $this->rbgenerator->add_condition($report, "tool_certification:tcid{$cfdatetimeid}");
        $this->rbgenerator->add_condition($report, "tool_certification:tcid{$cfcheckboxid}");
        $this->rbgenerator->add_condition($report, "tool_certification:tcid{$cfmenuid}");

        $this->rbgenerator->datasource_test_convert_to_core_reportbuilder(report_certifications::class, $this);
    }
}

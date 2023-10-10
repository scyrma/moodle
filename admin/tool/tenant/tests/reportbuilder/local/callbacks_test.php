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

declare(strict_types=1);

namespace tool_tenant\reportbuilder\local;

use advanced_testcase;
use cache_helper;
use core_reportbuilder\external\reports\listing;
use core_reportbuilder_generator;
use tool_tenant\sharedspace;
use tool_tenant_generator;
use tool_tenant\tenancy;
use core_reportbuilder\permission;
use core_user\reportbuilder\datasource\users;
use core_reportbuilder\local\models\schedule;

/**
 * Test for tenant callbacks for core reportbuilder
 *
 * @package    tool_tenant
 * @covers     \core_reportbuilder\permission
 * @covers     \tool_tenant\reportbuilder\local\callbacks
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Paul Holden <paulh@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class callbacks_test extends advanced_testcase {

    /**
     * Helper method to create a report with audience set to "All users"
     *
     * @param int|null $tenantid
     * @param array $params
     * @return array array of two elements - report and audience
     */
    private function create_report_with_audience(?int $tenantid = null, array $params = []): array {

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $report = $generator->create_report($params + ['name' => 'My report', 'source' => users::class], $tenantid);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $audience = $generator->create_audience(['reportid' => $report->get('id'), 'configdata' => []]);

        return [$report, $audience];
    }

    /**
     * Test current tenant is set for report
     */
    public function test_set_report_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        tenancy::set_switched_tenant_id($tenant->id);

        callbacks::set_report_tenant($report = (object) []);
        $this->assertEquals((object) ['component' => 'tool_tenant', 'itemid' => $tenant->id], $report);
    }

    /**
     * Test viewing report in same tenant
     */
    public function test_can_view_report_in_same_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$report] = $this->create_report_with_audience();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->setUser($generator->create_user());

        $this->assertNull(callbacks::override_can_view_report($report));

        // Ensure original permission method we added our callback to also returns the same result.
        $this->assertTrue(permission::can_view_report($report));
    }

    /**
     * Test viewing report in different tenant
     */
    public function test_can_view_report_in_different_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        tenancy::set_switched_tenant_id($tenant->id);

        // Since we switched tenant, the report will be created in it.
        [$report] = $this->create_report_with_audience();

        // Admin can access this report.
        $this->assertTrue(callbacks::override_can_view_report($report));
        $this->assertTrue(permission::can_view_report($report));

        // Switch back to default tenant.
        tenancy::set_switched_tenant_id(tenancy::get_default_tenant_id());

        // Admin should not be able to access this report.
        $this->assertFalse(callbacks::override_can_view_report($report));
        $this->assertFalse(permission::can_view_report($report));

        // Now create a user in default tenant.
        $this->setUser($generator->create_user());

        $this->assertFalse(callbacks::override_can_view_report($report));
        $this->assertFalse(permission::can_view_report($report));
    }

    /**
     * Test editing report in same tenant
     */
    public function test_can_edit_report_in_same_tenant(): void {
        $this->resetAfterTest();

        [$report] = $this->create_report_with_audience();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->setUser($generator->create_user(['tenantadmin' => true]));

        $this->assertNull(callbacks::override_can_edit_report($report));

        // Ensure original permission method we added our callback to also returns the same result.
        $this->assertTrue(permission::can_edit_report($report));
    }

    /**
     * Test editing report in different tenant
     */
    public function test_can_edit_report_in_different_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        tenancy::set_switched_tenant_id($tenant->id);

        // Since we switched tenant, the report will be created in it.
        $report = $generator->create_report(['name' => 'My report', 'source' => users::class]);

        // Admin is able to edit the new report when switched to the report tenant.
        $this->assertNull(callbacks::override_can_edit_report($report));
        $this->assertTrue(permission::can_edit_report($report));

        // Switch back to default tenant.
        tenancy::set_switched_tenant_id(tenancy::get_default_tenant_id());

        // Admin can not edit the report.
        $this->assertFalse(callbacks::override_can_edit_report($report));
        $this->assertFalse(permission::can_edit_report($report));

        // Now create a user in default tenant.
        $this->setUser($generator->create_user(['tenantadmin' => true]));

        $this->assertFalse(callbacks::override_can_edit_report($report));
        $this->assertFalse(permission::can_edit_report($report));
    }

    /**
     * Test viewing and editing callbacks for the shared reports
     */
    public function test_can_view_and_edit_shared_report(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $generator->create_tenant();
        $tenantadmin = $generator->create_user(['tenantadmin' => true, 'tenantid' => $tenant->id]);
        $sharedspaceid = sharedspace::enable_shared_space();
        $defaulttenantid = tenancy::get_default_tenant_id();

        // Create one report in tenant and two reports in shared space (one "available for all tenants" and one not).
        tenancy::set_switched_tenant_id($tenant->id);
        $report = $generator->create_report(['name' => 'Report1', 'source' => users::class]);
        tenancy::set_switched_tenant_id($sharedspaceid);
        $alltenantreport = $generator->create_report(['name' => 'Report2', 'source' => users::class, 'area' => 'shared']);
        $adminreport = $generator->create_report(['name' => 'Report3', 'source' => users::class]);

        // Admin in shared space can view and edit shared space reports.
        $this->assertNull(callbacks::override_can_edit_report($alltenantreport));
        $this->assertTrue(callbacks::override_can_view_report($alltenantreport));
        $this->assertNull(callbacks::override_can_edit_report($adminreport));
        $this->assertTrue(callbacks::override_can_view_report($adminreport));

        // Admin in shared space can not view or edit reports in other tenants.
        $this->assertFalse(callbacks::override_can_view_report($report));
        $this->assertFalse(callbacks::override_can_edit_report($report));

        // Switch to the tenant where $report is defined.
        tenancy::set_switched_tenant_id($tenant->id);

        // Admin still can view shared reports but not edit.
        $this->assertFalse(callbacks::override_can_edit_report($alltenantreport));
        $this->assertTrue(callbacks::override_can_view_report($alltenantreport));
        // Admin can not view or edit the report from shared space that is marked as not available in all tenants.
        $this->assertFalse(callbacks::override_can_edit_report($adminreport));
        $this->assertFalse(callbacks::override_can_view_report($adminreport));
        // Admin can view and edit reports from the same tenant.
        $this->assertTrue(callbacks::override_can_view_report($report));
        $this->assertNull(callbacks::override_can_edit_report($report));

        // Switch to the default tenant.
        tenancy::set_switched_tenant_id(tenancy::get_actual_tenant_id());

        // Admin can not view or edit report from another tenant.
        $this->assertFalse(callbacks::override_can_view_report($report));
        $this->assertFalse(callbacks::override_can_edit_report($report));

        // Tenant administrator.
        $this->setUser($tenantadmin);
        $this->assertEquals($tenant->id, tenancy::get_tenant_id());

        // User can view shared report that is "Available in all tenants" and can view and edit own tenant's reports.
        $this->assertFalse(callbacks::override_can_edit_report($alltenantreport));
        $this->assertTrue(callbacks::override_can_view_report($alltenantreport));
        $this->assertFalse(callbacks::override_can_edit_report($adminreport));
        $this->assertFalse(callbacks::override_can_view_report($adminreport));
        $this->assertTrue(callbacks::override_can_view_report($report));
        $this->assertNull(callbacks::override_can_edit_report($report));
    }

    /**
     * Tests for WS core_reportbuilder_list_reports
     *
     * @return void
     */
    public function test_core_reportbuilder_list_reports() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Generate two tenants with reports and also shared reports. Set audience to "all users" to all reports.
        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant1, [$user1]] = $generator->create_tenant_and_users(1);
        $tenant2 = $generator->create_tenant();
        $this->create_report_with_audience($tenant1->id, ['name' => 'R1']);
        $this->create_report_with_audience($tenant2->id, ['name' => 'R2']);
        $sharedspaceid = sharedspace::enable_shared_space();
        $this->create_report_with_audience($sharedspaceid, ['name' => 'Shared', 'area' => 'shared']);
        $this->create_report_with_audience($sharedspaceid, ['name' => 'Non-shared']);

        // User from the first tenant can see their tenant's report and shared report from the shared space.
        $this->setUser($user1);
        $result = listing::execute();
        $result = \external_api::clean_returnvalue(listing::execute_returns(), $result);
        $this->assertEquals(['R1', 'Shared'], array_column($result['reports'], 'name'));

        // Admin who switched to the shared space can see reports created there.
        $this->setAdminUser();
        tenancy::set_switched_tenant_id($sharedspaceid);
        $result = listing::execute();
        $result = \external_api::clean_returnvalue(listing::execute_returns(), $result);
        $this->assertEquals(['Non-shared', 'Shared'], array_column($result['reports'], 'name'));
    }

    /**
     * Data provider for {@see self::test_execute_report_viewas_user()}
     *
     * @return array[]
     */
    public function execute_report_viewas_user_provider(): array {
        return [
            'View report as schedule creator' => [
                schedule::REPORT_VIEWAS_CREATOR,
                null,
                "Username,\"Tenant name\"\nadmin,\"Default tenant\"\nuserone,\"New tenant 1\"\nusertwo,\"New tenant 2\"\n",
                "Username,\"Tenant name\"\nadmin,\"Default tenant\"\nuserone,\"New tenant 1\"\nusertwo,\"New tenant 2\"\n",
                "Username,\"Tenant name\"\nadmin,\"Default tenant\"\nuserone,\"New tenant 1\"\nusertwo,\"New tenant 2\"\n",
            ],
            'View report as schedule recipient' => [
                schedule::REPORT_VIEWAS_RECIPIENT,
                null,
                "Username,\"Tenant name\"\nadmin,\"Default tenant\"\nuserone,\"New tenant 1\"\nusertwo,\"New tenant 2\"\n",
                "Username\nuserone\n",
                "Username\nusertwo\n",
            ],
            'View report as specific user' => [
                null,
                'userone',
                "Username\nuserone\n",
                "Username\nuserone\n",
                "Username\nuserone\n",
            ],
        ];
    }

    /**
     * Test executing task for a schedule with differing "View as user" configuration
     *
     * @param int|null $viewasuser
     * @param string|null $viewasusername
     * @param string $adminsees
     * @param string $useronesees
     * @param string $usertwosees
     *
     * @dataProvider execute_report_viewas_user_provider
     */
    public function test_execute_report_viewas_user(
        ?int $viewasuser,
        ?string $viewasusername,
        string $adminsees,
        string $useronesees,
        string $usertwosees
    ): void {
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant1, [$userone]] = $generator->create_tenant_and_users(1, null, ['username' => 'userone', 'email' => 'u1@ex.com']);
        [$tenant2, [$usertwo]] = $generator->create_tenant_and_users(1, null, ['username' => 'usertwo', 'email' => 'u2@ex.com']);
        $sharedspaceid = sharedspace::enable_shared_space();

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');

        // Create a report with columns 'user:username' and 'tenant:name' sorted by username.
        [$report, $audience] = $this->create_report_with_audience($sharedspaceid,
            ['name' => 'T', 'source' => users::class, 'default' => false, 'area' => 'shared']);
        $c1 = $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:username']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'tenant:name']);
        \core_reportbuilder\local\helpers\report::toggle_report_column_sorting(
            $report->get('id'), $c1->get('id'), true);

        // If "View as user" isn't specified, it should be the ID of the given "View as username".
        if ($viewasuser === null) {
            $viewasuser = \core_user::get_user_by_username($viewasusername, '*', null, MUST_EXIST)->id;
        }
        $schedule = $generator->create_schedule([
            'reportid' => $report->get('id'),
            'name' => 'My schedule',
            'userviewas' => $viewasuser,
            'audiences' => json_encode([$audience->get_persistent()->get('id')]),
        ]);

        \core_reportbuilder\manager::reset_caches();

        // Send the schedule, catch emails in sink (noting the users are sorted alphabetically).
        $sink = $this->redirectEmails();

        $this->expectOutputRegex("/^Sending schedule: /");
        $sendschedule = new \core_reportbuilder\task\send_schedule();
        $sendschedule->set_custom_data(['reportid' => $report->get('id'), 'scheduleid' => $schedule->get('id')]);
        $sendschedule->execute();

        $messages = $sink->get_messages();
        $this->assertCount(3, $messages);

        $sink->close();

        // Ensure caught messages are consistently ordered by recipient email prior to assertions.
        \core_collator::asort_objects_by_property($messages, 'to');
        $messages = array_values($messages);

        $messageattachment = self::extract_message_attachment($messages[0]->body);
        $this->assertEquals(get_admin()->email, $messages[0]->to);
        $this->assertStringEndsWith($adminsees, $messageattachment);

        $messageoneattachment = self::extract_message_attachment($messages[1]->body);
        $this->assertEquals($userone->email, $messages[1]->to);
        $this->assertStringEndsWith($useronesees, $messageoneattachment);

        $messagetwoattachment = self::extract_message_attachment($messages[2]->body);
        $this->assertEquals($usertwo->email, $messages[2]->to);
        $this->assertStringEndsWith($usertwosees, $messagetwoattachment);
    }

    /**
     * Testing filtering categories of current tenant
     */
    public function test_filter_by_tenant_category(): void {
        global $DB;

        $this->resetAfterTest();

        // Remove initial capability from users.
        $userroleid = $DB->get_field('role', 'id', ['shortname' => 'user']);
        unassign_capability('moodle/category:viewcourselist', $userroleid);

        $tenantcategory = $this->getDataGenerator()->create_category();
        $tenantcategorychild = $this->getDataGenerator()->create_category(['parent' => $tenantcategory->id]);

        $nontenantcategory = $this->getDataGenerator()->create_category();
        $nontenantcategorychild = $this->getDataGenerator()->create_category(['parent' => $nontenantcategory->id]);

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        [$tenant, [$tenantuser]] = $generator->create_tenant_and_users(1, ['categoryid' => $tenantcategory->id]);
        $this->setUser($tenantuser);

        [$select, $params] = callbacks::filter_by_tenant_category('cc.id');
        $categories = $DB->get_fieldset_sql("SELECT cc.id FROM {course_categories} cc WHERE {$select}", $params);

        $this->assertEqualsCanonicalizing([
            $tenantcategory->id,
            $tenantcategorychild->id,
        ], $categories);

        // Grant capability to view courses in non-tenant category.
        assign_capability('moodle/category:viewcourselist', CAP_ALLOW, $userroleid, $nontenantcategory->get_context(), true);
        cache_helper::purge_by_event('changesincoursecat');

        [$select, $params] = callbacks::filter_by_tenant_category('cc.id');
        $categories = $DB->get_fieldset_sql("SELECT cc.id FROM {course_categories} cc WHERE {$select}", $params);

        $this->assertEqualsCanonicalizing([
            $tenantcategory->id,
            $tenantcategorychild->id,
            $nontenantcategory->id,
            $nontenantcategorychild->id,
        ], $categories);
    }

    /**
     * Testing filtering courses of current tenant
     */
    public function test_filter_by_tenant_courses(): void {
        global $DB;

        $this->resetAfterTest();

        // Remove initial capability from users.
        $userroleid = $DB->get_field('role', 'id', ['shortname' => 'user']);
        unassign_capability('moodle/category:viewcourselist', $userroleid);

        $tenantcategory = $this->getDataGenerator()->create_category();
        $tenantcategorychild = $this->getDataGenerator()->create_category(['parent' => $tenantcategory->id]);

        $tenantcourse = $this->getDataGenerator()->create_course(['category' => $tenantcategory->id]);
        $tenantcoursechild = $this->getDataGenerator()->create_course(['category' => $tenantcategorychild->id]);

        $nontenantcategory = $this->getDataGenerator()->create_category();
        $nontenantcategorychild = $this->getDataGenerator()->create_category(['parent' => $nontenantcategory->id]);
        $nontenantcourse = $this->getDataGenerator()->create_course(['category' => $nontenantcategory->id]);

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        [$tenant, [$tenantuser]] = $generator->create_tenant_and_users(1, ['categoryid' => $tenantcategory->id]);
        $this->setUser($tenantuser);

        [$select, $params] = callbacks::filter_by_tenant_courses('c.id');
        $courses = $DB->get_fieldset_sql("SELECT c.id FROM {course} c WHERE {$select}", $params);

        $this->assertEqualsCanonicalizing([
            $tenantcourse->id,
            $tenantcoursechild->id,
        ], $courses);

        // Grant capability to view courses in non-tenant category.
        assign_capability('moodle/category:viewcourselist', CAP_ALLOW, $userroleid, $nontenantcategory->get_context(), true);
        cache_helper::purge_by_event('changesincoursecat');

        [$select, $params] = callbacks::filter_by_tenant_courses('c.id');
        $courses = $DB->get_fieldset_sql("SELECT c.id FROM {course} c WHERE {$select}", $params);

        $this->assertEqualsCanonicalizing([
            $tenantcourse->id,
            $tenantcoursechild->id,
            $nontenantcourse->id,
        ], $courses);
    }

    /**
     * Testing filtering cohorts.
     */
    public function test_filter_by_tenant_cohort(): void {
        global $DB;
        $this->resetAfterTest();

        $syscohort = $this->getDataGenerator()->create_cohort(['contextid' => SYSCONTEXTID]);
        $syscohortunvis = $this->getDataGenerator()->create_cohort(['contextid' => SYSCONTEXTID, 'visible' => false]);

        $category = $this->getDataGenerator()->create_category();
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $catcohort = $this->getDataGenerator()->create_cohort(['contextid' => $category->get_context()->id]);
        $catcohortunvis = $this->getDataGenerator()->create_cohort(
            ['contextid' => $category->get_context()->id, 'visible' => false]);
        $subcatcohort = $this->getDataGenerator()->create_cohort(['contextid' => $subcategory->get_context()->id]);

        // Test as site admin, all cohorts should be listed.
        $this->setAdminUser();

        [$select, $params] = callbacks::filter_by_tenant_cohort('c.id');
        $cohorts = $DB->get_fieldset_sql("SELECT c.id FROM {cohort} c WHERE {$select}", $params);

        $this->assertEqualsCanonicalizing([
            $syscohort->id,
            $syscohortunvis->id,
            $catcohort->id,
            $catcohortunvis->id,
            $subcatcohort->id,
        ], $cohorts);
    }

    /**
     * Testing filtering cohorts in multitenant enviornment.
     */
    public function test_filter_by_tenant_cohort_multitenant(): void {
        global $DB;
        $this->resetAfterTest();
        // Change roles for multitenant environment.
        \tool_tenant\manager::change_core_roles();

        $user = $this->getDataGenerator()->create_user();

        $syscohort = $this->getDataGenerator()->create_cohort(['contextid' => SYSCONTEXTID]);
        $syscohortunvis = $this->getDataGenerator()->create_cohort(['contextid' => SYSCONTEXTID, 'visible' => false]);

        $tenant0category = $this->getDataGenerator()->create_category();
        $tenant0subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant0category->id]);
        $tenant0catcohort = $this->getDataGenerator()->create_cohort(['contextid' => $tenant0category->get_context()->id]);
        $tenant0catcohortunvis = $this->getDataGenerator()->create_cohort(
            ['contextid' => $tenant0category->get_context()->id, 'visible' => false]);
        $tenant0subcatcohort = $this->getDataGenerator()->create_cohort(['contextid' => $tenant0subcategory->get_context()->id]);

        $tenant1category = $this->getDataGenerator()->create_category();
        $tenant1subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant1category->id]);
        $tenant1catcohort = $this->getDataGenerator()->create_cohort(['contextid' => $tenant1category->get_context()->id]);
        $tenant1catcohortunvis = $this->getDataGenerator()->create_cohort(
            ['contextid' => $tenant1category->get_context()->id, 'visible' => false]);
        $tenant1subcatcohort = $this->getDataGenerator()->create_cohort(['contextid' => $tenant1subcategory->get_context()->id]);

        /** @var \tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant0, [$tenant0admin]] = $tenantgenerator->create_tenant_and_users(1, ['categoryid' => $tenant0category->id],
            ['tenantadmin' => 1]);

        [$tenant1, [$tenant1admin]] = $tenantgenerator->create_tenant_and_users(1, ['categoryid' => $tenant1category->id],
            ['tenantadmin' => 1]);

        // Test as site admin, all cohorts should be listed.
        $this->setAdminUser();

        [$select, $params] = callbacks::filter_by_tenant_cohort('c.id');
        $cohorts = $DB->get_fieldset_sql("SELECT c.id FROM {cohort} c WHERE {$select}", $params);

        $this->assertEqualsCanonicalizing([
            $syscohort->id,
            $syscohortunvis->id,
            $tenant0catcohort->id,
            $tenant0catcohortunvis->id,
            $tenant0subcatcohort->id,
            $tenant1catcohort->id,
            $tenant1catcohortunvis->id,
            $tenant1subcatcohort->id,
        ], $cohorts);

        // Test as tenant0 admin, only system visible cohorts and all within same tenant are listed.
        $this->setUser($tenant0admin);

        [$select, $params] = callbacks::filter_by_tenant_cohort('c.id');
        $cohorts = $DB->get_fieldset_sql("SELECT c.id FROM {cohort} c WHERE {$select}", $params);

        $this->assertEqualsCanonicalizing([
            $syscohort->id,
            $tenant0catcohort->id,
            $tenant0catcohortunvis->id,
            $tenant0subcatcohort->id,
        ], $cohorts);

        // Test as tenant1 admin, only system visible cohorts and all within same tenant are listed.
        $this->setUser($tenant1admin);

        [$select, $params] = callbacks::filter_by_tenant_cohort('c.id');
        $cohorts = $DB->get_fieldset_sql("SELECT c.id FROM {cohort} c WHERE {$select}", $params);

        $this->assertEqualsCanonicalizing([
            $syscohort->id,
            $tenant1catcohort->id,
            $tenant1catcohortunvis->id,
            $tenant1subcatcohort->id,
        ], $cohorts);

        // Test as ordinary user, only visible system cohort should be shown.
        $this->setUser($user);
        [$select, $params] = callbacks::filter_by_tenant_cohort('c.id');
        $cohorts = $DB->get_fieldset_sql("SELECT c.id FROM {cohort} c WHERE {$select}", $params);
        $this->assertEquals([
            $syscohort->id,
        ], $cohorts);

        // Site admin in shared space should see all cohorts.
        $this->setAdminUser();
        $sharedspaceid = sharedspace::enable_shared_space();
        tenancy::set_switched_tenant_id($sharedspaceid);

        [$select, $params] = callbacks::filter_by_tenant_cohort('c.id');
        $cohorts = $DB->get_fieldset_sql("SELECT c.id FROM {cohort} c WHERE {$select}", $params);

        $this->assertEqualsCanonicalizing([
            $syscohort->id,
            $syscohortunvis->id,
            $tenant0catcohort->id,
            $tenant0catcohortunvis->id,
            $tenant0subcatcohort->id,
            $tenant1catcohort->id,
            $tenant1catcohortunvis->id,
            $tenant1subcatcohort->id,
        ], $cohorts);
    }

    /**
     * Given a multi-part message in MIME format, return the base64 encoded attachment contained within
     *
     * @param string $messagebody
     * @return string
     */
    private static function extract_message_attachment(string $messagebody): string {
        $mimepart = preg_split('/Content-Disposition: attachment; filename="My schedule.csv"\s+/m', $messagebody);

        // Extract the base64 encoded content after the "Content-Disposition" header.
        preg_match_all('/^([A-Z0-9\/\+=]+)\s/im', $mimepart[1], $matches);

        return base64_decode(implode($matches[0]));
    }
}

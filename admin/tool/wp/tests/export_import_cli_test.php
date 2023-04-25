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

namespace tool_wp;

use advanced_testcase;
use context_coursecat;
use context_system;
use core_user;
use moodle_exception;
use tool_wp_generator;
use tool_tenant_generator;
use tool_wp\local\exportimport\cli_helper;
use tool_wp\tool_wp\exporter\courses as courses_exporter;
use tool_wp\tool_wp\importer\courses as courses_importer;

/**
 * Tests for the export/import CLI.
 *
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_cli_test extends advanced_testcase {
    /** @var tool_wp_generator */
    protected $wpgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Generate cli_helper and mock $_SERVER['argv']
     *
     * @param string $operation cli_helper::EXPORT or cli_helper::IMPORT
     * @param array $mockargv
     * @return cli_helper
     */
    protected function construct_helper(string $operation, array $mockargv = []) {
        if (array_key_exists('argv', $_SERVER)) {
            $oldservervars = $_SERVER['argv'];
        }
        $_SERVER['argv'] = array_merge([''], $mockargv);
        $clihelper = new cli_helper($operation);
        if (isset($oldservervars)) {
            $_SERVER['argv'] = $oldservervars;
        } else {
            unset($_SERVER['argv']);
        }
        return $clihelper;
    }

    /**
     * Test that function to print export help prints something and does not have errors
     */
    public function test_export_help() {
        $this->expectOutputRegex('/--non-interactive/');
        $clihelper = $this->construct_helper(cli_helper::EXPORT);
        $clihelper->print_export_help();
    }

    /**
     * Test that function to print import help prints something and does not have errors
     */
    public function test_import_help() {
        $this->expectOutputRegex('/--non-interactive/');
        $clihelper = $this->construct_helper(cli_helper::IMPORT);
        $clihelper->print_import_help();
    }

    public function test_locate_tenant_export() {
        $this->resetAfterTest();

        $tenant = $this->tenantgenerator->create_tenant(['idnumber' => 't1']);
        $user = $this->getDataGenerator()->create_user(['username' => 'user1']);
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$user->id], \tool_tenant\tenancy::get_default_tenant_id());

        // Admin has permission to switch tenants.
        $clihelper = $this->construct_helper(cli_helper::EXPORT,
            ['--user=admin', '--tenant=t1', '--exporter=tool_wp\\tool_wp\\exporter\\users']);
        cron_setup_user(get_admin());
        $clihelper->choose_exporter();
        $this->assertEquals($tenant->id, $clihelper->choose_tenant()->id);

        // User does not have permission to switch tenants.
        $this->expectOutputRegex('/This user is not allowed to switch to tenant/');
        $clihelper = $this->construct_helper(cli_helper::EXPORT,
            ['--user=user1', '--tenant=t1', '--exporter=tool_reportbuilder\\tool_wp\\exporter\\customreports']);
        cron_setup_user($user);
        $clihelper->choose_exporter();
        $this->assertEquals(\tool_tenant\tenancy::get_default_tenant_id(), $clihelper->choose_tenant()->id);
    }

    /**
     * Prepare a courses export
     *
     * @return int exportid
     */
    protected function prepare_courses_export() {
        self::setAdminUser();

        $course = self::getDataGenerator()->create_course(['shortname' => 'C1', 'fullname' => 'Course1']);

        // Create a new export containing one course.
        $exportid = $this->wpgenerator->perform_export(courses_exporter::class, [
            courses_exporter::EXPORT_INSTANCES => courses_exporter::EXPORT_INSTANCES_SELECTED,
            courses_exporter::EXPORT_SELECT_COURSES => [$course->id],
        ]);

        return $exportid;
    }

    public function test_start_importing() {
        global $CFG, $DB;
        $this->resetAfterTest();

        $exportid = $this->prepare_courses_export();
        $user = $this->getDataGenerator()->create_user();
        $lastimportid0 = $DB->get_field_sql("SELECT max(id) FROM {tool_wp_import}") ?: 0;

        $this->setAdminUser();
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--user=admin', '--exportid=' . $exportid]);
        $clihelper->start_importing();
        $lastimportid1 = $DB->get_field_sql("SELECT max(id) FROM {tool_wp_import}");
        $this->assertGreaterThan($lastimportid0, $lastimportid1);

        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--user=admin',
            '--file=' . $CFG->dirroot . '/admin/tool/organisation/tests/fixtures/fullexport.zip']);
        $clihelper->start_importing();
        $lastimportid2 = $DB->get_field_sql("SELECT max(id) FROM {tool_wp_import}");
        $this->assertGreaterThan($lastimportid1, $lastimportid2);

        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--user=admin', '--exportid=' . $exportid]);
        cron_setup_user($user);
        $this->expectOutputRegex('/can not access/');
        $this->expectExceptionMessage('CLI script finished with error code 1');
        $clihelper->start_importing();
        $lastimportid3 = $DB->get_field_sql("SELECT max(id) FROM {tool_wp_import}");
        $this->assertEquals($lastimportid2, $lastimportid3);
    }

    public function test_locate_tenant_import() {
        global $CFG;
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();

        $cat = $this->getDataGenerator()->create_category();
        list($tenant, $users) = $this->tenantgenerator->create_tenant_and_users(1,
            ['idnumber' => 't1', 'categoryid' => $cat->id], ['username' => 'user1']);
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        $tenant2 = $this->tenantgenerator->create_tenant(['idnumber' => 't2']);
        $params = ['--tenant=t2', '--file=' . $CFG->dirroot . '/admin/tool/organisation/tests/fixtures/fullexport.zip'];

        // Admin has permission to switch tenants.
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            array_merge(['--user=admin'], $params));
        cron_setup_user(get_admin());
        $clihelper->start_importing();
        $this->assertEquals($tenant2->id, $clihelper->choose_tenant()->id);

        // Admin can import courses without specifying a tenant.
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--exportid=' . $exportid, '--no-tenant']);
        cron_setup_user(core_user::get_user(2));
        $clihelper->start_importing();
        $clihelper->choose_importer();
        $this->assertNull($clihelper->choose_tenant());

        // User does not have permission to switch tenants.
        $this->expectOutputRegex('/This user is not allowed to switch to tenant/');
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            array_merge(['--user=user1'], $params));
        cron_setup_user($users[0]);
        $clihelper->start_importing();
        $this->assertEquals($tenant->id, $clihelper->choose_tenant()->id);
    }

    public function test_locate_tenant_import_with_input() {
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $tenant2 = $this->tenantgenerator->create_tenant(['idnumber' => 't2']);
        $this->setAdminUser();

        // Locate by ID number.
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--exportid=' . $exportid]);
        $clihelper->phpunitinputs = ['t2'];
        $clihelper->start_importing();
        $this->assertEquals($tenant2->id, $clihelper->choose_tenant()->id);

        // Locate by id.
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--exportid=' . $exportid]);
        $clihelper->phpunitinputs = [$tenant2->id];
        $clihelper->start_importing();
        $this->assertEquals($tenant2->id, $clihelper->choose_tenant()->id);

        // No tenant from the input.
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--exportid=' . $exportid]);
        $clihelper->phpunitinputs = ['-'];
        $clihelper->start_importing();
        $this->assertNull($clihelper->choose_tenant());

        // Specify non-existing first and then existing tenant.
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--exportid=' . $exportid]);
        $this->expectOutputRegex('/ \'t7\' is not found/');
        $clihelper->phpunitinputs = ['t7', $tenant2->id];
        $clihelper->start_importing();
        $this->assertEquals($tenant2->id, $clihelper->choose_tenant()->id);

        // Specify non-existing in options, a warning will be raised and then question asked.
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--exportid=' . $exportid, '--tenant=t9']);
        $this->expectOutputRegex('/ \'t9\' is not found/');
        $clihelper->phpunitinputs = [$tenant2->id];
        $clihelper->start_importing();
        $this->assertEquals($tenant2->id, $clihelper->choose_tenant()->id);

        // Specify non-existing in options, a warning will be raised and then question asked.
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--exportid=' . $exportid, '--tenant=8']);
        $this->expectOutputRegex('/ 8 is not found/');
        $clihelper->phpunitinputs = [$tenant2->id];
        $clihelper->start_importing();
        $this->assertEquals($tenant2->id, $clihelper->choose_tenant()->id);
    }

    public function test_print_imported_file_summary() {
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--exportid=' . $exportid, '--no-tenant']);
        $clihelper->start_importing();
        $this->expectOutputRegex('/Content\s+Courses \(1\)/');
        $clihelper->print_imported_file_summary();
    }

    public function test_choose_user() {
        $this->resetAfterTest();
        $cat = $this->getDataGenerator()->create_category();
        list($tenant, $users) = $this->tenantgenerator->create_tenant_and_users(1,
            ['idnumber' => 't1', 'categoryid' => $cat->id], ['username' => 'user1']);
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        $user1 = $users[0];
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);

        // Imprersonate as tenant admin in command line.
        $this->setAdminUser();
        $clihelper = $this->construct_helper(cli_helper::EXPORT, ['--user=user1']);
        $chosen = $clihelper->choose_user();
        $this->assertEquals($user1->id, $chosen->id);

        // Imprersonate as tenant admin after prompt.
        $this->setAdminUser();
        $clihelper = $this->construct_helper(cli_helper::EXPORT, []);
        $clihelper->phpunitinputs = ['user1'];
        $chosen = $clihelper->choose_user();
        $this->assertEquals($user1->id, $chosen->id);

        // Try impersonating as a user without permissions in CL.
        $this->setAdminUser();
        $clihelper = $this->construct_helper(cli_helper::EXPORT, ['--user=user2']);
        $this->expectOutputRegex('/does not have permission/');
        $clihelper->phpunitinputs = ['admin'];
        $chosen = $clihelper->choose_user();
        $this->assertEquals(2, $chosen->id);

        // Try impersonating as a user without permissions after prompt.
        $this->setAdminUser();
        $clihelper = $this->construct_helper(cli_helper::EXPORT, []);
        $this->expectOutputRegex('/does not have permission/');
        $clihelper->phpunitinputs = ['user2', null];
        $chosen = $clihelper->choose_user();
        $this->assertEquals(2, $chosen->id);

        // Non-interactive without user.
        $this->setAdminUser();
        $clihelper = $this->construct_helper(cli_helper::EXPORT, ['--non-interactive']);
        $this->expectOutputRegex('/User not specified, performing export as admin/');
        $chosen = $clihelper->choose_user();
        $this->assertEquals(2, $chosen->id);

        // Non-interactive without user.
        $this->setAdminUser();
        $clihelper = $this->construct_helper(cli_helper::EXPORT, ['--non-interactive', '--user=user2']);
        $this->expectExceptionMessage('CLI script finished with error code 1');
        $clihelper->choose_user();
    }

    /**
     * Wrapper for protected propery 'exporter'
     *
     * @param cli_helper $clihelper
     * @return mixed
     */
    protected function get_cli_helper_exporter(cli_helper $clihelper) {
        $exporterproperty = new \ReflectionProperty(cli_helper::class, 'exporter');
        $exporterproperty->setAccessible(true);
        return $exporterproperty->getValue($clihelper);
    }

    public function test_choose_exporter() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->create_course();

        // This is a tricky test because exporter choice depends on the plugins installed, exporters available, etc.
        // Build the list of exporters and find the index of courses exporter (index starting with 1).
        $exporters = \tool_wp\local\exportimport\helper::get_all_exporters();
        \core_collator::asort_objects_by_method($exporters, 'get_name');
        $exporters = array_values($exporters);
        $temp = array_filter($exporters, function ($e) {
            return $e instanceof courses_exporter;
        });
        $idxcourses = key($temp) + 1;

        // Specify a valid exporter in the command line.
        $clihelper = $this->construct_helper(cli_helper::EXPORT,
            ['--exporter=tool_organisation\\tool_wp\\exporter\\orgstructure']);
        $clihelper->choose_exporter();
        $this->assertEquals(\tool_organisation\tool_wp\exporter\orgstructure::class,
            get_class($this->get_cli_helper_exporter($clihelper)));

        // Select an exporter from a menu.
        $clihelper = $this->construct_helper(cli_helper::EXPORT, []);
        $clihelper->phpunitinputs = [$idxcourses];
        $clihelper->choose_exporter();
        $this->assertEquals(courses_exporter::class,
            get_class($this->get_cli_helper_exporter($clihelper)));

        // Specify non-valid class and then select an exporter from a menu.
        $clihelper = $this->construct_helper(cli_helper::EXPORT, ['--exporter=nonvalid']);
        $this->expectOutputRegex('/not found/');
        $clihelper->phpunitinputs = [$idxcourses];
        $clihelper->choose_exporter();
        $this->assertEquals(courses_exporter::class,
            get_class($this->get_cli_helper_exporter($clihelper)));

        // Specify non-valid class and fail if non interactive.
        $clihelper = $this->construct_helper(cli_helper::EXPORT, ['--exporter=nonvalid', '--non-interactive']);
        $this->expectOutputRegex('/not found/');
        $this->expectExceptionMessage('CLI script finished with error code 1');
        $clihelper->choose_exporter();
    }

    public function test_choose_exporter_none_available() {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $this->getDataGenerator()->create_course();

        $clihelper = $this->construct_helper(cli_helper::EXPORT, ['--user=user1']);
        cron_setup_user($user);
        $this->expectOutputRegex('/no exporters are available/');
        $this->expectExceptionMessage('CLI script finished with error code 1');
        $clihelper->choose_exporter();
    }

    public function test_print_export_summary1() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $cat = $this->getDataGenerator()->create_category();
        list($tenant, $users) = $this->tenantgenerator->create_tenant_and_users(1,
            ['idnumber' => 't1', 'categoryid' => $cat->id], ['username' => 'user1']);
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        $user1 = $users[0];
        $this->getDataGenerator()->create_course(['category' => $cat->id]);

        $clihelper = $this->construct_helper(cli_helper::EXPORT,
            ['--exporter=tool_wp\\tool_wp\\exporter\\courses']);
        $clihelper->phpunitinputs = ['user1'];
        cron_setup_user($clihelper->choose_user());
        $clihelper->choose_exporter();
        $this->expectOutputRegex('/Perform export as: user1/');
        $clihelper->print_export_summary($tenant);
    }

    public function test_print_export_summary2() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $cat = $this->getDataGenerator()->create_category();
        list($tenant, $users) = $this->tenantgenerator->create_tenant_and_users(1,
            ['idnumber' => 't1', 'name' => 'Tenant1', 'categoryid' => $cat->id], ['username' => 'user1']);
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        $user1 = $users[0];
        $this->getDataGenerator()->create_course(['category' => $cat->id]);

        $clihelper = $this->construct_helper(cli_helper::EXPORT,
            ['--exporter=tool_wp\\tool_wp\\exporter\\courses']);
        $clihelper->phpunitinputs = ['admin', 't1'];
        cron_setup_user($clihelper->choose_user());
        $clihelper->choose_exporter();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $this->expectOutputRegex('/Perform export as: admin[^\\0]*Tenant: Tenant1/m');
        $clihelper->print_export_summary($tenant);
    }

    /**
     * Set up environment for exporting a course
     *
     * @return array
     */
    protected function setup_for_export_courses() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $cat = $this->getDataGenerator()->create_category();
        list($tenant, $users) = $this->tenantgenerator->create_tenant_and_users(1,
            ['idnumber' => 't1', 'name' => 'Tenant1', 'categoryid' => $cat->id], ['username' => 'user1']);
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        $user1 = $users[0];
        $this->getDataGenerator()->create_course(['category' => $cat->id]);
        $cat2 = $this->getDataGenerator()->create_category(['parent' => $cat->id]);

        return [$users[0], $cat, $cat2];
    }

    public function test_perform_export_courses_success() {
        global $DB;
        list($user1, $cat, $cat2) = $this->setup_for_export_courses();

        $settings = [courses_exporter::EXPORT_INSTANCES => courses_exporter::EXPORT_INSTANCES_CATEGORY,
            courses_exporter::EXPORT_SELECT_CATEGORIES => [$cat->id]];
        $clihelper = $this->construct_helper(cli_helper::EXPORT,
            ['--exporter=tool_wp\\tool_wp\\exporter\\courses', '--user=user1',
                '--settings=' . json_encode($settings)]);
        $clihelper->phpunitinputs = ['Y'];
        cron_setup_user($clihelper->choose_user());
        $clihelper->choose_exporter();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $this->expectOutputRegex('/export completed with status: Success/');
        $clihelper->perform_export();
        $this->assertCount(1, $DB->get_records('tool_wp_export', []));
    }

    public function test_perform_export_courses_not_validated() {
        global $DB;
        list($user1, $cat, $cat2) = $this->setup_for_export_courses();

        $clihelper = $this->construct_helper(cli_helper::EXPORT,
            ['--exporter=tool_wp\\tool_wp\\exporter\\courses', '--tenant=t1', '--user=admin']);
        cron_setup_user($clihelper->choose_user());
        $clihelper->choose_exporter();
        $clihelper->apply_general_settings($clihelper->choose_tenant());

        $this->expectOutputRegex('/Validation failed:/');
        try {
            $clihelper->perform_export();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('CLI script finished with error code 1', $e->getMessage());
        }
        $this->assertCount(0, $DB->get_records('tool_wp_export', []));
    }

    public function test_perform_export_courses_nothing_to_export() {
        global $DB;
        list($user1, $cat, $cat2) = $this->setup_for_export_courses();

        $settings = [courses_exporter::EXPORT_INSTANCES => courses_exporter::EXPORT_INSTANCES_CATEGORY,
            courses_exporter::EXPORT_SELECT_CATEGORIES => [$cat2->id]];
        $clihelper = $this->construct_helper(cli_helper::EXPORT,
            ['--exporter=tool_wp\\tool_wp\\exporter\\courses', '--user=user1',
                '--settings=' . json_encode($settings)]);
        cron_setup_user($clihelper->choose_user());
        $clihelper->choose_exporter();
        $clihelper->apply_general_settings($clihelper->choose_tenant());

        $this->expectOutputRegex('/Nothing to export/');
        try {
            $clihelper->perform_export();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('CLI script finished with error code 1', $e->getMessage());
        }
        $this->assertCount(0, $DB->get_records('tool_wp_export', []));
    }

    public function test_perform_export_save_to_file() {
        global $DB;
        list($user1, $cat, $cat2) = $this->setup_for_export_courses();

        $filename = make_request_directory() . '/myexport.zip';
        $settings = [courses_exporter::EXPORT_INSTANCES => courses_exporter::EXPORT_INSTANCES_CATEGORY,
            courses_exporter::EXPORT_SELECT_CATEGORIES => [$cat->id]];
        $clihelper = $this->construct_helper(cli_helper::EXPORT,
            ['--exporter=tool_wp\\tool_wp\\exporter\\courses', '--user=user1',
                '--settings=' . json_encode($settings), '--file=' . $filename]);
        $clihelper->phpunitinputs = ['Y'];
        cron_setup_user($clihelper->choose_user());
        $clihelper->choose_exporter();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $this->expectOutputRegex('/export completed with status: Success[^\\0]*\/myexport\.zip/m');
        $clihelper->perform_export();
        $this->assertCount(1, $DB->get_records('tool_wp_export', []));

        $this->assertTrue(file_exists($filename));
    }

    public function test_perform_export_save_to_dir() {
        global $DB;
        list($user1, $cat, $cat2) = $this->setup_for_export_courses();

        $dir = make_request_directory();
        $settings = [courses_exporter::EXPORT_INSTANCES => courses_exporter::EXPORT_INSTANCES_CATEGORY,
            courses_exporter::EXPORT_SELECT_CATEGORIES => [$cat->id]];
        $clihelper = $this->construct_helper(cli_helper::EXPORT,
            ['--exporter=tool_wp\\tool_wp\\exporter\\courses', '--user=user1',
                '--settings=' . json_encode($settings), '--file=' . $dir]);
        $clihelper->phpunitinputs = ['Y'];
        cron_setup_user($clihelper->choose_user());
        $clihelper->choose_exporter();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $this->expectOutputRegex('/export completed with status: Success[^\\0]*Export saved to/m');
        $exportid = $clihelper->perform_export();
        $files = get_file_storage()->get_area_files(context_system::instance()->id, 'tool_wp', 'export', $exportid, '', false);
        $file = reset($files);

        $this->assertTrue(file_exists($dir . '/' . $file->get_filename()));
    }

    /**
     * Test creating an export in a tenant, using an exporter that doesn't require a tenant
     */
    public function test_perform_export_for_tenant(): void {
        global $DB;

        $this->resetAfterTest();

        $tenantcategory = $this->getDataGenerator()->create_category();
        $tenant = $this->tenantgenerator->create_tenant([
            'idnumber' => 't1',
            'categoryid' => $tenantcategory->id,
        ]);

        $othercategory = $this->getDataGenerator()->create_category();

        // Create two cohorts, one in the tenant category, one in another category.
        $tenantcohort = $this->getDataGenerator()->create_cohort([
            'contextid' => context_coursecat::instance($tenantcategory->id)->id,
            'name' => 'Tenant cohort',
        ]);
        $othercohort = $this->getDataGenerator()->create_cohort([
            'contextid' => context_coursecat::instance($othercategory->id)->id,
            'name' => 'Other cohort',
        ]);

        $clihelper = $this->construct_helper(cli_helper::EXPORT, ['--exporter=tool_wp\\tool_wp\\exporter\\cohorts']);
        $clihelper->phpunitinputs = ['admin', $tenant->idnumber, 'Y'];
        cron_setup_user($clihelper->choose_user());
        $clihelper->choose_exporter();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $this->expectOutputRegex('/Will be exported:[\n]  Tenant cohort[\n][\n]/m');
        $clihelper->perform_export($tenant);

        $exports = $DB->get_records('tool_wp_export');
        $this->assertCount(1, $exports);

        $this->assertEquals($tenant->id, reset($exports)->tenantid);
    }

    /**
     * Test creating an export using an exporter that doesn't require a tenant
     */
    public function test_perform_export_no_tenant(): void {
        global $DB;

        $this->resetAfterTest();

        $tenantcategory = $this->getDataGenerator()->create_category();
        $tenant = $this->tenantgenerator->create_tenant([
            'idnumber' => 't1',
            'categoryid' => $tenantcategory->id,
        ]);

        $othercategory = $this->getDataGenerator()->create_category();

        // Create two cohorts, one in the tenant category, one in another category.
        $tenantcohort = $this->getDataGenerator()->create_cohort([
            'contextid' => context_coursecat::instance($tenantcategory->id)->id,
            'name' => 'Tenant cohort',
        ]);
        $othercohort = $this->getDataGenerator()->create_cohort([
            'contextid' => context_coursecat::instance($othercategory->id)->id,
            'name' => 'Other cohort',
        ]);

        $clihelper = $this->construct_helper(cli_helper::EXPORT, ['--exporter=tool_wp\\tool_wp\\exporter\\cohorts']);
        $clihelper->phpunitinputs = ['admin', '-', 'Y'];
        cron_setup_user($clihelper->choose_user());
        $clihelper->choose_exporter();
        $clihelper->apply_general_settings(null);
        $this->expectOutputRegex('/Will be exported:[\n]  Tenant cohort[\n]  Other cohort[\n][\n]/m');
        $clihelper->perform_export(null);

        $exports = $DB->get_records('tool_wp_export');
        $this->assertCount(1, $exports);

        $this->assertNull(reset($exports)->tenantid);
    }

    public function test_check_import_source() {
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $filename = make_request_directory() . '/temp.zip';
        file_put_contents($filename, 'test');

        // Valid import source.
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--exportid=' . $exportid]);
        $clihelper->check_import_source();

        // Valid import source.
        $clihelper = $this->construct_helper(cli_helper::IMPORT, ['--file=' . $filename]);
        $clihelper->check_import_source();

        // Various invalid import sources.
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--file=' . $filename]);
        $this->expectOutputRegex('/Please specify either --exportid or --file but not both/');
        try {
            $clihelper->check_import_source();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('CLI script finished with error code 1', $e->getMessage());
        }

        $clihelper = $this->construct_helper(cli_helper::IMPORT, []);
        try {
            $clihelper->check_import_source();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('CLI script finished with error code 1', $e->getMessage());
        }

        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--file=' . $filename . 'x']);
        try {
            $clihelper->check_import_source();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('CLI script finished with error code 1', $e->getMessage());
        }

        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . ($exportid + 1)]);
        try {
            $clihelper->check_import_source();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('CLI script finished with error code 1', $e->getMessage());
        }
    }

    public function test_choose_tenant_import() {
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $tenant = $this->tenantgenerator->create_tenant(['idnumber' => 't1']);

        // Choose existing tenant.
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--user=admin']);
        $clihelper->phpunitinputs = ['t1'];
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $selectedtenant = $clihelper->choose_tenant();
        $this->assertEquals($tenant->id, $selectedtenant->id);
        $clihelper->apply_general_settings($selectedtenant);

        // Choose "no tenant".
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--user=admin']);
        $clihelper->phpunitinputs = ['-'];
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $selectedtenant = $clihelper->choose_tenant();
        $this->assertNull($selectedtenant);
        $clihelper->apply_general_settings($selectedtenant);

        // Choose "no tenant".
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--user=admin', '--non-interactive']);
        $clihelper->phpunitinputs = ['-'];
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $this->expectOutputRegex('/assuming user tenant/');
        $selectedtenant = $clihelper->choose_tenant();
        $this->assertEquals(\tool_tenant\tenancy::get_tenant_id(), $selectedtenant->id);
        $clihelper->apply_general_settings($selectedtenant);
    }

    public function test_choose_tenant_error() {
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $tenant = $this->tenantgenerator->create_tenant(['idnumber' => 't1']);

        // Choose existing tenant.
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--tenant=t1', '--no-tenant']);
        $clihelper->phpunitinputs = ['t1'];
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $this->expectOutputRegex('/can not be used together/');
        $this->expectExceptionMessage('CLI script finished with error code 1');
        $clihelper->choose_tenant();
    }

    public function test_print_import_summary() {
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $tenant = $this->tenantgenerator->create_tenant(['idnumber' => 't1']);

        // Choose existing tenant.
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--user=admin']);
        $clihelper->phpunitinputs = ['1'];
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $this->expectOutputRegex('/Perform import as: admin[^\\0]*Importer: Courses[^\\0]*'.
            'Import settings:[^\\0]*Available settings:/m');
        $clihelper->print_import_summary();
    }

    public function test_import_failed_validation() {
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $tenant = $this->tenantgenerator->create_tenant(['idnumber' => 't1']);

        // Choose existing tenant.
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--user=admin', '--tenant=t1']);
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $this->expectOutputRegex('/Validation failed/');
        $this->expectExceptionMessage('CLI script finished with error code 1');
        $clihelper->apply_import_settings();
    }

    public function test_import_conflicts_summary() {
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $cat = $this->getDataGenerator()->create_category();
        $tenant = $this->tenantgenerator->create_tenant(['idnumber' => 't1', 'categoryid' => $cat->id]);

        // Choose existing tenant.
        $settings = [courses_importer::IMPORT_SELECT_CATEGORY => $cat->id];
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--user=admin', '--tenant=t1', '--settings='.json_encode($settings)]);
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $this->expectOutputRegex('/Import conflict resolutions settings/');
        $clihelper->apply_import_settings();
    }

    public function test_import_no_conflicts() {
        global $DB;
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $cat = $this->getDataGenerator()->create_category();
        $tenant = $this->tenantgenerator->create_tenant(['idnumber' => 't1', 'categoryid' => $cat->id]);
        $DB->delete_records('course', ['shortname' => 'C1']);

        // Choose existing tenant.
        $settings = [courses_importer::IMPORT_SELECT_CATEGORY => $cat->id];
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--user=admin', '--tenant=t1', '--settings='.json_encode($settings)]);
        $clihelper->phpunitinputs = ['Y'];
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $this->expectOutputRegex('/No conflicts found[^\\0]*import completed with status: Success/m');
        $clihelper->apply_import_settings();
        $clihelper->perform_import();

        $this->assertCount(1, $DB->get_records('course', ['shortname' => 'C1']));
    }

    public function test_import_nothing_to_import() {
        global $DB;
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $cat = $this->getDataGenerator()->create_category();
        $tenant = $this->tenantgenerator->create_tenant(['idnumber' => 't1', 'categoryid' => $cat->id]);
        // There are two courses on the site - the site course and the 'C1'.
        $this->assertCount(2, $DB->get_records('course', []));

        // Choose existing tenant.
        $settings = [courses_importer::IMPORT_SELECT_CATEGORY => $cat->id,
            'conflict:course:shortnameconflict:action' => 'skip'];
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--user=admin', '--settings='.json_encode($settings)]);
        $clihelper->phpunitinputs = ['t1', 'Y'];
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $clihelper->apply_import_settings();
        $this->expectOutputRegex('/Nothing to import/');
        try {
            $clihelper->perform_import();
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('CLI script finished with error code 1', $e->getMessage());
        }

        // There are still two courses on the site - the site course and the 'C1', nothing was imported.
        $this->assertCount(2, $DB->get_records('course', []));
    }

    public function test_import_with_conflicts() {
        global $DB;
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $tenant = $this->tenantgenerator->create_tenant(['idnumber' => 't1']);
        $cat = $this->getDataGenerator()->create_category();
        // There are two courses on the site - the site course and the 'C1'.
        $this->assertCount(2, $DB->get_records('course', []));

        // Choose existing tenant.
        $settings = [courses_importer::IMPORT_SELECT_CATEGORY => $cat->id,
            'conflict:course:shortnameconflict:action' => 'increment'];
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--user=admin', '--settings='.json_encode($settings)]);
        $clihelper->phpunitinputs = ['-', 'Y'];
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $clihelper->apply_import_settings();
        $this->expectOutputRegex('/import completed with status: Success/');
        $clihelper->perform_import();

        // There are now three courses.
        $this->assertCount(3, $DB->get_records('course', []));
    }

    public function test_notify_about_ignored_settings() {
        global $DB;
        $this->resetAfterTest();
        $exportid = $this->prepare_courses_export();
        $this->setAdminUser();
        $cat = $this->getDataGenerator()->create_category();
        $tenant = $this->tenantgenerator->create_tenant(['idnumber' => 't1', 'categoryid' => $cat->id]);
        $DB->delete_records('course', ['shortname' => 'C1']);

        // Choose existing tenant.
        $settings = [courses_importer::IMPORT_SELECT_CATEGORY => $cat->id,
            'weirdsetting' => 1];
        $clihelper = $this->construct_helper(cli_helper::IMPORT,
            ['--exportid=' . $exportid, '--user=admin', '--tenant=t1', '--settings='.json_encode($settings)]);
        $clihelper->phpunitinputs = ['Y'];
        $clihelper->check_import_source();
        $clihelper->start_importing();
        $clihelper->apply_general_settings($clihelper->choose_tenant());
        $this->expectOutputRegex('/Some of the supplied settings were ignored/m');
        $clihelper->apply_import_settings();
    }

}

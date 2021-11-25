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
 * File containing tests for perform_import external class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\external;

use tool_organisation\tool_wp\importer\departments_csv as dep_importer_csv;
use tool_wp\tool_wp\importer\site as importer;
use tool_tenant\tenancy;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\external\perform_import
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_external_perform_import extends \advanced_testcase {

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Returns the plugin generator
     *
     * @return \tool_wp_generator
     */
    protected function get_plugin_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Prepare a user export
     *
     * @return int $exportid
     */
    protected function prepare_export(): int {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'user1']);

        // Create a new export containing one course.
        $exportid = $this->get_plugin_generator()->perform_export(\tool_wp\tool_wp\exporter\users::class, [
            'exportertenant' => $tenant->id,
        ]);

        return $exportid;
    }

    /**
     * Get item id from file
     *
     * @param string $file
     * @return int $itemid
     */
    protected function get_itemid_from_file($file): int {
        global $USER;
        $fs = get_file_storage();
        $itemid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($USER->id);
        $fs->create_file_from_pathname(['component' => 'user', 'filearea' => 'draft',
            'contextid' => $usercontext->id, 'itemid' => $itemid, 'filepath' => '/',
            'filename' => basename($file)], $file);
        return $itemid;
    }

    /**
     * Test perform_import missing rights.
     */
    public function test_perform_import_no_rights() {
        // Change user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        perform_import::execute(0, 0, '', '{}', 0, true, false);
    }

    /**
     * Test perform_import exportid and file params supplied.
     */
    public function test_perform_import_exportid_and_file_params() {
        $this->expectExceptionMessage(get_string('importeitherexportidorfile', 'tool_wp'));
        perform_import::execute(1000, 1000, '', '{}', 0, true, false);
    }

    /**
     * Test perform_import nonexisting exportid.
     */
    public function test_perform_import_nonexisting_exportid() {
        $this->expectExceptionMessage(get_string('exportnotfound', 'tool_wp'));
        perform_import::execute(1000, 0, '', '{}', 0, true, false);
    }

    /**
     * Test perform_import nonexisting file.
     */
    public function test_perform_import_nonexisting_file() {
        $this->expectExceptionMessage(get_string('cantlocatefileindraftarea', 'tool_wp'));
        perform_import::execute(0, 1000, '', '{}', 0, true, false);
    }

    /**
     * Test perform_import not available importer.
     */
    public function test_perform_import_no_importer() {
        $exportid = $this->prepare_export();

        $class = 'tool_wp\tool_wp\importer\nonexisting';
        $this->expectExceptionMessage(get_string('importernotfound', 'tool_wp', $class));
        perform_import::execute($exportid, 0, $class, '{}', 0, true, false);
    }

    /**
     * Test perform_import non-existing tenant.
     */
    public function test_perform_import_nonexisting_tenant() {
        $exportid = $this->prepare_export();
        $tenantid = 1000;
        $this->expectExceptionMessage(get_string('migrationcannotswitchtenant', 'tool_wp', $tenantid));
        perform_import::execute($exportid, 0, '', '{}', $tenantid, true, false);
    }

    /**
     * Test perform_import tenant and no-tenant supplied.
     */
    public function test_perform_import_tenant_and_notenant() {
        $exportid = $this->prepare_export();
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->expectExceptionMessage(get_string('migrationnotenanterror', 'tool_wp'));
        perform_import::execute($exportid, 0, '', '{}', $tenant->id, true, true);
    }

    /**
     * Test perform_import can't switch tenant.
     */
    public function test_perform_import_not_allowed_switch_tenant() {
        global $DB, $CFG;
        // Create export.
        $exportid = $this->prepare_export();

        $tenant = $this->get_tenant_generator()->create_tenant();
        $user = self::getDataGenerator()->create_user();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $context = \context_system::instance();
        self::getDataGenerator()->role_assign($managerrole->id, $user->id, $context);
        assign_capability('tool/wp:useexportimport', CAP_ALLOW, $managerrole->id, $context->id);
        $this->setUser($user);

        try {
            perform_import::execute($exportid, 0, '', '{}', $tenant->id, true, false);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            // For existing export tenant switch test is done in performance check,
            // so general message expected.
            $this->assertStringContainsString(get_string('exportnotfound', 'tool_wp'), $e->getMessage());
        }

        // Export from csv.
        $file = $CFG->dirroot . '/admin/tool/organisation/tests/fixtures/departments.csv';
        $itemid = $this->get_itemid_from_file($file);
        try {
            perform_import::execute(0, $itemid, 'tool_organisation\tool_wp\importer\departments_csv',
                '{}', $tenant->id, true, false);
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString(
                get_string('migrationcannotswitchtenant', 'tool_wp', $tenant->id), $e->getMessage());
        }
    }

    /**
     * Test perform_import settings validation failed.
     */
    public function test_perform_import_settings_validation_failed() {
        // Create export.
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'user1']);
        $export = \tool_wp\external\perform_export::execute(\tool_wp\tool_wp\exporter\users::class,
            '{}', $tenant->id, false, false);
        $this->runAdhocTasks(\tool_wp\task\export_adhoc_task::class);

        // Invalid JSON.
        try {
            perform_import::execute($export['id'], 0, '', 'string',
                $tenant->id, true, false);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString(get_string('importersettingsinvalid', 'tool_wp'), $e->getMessage());
        }

        try {
            perform_import::execute($export['id'], 0, '', '{"key":}',
                $tenant->id, true, false);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString(get_string('importersettingsinvalid', 'tool_wp'), $e->getMessage());
        }

        $this->expectExceptionMessage(
            get_string('importersettingsvalidationfailed', 'tool_wp', 'select_users - Required'));
        perform_import::execute($export['id'], 0, '', json_encode(['import_instances' => 'manual']),
            $tenant->id, true, false);
    }

    /**
     * Test perform_import, also check conflicts.
     */
    public function test_perform_import() {
        // Create export.
        $tenant = $this->get_tenant_generator()->create_tenant();
        $user = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'user1']);
        $export = \tool_wp\external\perform_export::execute(\tool_wp\tool_wp\exporter\users::class,
            '{}', $tenant->id, false, false);
        $this->runAdhocTasks(\tool_wp\task\export_adhoc_task::class);

        delete_user($user);
        // Dry-run of import.
        $result = perform_import::execute($export['id'], 0, '', '{}', 0, true, false);
        $result = \external_api::clean_returnvalue(perform_import::execute_returns(), $result);
        $this->assertEquals(0, $result['id']);

        // Perform import.
        $result = perform_import::execute($export['id'], 0, '', '{}', 0, false, false);
        $result = \external_api::clean_returnvalue(perform_import::execute_returns(), $result);
        $this->assertNotEquals(0, $result['id']);

        $this->runAdhocTasks(\tool_wp\task\import_adhoc_task::class);
        $this->assertNotEmpty(\core_user::get_user_by_username('user1'));

        // Perform import again, expect conflicts.
        $result = perform_import::execute($export['id'], 0, '', '{}', 0, true, false);
        $result = \external_api::clean_returnvalue(perform_import::execute_returns(), $result);
        // No import occured.
        $this->assertEquals(0, $result['id']);

        // There are conflits.
        $settings = json_decode($result['settings'], true);
        $this->assertArrayHasKey('conflict:user:usernameconflict:action', $settings);
        $this->assertArrayHasKey('conflict:user:emailconflict:action', $settings);
    }

    /**
     * Test perform_import_with_settings
     */
    public function test_perform_import_with_settings() {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $user1 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'user1']);
        $user2 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'user2']);
        $user3 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'user3']);

        // Create export.
        $result = perform_export::execute(\tool_wp\tool_wp\exporter\users::class, '{}', $tenant->id, false, false);
        $result = \external_api::clean_returnvalue(perform_export::execute_returns(), $result);
        $this->runAdhocTasks(\tool_wp\task\export_adhoc_task::class);

        // Remove users.
        delete_user($user1);
        delete_user($user2);
        delete_user($user3);

        // Sanity check.
        $this->assertNotEquals(0, $result['id']);

        // Perform import.
        $settings = ['import_picture' => 0, 'import_instances' => 'manual', 'select_users' => [$user1->id, $user2->id]];
        $result = perform_import::execute($result['id'], 0, '', json_encode($settings), 0, false, false);
        $result = \external_api::clean_returnvalue(perform_import::execute_returns(), $result);

        // Check settings in the result.
        $resultsettings = json_decode($result['settings'], true);
        $this->assertEquals($settings['import_instances'], $resultsettings['import_instances']);
        $this->assertEquals($settings['import_picture'], $resultsettings['import_picture']);
        $this->assertEqualsCanonicalizing($settings['select_users'], $resultsettings['select_users']);

        $this->assertNotEquals(0, $result['id']);
        $this->runAdhocTasks(\tool_wp\task\import_adhoc_task::class);

        // Check only two users were created.
        $this->assertNotEmpty(\core_user::get_user_by_username('user1'));
        $this->assertNotEmpty(\core_user::get_user_by_username('user2'));
        $this->assertEmpty(\core_user::get_user_by_username('user3'));
    }

    /**
     * Test perform_import using csv file (tool_organisation)
     */
    public function test_perform_import_csv(): void {
        global $CFG;

        $tenant = $this->get_tenant_generator()->create_tenant();
        $file = $CFG->dirroot . '/admin/tool/organisation/tests/fixtures/departments.csv';
        $itemid = $this->get_itemid_from_file($file);

        // No importer specified.
        try {
            perform_import::execute(0, $itemid, '', '{}', $tenant->id, true, false);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('More than one importer is available, importer must be specified', $e->getMessage());
            $this->assertStringContainsString('tool_organisation\tool_wp\importer\departments_csv', $e->getMessage());
        }

        // Specify importer.
        $result = perform_import::execute(0, $itemid, 'tool_organisation\tool_wp\importer\departments_csv',
            '{}', $tenant->id, false, false);
        $result = \external_api::clean_returnvalue(perform_import::execute_returns(), $result);
        $this->runAdhocTasks(\tool_wp\task\import_adhoc_task::class);
        $importid = $result['id'];

        // Analyse logs.
        $logs = $this->get_plugin_generator()->get_import_logs($importid);

        $this->assertStringStartsWith('Created new department framework', $logs[0]['detail']);
        // Number of departments per logs.
        $this->assertCount(26, $logs);

        // Check actual departments.
        $departments = \tool_organisation\department::get_records();
        $this->assertCount(26, $departments);
        $departmentframeworks = array_filter($departments, function($dep) {
            return ((int)$dep->get('pathlevel') === 1);
        });
        // Check there is 1 department framework.
        $this->assertCount(1, $departmentframeworks);
        $department = array_shift($departmentframeworks);

        // Try importing again, use existing framework this time.
        $settings = [
            dep_importer_csv::IMPORT_TARGET_FRAMEWORK => dep_importer_csv::IMPORT_TARGET_FRAMEWORK_SELECTED,
            dep_importer_csv::IMPORT_SELECT_FRAMEWORK => $department->get('id'),
        ];
        $result = perform_import::execute(0, $itemid, 'tool_organisation\tool_wp\importer\departments_csv',
            json_encode($settings), $tenant->id, false, false);
        $result = \external_api::clean_returnvalue(perform_import::execute_returns(), $result);
        $this->runAdhocTasks(\tool_wp\task\import_adhoc_task::class);

        // Check actual departments.
        $departments = \tool_organisation\department::get_records();
        $this->assertCount(51, $departments);
        $departmentframeworks = array_filter($departments, function($dep) {
            return ((int)$dep->get('pathlevel') === 1);
        });
        // Check there is 1 department framework.
        $this->assertCount(1, $departmentframeworks);
    }

    /**
     * Test perform site import from file
     */
    public function test_perform_import_file_site(): void {
        global $DB;

        $settings = [
            importer::IMPORT_TENANT_DETAILS => 1,
            importer::IMPORT_TENANT_APPEARANCE => 1,
            importer::IMPORT_TENANT_USERS => 1,
            importer::IMPORT_TENANT_CONTENT => 1,
            importer::IMPORT_COHORTS => 1,
            importer::IMPORT_CERTIFICATES => 1,
        ];

        $itemid = $this->get_itemid_from_file(__DIR__ . '/fixtures/site-export.zip');
        $result = perform_import::execute(0, $itemid, '', json_encode($settings), 0, false, true);
        $result = \external_api::clean_returnvalue(perform_import::execute_returns(), $result);
        $this->runAdhocTasks(\tool_wp\task\import_adhoc_task::class);
        $importid = $result['id'];

        // Analyse logs.
        $logs = $this->get_plugin_generator()->get_import_logs($importid);
        $this->assertCount(22, $logs);

        $details = array_column($logs, 'detail');
        $this->assertEquals('Imported site', $details[0]);

        // Importing first tenant.
        $this->assertMatchesRegularExpression('/Imported tenant \'<a href="[^"]+">Tenant one<\/a>\'/', $details[1]);
        $this->assertEquals('Created user \'Admin User\'', $details[2]);
        $this->assertEquals('Created user \'Tenant One User\'', $details[3]);
        $this->assertMatchesRegularExpression(
            '/Created new course category \'<a href="[^"]+">Tenant one category<\/a>\'/', $details[4]);
        $this->assertMatchesRegularExpression(
            '/Created new certificate template \'<a href="[^"]+">Tenant one certificate<\/a>\'/', $details[5]);

        // Importing second tenant.
        $this->assertMatchesRegularExpression('/Imported tenant \'<a href="[^"]+">Tenant two<\/a>\'/', $details[6]);
        $this->assertEquals('Created user \'Tenant Two User\'', $details[7]);
        $this->assertMatchesRegularExpression(
            '/Created new course category \'<a href="[^"]+">Tenant two category<\/a>\'/', $details[8]);
        $this->assertMatchesRegularExpression('/Created new cohort \'<a href="[^"]+">Tenant two cohort<\/a>\'/', $details[9]);
        $this->assertEquals('Allocated user \'Tenant Two User\' into cohort \'Tenant two cohort\'', $details[10]);
        $this->assertMatchesRegularExpression(
            '/Created new course category \'<a href="[^"]+">Tenant two sub-category<\/a>\'/', $details[11]);
        $this->assertMatchesRegularExpression('/Created new course \'<a href="[^"]+">Tenant two sub-course<\/a>\'/', $details[12]);

        // Importing third tenant.
        $this->assertMatchesRegularExpression('/Imported tenant \'<a href="[^"]+">Tenant three<\/a>\'/', $details[13]);

        // Importing top-level category not associated with a tenant.
        $this->assertMatchesRegularExpression(
            '/Created new course category \'<a href="[^"]+">Non-tenant category<\/a>\'/', $details[14]);
        $this->assertMatchesRegularExpression(
            '/Created new certificate template \'<a href="[^"]+">Non-tenant category certificate<\/a>\'/',
            $details[15]);
        $this->assertMatchesRegularExpression(
            '/Created new cohort \'<a href="[^"]+">Non-tenant category cohort<\/a>\'/', $details[16]);
        $this->assertEquals('Allocated user \'Admin User\' into cohort \'Non-tenant category cohort\'', $details[17]);

        // Import system-level entities.
        $this->assertMatchesRegularExpression('/Created new cohort \'<a href="[^"]+">System cohort<\/a>\'/', $details[18]);
        $this->assertEquals('Allocated user \'Tenant One User\' into cohort \'System cohort\'', $details[19]);
        $this->assertEquals('Allocated user \'Tenant Two User\' into cohort \'System cohort\'', $details[20]);
        $this->assertMatchesRegularExpression(
            '/Created new certificate template \'<a href="[^"]+">System certificate<\/a>\'/', $details[21]);

        // Ensure we have no errors or notices.
        $errors = array_filter($logs, static function(array $log): bool {
            return !empty($log['errors']);
        });
        $this->assertEmpty($errors);

        $notices = array_filter($logs, static function(array $log): bool {
            return !empty($log['notices']);
        });
        $this->assertEmpty($notices);

        // Ensure we have our three new tenants.
        $tenants = array_map(static function(\stdClass $tenant): string {
            return tenancy::get_tenant_name_from_id($tenant->id);
        }, tenancy::get_tenants());

        $this->assertEqualsCanonicalizing([
            'Default tenant',
            'Tenant one',
            'Tenant two',
            'Tenant three',
        ], $tenants);

        // Here's the top-level category not associated with one of those tenants.
        $nontenantcategoryid = $DB->get_field('course_categories', 'id', [
            'parent' => \core_course_category::top()->id,
            'name' => 'Non-tenant category',
        ], MUST_EXIST);

        $nontenantcategory = \core_course_category::get($nontenantcategoryid);

        $this->assertTrue($DB->record_exists('cohort', [
            'contextid' => $nontenantcategory->get_context()->id,
            'name' => 'Non-tenant category cohort',
        ]));
        $this->assertTrue($DB->record_exists('tool_certificate_templates', [
            'contextid' => $nontenantcategory->get_context()->id,
            'name' => 'Non-tenant category certificate',
        ]));

        // Check our system-level entities.
        $this->assertTrue($DB->record_exists('cohort', [
            'contextid' => \context_system::instance()->id,
            'name' => 'System cohort',
        ]));
        $this->assertTrue($DB->record_exists('tool_certificate_templates', [
            'contextid' => \context_system::instance()->id,
            'name' => 'System certificate',
        ]));
    }
}

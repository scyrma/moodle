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
 * File containing tests for import/export classes
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

use advanced_testcase;
use context_system;
use core_course_category;
use stdClass;
use tool_certificate_generator;
use tool_wp_generator;
use tool_tenant_generator;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\wp_imported_entities;
use tool_wp\local\exportimport\wp_imported_entity;
use tool_wp\tool_wp\exporter\site as exporter;
use tool_wp\tool_wp\importer\site as importer;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\exporter\site
 * @covers      \tool_wp\tool_wp\importer\site
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_site_test extends advanced_testcase {

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
    }

    /**
     * Test that we can export
     */
    public function test_export_site(): void {
        $tenantcategory = $this->getDataGenerator()->create_category();
        [$tenant, ] = $this->get_tenant_generator()->create_tenant_and_users(2, [
            'categoryid' => $tenantcategory->id,
        ]);

        // Create a top-level category not associated with a tenant.
        $nontenantcategory = $this->getDataGenerator()->create_category();

        // Create three cohorts (tenant category, non-tenant category, system).
        $tenantcohort = $this->getDataGenerator()->create_cohort([
            'contextid' => $tenantcategory->get_context()->id,
        ]);
        $nontenantcohort = $this->getDataGenerator()->create_cohort([
            'contextid' => $nontenantcategory->get_context()->id,
        ]);
        $systemcohort = $this->getDataGenerator()->create_cohort([
            'contextid' => context_system::instance()->id,
        ]);

        // Create three certificates (tenant category, non-tenant category, system).
        $tenantcertificate = $this->get_certificate_generator()->create_template([
            'name' => 'Tenant certificate',
            'contextid' => $tenantcategory->get_context()->id,
        ]);
        $nontenantcertificate = $this->get_certificate_generator()->create_template([
            'name' => 'Non-tenant certificate',
            'contextid' => $nontenantcategory->get_context()->id,
        ]);
        $systemcertificate = $this->get_certificate_generator()->create_template([
            'name' => 'System certificate',
            'contextid' => context_system::instance()->id,
        ]);

        // Export all the things.
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_TENANT_DETAILS => 1,
            exporter::EXPORT_TENANT_APPEARANCE => 1,
            exporter::EXPORT_TENANT_USERS => 1,
            exporter::EXPORT_TENANT_CONTENT => 1,
            exporter::EXPORT_COHORTS => 1,
            exporter::EXPORT_CERTIFICATES => 1,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_plugin_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have the tenant, plus the default tenant.
        $tenants = $importer->get_entities_in_workplace_export_file('tool_tenant');
        $this->assertCount(2, $tenants);

        $this->assertEqualsCanonicalizing([
            get_string('defaultname', 'tool_tenant'),
            tenancy::get_tenant_name_from_id($tenant->id),
        ], self::entities_to_raw_field($tenants));

        // Three categories.
        $categories = $importer->get_entities_in_workplace_export_file('course_categories');
        $this->assertCount(3, $categories);

        $this->assertEqualsCanonicalizing([
            'Category 1',
            $tenantcategory->name,
            $nontenantcategory->name,
        ], self::entities_to_raw_field($categories));

        // Three cohorts.
        $cohorts = $importer->get_entities_in_workplace_export_file('cohort');
        $this->assertCount(3, $cohorts);

        $this->assertEqualsCanonicalizing([
            $tenantcohort->name,
            $nontenantcohort->name,
            $systemcohort->name,
        ], self::entities_to_raw_field($cohorts));

        // Three certificates.
        $certficates = $importer->get_entities_in_workplace_export_file('tool_certificate_templates');
        $this->assertCount(3, $certficates);

        $this->assertEqualsCanonicalizing([
            $tenantcertificate->get_name(),
            $nontenantcertificate->get_name(),
            $systemcertificate->get_name(),
        ], self::entities_to_raw_field($certficates));
    }

    /**
     * Test that we receive an error when trying to export/import into the same site
     */
    public function test_export_import_same_site(): void {
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        $importid = $this->get_plugin_generator()->perform_import_from_export_id($export->get('id'), [
            helper::get_importer_setting_name_for_conflict_form(importer::ENTITY_NAME, 'exportsamesite', 'action') => 'skip',
        ]);

        // Conflicts.
        $conflicts = $this->get_plugin_generator()->get_import_conflict_review($importid);
        $this->assertEquals([
            ['Cannot import into the same site the export originated from', 'Do not import'],
        ], $conflicts);

        // Analyse logs.
        $logs = $this->get_plugin_generator()->get_import_logs($importid);
        $this->assertCount(1, $logs);

        [
            'detail' => $detail,
            'errors' => $errors,
            'notices' => $notices,
        ] = reset($logs);

        $this->assertEquals('Couldn\'t import site', $detail);
        $this->assertEquals([
            'Cannot import into the same site the export originated from',
        ], $errors);
        $this->assertEmpty($notices);
    }

    /**
     * Test that we can import
     */
    public function test_import_site(): void {
        global $DB;

        $fixture = __DIR__ . '/fixtures/site-export.zip';
        $importid = $this->get_plugin_generator()->perform_import_from_file($fixture, [
            importer::IMPORT_TENANT_DETAILS => 1,
            importer::IMPORT_TENANT_APPEARANCE => 1,
            importer::IMPORT_TENANT_USERS => 1,
            importer::IMPORT_TENANT_CONTENT => 1,
            importer::IMPORT_COHORTS => 1,
            importer::IMPORT_CERTIFICATES => 1,
        ]);

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
        $tenants = array_map(static function(stdClass $tenant): string {
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
            'parent' => core_course_category::top()->id,
            'name' => 'Non-tenant category',
        ], MUST_EXIST);

        $nontenantcategory = core_course_category::get($nontenantcategoryid);

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
            'contextid' => context_system::instance()->id,
            'name' => 'System cohort',
        ]));
        $this->assertTrue($DB->record_exists('tool_certificate_templates', [
            'contextid' => context_system::instance()->id,
            'name' => 'System certificate',
        ]));
    }

    /**
     * Test that we can import site with courses that share system context
     * questionbank and restoring it preserves original grades. This is basically validating
     * that issue WP-2782 and linked are not re-introduced.
     */
    public function test_import_site_courses_same_questionbank_grades(): void {
        global $DB;

        // Sanity check.
        $this->assertCount(0, $DB->get_records('quiz_attempts'));
        $this->assertCount(0, $DB->get_records('quiz_grades'));

        $fixture = __DIR__ . '/fixtures/site-export-grades.zip';
        $importid = $this->get_plugin_generator()->perform_import_from_file($fixture, [
            importer::IMPORT_TENANT_DETAILS => 1,
            importer::IMPORT_TENANT_APPEARANCE => 1,
            importer::IMPORT_TENANT_USERS => 1,
            importer::IMPORT_TENANT_CONTENT => 1,
            importer::IMPORT_COHORTS => 0,
            importer::IMPORT_CERTIFICATES => 1,
        ]);

        // Analyse logs.
        $logs = $this->get_plugin_generator()->get_import_logs($importid);
        $this->assertCount(10, $logs);

        $details = array_column($logs, 'detail');
        $this->assertEquals('Imported site', $details[0]);

        // Importing first tenant.
        $this->assertMatchesRegularExpression('/Imported tenant \'<a href="[^"]+">Default tenant<\/a>\'/', $details[1]);
        $this->assertEquals('Created user \'Admin User\'', $details[2]);
        $this->assertEquals('Created user \'User One\'', $details[3]);
        $this->assertEquals('Created user \'User Two\'', $details[4]);
        $this->assertMatchesRegularExpression(
            '/Created new course category \'<a href="[^"]+">Default tenant<\/a>\'/', $details[5]);
        $this->assertMatchesRegularExpression(
            '/Created new course \'<a href="[^"]+">Course 1<\/a>\'/', $details[6]);
        $this->assertMatchesRegularExpression(
            '/Created new course \'<a href="[^"]+">Course 2<\/a>\'/', $details[7]);

        // Ensure we have no errors or notices.
        $errors = array_filter($logs, static function(array $log): bool {
            return !empty($log['errors']);
        });
        $this->assertEmpty($errors);

        $notices = array_filter($logs, static function(array $log): bool {
            return !empty($log['notices']);
        });
        $this->assertEmpty($notices);

        // Check quiz attempts and grades are present.
        $this->assertCount(4, $DB->get_records('quiz_attempts'));
        $this->assertCount(4, $DB->get_records('quiz_grades'));

        $userid1 = $DB->get_field('user', 'id', ['username' => 'user1']);
        $userid2 = $DB->get_field('user', 'id', ['username' => 'user2']);
        $this->assertCount(2, grade_get_course_grade($userid1));
        $this->assertCount(2, grade_get_course_grade($userid2));
        $this->assertEqualsCanonicalizing([7.5, 7.5], array_column(grade_get_course_grade($userid1), 'grade'));
        $this->assertEqualsCanonicalizing([2.75, 10], array_column(grade_get_course_grade($userid2), 'grade'));
    }

    /**
     * Helper method to transform imported entities into an array of specified fields
     *
     * @param wp_imported_entities $entities
     * @param string $field
     * @return string[]
     */
    private static function entities_to_raw_field(wp_imported_entities $entities, string $field = 'name'): array {
        return array_map(static function(wp_imported_entity $entity) use ($field): string {
            return $entity->get_raw_field($field);
        }, iterator_to_array($entities, false));
    }

    /**
     * Get Workplace generator
     *
     * @return tool_wp_generator
     */
    protected function get_plugin_generator(): tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Get certificate generator
     *
     * @return tool_certificate_generator
     */
    protected function get_certificate_generator(): tool_certificate_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_certificate');
    }
}

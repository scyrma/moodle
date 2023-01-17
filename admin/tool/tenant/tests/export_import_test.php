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
 * File containing tests for import/export tenant classes
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use advanced_testcase;
use context_coursecat;
use context_system;
use core_course_category;
use tool_tenant_generator;
use stdClass;
use moodle_url;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\import_persistent;
use tool_wp\local\exportimport\wp_imported_entity;
use tool_tenant\tool_wp\exporter\tenants as exporter;
use tool_tenant\tool_wp\importer\tenants as importer;
use tool_wp_generator;

/**
 * Test class
 *
 * @package     tool_tenant
 * @group       tool_tenant
 * @category    test
 * @covers      \tool_tenant\tool_wp\exporter\tenants
 * @covers      \tool_tenant\tool_wp\importer\tenants
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_test extends advanced_testcase {

    /** @var string $importfixture Containing two tenants, each with two users. */
    protected $importfixture = __DIR__ . '/fixtures/tenants-export-users.zip';

    /** @var string $importfixtureappearance Single tenant, with appearance/branding files. */
    protected $importfixtureappearance = __DIR__ . '/fixtures/tenants-export-appearance.zip';

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    /**
     * Test exporting all tenants
     */
    public function test_export_all_tenants(): void {
        [$tenantone, ] = $this->get_plugin_generator()->create_tenant_and_users(2);
        [$tenanttwo, ] = $this->get_plugin_generator()->create_tenant_and_users(2);

        // Export them!
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
            exporter::EXPORT_USERS => 1,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have both tenants, plus the default tenant.
        $tenants = $importer->get_entities_in_workplace_export_file(tenant::TABLE);
        $this->assertCount(3, $tenants);

        $tenantnames = array_map(static function(wp_imported_entity $entity): string {
            return $entity->get_raw_field('name');
        }, iterator_to_array($tenants, true));

        $this->assertEqualsCanonicalizing([
            get_string('defaultname', 'tool_tenant'),
            tenancy::get_tenant_name_from_id($tenantone->id),
            tenancy::get_tenant_name_from_id($tenanttwo->id),
        ], $tenantnames);

        // We should have 5 tenant users, 2 from each tenant plus 1 from the default tenant.
        $tenantdefaultfilter = static function(array $data): bool {
            return $data['tenantid'] == tenancy::get_default_tenant_id();
        };
        $tenantdefaultusers = $importer->get_entities_in_workplace_export_file(tenant_user::TABLE, $tenantdefaultfilter);
        $this->assertCount(1, $tenantdefaultusers);

        $tenantonefilter = static function(array $data) use ($tenantone): bool {
            return $data['tenantid'] == $tenantone->id;
        };
        $tenantoneusers = $importer->get_entities_in_workplace_export_file(tenant_user::TABLE, $tenantonefilter);
        $this->assertCount(2, $tenantoneusers);

        $tenanttwofilter = static function(array $data) use ($tenanttwo): bool {
            return $data['tenantid'] == $tenanttwo->id;
        };
        $tenanttwousers = $importer->get_entities_in_workplace_export_file(tenant_user::TABLE, $tenanttwofilter);
        $this->assertCount(2, $tenanttwousers);
    }

    /**
     * Test exporting all tenants without user data
     */
    public function test_export_all_tenants_without_users(): void {
        [$tenantone, ] = $this->get_plugin_generator()->create_tenant_and_users(2);

        // Export them!
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
            exporter::EXPORT_USERS => 0,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have the tenant, plus the default tenant.
        $tenants = $importer->get_entities_in_workplace_export_file(tenant::TABLE);
        $this->assertCount(2, $tenants);

        $tenantnames = array_map(static function(wp_imported_entity $entity): string {
            return $entity->get_raw_field('name');
        }, iterator_to_array($tenants, true));

        $this->assertEqualsCanonicalizing([
            get_string('defaultname', 'tool_tenant'),
            tenancy::get_tenant_name_from_id($tenantone->id),
        ], $tenantnames);

        $tenantusers = $importer->get_entities_in_workplace_export_file(tenant_user::TABLE);
        $this->assertCount(0, $tenantusers);
    }

    /**
     * Test that exporting all tenants excludes the "shared space"
     */
    public function test_export_all_tenants_exclude_shared_space(): void {
        sharedspace::enable_shared_space();

        $tenantone = $this->get_plugin_generator()->create_tenant();

        // Export them!
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
            exporter::EXPORT_USERS => 0,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have the tenant, plus the default tenant.
        $tenants = $importer->get_entities_in_workplace_export_file(tenant::TABLE);
        $this->assertCount(2, $tenants);

        $tenantnames = array_map(static function(wp_imported_entity $entity): string {
            return $entity->get_raw_field('name');
        }, iterator_to_array($tenants, true));

        $this->assertEqualsCanonicalizing([
            get_string('defaultname', 'tool_tenant'),
            tenancy::get_tenant_name_from_id($tenantone->id),
        ], $tenantnames);
    }

    /**
     * Test exporting single tenant
     */
    public function test_export_single_tenant(): void {
        $tenantone = $this->get_plugin_generator()->create_tenant();
        $tenanttwo = $this->get_plugin_generator()->create_tenant();

        // Export the first tenant.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$tenantone->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have the first tenant.
        $tenants = $importer->get_entities_in_workplace_export_file(tenant::TABLE);
        $this->assertCount(1, $tenants);

        /** @var wp_imported_entity $tenant */
        $tenant = iterator_to_array($tenants, false)[0];
        $this->assertEquals($tenantone->id, $tenant->get_original_id());
    }

    /**
     * Test exporting single tenant including tenant category
     */
    public function test_export_single_tenant_with_category(): void {
        $tenantonecategory = $this->getDataGenerator()->create_category([
            'name' => 'Tenant category'
        ]);

        $tenantone = $this->get_plugin_generator()->create_tenant([
            'name' => 'Tenant one',
            'categoryid' => $tenantonecategory->id,
        ]);

        // Export the tenant, with category.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$tenantone->id],
            exporter::EXPORT_CATEGORIES => 1,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have our tenant.
        $tenants = $importer->get_entities_in_workplace_export_file(tenant::TABLE);
        $this->assertCount(1, $tenants);

        /** @var wp_imported_entity $tenant */
        $tenant = iterator_to_array($tenants, false)[0];
        $this->assertEquals($tenantone->id, $tenant->get_original_id());

        // Along with our tenant category.
        $categories = $importer->get_entities_in_workplace_export_file('course_categories');
        $this->assertCount(1, $categories);

        /** @var wp_imported_entity $category */
        $category = iterator_to_array($categories, false)[0];
        $this->assertEquals($tenantonecategory->id, $category->get_original_id());
    }

    /**
     * Test exporting single tenant including appearance/branding images
     */
    public function test_export_single_tenant_with_images(): void {
        $tenantone = $this->get_plugin_generator()->create_tenant();

        // Remove all automatically added images, and add our own.
        (new manager())->remove_tenant_images($tenantone->id);
        get_file_storage()->create_file_from_string([
            'contextid' => context_system::instance()->id,
            'component' => 'tool_tenant',
            'filearea' => 'headerlogo',
            'itemid' => $tenantone->id,
            'filepath' => '/',
            'filename' => 'cat.jpg',
        ], 'pictureofacat');

        // Export the tenant, with appearance/branding images.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$tenantone->id],
            exporter::EXPORT_APPEARANCE => 1,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have our tenant.
        $tenants = $importer->get_entities_in_workplace_export_file(tenant::TABLE);
        $this->assertCount(1, $tenants);

        /** @var wp_imported_entity $tenant */
        $tenant = iterator_to_array($tenants, false)[0];
        $this->assertEquals($tenantone->id, $tenant->get_original_id());

        // Along with our tenant files.
        $files = $tenant->get_raw_files();
        $this->assertCount(1, $files);

        $file = reset($files);
        $this->assertEquals('headerlogo', $file['filearea']);
        $this->assertEquals('cat.jpg', $file['filename']);
    }

    /**
     * Test importing all tenants with users
     *
     * The test fixture contains two tenants:
     *  - Tenant 1 (idnumber: tenant01), with two allocated users (user01/user02)
     *  - Tenant 2 (idnumber: tenant02), with two allocated users (user03/user04)
     */
    public function test_import_all_tenants_with_users(): void {
        global $DB;

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            importer::IMPORT_DESTINATION => importer::IMPORT_DESTINATION_NEW,
            importer::IMPORT_USERS => 1,
        ]);

        $import = new import_persistent($importid);
        $this->assertEquals(helper::STATUS_DONE, $import->get('status'));

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($import->get('id'));
        $this->assertEmpty($conflicts);

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($import->get('id'));
        $this->assertCount(6, $logs);

        // We should create both tenants from the test fixture, allocating the users from each.
        $tenantoneid = $DB->get_field(tenant::TABLE, 'id', ['idnumber' => 'tenant01'], MUST_EXIST);
        $tenantoneurl = (new moodle_url('/admin/tool/tenant/edit.php', ['id' => $tenantoneid]))->out();

        $tenanttwoid = $DB->get_field(tenant::TABLE, 'id', ['idnumber' => 'tenant02'], MUST_EXIST);
        $tenanttwourl = (new moodle_url('/admin/tool/tenant/edit.php', ['id' => $tenanttwoid]))->out();

        $this->assertEquals([
            ['detail' => "Imported tenant '<a href=\"{$tenantoneurl}\">Tenant one</a>'", 'errors' => [], 'notices' => []],
            ['detail' => 'Created user \'User One\'', 'errors' => [], 'notices' => []],
            ['detail' => 'Created user \'User Two\'', 'errors' => [], 'notices' => []],
            ['detail' => "Imported tenant '<a href=\"{$tenanttwourl}\">Tenant two</a>'", 'errors' => [], 'notices' => []],
            ['detail' => 'Created user \'User Three\'', 'errors' => [], 'notices' => []],
            ['detail' => 'Created user \'User Four\'', 'errors' => [], 'notices' => []],
        ], $logs);

        // There should now be three tenants (default one, plus two new).
        $tenants = array_map(static function(stdClass $tenant): string {
            return tenancy::get_tenant_name_from_id($tenant->id);
        }, tenancy::get_tenants());

        $this->assertEqualsCanonicalizing([
            'Default tenant',
            'Tenant one',
            'Tenant two',
        ], $tenants);

        // Each new tenant should have two users.
        [$join, $where, $params] = tenancy::get_users_sql('u', $tenantoneid);
        $tenantoneusers = $DB->get_fieldset_sql("SELECT u.username FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEqualsCanonicalizing([
            'user01',
            'user02',
        ], $tenantoneusers);

        [$join, $where, $params] = tenancy::get_users_sql('u', $tenanttwoid);
        $tenanttwousers = $DB->get_fieldset_sql("SELECT u.username FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEqualsCanonicalizing([
            'user03',
            'user04',
        ], $tenanttwousers);
    }

    /**
     * Test importing tenant without user data
     */
    public function test_import_all_tenants_without_users(): void {
        global $DB;

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            importer::IMPORT_DESTINATION => importer::IMPORT_DESTINATION_NEW,
            importer::IMPORT_USERS => 0,
        ]);

        $import = new import_persistent($importid);
        $this->assertEquals(helper::STATUS_DONE, $import->get('status'));

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($import->get('id'));
        $this->assertEmpty($conflicts);

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($import->get('id'));
        $this->assertCount(2, $logs);

        // We should create both tenants from the test fixture.
        $tenantoneid = $DB->get_field(tenant::TABLE, 'id', ['idnumber' => 'tenant01'], MUST_EXIST);
        $tenantoneurl = (new moodle_url('/admin/tool/tenant/edit.php', ['id' => $tenantoneid]))->out();

        $tenanttwoid = $DB->get_field(tenant::TABLE, 'id', ['idnumber' => 'tenant02'], MUST_EXIST);
        $tenanttwourl = (new moodle_url('/admin/tool/tenant/edit.php', ['id' => $tenanttwoid]))->out();

        $this->assertEquals([
            ['detail' => "Imported tenant '<a href=\"{$tenantoneurl}\">Tenant one</a>'", 'errors' => [], 'notices' => []],
            ['detail' => "Imported tenant '<a href=\"{$tenanttwourl}\">Tenant two</a>'", 'errors' => [], 'notices' => []],
        ], $logs);

        // There should now be three tenants (default one, plus two new).
        $tenants = array_map(static function(stdClass $tenant): string {
            return tenancy::get_tenant_name_from_id($tenant->id);
        }, tenancy::get_tenants());

        $this->assertEqualsCanonicalizing([
            'Default tenant',
            'Tenant one',
            'Tenant two',
        ], $tenants);

        // Each new tenant should have no users.
        $tenantoneusers = tenant_user::get_records(['tenantid' => $tenantoneid]);
        $this->assertEmpty($tenantoneusers);

        $tenanttwousers = tenant_user::get_records(['tenantid' => $tenanttwoid]);
        $this->assertEmpty($tenanttwousers);
    }

    /**
     * Test importing single tenant
     */
    public function test_import_single_tenant(): void {
        global $DB;

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_MANUAL,
            // Note this is the ID of "Tenant 2" from the test fixture.
            importer::IMPORT_SELECT_MANUAL => [3],
            importer::IMPORT_DESTINATION => importer::IMPORT_DESTINATION_NEW,
            importer::IMPORT_USERS => 1,
        ]);

        $import = new import_persistent($importid);
        $this->assertEquals(helper::STATUS_DONE, $import->get('status'));

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($import->get('id'));
        $this->assertEmpty($conflicts);

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($import->get('id'));
        $this->assertCount(3, $logs);

        // We should create "Tenant 2" from the test fixture, allocating the appropriate users.
        $tenanttwoid = $DB->get_field(tenant::TABLE, 'id', ['idnumber' => 'tenant02'], MUST_EXIST);
        $tenanttwourl = (new moodle_url('/admin/tool/tenant/edit.php', ['id' => $tenanttwoid]))->out();

        $this->assertEquals([
            ['detail' => "Imported tenant '<a href=\"{$tenanttwourl}\">Tenant two</a>'", 'errors' => [], 'notices' => []],
            ['detail' => 'Created user \'User Three\'', 'errors' => [], 'notices' => []],
            ['detail' => 'Created user \'User Four\'', 'errors' => [], 'notices' => []],
        ], $logs);

        // There should now be two tenants (default one, plus new one).
        $tenants = array_map(static function(stdClass $tenant): string {
            return tenancy::get_tenant_name_from_id($tenant->id);
        }, tenancy::get_tenants());

        $this->assertEqualsCanonicalizing([
            'Default tenant',
            'Tenant two',
        ], $tenants);

        // The new tenant should have two users.
        [$join, $where, $params] = tenancy::get_users_sql('u', $tenanttwoid);
        $tenanttwousers = $DB->get_fieldset_sql("SELECT u.username FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEqualsCanonicalizing([
            'user03',
            'user04',
        ], $tenanttwousers);
    }

    /**
     * Test importing single tenant including appearance/branding images
     */
    public function test_import_single_tenant_with_images(): void {
        global $DB;

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixtureappearance, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            importer::IMPORT_DESTINATION => importer::IMPORT_DESTINATION_NEW,
            importer::IMPORT_APPEARANCE => 1,
        ]);

        $import = new import_persistent($importid);
        $this->assertEquals(helper::STATUS_DONE, $import->get('status'));

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($import->get('id'));
        $this->assertEmpty($conflicts);

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($import->get('id'));
        $this->assertCount(1, $logs);

        [
            'detail' => $detail,
            'errors' => $errors,
            'notices' => $notices,
        ] = reset($logs);

        $this->assertMatchesRegularExpression('/^Imported tenant .*My tenant/', $detail);
        $this->assertEmpty($errors);
        $this->assertEmpty($notices);

        $mytenantid = $DB->get_field(tenant::TABLE, 'id', ['name' => 'My tenant'], MUST_EXIST);
        $mytenantfiles = $DB->get_records_select('files', 'contextid = ? AND component = ? AND itemid = ? AND filename != ?', [
            context_system::instance()->id,
            'tool_tenant',
            $mytenantid,
            '.',
        ]);

        $this->assertCount(1, $mytenantfiles);
        $mytenantfile = reset($mytenantfiles);

        $this->assertEquals('headerlogo', $mytenantfile->filearea);
        $this->assertEquals('Moodle-1-740x380.png', $mytenantfile->filename);

        $manager = new manager();
        $tenant = $manager->get_tenant($mytenantid);

        // Get cssconfig value exactly as the exported tenant file (tenants-export-appearance.zip).
        $css = '{"mform_isexpanded_id_colours_H9qrugxfS7XP6xN":1,"primary":"#D60010","brand":"#B800E6",' .
            '"button":"#04CFE6","drawer":"#7660FB","footer":"#810096","mform_isexpanded_id_advanced_OOIIIbM7CM6nwGi":1,' .
            '"customcss":"","footertext":"","mform_isexpanded_id_reset_hmg8BIqcMD7XBUC":1}';

        // Assert CSS config was imported to recently created tenant.
        $this->assertEqualsCanonicalizing($css, $tenant->get('cssconfig'));

    }

    /**
     * Test importing single tenant, merging into an existing tenant
     */
    public function test_import_single_tenant_merge(): void {
        global $DB;

        // Merge into the default tenant.
        $tenantmergeid = tenancy::get_default_tenant_id();

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_MANUAL,
            // Note this is the ID of "Tenant 2" from the test fixture.
            importer::IMPORT_SELECT_MANUAL => [3],
            importer::IMPORT_DESTINATION => importer::IMPORT_DESTINATION_MERGE,
            importer::IMPORT_SELECT_DESTINATION_TENANT => $tenantmergeid,
            importer::IMPORT_USERS => 1,
        ]);

        $import = new import_persistent($importid);
        $this->assertEquals(helper::STATUS_DONE, $import->get('status'));

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($import->get('id'));
        $this->assertEmpty($conflicts);

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($import->get('id'));
        $this->assertCount(3, $logs);

        // We should create "Tenant 2" from the test fixture, allocating the appropriate users.
        $tenanttwourl = (new moodle_url('/admin/tool/tenant/edit.php', ['id' => $tenantmergeid]))->out();

        $this->assertEquals([
            ['detail' => "Imported tenant '<a href=\"{$tenanttwourl}\">Tenant two</a>'", 'errors' => [], 'notices' => []],
            ['detail' => 'Created user \'User Three\'', 'errors' => [], 'notices' => []],
            ['detail' => 'Created user \'User Four\'', 'errors' => [], 'notices' => []],
        ], $logs);

        // There should one tenant (the default one, renamed to "Tenant two").
        $tenants = array_map(static function(stdClass $tenant): string {
            return tenancy::get_tenant_name_from_id($tenant->id);
        }, tenancy::get_tenants());

        $this->assertEqualsCanonicalizing([
            'Tenant two',
        ], $tenants);

        // The new tenant should have three users (the admin, plus two new ones).
        [$join, $where, $params] = tenancy::get_users_sql('u', $tenantmergeid);
        $tenanttwousers = $DB->get_fieldset_sql("SELECT u.username FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEqualsCanonicalizing([
            'admin',
            'user03',
            'user04',
        ], $tenanttwousers);
    }

    /**
     * Test importing single tenant, resolving idnumber conflict by incrementing
     */
    public function test_import_single_tenant_idnumber_conflict_increment(): void {
        global $DB;

        // Create a tenant that will conflict with one from the test fixture.
        $this->get_plugin_generator()->create_tenant([
            'name' => 'Tenant conflict',
            'idnumber' => 'tenant02',
        ]);

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_MANUAL,
            // Note this is the ID of "Tenant 2" from the test fixture.
            importer::IMPORT_SELECT_MANUAL => [3],
            importer::IMPORT_DESTINATION => importer::IMPORT_DESTINATION_NEW,
            importer::IMPORT_USERS => 0,
            helper::get_importer_setting_name_for_conflict_form(tenant::TABLE, 'idnumberconflict', 'action') => 'increment',
        ]);

        $import = new import_persistent($importid);
        $this->assertEquals(helper::STATUS_DONE, $import->get('status'));

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($import->get('id'));
        $this->assertCount(1, $conflicts);
        $this->assertEquals([
            'An instance with the same \'idnumber\' already exists',
            'Add a numeric suffix to the \'idnumber\' field',
        ], reset($conflicts));

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($import->get('id'));
        $this->assertCount(1, $logs);

        // The idnumber has been changed to "tenant3" for our imported tenant.
        $tenanttwoid = $DB->get_field(tenant::TABLE, 'id', ['idnumber' => 'tenant3'], MUST_EXIST);
        $tenanttwourl = (new moodle_url('/admin/tool/tenant/edit.php', ['id' => $tenanttwoid]))->out();

        $this->assertEquals([
            'detail' => "Imported tenant '<a href=\"{$tenanttwourl}\">Tenant two</a>'",
            'errors' => [],
            'notices' => [
                'Changed field \'idnumber\' from \'tenant02\' to \'tenant3\'',
            ],
        ], reset($logs));

        // There should now be three tenants (default one, conflict one, plus new one).
        $tenants = array_map(static function(stdClass $tenant): string {
            return tenancy::get_tenant_name_from_id($tenant->id);
        }, tenancy::get_tenants());

        $this->assertEqualsCanonicalizing([
            'Default tenant',
            'Tenant conflict',
            'Tenant two',
        ], $tenants);
    }

    /**
     * Test importing single tenant, resolving idnumber conflict by skipping
     */
    public function test_import_single_tenant_idnumber_conflict_skip(): void {
        // Create a tenant that will conflict with one from the test fixture.
        $this->get_plugin_generator()->create_tenant([
            'name' => 'Tenant conflict',
            'idnumber' => 'tenant02',
        ]);

        $importid = $this->get_workplace_generator()->perform_import_from_file($this->importfixture, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_MANUAL,
            // Note this is the ID of "Tenant 2" from the test fixture.
            importer::IMPORT_SELECT_MANUAL => [3],
            importer::IMPORT_DESTINATION => importer::IMPORT_DESTINATION_NEW,
            importer::IMPORT_USERS => 0,
            helper::get_importer_setting_name_for_conflict_form(tenant::TABLE, 'idnumberconflict', 'action') => 'skip',
        ]);

        $import = new import_persistent($importid);
        $this->assertEquals(helper::STATUS_DONE, $import->get('status'));

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($import->get('id'));
        $this->assertCount(1, $conflicts);
        $this->assertEquals([
            'An instance with the same \'idnumber\' already exists',
            'Do not import',
        ], reset($conflicts));

        // Analyse logs.
        $logs = $this->get_workplace_generator()->get_import_logs($import->get('id'));
        $this->assertCount(1, $logs);

        $this->assertEquals([
            'detail' => 'Couldn\'t import tenant \'Tenant two\'',
            'errors' => [
                'An instance with the same \'idnumber\' already exists',
            ],
            'notices' => [],
        ], reset($logs));

        // There should just be the original tenants (default one, plus conflict one).
        $tenants = array_map(static function(stdClass $tenant): string {
            return tenancy::get_tenant_name_from_id($tenant->id);
        }, tenancy::get_tenants());

        $this->assertEqualsCanonicalizing([
            'Default tenant',
            'Tenant conflict',
        ], $tenants);
    }

    /**
     * Test exporting a tenant with users, and then importing it into a new tenant on the same site
     */
    public function test_import_single_tenant_with_users_same_site(): void {
        $tenantonecategory = $this->getDataGenerator()->create_category();

        [$tenantone, $tenantoneusers] = $this->get_plugin_generator()->create_tenant_and_users(3, [
            'name' => 'Tenant one',
            'categoryid' => $tenantonecategory->id,
        ]);

        // Create a cohort in the tenant category instance, and assign our tenant users to it.
        $cohort = $this->getDataGenerator()->create_cohort([
            'contextid' => context_coursecat::instance($tenantonecategory->id)->id,
        ]);

        foreach ($tenantoneusers as $tenantoneuser) {
            cohort_add_member($cohort->id, $tenantoneuser->id);
        }

        // Export the tenant, with category and users.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$tenantone->id],
            exporter::EXPORT_CATEGORIES => 1,
            exporter::EXPORT_USERS => 1,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Delete one user, they will be created and allocated during import.
        delete_user($tenantoneusers[2]);

        // Now import it.
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            importer::IMPORT_DESTINATION => importer::IMPORT_DESTINATION_NEW,
            importer::IMPORT_CATEGORIES => 1,
            importer::IMPORT_USERS => 1,
            helper::get_importer_setting_name_for_conflict_form('user', 'usernameconflict', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form('user', 'emailconflict', 'action') => 'skip',
        ]);

        $import = new import_persistent($importid);
        $this->assertEquals(helper::STATUS_DONE, $import->get('status'));

        // Conflicts.
        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($import->get('id'));
        $this->assertEquals([
            [
                'An instance with the same \'username\' already exists',
                'Do not import',
            ],
            [
                'An instance with the same \'email\' already exists',
                'Do not import',
            ],
        ], $conflicts);

        // Get the last tenant, which should be our imported one.
        $tenants = tenancy::get_tenants();
        $importedtenant = end($tenants);

        $importedtenanturl = (new moodle_url('/admin/tool/tenant/edit.php', ['id' => $importedtenant->id]))->out();

        $importedcategory = core_course_category::get($importedtenant->categoryid);
        $importedcategoryurl = (new moodle_url('/course/management.php', ['categoryid' => $importedcategory->id]))->out();

        $importedcohorts = cohort_get_cohorts(context_coursecat::instance($importedcategory->id)->id);
        $this->assertEquals(1, $importedcohorts['totalcohorts']);

        $importedcohort = reset($importedcohorts['cohorts']);
        $importedcohorturl = (new moodle_url('/cohort/edit.php', ['id' => $importedcohort->id]))->out();

        // Analyse logs (the users already exist on the site so can't be created, nor allocated to the imported cohort).
        $logs = $this->get_workplace_generator()->get_import_logs($import->get('id'));
        $this->assertEquals([
            "Imported tenant '<a href=\"{$importedtenanturl}\">Tenant one</a>'",
            'Couldn\'t create user \'' . fullname($tenantoneusers[0]) . '\'',
            'Couldn\'t create user \'' . fullname($tenantoneusers[1]) . '\'',
            'Created user \'' . fullname($tenantoneusers[2]) . '\'',
            "Created new course category '<a href=\"{$importedcategoryurl}\">{$importedcategory->name}</a>'",
            "Created new cohort '<a href=\"{$importedcohorturl}\">{$importedcohort->name}</a>'",
            "Could not allocate user '" . fullname($tenantoneusers[0]) . "' to cohort '{$importedcohort->name}'",
            "Could not allocate user '" . fullname($tenantoneusers[1]) . "' to cohort '{$importedcohort->name}'",
            'Allocated user \'' . fullname($tenantoneusers[2]) . "' into cohort '{$importedcohort->name}'",
        ], array_column($logs, 'detail'));
    }

    /**
     * Returns the plugin generator
     *
     * @return tool_tenant_generator
     */
    protected function get_plugin_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Returns the Workplace generator
     *
     * @return tool_wp_generator
     */
    protected function get_workplace_generator(): tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }
}

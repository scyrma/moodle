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
 * Class export_import_test
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

use advanced_testcase;
use context;
use context_coursecat;
use context_system;
use tool_wp_generator;
use tool_tenant_generator;
use tool_certificate\persistent\template;
use tool_certificate_generator;
use tool_wp\local\exportimport\import_manager;
use tool_wp\tool_wp\exporter\certificates as exporter;
use tool_wp\tool_wp\importer\certificates as importer;
use tool_wp\local\exportimport\helper;

/**
 * Test class
 *
 * @package     tool_wp
 * @covers      \tool_wp\tool_wp\exporter\certificates
 * @covers      \tool_wp\tool_wp\importer\certificates
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_certificates_test extends advanced_testcase {

    /** @var tool_certificate_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_wp_generator */
    protected $wpgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certificate');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');

        $this->resetAfterTest();
    }

    /**
     * tearDown.
     */
    public function tearDown(): void {
        // Custom fields need to be reset after each test runs.
        \tool_certificate\customfield\issue_handler::create()->delete_all();
    }

    /**
     * Create dummy certificates
     *
     * @param int $numbercertificates
     * @param context|null $context If omitted, then system context will be used
     * @return \tool_certificate\template[]
     */
    public function generate_dummy_certificates(int $numbercertificates = 1, context $context = null): array {
        $certificates = [];
        for ($i = 1; $i <= $numbercertificates; $i++) {
            $params = (object) [
                'name' => 'Certificate ' . $i,
                'contextid' => $context ? $context->id : context_system::instance()->id,
            ];
            $template = $this->generator->create_template($params);
            $pageid1 = $this->generator->create_page($template)->get_id();
            $e = $this->generator->new_element($pageid1, 'text');
            $newdata = (object)['text' => 'New text 1'];
            $e->save_form_data($newdata);
            $pageid2 = $this->generator->create_page($template)->get_id();
            $e = $this->generator->new_element($pageid2, 'text');
            $newdata = (object)['text' => 'New text 2'];
            $e->save_form_data($newdata);
            $certificates[] = $template;
        }
        return $certificates;
    }

    /**
     * Test for export all certificates.
     */
    public function test_export_all_certificates() {
        global $DB;
        self::setAdminUser();
        $certificates = $this->generate_dummy_certificates(2);

        $this->assertCount(2, $DB->get_records(\tool_certificate\persistent\template::TABLE));
        $this->assertCount(4, $DB->get_records(\tool_certificate\persistent\element::TABLE));
        $this->assertCount(4, $DB->get_records(\tool_certificate\persistent\page::TABLE));

        // Create a new export containing all certificate templates.
        $exportid = $this->wpgenerator->perform_export(exporter::class, [
            exporter::EXPORT_TYPE => exporter::EXPORT_TYPE_ALL,
            exporter::EXPORT_CERTIFICATE_TEMPLATES => 1,
            exporter::EXPORT_ISSUED_CERTIFICATES => 1,
        ]);

        // Analyse export file.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid, [
            importer::IMPORT_TYPE => importer::IMPORT_TYPE_ALL,
            importer::IMPORT_CERTIFICATE_TEMPLATES => 1,
            exporter::EXPORT_ISSUED_CERTIFICATES => 1,
        ]);
        $importmanager = new import_manager($importid);
        $importer = $importmanager->get_importer();
        $this->assertInstanceOf(importer::class, $importer);

        $templates = $importer->get_entities_in_workplace_export_file(\tool_certificate\persistent\template::TABLE);
        $this->assertCount(2, $templates);
        $this->assertCount(2, $importmanager->get_instances());

        $templatenames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($templates, false));
        $expected = [$certificates[0]->get_name(), $certificates[1]->get_name()];
        $this->assertEqualsCanonicalizing($expected, $templatenames);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($templates, false)[0];

        $pages = $entity->get_nested_entities(\tool_certificate\persistent\page::TABLE);
        $this->assertCount(2, $pages);

        $elements = $entity->get_nested_entities(\tool_certificate\persistent\element::TABLE);
        $this->assertCount(2, $elements);
    }

    /**
     * Test for export all certificates from one category.
     */
    public function test_export_all_certificates_from_one_category() {
        global $DB;

        self::setAdminUser();

        // Create category heirarchy: Category -> Sub-category -> Last category.
        $category = $this->getDataGenerator()->create_category();
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $lastcategory = $this->getDataGenerator()->create_category(['parent' => $subcategory->id]);

        $categorycontext = context_coursecat::instance($category->id);
        $subcategorycontext = context_coursecat::instance($subcategory->id);
        $lastcategorycontext = context_coursecat::instance($lastcategory->id);

        $certificates = $this->generate_dummy_certificates(1, $categorycontext);
        $subcertificates = $this->generate_dummy_certificates(1, $subcategorycontext);
        $lastcertificates = $this->generate_dummy_certificates(1, $lastcategorycontext);

        // Sanity check.
        $this->assertCount(3, $DB->get_records(template::TABLE));

        // Export certificates from the parent category context (should also include those in child categories).
        $exportid = $this->wpgenerator->perform_export(exporter::class, [
            exporter::EXPORT_TYPE => exporter::EXPORT_TYPE_CAT_MANUALLY,
            exporter::EXPORT_SELECTED_CATEGORIES => [$category->id],
            exporter::EXPORT_CERTIFICATE_TEMPLATES => 1,
        ]);

        // Analyse export file.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid, [
            importer::IMPORT_TYPE => importer::IMPORT_TYPE_ALL,
            importer::IMPORT_CERTIFICATE_TEMPLATES => 1,
        ]);

        $importmanager = new import_manager($importid);

        $importer = $importmanager->get_importer();
        $this->assertInstanceOf(importer::class, $importer);

        $templates = $importer->get_entities_in_workplace_export_file(\tool_certificate\persistent\template::TABLE);
        $this->assertCount(3, $templates);
        $this->assertCount(3, $importmanager->get_instances());

        $templatenames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($templates, false));

        $this->assertEqualsCanonicalizing([
            $certificates[0]->get_name(),
            $subcertificates[0]->get_name(),
            $lastcertificates[0]->get_name(),
        ], $templatenames);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($templates, false)[0];

        $pages = $entity->get_nested_entities(\tool_certificate\persistent\page::TABLE);
        $this->assertCount(2, $pages);

        $elements = $entity->get_nested_entities(\tool_certificate\persistent\element::TABLE);
        $this->assertCount(2, $elements);
    }

    /**
     * Test for export and import one certificate
     */
    public function test_export_import_one_certificate() {
        global $DB;
        self::setAdminUser();
        $certificates = $this->generate_dummy_certificates(2);
        $certificate = reset($certificates);
        $this->assertCount(2, $DB->get_records(\tool_certificate\persistent\template::TABLE));

        // Create a new export containing only the first certificate template.
        $exportid = $this->wpgenerator->perform_export(exporter::class, [
            exporter::EXPORT_TYPE => exporter::EXPORT_TYPE_MANUALLY,
            exporter::EXPORT_SELECTED_TEMPLATES => [$certificate->get_id()],
            exporter::EXPORT_CERTIFICATE_TEMPLATES => 1,
            exporter::EXPORT_ISSUED_CERTIFICATES => 1,
        ]);

        // Analyse export file.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid, [
            importer::IMPORT_TYPE => importer::IMPORT_TYPE_ALL,
            importer::IMPORT_CERTIFICATE_TEMPLATES => 1,
            importer::IMPORT_CERTIFICATE_ISSUES => 1,
        ]);
        $importmanager = new import_manager($importid);
        $importer = $importmanager->get_importer();
        $this->assertInstanceOf(importer::class, $importer);

        $entities = $importer->get_entities_in_workplace_export_file(\tool_certificate\persistent\template::TABLE);
        $this->assertCount(1, $entities);
        $this->assertCount(1, $importmanager->get_instances());

        // Perform import.
        $this->wpgenerator->perform_import($importid);
        $logs = $this->wpgenerator->get_import_logs($importid);

        // Get the imported certificate template.
        $certificates = \tool_certificate\persistent\template::get_records(['name' => 'Certificate 1'], 'id');
        $newcertificate = \tool_certificate\template::instance(array_pop($certificates)->get('id'));
        $this->assertEquals([[
            'detail' => 'Created new certificate template \'<a href="' . $newcertificate->edit_url()->out()
                . '">Certificate 1</a>\' with 2 pages and 2 elements',
            'errors' => [],
            'notices' => [],
        ]], $logs);
        $this->assertCount(3, $DB->get_records(\tool_certificate\persistent\template::TABLE));
    }

    /**
     * Test for importing a certificate with issues.
     */
    public function test_certificate_import_generates_new_codes() {
        global $DB;
        self::setAdminUser();
        $certificates = $this->generate_dummy_certificates(1);
        $certificate = reset($certificates);

        $user = self::getDataGenerator()->create_user();
        $this->generator->issue($certificate, $user);

        $this->assertCount(1, \tool_certificate\persistent\template::get_records());
        $this->assertCount(1, $DB->get_records('tool_certificate_issues'));

        // Create a new export containing only the first certificate template.
        $exportid = $this->wpgenerator->perform_export(exporter::class, [
            exporter::EXPORT_TYPE => exporter::EXPORT_TYPE_ALL,
            exporter::EXPORT_CERTIFICATE_TEMPLATES => 1,
            exporter::EXPORT_ISSUED_CERTIFICATES => 1,
        ]);

        // Import certificate from the export file.
        $settings = [
            importer::IMPORT_TYPE => importer::IMPORT_TYPE_ALL,
            importer::IMPORT_CERTIFICATE_TEMPLATES => 1,
            importer::IMPORT_CERTIFICATE_ISSUES => 1,
        ];
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $importrecord->status);

        // Template and issue was imported.
        $this->assertCount(2, \tool_certificate\persistent\template::get_records());
        $this->assertCount(2, $DB->get_records('tool_certificate_issues'));

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(2, $logs);
        $this->assertCount(0, $logs[0]['errors']);
        $this->assertCount(0, $logs[0]['notices']);
        $this->assertCount(0, $logs[1]['errors']);
        $this->assertCount(1, $logs[1]['notices']);
        $this->assertStringStartsWith('Issue code was changed from', $logs[1]['notices'][0]);
        $a = (object)[
            'template' => $certificate->get_name(),
            'originaluserfullname' => fullname($user),
        ];
        $expected = get_string('importlogsuccessissue', 'tool_wp', $a);
        $this->assertEquals($expected, $logs[1]['detail']);
    }

    /**
     * Test callbacks for the export review
     */
    public function test_export_review() {
        self::setAdminUser();
        $certificates = $this->generate_dummy_certificates(3);
        $certificate = reset($certificates);

        $user = self::getDataGenerator()->create_user();
        $this->generator->issue($certificate, $user);

        // Test exporter summary for 3 certificates.
        $settings = [
            exporter::EXPORT_TYPE => exporter::EXPORT_TYPE_ALL,
            exporter::EXPORT_CERTIFICATE_TEMPLATES => 1,
            exporter::EXPORT_ISSUED_CERTIFICATES => 1,
        ];
        $exporter = \tool_wp\local\exportimport\export_manager::create_exporter(exporter::class,
            '', 0, $settings);

        $summary = $exporter->get_summary_for_review_step(false);
        $this->assertNotEmpty($summary);
        foreach ($exporter->get_entities() as $entityname) {
            $instances = $exporter->get_instances_for_review_step($entityname);
            $this->assertCount(3, $instances);
        }

        // Test exporter summary for 1 certificate.
        $settings = [
            exporter::EXPORT_TYPE => exporter::EXPORT_TYPE_MANUALLY,
            exporter::EXPORT_SELECTED_TEMPLATES => [$certificate->get_id()],
            exporter::EXPORT_CERTIFICATE_TEMPLATES => 1,
            exporter::EXPORT_ISSUED_CERTIFICATES => 1,
        ];
        $exporter = \tool_wp\local\exportimport\export_manager::create_exporter(exporter::class,
            '', 0, $settings);

        $summary = $exporter->get_summary_for_review_step(false);
        $this->assertNotEmpty($summary);
        foreach ($exporter->get_entities() as $entityname) {
            $instances = $exporter->get_instances_for_review_step($entityname);
            $this->assertCount(1, $instances);
        }
    }

    /**
     * Test settings form callback.
     */
    public function test_settings_form() {
        self::setAdminUser();
        $category1 = self::getDataGenerator()->create_category();
        $certificates = $this->generate_dummy_certificates(3);
        $certificate = reset($certificates);

        $user = self::getDataGenerator()->create_user();
        $this->generator->issue($certificate, $user);

        // Create a new export containing only the first certificate template.
        $exportid = $this->wpgenerator->perform_export(exporter::class, [
            exporter::EXPORT_TYPE => exporter::EXPORT_TYPE_ALL,
            exporter::EXPORT_CERTIFICATE_TEMPLATES => 1,
            exporter::EXPORT_ISSUED_CERTIFICATES => 1,
        ]);

        // Import certificate from the export file.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [
            importer::IMPORT_TYPE => importer::IMPORT_TYPE_SELECTED,
            importer::IMPORT_TYPE_SELECTED => [],
            importer::IMPORT_CERTIFICATE_TEMPLATES => 1,
            importer::IMPORT_CERTIFICATE_ISSUES => 1,
            importer::IMPORT_SELECT_CATEGORY => $category1->id,
            helper::get_importer_setting_name_for_conflict_form('tool_certificate_issues', 'codeexistsconflict',
                'action') => 'generatenew',
        ];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertFalse($form->is_validated());

        $this->assertEquals([importer::IMPORT_SELECTED_TEMPLATES => 'Select at least one template'],
            $form->get_quick_form()->_errors);

        // Submitting an import form without errors, make sure all default values apply.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [importer::IMPORT_SELECT_CATEGORY => $category1->id];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertEquals(1, $data->{importer::IMPORT_CERTIFICATE_TEMPLATES});
        $this->assertEquals(1, $data->{importer::IMPORT_CERTIFICATE_ISSUES});
        $this->assertEquals(importer::IMPORT_TYPE_ALL, $data->{importer::IMPORT_TYPE});
    }
}

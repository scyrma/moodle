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
 * Class export_import_course_categories_test
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_wp\tool_wp\importer\coursecategories as categoriesimporter;
use tool_wp\tool_wp\exporter\coursecategories as categoriesexporter;

/**
 * Class export_import_course_categories_test
 *
 * @covers     \tool_wp\tool_wp\exporter\coursecategories
 * @covers     \tool_wp\tool_wp\importer\coursecategories
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_export_import_course_categories_testcase extends advanced_testcase {
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_wp_generator */
    protected $wpgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test exporting single course category
     *
     * @return void
     */
    public function test_export_single_course_category(): void {
        self::setAdminUser();

        $coursecategory = self::getDataGenerator()->create_category();

        // Create a new export.
        $exportid = $this->wpgenerator->perform_export(categoriesexporter::class, [
            categoriesexporter::EXPORT_CONTENT => 1,
            categoriesexporter::EXPORT_COURSES => 0,
            categoriesexporter::EXPORT_COURSES_CONTENT => 0,
            categoriesexporter::EXPORT_CERTIFICATE_TEMPLATES => 0,
            categoriesexporter::EXPORT_COHORTS => 0,
            categoriesexporter::EXPORT_SELECT_CATEGORIES => [$coursecategory->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\coursecategories::class, $importer);

        // We should have the first course in the import.
        $courses = $importer->get_entities_in_workplace_export_file('course_categories');
        $this->assertCount(1, $courses);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($courses, false)[0];
        $this->assertEquals($coursecategory->name, $entity->get_raw_field('name'));
        $this->assertCount(0, $entity->get_raw_files([]));
    }

    /**
     * Test exporting and import all categories with subcategories
     *
     * @return void
     */
    public function test_export_import_all_course_categories(): void {
        global $DB;
        self::setAdminUser();

        $category1 = self::getDataGenerator()->create_category();
        $category1a = self::getDataGenerator()->create_category(['parent' => $category1->id]);
        $category2 = self::getDataGenerator()->create_category();

        $this->assertCount(4, $DB->get_records('course_categories'));

        // Create a new export.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\coursecategories::class, [
            categoriesexporter::EXPORT_CONTENT => 1,
            categoriesexporter::EXPORT_COURSES => 0,
            categoriesexporter::EXPORT_COURSES_CONTENT => 0,
            categoriesexporter::EXPORT_CERTIFICATE_TEMPLATES => 0,
            categoriesexporter::EXPORT_COHORTS => 0,
            categoriesexporter::EXPORT_SELECT_CATEGORIES => [$category1->id, $category1a->id, $category2->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\coursecategories::class, $importer);

        // We should have 3 course categories in the import.
        $categories = $importer->get_entities_in_workplace_export_file('course_categories');
        $this->assertCount(3, $categories);

        $categoriesnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($categories, false));
        $this->assertEqualsCanonicalizing([$category1->name, $category1a->name, $category2->name], $categoriesnames);

        // Import course categories from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            categoriesimporter::IMPORT_CONTENT => 1,
            categoriesimporter::IMPORT_COURSES => 0,
            categoriesimporter::IMPORT_CERTIFICATE_TEMPLATES => 0,
            categoriesimporter::IMPORT_COHORTS => 0,
            categoriesimporter::IMPORT_INSTANCES => categoriesimporter::IMPORT_INSTANCES_ALL,
        ]);

        $this->assertCount(7, $DB->get_records('course_categories'));

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(0, $conflicts);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(3, $logs);

        $this->assertStringStartsWith('Created new course category', $logs[0]['detail']);
        $this->assertEmpty($logs[0]['errors']);
        $this->assertStringStartsWith('Created new course category', $logs[1]['detail']);
        $this->assertEmpty($logs[1]['errors']);
        $this->assertStringStartsWith('Created new course category', $logs[2]['detail']);
        $this->assertEmpty($logs[2]['errors']);
    }

    /**
     * Test exporting and import some categories with subcategories
     *
     * @return void
     */
    public function test_export_import_some_course_categories_subcategories(): void {
        global $DB;
        self::setAdminUser();

        $category1 = self::getDataGenerator()->create_category(['name' => 'Cat 1']);
        $category1a = self::getDataGenerator()->create_category(['parent' => $category1->id, 'name' => 'Cat 1A']);
        $category2 = self::getDataGenerator()->create_category();
        $categorybase = self::getDataGenerator()->create_category();

        $this->assertCount(5, $DB->get_records('course_categories'));

        // Create a new export.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\coursecategories::class, [
            categoriesexporter::EXPORT_CONTENT => 1,
            categoriesexporter::EXPORT_COURSES => 0,
            categoriesexporter::EXPORT_COURSES_CONTENT => 0,
            categoriesexporter::EXPORT_CERTIFICATE_TEMPLATES => 0,
            categoriesexporter::EXPORT_COHORTS => 0,
            categoriesexporter::EXPORT_SELECT_CATEGORIES => [$category1->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\coursecategories::class, $importer);

        // We should have 2 course categories in the import.
        $categories = $importer->get_entities_in_workplace_export_file('course_categories');
        $this->assertCount(2, $categories);

        $categoriesnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($categories, false));
        $this->assertEqualsCanonicalizing([$category1->name, $category1a->name], $categoriesnames);

        // Import course categories from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            categoriesimporter::IMPORT_CONTENT => 1,
            categoriesimporter::IMPORT_COURSES => 0,
            categoriesimporter::IMPORT_CERTIFICATE_TEMPLATES => 0,
            categoriesimporter::IMPORT_COHORTS => 0,
            categoriesimporter::IMPORT_INSTANCES => categoriesimporter::IMPORT_INSTANCES_SELECTED,
            categoriesimporter::IMPORT_SELECT_COURSE_CATEGORIES => [$category1->id],
            categoriesimporter::IMPORT_SELECT_CATEGORY => $categorybase->id,
        ]);

        $this->assertCount(7, $DB->get_records('course_categories'));

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(0, $conflicts);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(2, $logs);

        $this->assertStringStartsWith('Created new course category', $logs[0]['detail']);
        $this->assertEmpty($logs[0]['errors']);
        $this->assertStringStartsWith('Created new course category', $logs[1]['detail']);
        $this->assertEmpty($logs[1]['errors']);

        // Check that Cat1 has been imported under categorybase, and that cat1A has been imported under Cat1.
        $cats = array_values($DB->get_records('course_categories', ['parent' => $categorybase->id]));
        $this->assertCount(1, $cats);
        $this->assertEquals('Cat 1', $cats[0]->name);
        $subcats = array_values($DB->get_records('course_categories', ['parent' => $cats[0]->id]));
        $this->assertCount(1, $subcats);
        $this->assertEquals('Cat 1A', $subcats[0]->name);
    }

    /**
     * Test exporting and import all categories with idnumber conflict resolution increment
     *
     * @return void
     */
    public function test_export_import_idnumber_conflict_increment(): void {
        global $DB;
        self::setAdminUser();

        $category1 = self::getDataGenerator()->create_category(['idnumber' => 'ID1']);
        $category1a = self::getDataGenerator()->create_category(['parent' => $category1->id, 'idnumber' => 'ID2']);
        $category2 = self::getDataGenerator()->create_category();

        $this->assertCount(4, $DB->get_records('course_categories'));

        // Create a new export.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\coursecategories::class, [
            categoriesexporter::EXPORT_CONTENT => 1,
            categoriesexporter::EXPORT_COURSES => 0,
            categoriesexporter::EXPORT_COURSES_CONTENT => 0,
            categoriesexporter::EXPORT_CERTIFICATE_TEMPLATES => 0,
            categoriesexporter::EXPORT_COHORTS => 0,
            categoriesexporter::EXPORT_SELECT_CATEGORIES => [$category1->id, $category2->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\coursecategories::class, $importer);

        // We should have 3 course categories in the import.
        $categories = $importer->get_entities_in_workplace_export_file('course_categories');
        $this->assertCount(3, $categories);

        $categoriesnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($categories, false));
        $this->assertEqualsCanonicalizing([$category1->name, $category1a->name, $category2->name], $categoriesnames);

        // Import course categories from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            categoriesimporter::IMPORT_CONTENT => 1,
            categoriesimporter::IMPORT_COURSES => 0,
            categoriesimporter::IMPORT_CERTIFICATE_TEMPLATES => 0,
            categoriesimporter::IMPORT_COHORTS => 0,
            categoriesimporter::IMPORT_INSTANCES => categoriesimporter::IMPORT_INSTANCES_SELECTED,
            categoriesimporter::IMPORT_SELECT_COURSE_CATEGORIES => [$category1->id, $category2->id],
            \tool_wp\local\exportimport\helper::get_importer_setting_name_for_conflict_form(
                'course_categories', 'idnumberconflict', 'action') => 'increment',
        ]);

        $this->assertCount(7, $DB->get_records('course_categories'));

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(3, $logs);

        $this->assertStringStartsWith('Created new course category', $logs[0]['detail']);
        $this->assertEmpty($logs[0]['errors']);
        $this->assertEquals($logs[0]['notices'][0], "ID number was changed from 'ID1' to 'ID3'");

        $this->assertStringStartsWith('Created new course category', $logs[1]['detail']);
        $this->assertEmpty($logs[1]['errors']);
        $this->assertEquals($logs[1]['notices'][0], "ID number was changed from 'ID2' to 'ID4'");

        $this->assertStringStartsWith('Created new course category', $logs[2]['detail']);
        $this->assertEmpty($logs[2]['errors']);
        $this->assertEmpty($logs[2]['notices']);
    }

    /**
     * Test exporting and import all categories with idnumber conflict resolution skip
     *
     * @return void
     */
    public function test_export_import_idnumber_conflict_skip(): void {
        global $DB;
        self::setAdminUser();

        $category1 = self::getDataGenerator()->create_category(['idnumber' => 'ID1']);
        $category1a = self::getDataGenerator()->create_category(['parent' => $category1->id, 'idnumber' => 'ID2']);
        $category2 = self::getDataGenerator()->create_category();

        $this->assertCount(4, $DB->get_records('course_categories'));

        // Create a new export.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\coursecategories::class, [
            categoriesexporter::EXPORT_CONTENT => 1,
            categoriesexporter::EXPORT_COURSES => 0,
            categoriesexporter::EXPORT_COURSES_CONTENT => 0,
            categoriesexporter::EXPORT_CERTIFICATE_TEMPLATES => 0,
            categoriesexporter::EXPORT_COHORTS => 0,
            categoriesexporter::EXPORT_SELECT_CATEGORIES => [$category1->id, $category2->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\coursecategories::class, $importer);

        // We should have 3 course categories in the import.
        $categories = $importer->get_entities_in_workplace_export_file('course_categories');
        $this->assertCount(3, $categories);

        $categoriesnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($categories, false));
        $this->assertEqualsCanonicalizing([$category1->name, $category1a->name, $category2->name], $categoriesnames);

        // Import course categories from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            categoriesimporter::IMPORT_CONTENT => 1,
            categoriesimporter::IMPORT_COURSES => 0,
            categoriesimporter::IMPORT_CERTIFICATE_TEMPLATES => 0,
            categoriesimporter::IMPORT_COHORTS => 0,
            categoriesimporter::IMPORT_INSTANCES => categoriesimporter::IMPORT_INSTANCES_SELECTED,
            categoriesimporter::IMPORT_SELECT_COURSE_CATEGORIES => [$category1->id, $category2->id],
            \tool_wp\local\exportimport\helper::get_importer_setting_name_for_conflict_form(
                'course_categories', 'idnumberconflict', 'action') => 'skip',
        ]);

        $this->assertCount(5, $DB->get_records('course_categories'));

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(3, $logs);

        $str = get_string('importlogfailedcoursecategory', 'tool_wp', ['name' => $category1->name]);
        $this->assertStringStartsWith($str, $logs[0]['detail']);
        $str = get_string('exportimporterrorentityexists', 'tool_wp', 'idnumber');
        $this->assertEquals($str, $logs[0]['errors'][0]);

        $str = get_string('importlogfailedcoursecategory', 'tool_wp', ['name' => $category1a->name]);
        $this->assertStringStartsWith($str, $logs[1]['detail']);
        $str = get_string('exportimporterrorentityexists', 'tool_wp', 'idnumber');
        $this->assertEquals($str, $logs[1]['errors'][0]);

        $this->assertStringStartsWith('Created new course category', $logs[2]['detail']);
        $this->assertEmpty($logs[2]['errors']);
        $this->assertEmpty($logs[2]['notices']);
    }

    /**
     * Test exporting and import chaining entities
     *
     * @return void
     */
    public function test_export_import_chaining_entities(): void {
        global $DB;
        self::setAdminUser();

        $category1 = self::getDataGenerator()->create_category(['name' => 'Cat 1']);
        $category1a = self::getDataGenerator()->create_category(['parent' => $category1->id, 'name' => 'Cat 1A']);
        $category2 = self::getDataGenerator()->create_category(['name' => 'Cat 2']);
        $categorybase = self::getDataGenerator()->create_category();

        $course1 = self::getDataGenerator()->create_course(['category' => $category1->id]);
        $course1a = self::getDataGenerator()->create_course(['category' => $category1a->id]);
        $course2 = self::getDataGenerator()->create_course(['category' => $category2->id]);

        $cohort1 = $this->generate_cohort_with_member($category1->id);
        $cohort1a = $this->generate_cohort_with_member($category1a->id);
        $cohort2 = $this->generate_cohort_with_member($category2->id);

        $template1 = $this->generate_certificate_template(context_coursecat::instance($category1->id)->id, 'Certificate 1');
        $template1a = $this->generate_certificate_template(context_coursecat::instance($category1a->id)->id, 'Certificate 1a');
        $template2 = $this->generate_certificate_template(context_coursecat::instance($category2->id)->id, 'Certificate 2');

        // Sanity check.
        $this->assertCount(5, $DB->get_records('course_categories'));
        $this->assertCount(4, $DB->get_records('course'));
        $this->assertCount(3, $DB->get_records('cohort'));
        $this->assertCount(3, $DB->get_records('cohort_members'));
        $this->assertCount(3, $DB->get_records(\tool_certificate\persistent\template::TABLE));
        $this->assertCount(6, $DB->get_records(\tool_certificate\persistent\element::TABLE));
        $this->assertCount(6, $DB->get_records(\tool_certificate\persistent\page::TABLE));

        // Create a new export.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\coursecategories::class, [
            categoriesexporter::EXPORT_CONTENT => 1,
            categoriesexporter::EXPORT_COURSES => 1,
            categoriesexporter::EXPORT_COURSES_CONTENT => 1,
            categoriesexporter::EXPORT_CERTIFICATE_TEMPLATES => 1,
            categoriesexporter::EXPORT_COHORTS => 1,
            categoriesexporter::EXPORT_SELECT_CATEGORIES => [$category1->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\coursecategories::class, $importer);

        // We should have 2 course categories in the import.
        $categories = $importer->get_entities_in_workplace_export_file('course_categories');
        $this->assertCount(2, $categories);

        $categoriesnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($categories, false));
        $this->assertEqualsCanonicalizing([$category1->name, $category1a->name], $categoriesnames);

        // We should have 2 courses in the import.
        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(2, $courses);

        $coursesnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('fullname');
        }, iterator_to_array($courses, false));
        $this->assertEqualsCanonicalizing([$course1->fullname, $course1a->fullname], $coursesnames);

        // We should have 2 cohorts in the import.
        $cohorts = $importer->get_entities_in_workplace_export_file('cohort');
        $this->assertCount(2, $cohorts);

        $cohortnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($cohorts, false));
        $this->assertEqualsCanonicalizing([$cohort1->name, $cohort1a->name], $cohortnames);

        // We should have 2 certificate templates in the import.
        $templates = $importer->get_entities_in_workplace_export_file(\tool_certificate\persistent\template::TABLE);
        $this->assertCount(2, $templates);

        $templatenames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($templates, false));
        $this->assertEqualsCanonicalizing([$template1->get_name(), $template1a->get_name()], $templatenames);

        // Import course categories from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            categoriesimporter::IMPORT_CONTENT => 1,
            categoriesimporter::IMPORT_COURSES => 1,
            categoriesimporter::IMPORT_CERTIFICATE_TEMPLATES => 1,
            categoriesimporter::IMPORT_COHORTS => 1,
            categoriesimporter::IMPORT_INSTANCES => categoriesimporter::IMPORT_INSTANCES_SELECTED,
            categoriesimporter::IMPORT_SELECT_COURSE_CATEGORIES => [$category1->id],
            categoriesimporter::IMPORT_SELECT_CATEGORY => $categorybase->id,
            \tool_wp\local\exportimport\helper::get_importer_setting_name_for_conflict_form(
                'course', 'shortnameconflict', 'action') => 'increment',
        ]);

        $this->assertCount(7, $DB->get_records('course_categories'));
        $this->assertCount(6, $DB->get_records('course'));
        $this->assertCount(5, $DB->get_records('cohort'));
        $this->assertCount(5, $DB->get_records('cohort_members'));
        $this->assertCount(5, $DB->get_records(\tool_certificate\persistent\template::TABLE));
        $this->assertCount(10, $DB->get_records(\tool_certificate\persistent\element::TABLE));
        $this->assertCount(10, $DB->get_records(\tool_certificate\persistent\page::TABLE));

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(10, $logs);

        $this->assertStringStartsWith('Created new course category', $logs[0]['detail']);
        $this->assertEmpty($logs[0]['errors']);
        $this->assertEmpty($logs[0]['notices']);

        $this->assertStringStartsWith('Created new course', $logs[1]['detail']);
        $this->assertEmpty($logs[1]['errors']);
        $this->assertStringStartsWith('Shortname changed', $logs[1]['notices'][0]);

        $this->assertStringStartsWith('Created new certificate template', $logs[2]['detail']);
        $this->assertEmpty($logs[2]['errors']);
        $this->assertEmpty($logs[2]['notices']);

        $this->assertStringStartsWith('Created new cohort', $logs[3]['detail']);
        $this->assertEmpty($logs[3]['errors']);
        $this->assertEmpty($logs[3]['notices']);

        $this->assertStringStartsWith('Allocated user', $logs[4]['detail']);
        $this->assertEmpty($logs[4]['errors']);
        $this->assertEmpty($logs[4]['notices']);

        $this->assertStringStartsWith('Created new course category', $logs[5]['detail']);
        $this->assertEmpty($logs[5]['errors']);
        $this->assertEmpty($logs[5]['notices']);

        $this->assertStringStartsWith('Created new course', $logs[6]['detail']);
        $this->assertEmpty($logs[6]['errors']);
        $this->assertStringStartsWith('Shortname changed', $logs[6]['notices'][0]);

        $this->assertStringStartsWith('Created new certificate template', $logs[7]['detail']);
        $this->assertEmpty($logs[7]['errors']);
        $this->assertEmpty($logs[7]['notices']);

        $this->assertStringStartsWith('Created new cohort', $logs[8]['detail']);
        $this->assertEmpty($logs[8]['errors']);
        $this->assertEmpty($logs[8]['notices']);

        $this->assertStringStartsWith('Allocated user', $logs[9]['detail']);
        $this->assertEmpty($logs[9]['errors']);
        $this->assertEmpty($logs[9]['notices']);

        // Check that Cat1 has been imported under categorybase.
        $cats = array_values($DB->get_records('course_categories', ['parent' => $categorybase->id]));
        $this->assertCount(1, $cats);
        $this->assertEquals('Cat 1', $cats[0]->name);
        // Check that course has been placed in the correct category.
        $courses = array_values($DB->get_records('course', ['category' => $cats[0]->id]));
        $this->assertCount(1, $courses);
        $this->assertEquals($course1->fullname . ' copy 1', $courses[0]->fullname);
        // Check that certificate has been placed in the correct category.
        $context = context_coursecat::instance($cats[0]->id);
        $this->assertCount(1, \tool_certificate\persistent\template::get_records(['contextid' => $context->id]));
        // Check that cohort has been placed in the correct category.
        $this->assertCount(1, $DB->get_records('cohort', ['contextid' => $context->id]));

        // Check that cat1A has been imported under Cat1.
        $subcats = array_values($DB->get_records('course_categories', ['parent' => $cats[0]->id]));
        $this->assertCount(1, $subcats);
        $this->assertEquals('Cat 1A', $subcats[0]->name);
        // Check that course has been placed in the correct category.
        $courses2 = array_values($DB->get_records('course', ['category' => $subcats[0]->id]));
        $this->assertCount(1, $courses2);
        $this->assertEquals($course1a->fullname . ' copy 1', $courses2[0]->fullname);
        // Check that certificate has been placed in the correct category.
        $context2 = context_coursecat::instance($subcats[0]->id);
        $this->assertCount(1, \tool_certificate\persistent\template::get_records(['contextid' => $context2->id]));
        // Check that cohort has been placed in the correct category.
        $this->assertCount(1, $DB->get_records('cohort', ['contextid' => $context2->id]));
    }

    /**
     * Generate a certificate template
     *
     * @param int $contextid
     * @param string $name
     * @return \tool_certificate\template
     * @throws coding_exception
     */
    private function generate_certificate_template(int $contextid, string $name): \tool_certificate\template {
        $certificatesgenerator = self::getDataGenerator()->get_plugin_generator('tool_certificate');

        $params = (object) [
            'name' => $name,
            'contextid' => $contextid,
        ];
        $template = $certificatesgenerator->create_template($params);
        $pageid1 = $certificatesgenerator->create_page($template)->get_id();
        $e = $certificatesgenerator->new_element($pageid1, 'text');
        $newdata = (object)['text' => 'New text 1'];
        $e->save_form_data($newdata);
        $pageid2 = $certificatesgenerator->create_page($template)->get_id();
        $e = $certificatesgenerator->new_element($pageid2, 'text');
        $newdata = (object)['text' => 'New text 2'];
        $e->save_form_data($newdata);
        return $template;
    }

    /**
     * Generate a cohort with a member
     *
     * @param int $categoryid
     * @return stdClass
     */
    private function generate_cohort_with_member(int $categoryid): \stdClass {
        $context = context_coursecat::instance($categoryid);
        $cohort = self::getDataGenerator()->create_cohort(['contextid' => $context->id]);
        $user = self::getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $user->id);
        return $cohort;
    }
}

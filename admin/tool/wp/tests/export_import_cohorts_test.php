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
 * Class export_import_cohorts_test
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_wp\tool_wp\importer\cohorts as cohortsimporter;
use tool_wp\tool_wp\exporter\cohorts as cohortsexporter;

/**
 * Class export_import_cohorts_test
 *
 * @covers     \tool_wp\tool_wp\exporter\cohorts
 * @covers     \tool_wp\tool_wp\importer\cohorts
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_export_import_cohorts_testcase extends advanced_testcase {

    /** @var tool_wp_generator */
    protected $wpgenerator;

    /**
     * setUpBeforeClass.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot.'/cohort/lib.php');
    }

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->resetAfterTest();
    }

    /**
     * Test exporting single cohort
     *
     * @return void
     */
    public function test_export_single_cohort(): void {
        self::setAdminUser();

        $cohort = self::getDataGenerator()->create_cohort();

        // Create a new export containing all cohorts.
        $exportid = $this->wpgenerator->perform_export(cohortsexporter::class, [
            cohortsexporter::EXPORT_CONTENT => 1,
            cohortsexporter::EXPORT_USERS => 0,
            cohortsexporter::EXPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_ALL,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\cohorts::class, $importer);

        // We should have the first cohort in the import.
        $cohorts = $importer->get_entities_in_workplace_export_file('cohort');
        $this->assertCount(1, $cohorts);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($cohorts, false)[0];
        $this->assertEquals($cohort->name, $entity->get_raw_field('name'));
    }

    /**
     * Test exporting and import all cohorts with members
     *
     * @return void
     */
    public function test_export_import_all_cohorts(): void {
        global $DB;
        self::setAdminUser();

        $cohort1 = self::getDataGenerator()->create_cohort();
        $cohort2 = self::getDataGenerator()->create_cohort();
        $cohort3 = self::getDataGenerator()->create_cohort();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort2->id, $user3->id);

        // Create a new export containing all cohorts.
        $exportid = $this->wpgenerator->perform_export(cohortsexporter::class, [
            cohortsexporter::EXPORT_CONTENT => 1,
            cohortsexporter::EXPORT_USERS => 1,
            cohortsexporter::EXPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_ALL,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\cohorts::class, $importer);

        // We should have 3 cohorts in the import.
        $cohorts = $importer->get_entities_in_workplace_export_file('cohort');
        $this->assertCount(3, $cohorts);

        $cohortnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($cohorts, false));
        $this->assertEqualsCanonicalizing([$cohort1->name, $cohort2->name, $cohort3->name], $cohortnames);

        // We should have 3 cohorts members in the import.
        $members = $importer->get_entities_in_workplace_export_file('cohort_members');
        $this->assertCount(3, $members);

        // Import cohorts from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            cohortsimporter::IMPORT_CONTEXT => 1,
            cohortsimporter::IMPORT_USERS => 1,
            cohortsimporter::IMPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_ALL,
        ]);

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(0, $conflicts);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(6, $logs);

        // There is no errors in logs.
        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(0, $errors);

        $this->assertStringStartsWith('Created new cohort', $logs[0]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[1]['detail']);
        $this->assertStringStartsWith('Created new cohort', $logs[2]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[3]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[4]['detail']);
        $this->assertStringStartsWith('Created new cohort', $logs[5]['detail']);

        $cohorts = $DB->get_records('cohort');
        $this->assertCount(6, $cohorts);

        $members = $DB->get_records('cohort_members');
        $this->assertCount(6, $members);
    }

    /**
     * Test exporting and import all system cohorts with members
     *
     * @return void
     */
    public function test_export_import_all_system_cohorts(): void {
        global $DB;
        self::setAdminUser();

        $contextsystem = context_system::instance();
        $category1 = self::getDataGenerator()->create_category();
        $context1 = context_coursecat::instance($category1->id);

        $cohort1 = self::getDataGenerator()->create_cohort(['contextid' => $contextsystem->id]);
        $cohort2 = self::getDataGenerator()->create_cohort(['contextid' => $context1->id]);
        $cohort3 = self::getDataGenerator()->create_cohort(['contextid' => $contextsystem->id]);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort3->id, $user3->id);

        // Create a new export containing all cohorts.
        $exportid = $this->wpgenerator->perform_export(cohortsexporter::class, [
            cohortsexporter::EXPORT_CONTENT => 1,
            cohortsexporter::EXPORT_USERS => 1,
            cohortsexporter::EXPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_ALL_SYSTEM,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\cohorts::class, $importer);

        // We should have 3 cohorts in the import.
        $cohorts = $importer->get_entities_in_workplace_export_file('cohort');
        $this->assertCount(2, $cohorts);

        $cohortnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($cohorts, false));
        $this->assertEqualsCanonicalizing([$cohort1->name, $cohort3->name], $cohortnames);

        // Import cohorts from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            cohortsimporter::IMPORT_CONTEXT => 1,
            cohortsimporter::IMPORT_USERS => 1,
            cohortsimporter::IMPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_ALL,
        ]);

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(0, $conflicts);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(4, $logs);

        // There is no errors in logs.
        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(0, $errors);

        $this->assertStringStartsWith('Created new cohort', $logs[0]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[1]['detail']);
        $this->assertStringStartsWith('Created new cohort', $logs[2]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[3]['detail']);

        $cohorts = $DB->get_records('cohort');
        $this->assertCount(5, $cohorts);

        $members = $DB->get_records('cohort_members');
        $this->assertCount(5, $members);
    }

    /**
     * Test exporting and import cohorts in selected category
     *
     * @return void
     */
    public function test_export_import_cohorts_selected_category(): void {
        global $DB;
        self::setAdminUser();

        $contextsystem = context_system::instance();
        $category1 = self::getDataGenerator()->create_category();
        $category2 = self::getDataGenerator()->create_category();
        $context1 = context_coursecat::instance($category1->id);
        $context2 = context_coursecat::instance($category2->id);

        $cohort1 = self::getDataGenerator()->create_cohort(['contextid' => $contextsystem->id]);
        $cohort2 = self::getDataGenerator()->create_cohort(['contextid' => $context1->id]);
        $cohort3 = self::getDataGenerator()->create_cohort(['contextid' => $contextsystem->id]);
        $cohort4 = self::getDataGenerator()->create_cohort(['contextid' => $context2->id]);
        $cohort5 = self::getDataGenerator()->create_cohort(['contextid' => $context2->id]);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort3->id, $user3->id);

        // Create a new export containing all cohorts.
        $exportid = $this->wpgenerator->perform_export(cohortsexporter::class, [
            cohortsexporter::EXPORT_CONTENT => 1,
            cohortsexporter::EXPORT_USERS => 1,
            cohortsexporter::EXPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_CATEGORY,
            cohortsexporter::EXPORT_SELECT_CATEGORIES => [$category2->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\cohorts::class, $importer);

        // We should have 3 cohorts in the import.
        $cohorts = $importer->get_entities_in_workplace_export_file('cohort');
        $this->assertCount(2, $cohorts);

        $cohortnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($cohorts, false));
        $this->assertEqualsCanonicalizing([$cohort4->name, $cohort5->name], $cohortnames);

        // Import cohorts from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            cohortsimporter::IMPORT_CONTEXT => 1,
            cohortsimporter::IMPORT_USERS => 1,
            cohortsimporter::IMPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_SELECTED,
            cohortsimporter::IMPORT_SELECT_COHORTS => [$cohort4->id, $cohort5->id],
        ]);

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(0, $conflicts);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(2, $logs);

        ['detail' => $detail, 'errors' => $errors, 'notices' => $notices] = $logs[0];
        $this->assertStringStartsWith('Created new cohort', $detail);
        $this->assertEmpty($errors);
        $this->assertEmpty($notices);

        ['detail' => $detail, 'errors' => $errors, 'notices' => $notices] = $logs[1];
        $this->assertStringStartsWith('Created new cohort', $detail);
        $this->assertEmpty($errors);
        $this->assertEmpty($notices);

        $cohorts = $DB->get_records('cohort');
        $this->assertCount(7, $cohorts);

        $members = $DB->get_records('cohort_members');
        $this->assertCount(3, $members);
    }

    /**
     * Test exporting and import cohorts manually selected
     *
     * @return void
     */
    public function test_export_import_cohorts_select_manually(): void {
        global $DB;
        self::setAdminUser();

        $contextsystem = context_system::instance();
        $category1 = self::getDataGenerator()->create_category();
        $category2 = self::getDataGenerator()->create_category();
        $context1 = context_coursecat::instance($category1->id);
        $context2 = context_coursecat::instance($category2->id);

        $cohort1 = self::getDataGenerator()->create_cohort(['contextid' => $contextsystem->id]);
        $cohort2 = self::getDataGenerator()->create_cohort(['contextid' => $context1->id]);
        $cohort3 = self::getDataGenerator()->create_cohort(['contextid' => $contextsystem->id]);
        $cohort4 = self::getDataGenerator()->create_cohort(['contextid' => $context2->id]);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort3->id, $user3->id);

        // Create a new export containing all cohorts.
        $exportid = $this->wpgenerator->perform_export(cohortsexporter::class, [
            cohortsexporter::EXPORT_CONTENT => 1,
            cohortsexporter::EXPORT_USERS => 1,
            cohortsexporter::EXPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_SELECTED,
            cohortsexporter::EXPORT_SELECT_COHORTS => [$cohort1->id, $cohort2->id, $cohort4->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\cohorts::class, $importer);

        // We should have 3 cohorts in the import.
        $cohorts = $importer->get_entities_in_workplace_export_file('cohort');
        $this->assertCount(3, $cohorts);

        $cohortnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($cohorts, false));
        $this->assertEqualsCanonicalizing([$cohort1->name, $cohort2->name, $cohort4->name], $cohortnames);

        // Import cohorts from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            cohortsimporter::IMPORT_CONTEXT => 1,
            cohortsimporter::IMPORT_USERS => 1,
            cohortsimporter::IMPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_SELECTED,
            cohortsimporter::IMPORT_SELECT_COHORTS => [$cohort1->id, $cohort2->id, $cohort4->id],
        ]);

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(0, $conflicts);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(5, $logs);

        // There is no errors in logs.
        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(0, $errors);

        $this->assertStringStartsWith('Created new cohort', $logs[0]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[1]['detail']);
        $this->assertStringStartsWith('Created new cohort', $logs[2]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[3]['detail']);
        $this->assertStringStartsWith('Created new cohort', $logs[4]['detail']);

        $cohorts = $DB->get_records('cohort');
        $this->assertCount(7, $cohorts);

        $members = $DB->get_records('cohort_members');
        $this->assertCount(5, $members);
    }

    /**
     * Test exporting and import with idnumber conflict with increment resolution
     *
     * @return void
     */
    public function test_export_import_idnumber_conflict_increment(): void {
        global $DB;
        self::setAdminUser();

        $cohort1 = self::getDataGenerator()->create_cohort(['idnumber' => 'ID1']);
        $cohort2 = self::getDataGenerator()->create_cohort(['idnumber' => 'ID2']);
        $cohort3 = self::getDataGenerator()->create_cohort();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort3->id, $user3->id);

        // Create a new export containing all cohorts.
        $exportid = $this->wpgenerator->perform_export(cohortsexporter::class, [
            cohortsexporter::EXPORT_CONTENT => 1,
            cohortsexporter::EXPORT_USERS => 1,
            cohortsexporter::EXPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_ALL,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\cohorts::class, $importer);

        // We should have 3 cohorts in the import.
        $cohorts = $importer->get_entities_in_workplace_export_file('cohort');
        $this->assertCount(3, $cohorts);

        $cohortnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($cohorts, false));
        $this->assertEqualsCanonicalizing([$cohort1->name, $cohort2->name, $cohort3->name], $cohortnames);

        // Import cohorts from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            cohortsimporter::IMPORT_CONTEXT => 1,
            cohortsimporter::IMPORT_USERS => 1,
            cohortsimporter::IMPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_ALL,
            \tool_wp\local\exportimport\helper::get_importer_setting_name_for_conflict_form(
                'cohort', 'idnumberconflict', 'action') => 'increment',
        ]);

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals(get_string('errorcohortsameidnumber', 'tool_wp'), $conflicts[0][0]);
        $this->assertEquals(get_string('conflictidnumber', 'tool_wp'), $conflicts[0][1]);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(6, $logs);

        // There is no errors in logs.
        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(0, $errors);

        $this->assertStringStartsWith('Created new cohort', $logs[0]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[1]['detail']);
        $this->assertStringStartsWith('Created new cohort', $logs[2]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[3]['detail']);
        $this->assertStringStartsWith('Created new cohort', $logs[4]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[5]['detail']);

        $cohorts = $DB->get_records('cohort');
        $this->assertCount(6, $cohorts);

        $members = $DB->get_records('cohort_members');
        $this->assertCount(6, $members);
    }

    /**
     * Test exporting and import with idnumber conflict with skip resolution
     *
     * @return void
     */
    public function test_export_import_idnumber_conflict_skip(): void {
        global $DB;
        self::setAdminUser();

        $cohort1 = self::getDataGenerator()->create_cohort(['idnumber' => 'ID1', 'name' => 'Cohort 1']);
        $cohort2 = self::getDataGenerator()->create_cohort(['idnumber' => 'ID2', 'name' => 'Cohort 2']);
        $cohort3 = self::getDataGenerator()->create_cohort();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort3->id, $user3->id);

        // Create a new export containing all cohorts.
        $exportid = $this->wpgenerator->perform_export(cohortsexporter::class, [
            cohortsexporter::EXPORT_CONTENT => 1,
            cohortsexporter::EXPORT_USERS => 1,
            cohortsexporter::EXPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_ALL,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\cohorts::class, $importer);

        // We should have 3 cohorts in the import.
        $cohorts = $importer->get_entities_in_workplace_export_file('cohort');
        $this->assertCount(3, $cohorts);

        $cohortnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('name');
        }, iterator_to_array($cohorts, false));
        $this->assertEqualsCanonicalizing([$cohort1->name, $cohort2->name, $cohort3->name], $cohortnames);

        // Import cohorts from the export file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            cohortsimporter::IMPORT_CONTENT => 1,
            cohortsimporter::IMPORT_USERS => 1,
            cohortsimporter::IMPORT_INSTANCES => cohortsimporter::IMPORT_INSTANCES_ALL,
            \tool_wp\local\exportimport\helper::get_importer_setting_name_for_conflict_form(
                'cohort', 'idnumberconflict', 'action') => 'skip',
        ]);

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals(get_string('errorcohortsameidnumber', 'tool_wp'), $conflicts[0][0]);
        $this->assertEquals('Do not import', $conflicts[0][1]);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(4, $logs);

        // There is one error in logs.
        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(2, $errors);

        $this->assertEquals('Could not import cohort \'Cohort 1\'', $logs[0]['detail']);
        $this->assertEquals('Cohorts with the same idnumber already exist', $logs[0]['errors'][0]);
        $this->assertEquals('Could not import cohort \'Cohort 2\'', $logs[1]['detail']);
        $this->assertEquals('Cohorts with the same idnumber already exist', $logs[1]['errors'][0]);
        $this->assertStringStartsWith('Created new cohort', $logs[2]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[3]['detail']);

        $cohorts = $DB->get_records('cohort');
        $this->assertCount(4, $cohorts);

        $members = $DB->get_records('cohort_members');
        $this->assertCount(4, $members);
    }

    /**
     * Test settings form callback.
     */
    public function test_settings_form() {
        self::setAdminUser();

        $cat = self::getDataGenerator()->create_category();
        $cohort = self::getDataGenerator()->create_cohort();

        $exportid = $this->wpgenerator->perform_export(cohortsexporter::class, [
            cohortsexporter::EXPORT_CONTENT => 1,
            cohortsexporter::EXPORT_USERS => 1,
            cohortsexporter::EXPORT_INSTANCES => cohortsexporter::EXPORT_INSTANCES_ALL,
        ]);

        // Submitting an import form without a required field.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [
            cohortsimporter::IMPORT_INSTANCES => cohortsimporter::IMPORT_SELECT_COHORTS,
        ];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertFalse($form->is_validated());
        $this->assertEquals(
            [cohortsimporter::IMPORT_INSTANCES => get_string('required')],
            $form->get_quick_form()->_errors);

        // Submitting an import form with a non-existing category.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [
            cohortsimporter::IMPORT_INSTANCES => cohortsimporter::IMPORT_CONTEXT_CATEGORY,
            cohortsimporter::IMPORT_SELECT_CATEGORY => $cat->id + 1,
            ];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertFalse($form->is_validated());
        $this->assertEquals(
            [cohortsimporter::IMPORT_INSTANCES => get_string('required')],
            $form->get_quick_form()->_errors);

        // Submitting an import form without errors, make sure all default values apply.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertEquals(1, $data->{cohortsimporter::IMPORT_CONTENT});
        $this->assertEquals(1, $data->{cohortsimporter::IMPORT_USERS});
        $this->assertEquals(cohortsimporter::IMPORT_INSTANCES_ALL, $data->{cohortsimporter::IMPORT_INSTANCES});
        $this->assertEquals(cohortsimporter::IMPORT_CONTEXT_CATEGORY, $data->{cohortsimporter::IMPORT_CONTEXT});
    }
}

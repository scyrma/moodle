<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Class export_import_courses_test
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_wp\tool_wp\importer\courses as coursesimporter;
use tool_wp\tool_wp\exporter\courses as coursesexporter;

/**
 * Class export_import_courses_test
 *
 * @covers     \tool_wp\tool_wp\exporter\courses
 * @covers     \tool_wp\tool_wp\importer\courses
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_export_import_courses_testcase extends advanced_testcase {
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_wp_generator */
    protected $wpgenerator;

    /**
     * setUp.
     */
    public function setUp() {
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    /**
     * Test exporting single course
     *
     * @return void
     */
    public function test_export_single_course(): void {
        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create a new export containing one course.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\courses::class, [
            tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_SELECTED,
            tool_wp\tool_wp\exporter\courses::EXPORT_SELECT_COURSES => [$course->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\courses::class, $importer);

        // We should have the first course in the import.
        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(1, $courses);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($courses, false)[0];
        $this->assertEquals($course->fullname, $entity->get_raw_field('fullname'));
        $this->assertCount(1, $entity->get_raw_files([]));
    }

    /**
     * Test exporting single category
     *
     * @return void
     */
    public function test_export_single_category(): void {
        self::setAdminUser();

        $category1 = self::getDataGenerator()->create_category(['name' => 'Category 1']);
        $course1 = self::getDataGenerator()->create_course(['category' => $category1->id]);
        $category1b = self::getDataGenerator()->create_category(['name' => 'Category 1B', 'parent' => $category1->id]);
        $course1b = self::getDataGenerator()->create_course(['category' => $category1b->id]);
        $category2 = self::getDataGenerator()->create_category(['name' => 'Category 2']);
        $course2 = self::getDataGenerator()->create_course(['category' => $category2->id]);

        // Create a new export containing one category.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\courses::class, [
            tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_CATEGORY,
            tool_wp\tool_wp\exporter\courses::EXPORT_SELECT_CATEGORIES => [$category1->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\courses::class, $importer);

        // We should only have course1 in the export file.
        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(1, $courses);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($courses, false)[0];
        $this->assertEquals($course1->fullname, $entity->get_raw_field('fullname'));
        $this->assertCount(1, $entity->get_raw_files([]));
    }

    /**
     * Test exporting all courses for a tenant
     *
     * @return void
     */
    public function test_export_all_courses_in_wp(): void {
        global $DB;

        $category1 = self::getDataGenerator()->create_category(['name' => 'Category 1']);
        $course1 = self::getDataGenerator()->create_course(['category' => $category1->id]);
        $category1b = self::getDataGenerator()->create_category(['name' => 'Category 1B', 'parent' => $category1->id]);
        $course1b = self::getDataGenerator()->create_course(['category' => $category1b->id]);
        $category2 = self::getDataGenerator()->create_category(['name' => 'Category 2']);
        $course2 = self::getDataGenerator()->create_course(['category' => $category2->id]);

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(1, ['categoryid' => $category1->id]);
        $context = context_coursecat::instance($category1->id);

        // We assign backupcourse capability to user.
        $role = $DB->get_record('role', ['shortname' => 'tool_tenant_admin']);
        assign_capability('moodle/backup:backupcourse', CAP_ALLOW, $role->id, $context->id);
        role_assign($role->id, $users[0]->id, $context->id);

        self::setUser($users[0]);

        // Create a new export containing all courses in current tenant category.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\courses::class, [
            tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_ALL,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        self::setAdminUser();
        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\courses::class, $importer);

        // We should only have course1 and course1b in the export file.
        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(2, $courses);

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(tool_wp\tool_wp\importer\courses::class, $importer);

        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(2, $courses);

        $coursenames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('fullname');
        }, iterator_to_array($courses, false));
        $this->assertEqualsCanonicalizing([$course1->fullname, $course1b->fullname], $coursenames);
    }

    /**
     * Test importing all courses
     *
     * @return void
     */
    public function test_import_all_courses() {
        global $DB;
        self::setAdminUser();

        $coursecat = self::getDataGenerator()->create_category([]);
        $tenant = $this->tenantgenerator->create_tenant(['categoryid' => $coursecat->id]);

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // Create a new export containing one ourse.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\courses::class, [
            tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_SELECTED,
            tool_wp\tool_wp\exporter\courses::EXPORT_SELECT_COURSES => [$course1->id, $course2->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Delete all courses.
        $selectedcourses = [$course1->id, $course2->id];
        delete_course($course1->id, false);
        delete_course($course2->id, false);

        // Sanity test.
        $this->assertFalse($DB->record_exists_select('course', 'category > 0'));

        // Import courses from the export file.
        $settings = [
            tool_wp\tool_wp\importer\courses::IMPORT_CONTENT => 1,
            tool_wp\tool_wp\importer\courses::IMPORT_INSTANCES => tool_wp\tool_wp\importer\courses::IMPORT_INSTANCES_SELECTED,
            tool_wp\tool_wp\importer\courses::IMPORT_SELECT_COURSES => $selectedcourses,
            'tenantid' => $tenant->id
        ];
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, $settings);

        $courses = $DB->get_records_select('course', 'category > 0');
        $this->assertCount(2, $courses);
        $expected = [$course1->fullname, $course2->fullname];
        $this->assertEqualsCanonicalizing($expected, array_column($courses, 'fullname'));

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(0, $conflicts);
    }

    /**
     * Test shortname conflict increment
     *
     * @return void
     */
    public function test_import_shortname_conflict_increment() {
        global $DB;
        self::setAdminUser();

        $course1 = self::getDataGenerator()->create_course(['shortname' => 'SH']);
        $course2 = self::getDataGenerator()->create_course(['shortname' => 'tc_1']);

        $courses = $DB->get_records_select('course', 'category > 0');
        $this->assertCount(2, $courses);
        $expected = [$course1->fullname, $course2->fullname];
        $this->assertEqualsCanonicalizing($expected, array_column($courses, 'fullname'));

        // Create a new export containing two courses.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\courses::class, [
            tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_SELECTED,
            tool_wp\tool_wp\exporter\courses::EXPORT_SELECT_COURSES => [$course1->id, $course2->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import courses from the export file.
        $settings = [
            tool_wp\tool_wp\importer\courses::IMPORT_CONTENT => 1,
            tool_wp\tool_wp\importer\courses::IMPORT_INSTANCES => tool_wp\tool_wp\importer\courses::IMPORT_INSTANCES_SELECTED,
            tool_wp\tool_wp\importer\courses::IMPORT_SELECT_COURSES => [$course1->id, $course2->id],
            \tool_wp\local\exportimport\helper::get_importer_setting_name_for_conflict_form(
                'course', 'shortnameconflict', 'action') => 'increment',
        ];
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, $settings);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(2, $logs);
        $this->assertCount(0, $logs[0]['errors']);
        $this->assertCount(1, $logs[0]['notices']);
        $a = ['from' => 'SH', 'to' => 'SH2'];
        $this->assertEquals(get_string('shortnamechanged', 'tool_wp', $a),  $logs[0]['notices'][0]);
        $this->assertCount(0, $logs[1]['errors']);
        $this->assertCount(1, $logs[1]['notices']);
        $a = ['from' => 'tc_1', 'to' => 'tc_2'];
        $this->assertEquals(get_string('shortnamechanged', 'tool_wp', $a),  $logs[1]['notices'][0]);

        $courses = $DB->get_records_select('course', 'category > 0');
        $this->assertCount(4, $courses);
        $this->assertCount(1, $DB->get_records('course', ['shortname' => 'SH_1']));
        $expected[] = $course1->fullname . ' copy 1';
        $expected[] = $course2->fullname . ' copy 1';
        $this->assertEqualsCanonicalizing($expected, array_column($courses, 'fullname'));

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals('Courses with the same shortname already exist', $conflicts[0][0]);
        $this->assertEquals('Add a numeric suffix to the course short name', $conflicts[0][1]);
    }

    /**
     * Test shortname conflict skip
     *
     * @return void
     */
    public function test_import_shortname_conflict_skip() {
        global $DB;
        self::setAdminUser();

        $course1 = self::getDataGenerator()->create_course(['fullname' => 'Course 1', 'shortname' => 'SH']);
        $course2 = self::getDataGenerator()->create_course(['fullname' => 'Course 2', 'shortname' => 'TheSunShines']);

        $courses = $DB->get_records_select('course', 'category > 0');
        $this->assertCount(2, $courses);
        $expected = [$course1->fullname, $course2->fullname];
        $this->assertEqualsCanonicalizing($expected, array_column($courses, 'fullname'));

        // Create a new export containing two courses.
        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\courses::class, [
            tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_SELECTED,
            tool_wp\tool_wp\exporter\courses::EXPORT_SELECT_COURSES => [$course1->id, $course2->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // We change course2 shortname to avoid getting conflict.
        $course2->shortname = 'Mandalorian';
        update_course($course2);

        // Import courses from the export file.
        $settings = [
            tool_wp\tool_wp\importer\courses::IMPORT_CONTENT => 1,
            tool_wp\tool_wp\importer\courses::IMPORT_INSTANCES => tool_wp\tool_wp\importer\courses::IMPORT_INSTANCES_SELECTED,
            tool_wp\tool_wp\importer\courses::IMPORT_SELECT_COURSES => [$course1->id, $course2->id],
            \tool_wp\local\exportimport\helper::get_importer_setting_name_for_conflict_form(
                'course', 'shortnameconflict', 'action') => 'skip',
        ];
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, $settings);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(2, $logs);
        $this->assertCount(1, $logs[0]['errors']);
        $this->assertCount(0, $logs[0]['notices']);

        // We have imported only 1 course, the other one has been skipped.
        $courses = $DB->get_records_select('course', 'category > 0');
        $this->assertCount(3, $courses);
        $expected[] = $course2->fullname . ' copy 1';
        $this->assertEqualsCanonicalizing($expected, array_column($courses, 'fullname'));

        // Check the conflicts review.
        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals('Courses with the same shortname already exist', $conflicts[0][0]);
        $this->assertEquals('Do not import', $conflicts[0][1]);
    }

    /**
     * Test callbacks for the export review
     */
    public function test_export_review() {

        $cat = self::getDataGenerator()->create_category();
        list($tenant, $users) = $this->tenantgenerator->create_tenant_and_users(1, ['categoryid' => $cat->id]);
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        self::setUser($users[0]);

        $cat2 = self::getDataGenerator()->create_category(['parent' => $cat->id]);
        $course1 = self::getDataGenerator()->create_course(['fullname' => 'Course 1', 'shortname' => 'SH', 'category' => $cat->id]);
        $course2 = self::getDataGenerator()->create_course(['fullname' => 'Course 2', 'shortname' => 'TheSunShines']);
        $course3 = self::getDataGenerator()->create_course(['fullname' => 'Course 3', 'category' => $cat2->id]);
        $course4 = self::getDataGenerator()->create_course(['fullname' => 'Course 4', 'category' => $cat2->id]);
        $exporterclass = \tool_wp\tool_wp\exporter\courses::class;

        // Test exporter summary for two individual courses.
        $settings = [
            tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_SELECTED,
            tool_wp\tool_wp\exporter\courses::EXPORT_SELECT_COURSES => [$course1->id, $course4->id],
        ];
        $exporter = \tool_wp\local\exportimport\export_manager::create_exporter($exporterclass, '', 0, $settings);
        $summary = $exporter->get_summary_for_review_step(false);
        $this->assertNotEmpty($summary);
        foreach ($exporter->get_entities() as $entityname) {
            $instances = $exporter->get_instances_for_review_step($entityname);
            $this->assertCount(2, $instances);
        }

        // Test exporter summary for all courses in a category.
        $settings = [
            tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_CATEGORY,
            tool_wp\tool_wp\exporter\courses::EXPORT_SELECT_CATEGORIES => [$cat->id],
        ];
        $exporter = \tool_wp\local\exportimport\export_manager::create_exporter($exporterclass, '', 0, $settings);
        $summary = $exporter->get_summary_for_review_step(false);
        $this->assertNotEmpty($summary);
        foreach ($exporter->get_entities() as $entityname) {
            $instances = $exporter->get_instances_for_review_step($entityname);
            $this->assertCount(1, $instances);
        }

        // Test exporter summary for all courses in a tenant category.
        $settings = [tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_ALL];
        $exporter = \tool_wp\local\exportimport\export_manager::create_exporter($exporterclass, '', 0, $settings);
        $summary = $exporter->get_summary_for_review_step(false);
        $this->assertNotEmpty($summary);
        foreach ($exporter->get_entities() as $entityname) {
            $instances = $exporter->get_instances_for_review_step($entityname);
            $this->assertCount(3, $instances);
        }
    }

    /**
     * Helper method to prepare export of two courses
     *
     * @return int
     */
    protected function prepare_export(): int {
        $course1 = self::getDataGenerator()->create_course(['shortname' => 'SH']);
        $course2 = self::getDataGenerator()->create_course();

        // Create a new export containing two courses.
        $exportid = $this->wpgenerator->perform_export(coursesexporter::class, [
            coursesexporter::EXPORT_INSTANCES => coursesexporter::EXPORT_INSTANCES_SELECTED,
            coursesexporter::EXPORT_SELECT_COURSES => [$course1->id, $course2->id],
        ]);

        return $exportid;
    }

    /**
     * Test settings form callback.
     */
    public function test_settings_form() {
        self::setAdminUser();

        $cat = self::getDataGenerator()->create_category();
        $exportid = $this->prepare_export();

        // Submitting an import form without a required field.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertFalse($form->is_validated());
        $this->assertEquals(
            [coursesimporter::IMPORT_SELECT_CATEGORY => get_string('err_required', 'form')],
            $form->get_quick_form()->_errors);

        // Submitting an import form with a non-existing category.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [coursesimporter::IMPORT_SELECT_CATEGORY => $cat->id + 1];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertFalse($form->is_validated());
        $this->assertEquals(
            [coursesimporter::IMPORT_SELECT_CATEGORY => 'You don\'t have permission to restore on this category.'],
            $form->get_quick_form()->_errors);

        // Submitting an import form without errors, make sure all default values apply.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [coursesimporter::IMPORT_SELECT_CATEGORY => $cat->id];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertEquals(coursesimporter::IMPORT_INSTANCES_ALL, $data->{coursesimporter::IMPORT_INSTANCES});
        $this->assertEquals([], $data->{coursesimporter::IMPORT_SELECT_COURSES});
        $this->assertEquals($cat->id, $data->{coursesimporter::IMPORT_SELECT_CATEGORY});
        $this->assertEquals(coursesimporter::IMPORT_INSTANCES_ALL, $data->{coursesimporter::IMPORT_INSTANCES});
    }

    /**
     * Tenant admin trying to export a program with some courses when this user does not have permission to export them.
     */
    public function test_export_program_with_courses_and_no_permission() {
        global $DB;

        $category1 = self::getDataGenerator()->create_category();
        $category2 = self::getDataGenerator()->create_category();
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(1, ['categoryid' => $category1->id]);
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        self::setUser($users[0]);

        $role = $DB->get_record('role', ['shortname' => 'tool_tenant_admin']);
        $context1 = context_coursecat::instance($category1->id);
        $context2 = context_coursecat::instance($category2->id);
        assign_capability('moodle/backup:backupcourse', CAP_ALLOW, $role->id, $context1->id);
        unassign_capability('moodle/backup:backupcourse', $role->id, $context2->id);
        role_assign($role->id, $users[0]->id, $context1->id);

        $course1 = self::getDataGenerator()->create_course(['fullname' => 'Course 1', 'category' => $category1->id]);
        $course2 = self::getDataGenerator()->create_course(['fullname' => 'Course 2', 'category' => $category2->id]);
        $course3 = self::getDataGenerator()->create_course(['fullname' => 'Course 3', 'category' => $category1->id]);
        $course4 = self::getDataGenerator()->create_course(['fullname' => 'Course 4', 'category' => $category2->id]);

        $program = $this->programgenerator->generate_program((object) ['tenantid' => $tenant->id]);
        $this->programgenerator->add_course_to_set($course1->id, $program->get_base_set()->get('id'));
        $this->programgenerator->add_course_to_set($course2->id, $program->get_base_set()->get('id'));
        $this->programgenerator->add_course_to_set($course3->id, $program->get_base_set()->get('id'));
        $this->programgenerator->add_course_to_set($course4->id, $program->get_base_set()->get('id'));

        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, [
            tool_program\tool_wp\exporter\programs::EXPORT_CONTENT => 1,
            tool_program\tool_wp\exporter\programs::EXPORT_INSTANCES =>
                tool_program\tool_wp\exporter\programs::EXPORT_INSTANCES_SELECTED,
            tool_program\tool_wp\exporter\programs::EXPORT_SELECT_PROGRAMS => [$program->get('id')],
            tool_program\tool_wp\exporter\programs::EXPORT_COURSE_BACKUPS => 1,
            tool_program\tool_wp\exporter\programs::EXPORT_USER_ALLOCATIONS => 0,
            tool_program\tool_wp\exporter\programs::EXPORT_PROGRAM_DYNAMICRULES => 0,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(\tool_program\tool_wp\importer\programs::class, $importer);

        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(1, $programs);
        // Only 2 courses get exported.
        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(2, $courses);

        $coursenames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('fullname');
        }, iterator_to_array($courses, false));
        $this->assertEqualsCanonicalizing([$course1->fullname, $course3->fullname], $coursenames);
    }

    /**
     * Test for make_tenant_categories_list
     */
    public function test_make_tenant_categories_list() {
        $cat1 = self::getDataGenerator()->create_category(['name' => 'Category #1']);
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(1, ['categoryid' => $cat1->id]);
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        self::setUser($users[0]);

        $catlist = tool_wp\tool_wp\importer\courses::make_tenant_categories_list($tenant->id);
        $this->assertEquals([$cat1->id => 'Category #1'], $catlist);

        $cat1a = self::getDataGenerator()->create_category(['name' => 'Category #1 A', 'parent' => $cat1->id]);
        $catlist = tool_wp\tool_wp\importer\courses::make_tenant_categories_list($tenant->id);
        $this->assertEquals([$cat1->id => 'Category #1', $cat1a->id => 'Category #1 / Category #1 A'], $catlist);

        // Test with a tenant without categoryid.
        $cat2 = self::getDataGenerator()->create_category(['name' => 'Category #2']);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(1, []);
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users2[0]->id], $tenant2->id);
        self::setUser($users2[0]);

        $catlist = tool_wp\tool_wp\importer\courses::make_tenant_categories_list($tenant2->id);
        $this->assertEmpty($catlist);
    }

    /**
     * Test importing courses as admin to any category
     *
     * @return void
     */
    public function test_import_as_admin_to_any_category() {
        global $DB;
        self::setAdminUser();

        $coursecat = self::getDataGenerator()->create_category([]);
        $coursecat2 = self::getDataGenerator()->create_category([]);
        $course1 = self::getDataGenerator()->create_course(['categoryid' => $coursecat->id]);
        $course2 = self::getDataGenerator()->create_course(['categoryid' => $coursecat->id]);

        $exportid = $this->wpgenerator->perform_export(tool_wp\tool_wp\exporter\courses::class, [
            tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_SELECTED,
            tool_wp\tool_wp\exporter\courses::EXPORT_SELECT_COURSES => [$course1->id, $course2->id],
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $export->get('status'));

        // Delete all courses.
        delete_course($course1->id, false);
        delete_course($course2->id, false);

        // Sanity test.
        $this->assertCount(0, $DB->get_records('course', ['category' => $coursecat2->id]));

        // Import courses from the export file.
        $settings = [
            tool_wp\tool_wp\importer\courses::IMPORT_CONTENT => 1,
            tool_wp\tool_wp\importer\courses::IMPORT_INSTANCES => tool_wp\tool_wp\importer\courses::IMPORT_INSTANCES_ALL,
            \tool_wp\tool_wp\importer\courses::IMPORT_SELECT_CATEGORY => $coursecat2->id,
        ];
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, $settings);

        $courses = $DB->get_records('course', ['category' => $coursecat2->id]);
        $this->assertCount(2, $courses);
        $expected = [$course1->fullname, $course2->fullname];
        $this->assertEqualsCanonicalizing($expected, array_column($courses, 'fullname'));
    }
}
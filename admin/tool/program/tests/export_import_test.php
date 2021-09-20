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
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_program\persistent\program;
use tool_program\tool_wp\exporter\programs as programs_exporter;
use tool_program\tool_wp\importer\programs as programs_importer;
use tool_tenant\tool_wp\exporter\tenants as tenants_exporter;
use tool_tenant\tool_wp\importer\tenants as tenants_importer;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;

/**
 * Class export_import_test
 *
 * @covers     \tool_program\tool_wp\exporter\programs
 * @covers     \tool_program\tool_wp\importer\programs
 * @package    tool_program
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_export_import_testcase extends advanced_testcase {
    /** @var tool_program_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_wp_generator */
    protected $wpgenerator;
    /** @var string $importfixture */
    protected $importfixture = __DIR__ . '/fixtures/program-export.zip';

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->resetAfterTest();
        // Custom fields need to be reset after each test runs.
        \tool_program\customfield\program_handler::create()->delete_all();
    }

    /**
     * Test exporting single program
     */
    public function test_export_single_program_manually(): void {
        self::setAdminUser();

        // Create one user and one allocation to a program in the default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $params = (object) ['tenantid' => $defaulttenantid];
        $program1 = $this->generator->generate_program_with_course($params);

        // Create a new export containing only the first program.
        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, [
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
            programs_exporter::EXPORT_SELECT_PROGRAMS => [$program1->get('id')],
            programs_exporter::EXPORT_COURSE_BACKUPS => 0,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 0,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        // We should have the first program in the import.
        $programs = $importer->get_entities_in_workplace_export_file(program::TABLE);
        $this->assertCount(1, $programs);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($programs, false)[0];
        $this->assertEquals($program1->get('fullname'), $entity->get_raw_field('fullname'));

        // Program sets.
        $sets = $entity->get_nested_entities(\tool_program\persistent\program_set::TABLE);
        $this->assertCount(1, $sets);

        // Program courses.
        $courses = $entity->get_nested_entities(\tool_program\persistent\program_course::TABLE);
        $this->assertCount(1, $courses);
    }

    /**
     * Basic test to export only active programs
     */
    public function test_program_export_active_programs() {
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(3);
        $params = ['tenantid' => $tenant->id];
        $program1 = $this->generator->generate_program_with_course((object)($params + ['fullname' => 'Program 1']));
        $program2 = $this->generator->generate_program_with_course((object)($params + ['fullname' => 'Program 2']));
        $program3 = $this->generator->generate_program_with_course((object)($params + ['fullname' => 'Program 3',
                'archived' => 1]));
        $this->generator->assign_edit_capability($users[0]->id, context_system::instance());
        self::setUser($users[0]);

        $exportid = $this->wpgenerator->perform_export(programs_exporter::class, [
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_ACTIVE,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 0,
            programs_exporter::EXPORT_COURSE_BACKUPS => 0,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(2, $programs);

        $programnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('fullname');
        }, iterator_to_array($programs, false));
        $expected = [$program1->get('fullname'), $program2->get('fullname')];
        $this->assertEqualsCanonicalizing($expected, $programnames);
    }

    /**
     * Test exporting all programs
     *
     * @return void
     */
    public function test_export_all_programs() {
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(3);
        $params = ['tenantid' => $tenant->id];
        $program1 = $this->generator->generate_program_with_course((object)($params + ['fullname' => 'Program 1']));
        $program2 = $this->generator->generate_program_with_course((object)($params + ['fullname' => 'Program 2']));
        $program3 = $this->generator->generate_program_with_course((object)($params + ['fullname' => 'Program 3',
                'archived' => 1]));
        $this->generator->assign_edit_capability($users[0]->id, context_system::instance());
        self::setUser($users[0]);

        // Create a new export containing all programs.
        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, [
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_ALL,
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 0,
            programs_exporter::EXPORT_COURSE_BACKUPS => 0,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(3, $programs);

        $programnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('fullname');
        }, iterator_to_array($programs, false));
        $expected = [$program1->get('fullname'), $program2->get('fullname'), $program3->get('fullname')];
        $this->assertEqualsCanonicalizing($expected, $programnames);
    }

    /**
     * Tenant admin can export shared program and only users from the current tenant will be exported.
     * Admin will be able to export the same program with all users.
     */
    public function test_export_shared_program() {
        global $DB;

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        // Create a shared program.
        $program1 = $this->generator->generate_program_with_course(
            (object)['fullname' => 'Program 1', 'tenantid' => $sharedtenantid]);
        // Create a program in a parent tenant but make it not shared.
        $program2 = $this->generator->generate_program_with_course(
            (object)['fullname' => 'Program 2', 'tenantid' => $sharedtenantid]);
        $DB->update_record('tool_program', ['id' => $program2->get('id'), 'shared' => 0]); // Only possible manually.

        // Allocate users to first program from both tenants.
        $this->generator->allocate_users_to_program($program1->get('id'), array_column(array_merge($users, $users2), 'id'));
        // Make user $users[0] tenant administrator.
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);

        // Export shared program as a tenant admin.
        $this->setUser($users[0]);
        $exportparams = [
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_ALL,
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 1,
            programs_exporter::EXPORT_COURSE_BACKUPS => 0,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::INCLUDE_SHARED_ENTITIES => 1,
        ];
        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, $exportparams);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Analyze the export, there should be one program (shared one) and only three users (from the current tenant).
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importer = $importmanager->get_importer();
        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(1, $programs);
        $this->assertEquals($program1->get('id'), $programs->current()->get_raw_field('id'));
        $programusers = $importer->get_entities_in_workplace_export_file('tool_program_users');
        $this->assertCount(3, $programusers);

        // Do the same export as a global admin in the shared space. 2 programs will be exported and 5 users.
        $this->setAdminUser();
        \tool_tenant\tenancy::set_switched_tenant_id($sharedtenantid);
        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, $exportparams);
        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importer = $importmanager->get_importer();
        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(2, $programs);
        $programusers = $importer->get_entities_in_workplace_export_file('tool_program_users');
        $this->assertCount(5, $programusers);
    }

    /**
     * Create and export a program
     *
     * @param array $programparams
     * @param array $exportsettings settings for export (by default export only this program)
     * @param array $users
     * @return array [$program, $exportid]
     */
    protected function create_and_export_program(array $programparams, array $exportsettings = [], array $users = []) {
        global $DB, $CFG;
        $cfgenerator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        // Define one customfield.
        $params = [
            'component' => 'tool_program',
            'area' => 'program',
            'itemid' => 0,
            'contextid' => context_system::instance()->id
        ];
        $category = $cfgenerator->create_category($params);
        $cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld1']);
        $cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld2']);
        $cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld3']);
        $cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'select', 'shortname' => 'fld4']);

        $programparams['customfield_fld1'] = 'Hello1';
        $programparams['customfield_fld2'] = 'Hello2';
        $programparams['customfield_fld3'] = 'Hello3';
        $programparams['customfield_fld4'] = '1';

        $program = $this->generator->generate_program_with_course((object)$programparams);
        $newset = $this->generator->generate_set((object)['parent' => $program->get_base_set()->get('id')]);
        $course = $this->generator->generate_course_with_completion_self(['shortname' => 'c2']);
        $this->generator->add_course_to_set($course->id, $newset->get('id'));

        $sql = '
                SELECT drc.*
                FROM {tool_dynamicrule_condition} drc
                JOIN {tool_dynamicrule} dr
                ON dr.id = drc.ruleid
                WHERE dr.itemid = :itemid AND dr.component = :component AND dr.componentarea = :componentarea
                AND drc.classname = :classname
            ';
        $params0 = [
            'component' => 'tool_program',
            'componentarea' => 'program',
            'itemid' => $program->get('id'),
            'classname' => 'tool_program\tool_dynamicrule\condition\program_completed',
        ];
        $record = $DB->get_record_sql($sql, $params0);
        $configdata = [
            'instanceclass' => 'tool_dynamicrule:notification',
            'subject' => 'Subject',
            'body' => ['text' => '<p>Hello World<\/p>', 'format' => '1'],
        ];
        $conditionclass = '\\tool_dynamicrule\tool_dynamicrule\outcome\notification';
        \tool_dynamicrule\api::create_rule_outcome($record->ruleid, $conditionclass, $configdata, true);

        foreach ($users as $user) {
            \tool_program\api::allocate_user($program, (object) ['userid' => $user->id, 'certificationid' => 0]);
        }

        // Now add an image to the program.
        $filepath = "{$CFG->dirroot}/lib/filestorage/tests/fixtures/testimage.jpg";
        $filerecord = [
            'contextid' => $program->get_context()->id,
            'component' => 'tool_program',
            'filearea'  => 'program_image',
            'itemid'    => $program->get('id'),
            'filepath'  => '/',
            'filename'  => basename($filepath),
        ];
        $file = get_file_storage()->create_file_from_pathname($filerecord, $filepath);

        $image = $program->get_image();
        $this->assertInstanceOf(stored_file::class, $image);
        $this->assertEquals($file->get_id(), $image->get_id());

        // Export program $program.
        $settings = $exportsettings + [
                programs_exporter::EXPORT_CONTENT => 1,
                programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
                programs_exporter::EXPORT_USER_ALLOCATIONS => 1,
                programs_exporter::EXPORT_COURSE_BACKUPS => 0,
                programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 1,
                programs_exporter::EXPORT_SELECT_PROGRAMS => [$program->get('id')],
                programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
            ];
        $exportid = $this->wpgenerator->perform_export(
            programs_exporter::class, $settings);
        return [$program, $exportid];
    }

    /**
     * Generates tenant admin with program:edit and course_create capabilities and users
     */
    private function generate_tenant_and_users() {
        global $DB;
        $category = self::getDataGenerator()->create_category();
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        $tenant = new \tool_tenant\tenant(0, $tenant);
        $tenant->set('categoryid', $category->id);
        $tenant->update();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $context = context_system::instance();
        self::getDataGenerator()->role_assign($managerrole->id, $users[0]->id, $context);
        assign_capability('tool/program:edit', CAP_ALLOW, $managerrole->id, $context->id);
        assign_capability('moodle/course:create', CAP_ALLOW, $managerrole->id, $context->id);
        return [$tenant, $users];
    }

    /**
     * Test import single program with user allocations
     */
    public function test_import_single_program(): void {
        global $DB;

        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        [$program, $exportid] = $this->create_and_export_program([
            'tenantid' => $tenant->get('id'),
            'idnumber' => 'TESTP',
            'fullname' => 'My program1',
            'program_tags' => ['cat', 'dog'],
        ], [], $users);

        $this->assertCount(4, $DB->get_records('customfield_data'));

        $programid = $program->get('id');
        $originalcontenthash = $program->get_image()->get_contenthash();
        \tool_program\api::archive_program($program);
        \tool_program\api::delete_program($program);
        $this->assertCount(0, $DB->get_records('tool_program'));
        $this->assertCount(0, $DB->get_records('tool_program_users'));
        // Delete user[1] and user[2] and create a new user with same username as user[2] but different firstname.
        $deleteduser0 = $users[1];
        delete_user($users[1]);
        $deleteduser = $users[2];
        delete_user($users[2]);
        $newuser1 = self::getDataGenerator()->create_user(['username' => $deleteduser->username, 'firstname' => 'Laia']);
        $this->tenantgenerator->allocate_user($newuser1->id, $tenant->get('id'));

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_SELECTED,
            programs_importer::IMPORT_SELECTED_PROGRAMS => [$programid],
            programs_importer::IMPORT_USER_ALLOCATIONS => 1,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 1,
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(5, $logs);
        // There is 1 error for one user who could not be allocated to the program.
        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(1, $errors);
        $errors = reset($errors);
        $this->assertCount(0, $errors['notices']);
        $fullname = fullname($deleteduser0);
        $programname = $program->get('fullname');
        $this->assertEquals("Could not allocate user '$fullname' to program '$programname'", $errors['detail']);

        // Make sure the program is imported with the courses and tags.
        $newprogram = program::get_record(['idnumber' => 'TESTP']);
        $this->assertEquals(2, $newprogram->get_courses_count());
        $tags = core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $newprogram->get('id'));
        $this->assertEqualsCanonicalizing(['cat', 'dog'], $tags);

        $this->assertCount(1, $DB->get_records('tool_program'));
        $programusers = $DB->get_records('tool_program_users');
        // Only 2 users got imported from the 3 in the export file.
        $this->assertCount(2, $programusers);
        $usernames = [];
        foreach ($programusers as $programuser) {
            $usernames[] = $programuser->userid;
        }
        $this->assertEqualsCanonicalizing([$newuser1->id, $users[0]->id], $usernames);
        // One user was deleted and could not be imported.
        $this->assertCount(1, $this->wpgenerator->get_import_conflict_review($importid));

        // Now check that program image was added to the program.
        $image = $newprogram->get_image();
        $this->assertInstanceOf(stored_file::class, $image);
        $this->assertEquals($newprogram->get('id'), $image->get_itemid());
        $this->assertEquals($newprogram->get_context()->id, $image->get_contextid());
        $this->assertEquals('tool_program', $image->get_component());
        $this->assertEquals($originalcontenthash, $image->get_contenthash());
    }

    /**
     * Test import all programs.
     */
    public function test_import_all_programs(): void {
        global $DB;

        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        $params = ['tenantid' => $tenant->get('id')];
        $program1 = $this->generator->generate_program_with_course((object)($params +
            ['fullname' => 'Program 1', 'idnumber' => '']));
        $program2 = $this->generator->generate_program_with_course((object)($params + ['fullname' => 'Program 2']));

        // Create a new export containing all programs.
        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, [
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_ALL,
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 0,
            programs_exporter::EXPORT_COURSE_BACKUPS => 0,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(2, $programs);

        $program1name = $program1->get('fullname');
        \tool_program\api::archive_program($program1);
        \tool_program\api::delete_program($program1);
        $program2name = $program2->get('fullname');
        \tool_program\api::archive_program($program2);
        \tool_program\api::delete_program($program2);

        $this->assertCount(0, $DB->get_records(program::TABLE));

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
            programs_importer::IMPORT_COURSE_CATEGORY => 0,
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(2, $logs);
        $this->assertCount(0, $logs[0]['errors']);
        $this->assertCount(0, $logs[0]['notices']);
        $this->assertCount(0, $logs[1]['errors']);
        $this->assertCount(0, $logs[1]['notices']);

        $programs = $DB->get_records(program::TABLE);
        $this->assertCount(2, $programs);

        $programnames = [];
        foreach ($programs as $program) {
            $programnames[] = $program->fullname;
        }
        $this->assertEqualsCanonicalizing([$program1name, $program2name], $programnames);

        $this->assertEmpty($this->wpgenerator->get_import_conflict_review($importid));
    }

    /**
     * Admin can import shared programs with users allocations from different tenants
     */
    public function test_import_shared_program() {
        global $DB;

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        // Create a shared program.
        $program1 = $this->generator->generate_program_with_course(
            (object)['fullname' => 'Program 1', 'tenantid' => $sharedtenantid]);

        // Allocate users to first program from both tenants.
        $this->generator->allocate_users_to_program($program1->get('id'), array_column(array_merge($users, $users2), 'id'));

        // Export shared program as an admin.
        $this->setAdminUser();
        \tool_tenant\tenancy::set_switched_tenant_id($sharedtenantid);
        $exportparams = [
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_ALL,
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 1,
            programs_exporter::EXPORT_COURSE_BACKUPS => 0,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::INCLUDE_SHARED_ENTITIES => 1,
        ];
        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, $exportparams);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Delete program.
        \tool_program\api::archive_program($program1);
        \tool_program\api::delete_program($program1);
        $this->assertEmpty($DB->get_records('tool_program'));
        $this->assertEmpty($DB->get_records('tool_program_users'));

        // Perform import.
        $importid = $this->wpgenerator->perform_import_from_export_id($export->get('id'), [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_USER_ALLOCATIONS => 1,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
            programs_importer::IMPORT_COURSE_CATEGORY => 0,
        ]);
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(6, $logs);
        $this->assertStringStartsWith('Created new program', $logs[0]['detail']);
        for ($i = 1; $i < 6; $i++) {
            $this->assertStringStartsWith('Allocated user', $logs[$i]['detail']);
        }
        $this->assertCount(5, $DB->get_records('tool_program_users'));
    }

    /**
     * Export and import shared program allocations without the shared program itself
     */
    public function test_export_import_shared_program_allocations() {
        global $DB;

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        // Create a shared program.
        $program1 = $this->generator->generate_program_with_course(
            (object)['fullname' => 'Program 1', 'tenantid' => $sharedtenantid]);
        // Make user $users[0] tenant administrator.
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);

        // Allocate users to first program from both tenants.
        $this->generator->allocate_users_to_program($program1->get('id'), array_column(array_merge($users, $users2), 'id'));

        // Export shared program as a tenant administrator.
        $this->setUser($users[0]);
        $exportparams = [
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_ALL,
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 1,
            programs_exporter::EXPORT_COURSE_BACKUPS => 0,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, $exportparams);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Analyze the export file. Zero programs where exported but three user allocations.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importer = $importmanager->get_importer();

        $programs = $importer->get_entities_in_workplace_export_file(program::TABLE);
        $this->assertCount(0, $programs);
        $allocations = $importer->get_entities_in_workplace_export_file('tool_program_users');
        $this->assertCount(3, $allocations);

        // Remove all allocations, move one user to a different tenant, and perform import.
        array_walk($users, function($user) use ($program1) {
            \tool_program\api::deallocate_user($program1->get('id'), $user->id);
        });
        $this->assertCount(2, $DB->get_records('tool_program_users'));
        (new \tool_tenant\manager())->allocate_user($users[2]->id, $tenant2->id, 'test', '');

        // Perform import.
        $importid = $this->wpgenerator->perform_import_from_export_id($export->get('id'), [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_USER_ALLOCATIONS => 1,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
            programs_importer::IMPORT_COURSE_CATEGORY => 0,
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
        ]);
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(3, $logs);
        core_collator::asort_array_of_arrays_by_key($logs, 'detail');
        $this->assertStringStartsWith('Allocated user', $logs[0]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[1]['detail']);
        $this->assertStringStartsWith('Could not allocate user', $logs[2]['detail']);
        $this->assertStringStartsWith('Could not find user', $logs[2]['errors'][0]);

        $this->assertCount(4, $DB->get_records('tool_program_users'));

        // Perform the same import again, users should not be allocated.
        $importid = $this->wpgenerator->perform_import_from_export_id($export->get('id'), [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_USER_ALLOCATIONS => 1,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
            programs_importer::IMPORT_COURSE_CATEGORY => 0,
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form('tool_program_users', 'cannotallocate', 'action') => 'skip',
        ]);
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(3, $logs);
        $this->assertCount(4, $DB->get_records('tool_program_users'));
        $this->assertStringStartsWith('Could not allocate user', $logs[0]['detail']);
        $this->assertStringStartsWith('Not possible to allocate user', $logs[0]['errors'][0]);
        $this->assertStringStartsWith('Could not allocate user', $logs[1]['detail']);
        $this->assertStringStartsWith('Not possible to allocate user', $logs[1]['errors'][0]);
        $this->assertStringStartsWith('Could not allocate user', $logs[2]['detail']);
        $this->assertStringStartsWith('Could not find user', $logs[2]['errors'][0]);
    }

    /**
     * Test exporting and importing a tenant with shared program - allocations are exported and imported without programs.
     */
    public function test_export_shared_programs_in_tenant_export() {
        global $DB;

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        // Create a shared program.
        $program1 = $this->generator->generate_program_with_course(
            (object)['fullname' => 'Program 1', 'tenantid' => $sharedtenantid]);
        // Make user $users[0] tenant administrator.
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);

        // Allocate users to first program from both tenants.
        $this->generator->allocate_users_to_program($program1->get('id'), array_column(array_merge($users, $users2), 'id'));

        // Export shared program as an admin.
        $this->setAdminUser();
        $exportparams = [
            tenants_exporter::EXPORT_USERS => 1,
            tenants_exporter::EXPORT_PROGRAMS => 1,
            tenants_exporter::EXPORT_INSTANCES => tenants_exporter::EXPORT_INSTANCES_MANUAL,
            tenants_exporter::EXPORT_SELECT_MANUAL => [$tenant->id, $tenant2->id],
        ];
        $exportid = $this->wpgenerator->perform_export(tenants_exporter::class, $exportparams);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Analyze the export file. Zero programs where exported but five user allocations (from both tenants).
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importer = $importmanager->get_importer();

        $programs = $importer->get_entities_in_workplace_export_file(program::TABLE);
        $this->assertCount(0, $programs);
        $allocations = $importer->get_entities_in_workplace_export_file('tool_program_users');
        $this->assertCount(5, $allocations);

        // Delete two users. Delete program allocations for all users (to make sure they are not created).
        delete_user($users[2]);
        delete_user($users2[1]);
        \tool_program\api::deallocate_user($program1->get('id'), $users[0]->id);
        \tool_program\api::deallocate_user($program1->get('id'), $users[1]->id);
        \tool_program\api::deallocate_user($program1->get('id'), $users2[0]->id);
        $this->assertEmpty($DB->get_records('tool_program_users'));

        // Import only one tenant into a new tenant.
        // What should happen: users that already exist will not be created. One user will be created ($users[2]).
        // This user will be allocated to the shared program. No other allocations will be created.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            tenants_importer::IMPORT_INSTANCES => tenants_importer::IMPORT_INSTANCES_MANUAL,
            tenants_importer::IMPORT_SELECT_MANUAL => [$tenant->id],
            tenants_importer::IMPORT_DESTINATION => tenants_importer::IMPORT_DESTINATION_NEW,
            tenants_importer::IMPORT_USERS => 1,
            tenants_importer::IMPORT_PROGRAMS => 1,
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form('user', 'usernameconflict', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form('user', 'emailconflict', 'action') => 'skip',
        ]);
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(7, $logs);
        // Imported tenant, Couldn't create user x 2, Created user, Could not allocate user x 2, Allocated user.

        $this->assertCount(1, $DB->get_records('tool_program_users'));
        $this->assertCount(1, $DB->get_records_sql(
            'SELECT 1 FROM {tool_program_users} tu JOIN {user} u ON u.id=tu.userid WHERE u.username=?', [$users[2]->username]));
    }

    /**
     * Test for importing a program with idnumber conflict and skip resolution.
     */
    public function test_program_import_idnumberconflict_skip() {
        global $DB;
        $this->resetAfterTest();

        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        [$program, $exportid] = $this->create_and_export_program(
            ['tenantid' => $tenant->get('id'), 'idnumber' => 'TESTP', 'fullname' => 'My program',
                'program_tags' => ['cat', 'dog']]);

        // Import program from the export file.
        $settings = [
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            helper::get_importer_setting_name_for_conflict_form('tool_program', 'idnumberconflict', 'action') => 'skip',
        ];
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        // Program was not imported (only one program exists in the DB.
        $programs = program::get_records([]);
        $this->assertCount(1, $programs);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);
        $this->assertCount(1, $logs[0]['errors']);
        $this->assertCount(0, $logs[0]['notices']);
        $this->assertEquals("Could not import program 'My program'", $logs[0]['detail']);
        $this->assertEquals("Program with ID number 'TESTP' already exists", $logs[0]['errors'][0]);

        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals('Program with the same ID number already exists', $conflicts[0][0]);
        $this->assertEquals('Do not import', $conflicts[0][1]);
    }

    /**
     * Test for importing a program with idnumber conflict and increment idnumber resolution.
     */
    public function test_program_import_idnumberconflict_increment() {
        global $DB;
        $this->resetAfterTest();

        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        [$program, $exportid] = $this->create_and_export_program(
            ['tenantid' => $tenant->get('id'), 'idnumber' => 'TESTP', 'fullname' => 'My program',
                'program_tags' => ['cat', 'dog']]);

        // Import program from the export file.
        $settings = [
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            helper::get_importer_setting_name_for_conflict_form('tool_program', 'idnumberconflict', 'action') => 'increment',
        ];
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        // Program was imported and idnumber was changed.
        $programs = program::get_records([]);
        $this->assertCount(2, $programs);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);
        $this->assertCount(0, $logs[0]['errors']);
        $this->assertCount(1, $logs[0]['notices']);
        $this->assertStringStartsWith("Created new program", $logs[0]['detail']);
        $this->assertEquals("ID number was changed from 'TESTP' to 'TESTP2'", $logs[0]['notices'][0]);

        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals('Program with the same ID number already exists', $conflicts[0][0]);
        $this->assertEquals('Add a numeric suffix to the ID number', $conflicts[0][1]);
    }

    /**
     * Test for importing a program that contains non-existing course.
     */
    public function test_program_import_nocourse() {
        global $DB;
        $this->resetAfterTest();

        $cat = self::getDataGenerator()->create_category();
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3, ['categoryid' => $cat->id]);
        (new tool_tenant\manager())->assign_tenant_admin_role($tenant->id, [$users[0]->id]);
        $this->generator->assign_edit_capability($users[0]->id, context_system::instance());
        self::setUser($users[0]);

        [$program, $exportid] = $this->create_and_export_program(
            ['tenantid' => $tenant->id, 'idnumber' => 'TESTP', 'fullname' => 'My program',
                'program_tags' => ['cat', 'dog']]);
        $programcourses = $program->get_courses_ids();
        $course = get_course($programcourses[1]);

        // Delete program $program.
        \tool_program\api::archive_program($program);
        \tool_program\api::delete_program($program);

        // Delete the course.
        delete_course($course->id, false);

        // Import program from the export file.
        $settings = [
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            helper::get_setting_name_for_conflict_form('course', 'action') => 'create',
            helper::get_setting_name_for_conflict_form('course', 'catid') => $tenant->categoryid,
        ];
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        // Program was imported with two courses, second course was created.
        $newprogram = program::get_record(['idnumber' => 'TESTP']);
        $this->assertEquals(2, $newprogram->get_courses_count());

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);
        $this->assertCount(0, $logs[0]['errors']);
        $this->assertCount(1, $logs[0]['notices']);
        $this->assertEquals("Empty course {$course->fullname} was created", strip_tags($logs[0]['notices'][0]));

        $conflicts = $this->wpgenerator->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals('Some courses do not exist', $conflicts[0][0]);
        $this->assertStringStartsWith('Create empty course', $conflicts[0][1]);
    }

    /**
     * Test callbacks for the export review
     */
    public function test_export_review() {
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(3);
        $params = ['tenantid' => $tenant->id];
        $program1 = $this->generator->generate_program_with_course((object)($params + ['fullname' => 'Program 1']));
        $program2 = $this->generator->generate_program_with_course((object)($params + ['fullname' => 'Program 2']));
        $program3 = $this->generator->generate_program_with_course((object)($params + ['fullname' => 'Program 3',
                'archived' => 1]));
        $this->generator->assign_edit_capability($users[0]->id, context_system::instance());
        self::setUser($users[0]);

        $exporterclass = \tool_program\tool_wp\exporter\programs::class;

        // Test exporter summary for all programs.
        $settings = [programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_ALL];
        $exporter = \tool_wp\local\exportimport\export_manager::create_exporter($exporterclass,
            '', 0, $settings);

        $summary = $exporter->get_summary_for_review_step(false);
        $this->assertNotEmpty($summary);
        foreach ($exporter->get_entities() as $entityname) {
            $instances = $exporter->get_instances_for_review_step($entityname);
            $this->assertCount(3, $instances);
        }

        // Test exporter summary for 2 individual programs.
        $settings = [
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
            programs_exporter::EXPORT_SELECT_PROGRAMS => [$program1->get('id'), $program2->get('id')],
        ];
        $exporter = \tool_wp\local\exportimport\export_manager::create_exporter($exporterclass,
            '', 0, $settings);

        $summary = $exporter->get_summary_for_review_step(false);
        $this->assertNotEmpty($summary);
        foreach ($exporter->get_entities() as $entityname) {
            $instances = $exporter->get_instances_for_review_step($entityname);
            $this->assertCount(2, $instances);
        }
    }

    /**
     * Test import program in a different tenant
     */
    public function test_import_program_different_tenant(): void {
        global $DB;

        $tenantid2 = $this->tenantgenerator->create_tenant()->id;
        self::setAdminUser();

        // Create one user and one allocation to a program in the default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $params = (object) ['tenantid' => $defaulttenantid];
        $program1 = $this->generator->generate_program_with_course($params);
        $programid = $program1->get('id');

        // Create a new export containing only the first program.
        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, [
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
            programs_exporter::EXPORT_SELECT_PROGRAMS => [$program1->get('id')],
            programs_exporter::EXPORT_COURSE_BACKUPS => 0,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 0,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Sanity check.
        $this->assertCount(0, $DB->get_records(program::TABLE, ['tenantid' => $tenantid2]));

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            'tenantid' => $tenantid2,
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_SELECTED,
            programs_importer::IMPORT_SELECTED_PROGRAMS => [$programid],
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);
        $this->assertCount(1, $DB->get_records(program::TABLE, ['tenantid' => $tenantid2]));

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);
        $this->assertCount(0, $logs[0]['errors']);
        $this->assertCount(0, $logs[0]['notices']);
    }

    /**
     * Test import program with courses.
     */
    public function test_import_program_with_courses() {
        global $DB;

        $category1 = self::getDataGenerator()->create_category(['name' => 'Category 1']);
        self::setAdminUser();

        $this->assertCount(0, $DB->get_records('tool_program'));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(0, $DB->get_records('course', ['category' => $category1->id]));

        $importid = $this->wpgenerator->perform_import_from_file($this->importfixture, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_USER_ALLOCATIONS => 1,
            programs_importer::IMPORT_COURSE_BACKUPS => 1,
            programs_importer::IMPORT_COURSE_CATEGORY => $category1->id,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);
        $this->assertCount(1, $DB->get_records(program::TABLE));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        $this->assertCount(6, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(3, $DB->get_records('course', ['category' => $category1->id]));
    }

    /**
     * Test exporting a program with course
     */
    public function test_export_program_with_course(): void {
        self::setAdminUser();

        // Create one user and one allocation to a program in the default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $program1 = $this->generator->generate_program((object) ['tenantid' => $defaulttenantid]);
        $course = $this->generator->generate_course_with_completion_self();
        $this->generator->add_course_to_set($course->id, $program1->get_base_set()->get('id'));

        // Create a new export containing only the first program.
        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, [
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
            programs_exporter::EXPORT_SELECT_PROGRAMS => [$program1->get('id')],
            programs_exporter::EXPORT_COURSE_BACKUPS => 1,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 0,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        $programs = $importer->get_entities_in_workplace_export_file(program::TABLE);
        $this->assertCount(1, $programs);
        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(1, $courses);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($programs, false)[0];
        $this->assertEquals($program1->get('fullname'), $entity->get_raw_field('fullname'));

        $programcourses = $entity->get_nested_entities(\tool_program\persistent\program_course::TABLE);
        $this->assertCount(1, $programcourses);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($courses, false)[0];
        $this->assertEquals($course->fullname, $entity->get_raw_field('fullname'));
    }

    /**
     * Test importing a program with no course backup and course skip resolution
     *
     * If we skip to import the courses program should not be imported either.
     */
    public function test_import_program_with_course_skip(): void {
        global $DB;

        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        $this->assertCount(0, $DB->get_records('tool_program'));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(0, $DB->get_records_select('course', 'category > 0'));

        $importid = $this->wpgenerator->perform_import_from_file($this->importfixture, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_COURSE_CATEGORY => $tenant->get('categoryid'),
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
            helper::get_setting_name_for_conflict_form('course', 'action') => 'skip',
            helper::get_setting_name_for_conflict_form('course', 'catid') => $tenant->get('categoryid'),
        ]);

        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);
        $this->assertCount(3, $logs[0]['errors']);
        $this->assertCount(0, $logs[0]['notices']);
        $this->assertStringStartsWith('Could not import program \'Program 1A\'', $logs[0]['detail']);
        $this->assertEquals("Course 'C3' was not found", $logs[0]['errors'][0]);
        $this->assertEquals("Course 'C1a' ('id123') was not found", $logs[0]['errors'][1]);
        $this->assertEquals("Course 'C2ABCD' was not found", $logs[0]['errors'][2]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);
        $this->assertCount(0, $DB->get_records(program::TABLE));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(0, $DB->get_records('course', ['category' => $tenant->get('categoryid')]));
    }

    /**
     * Test importing a program with no course backup and course create empty resolution
     */
    public function test_import_program_with_course_create_empty(): void {
        global $DB;

        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        $this->assertCount(0, $DB->get_records('tool_program'));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(0, $DB->get_records_select('course', 'category > 0'));

        $importid = $this->wpgenerator->perform_import_from_file($this->importfixture, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
            helper::get_setting_name_for_conflict_form('course', 'action') => 'create',
            helper::get_setting_name_for_conflict_form('course', 'catid') => $tenant->get('categoryid'),
        ]);

        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);
        $this->assertCount(0, $logs[0]['errors']);
        $this->assertCount(3, $logs[0]['notices']);
        $this->assertStringStartsWith('Created new program', $logs[0]['detail']);
        $this->assertStringStartsWith('Empty course', $logs[0]['notices'][0]);
        $this->assertStringStartsWith('Empty course', $logs[0]['notices'][1]);
        $this->assertStringStartsWith('Empty course', $logs[0]['notices'][2]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);
        $this->assertCount(1, $DB->get_records(program::TABLE));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        $this->assertCount(6, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(3, $DB->get_records('course', ['category' => $tenant->get('categoryid')]));
    }

    /**
     * Test importing a program with no 'moodle/restore:restorecourse' capability
     */
    public function test_import_program_with_no_course_restore_capability(): void {
        global $DB;

        $category = self::getDataGenerator()->create_category();
        (new \tool_tenant\manager())->update_tenant(\tool_tenant\tenancy::get_tenant_id(), (object)['categoryid' => $category->id]);

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        $tenant = new \tool_tenant\tenant($tenant->id);
        $tenant->set('categoryid', $category->id);
        $tenant->update();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $context = context_system::instance();
        self::getDataGenerator()->role_assign($managerrole->id, $users[0]->id, $context);
        assign_capability('tool/program:edit', CAP_ALLOW, $managerrole->id, $context->id);
        assign_capability('moodle/course:create', CAP_ALLOW, $managerrole->id, $context->id);
        // Test user with no moodle/restore:restorecourse capability.
        unassign_capability('moodle/restore:restorecourse', $managerrole->id);
        self::setUser($users[0]);

        $this->assertCount(0, $DB->get_records('tool_program'));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(0, $DB->get_records('course', ['category' => $tenant->get('categoryid')]));

        $importid = $this->wpgenerator->perform_import_from_file($this->importfixture, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_COURSE_BACKUPS => 1,
            programs_importer::IMPORT_COURSE_CATEGORY => $tenant->get('categoryid'),
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
            helper::get_setting_name_for_conflict_form('course', 'action') => 'skip',
        ]);

        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);
        $this->assertCount(3, $logs[0]['errors']);
        $this->assertCount(0, $logs[0]['notices']);
        $this->assertStringStartsWith('Could not import program \'Program 1A\'', $logs[0]['detail']);
        $this->assertEquals("Course 'C3' was not found", $logs[0]['errors'][0]);
        $this->assertEquals("Course 'C1a' ('id123') was not found", $logs[0]['errors'][1]);
        $this->assertEquals("Course 'C2ABCD' was not found", $logs[0]['errors'][2]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);
        $this->assertCount(0, $DB->get_records(program::TABLE));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(0, $DB->get_records('course', ['category' => $tenant->get('categoryid')]));
    }

    /**
     * Test importing a program with no user allocation capability and no dynamic rules manage capability
     */
    public function test_import_program_with_no_capabilities(): void {
        global $DB;

        $category = self::getDataGenerator()->create_category();
        (new \tool_tenant\manager())->update_tenant(\tool_tenant\tenancy::get_tenant_id(), (object)['categoryid' => $category->id]);

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        $tenant = new \tool_tenant\tenant($tenant->id);
        $tenant->set('categoryid', $category->id);
        $tenant->update();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $context = context_system::instance();
        self::getDataGenerator()->role_assign($managerrole->id, $users[0]->id, $context);
        assign_capability('tool/program:edit', CAP_ALLOW, $managerrole->id, $context->id);
        unassign_capability('tool/program:allocateuser', $managerrole->id);
        unassign_capability('tool/dynamicrule:manage', $managerrole->id);
        assign_capability('moodle/course:create', CAP_ALLOW, $managerrole->id, $context->id);
        self::setUser($users[0]);

        // This user exists in the export file and should be allocated into program if user had correct permissions.
        $user1 = self::getDataGenerator()->create_user([
            'username' => 'kira',
            'email' => 'kirasan@test.com',
            'firstname' => 'Kira'
        ]);
        $this->tenantgenerator->allocate_user($user1->id, $tenant->get('id'));

        $this->assertCount(0, $DB->get_records('tool_program'));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(0, $DB->get_records('course', ['category' => $tenant->get('categoryid')]));

        $importid = $this->wpgenerator->perform_import_from_file($this->importfixture, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_USER_ALLOCATIONS => 1,
            programs_importer::IMPORT_COURSE_BACKUPS => 1,
            programs_importer::IMPORT_COURSE_CATEGORY => $tenant->get('categoryid'),
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 1,
            helper::get_setting_name_for_conflict_form('course', 'action') => 'skip',
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
        ]);

        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(4, $logs);
        $this->assertStringStartsWith('Created new course',  $logs[0]['detail']);
        $this->assertStringStartsWith('Created new course',  $logs[1]['detail']);
        $this->assertStringStartsWith('Created new course',  $logs[2]['detail']);
        $this->assertStringStartsWith('Created new program', $logs[3]['detail']);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);
        $this->assertCount(1, $DB->get_records(program::TABLE));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        // The program default rules are created with all outcomes empty but they have not been imported from export file.
        $this->assertCount(6, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule_outcome'));
        $this->assertCount(3, $DB->get_records('course', ['category' => $tenant->get('categoryid')]));
    }

    /**
     * Test settings form callback.
     */
    public function test_settings_form() {
        global $DB;
        $category1 = self::getDataGenerator()->create_category();
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(1, ['categoryid' => $category1->id]);
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        self::setUser($users[0]);

        $role = $DB->get_record('role', ['shortname' => 'tool_tenant_admin']);
        $context1 = context_coursecat::instance($category1->id);
        role_assign($role->id, $users[0]->id, $context1->id);
        $this->generator->assign_edit_capability($users[0]->id, context_system::instance());

        $course1 = self::getDataGenerator()->create_course(['fullname' => 'Course 1', 'category' => $category1->id]);
        $program = $this->generator->generate_program((object) ['tenantid' => $tenant->id]);
        $this->generator->add_course_to_set($course1->id, $program->get_base_set()->get('id'));

        // Add a dynamic rule outcome to the program.
        $sql = '
                SELECT drc.*
                FROM {tool_dynamicrule_condition} drc
                JOIN {tool_dynamicrule} dr
                ON dr.id = drc.ruleid
                WHERE dr.itemid = :itemid AND dr.component = :component AND dr.componentarea = :componentarea
                AND drc.classname = :classname
            ';
        $params0 = [
            'component' => 'tool_program',
            'componentarea' => 'program',
            'itemid' => $program->get('id'),
            'classname' => 'tool_program\tool_dynamicrule\condition\program_completed',
        ];
        $record = $DB->get_record_sql($sql, $params0);
        $configdata = [
            'instanceclass' => 'tool_dynamicrule:notification',
            'subject' => 'Subject',
            'body' => ['text' => '<p>Hello World<\/p>', 'format' => '1'],
        ];
        $conditionclass = '\\tool_dynamicrule\tool_dynamicrule\outcome\notification';
        \tool_dynamicrule\api::create_rule_outcome($record->ruleid, $conditionclass, $configdata, true);

        // Export program $program.
        $settings = [
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 0,
            programs_exporter::EXPORT_COURSE_BACKUPS => 1,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 1,
            programs_exporter::EXPORT_SELECT_PROGRAMS => [$program->get('id')],
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(
            programs_exporter::class, $settings);

        // Submitting an import form without a required field.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_SELECTED,
            programs_importer::IMPORT_SELECTED_PROGRAMS => [],
            programs_importer::IMPORT_COURSE_CATEGORY => $category1->id,
        ];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertFalse($form->is_validated());
        $this->assertEquals(
            [programs_importer::IMPORT_SELECTED_PROGRAMS => get_string('selectatleastoneprogram', 'tool_program')],
            $form->get_quick_form()->_errors);

        // Submitting an import form with a non-existing category.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_COURSE_BACKUPS => 1,
            programs_importer::IMPORT_COURSE_CATEGORY => $category1->id + 1,
        ];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertFalse($form->is_validated());
        $this->assertEquals(
            ['managecatgroup' => 'You must supply a value here.'],
            $form->get_quick_form()->_errors);

        // Submitting an import form without errors, make sure all default values apply.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [programs_importer::IMPORT_COURSE_CATEGORY => $category1->id];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertEquals(programs_importer::IMPORT_INSTANCES_ALL, $data->{programs_importer::IMPORT_INSTANCES});
        $this->assertEquals(1, $data->{programs_importer::IMPORT_CONTENT});
        $this->assertEquals(1, $data->{programs_importer::IMPORT_COURSE_BACKUPS});
        $this->assertEquals(0, $data->{programs_importer::IMPORT_USER_ALLOCATIONS});
        $this->assertEquals(1, $data->{programs_importer::IMPORT_PROGRAM_DYNAMICRULES});
    }

    /**
     * Test import program with DRs and import just one rule with outcome and check if the others get created.
     */
    public function test_import_program_with_dynamicrules() {
        global $DB;

        $category1 = self::getDataGenerator()->create_category(['name' => 'Category 1']);
        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        [$program, $exportid] = $this->create_and_export_program([
            'tenantid' => $tenant->get('id'),
            'idnumber' => 'ID1',
            'fullname' => 'My program1',
        ]);

        $programid = $program->get('id');
        \tool_program\api::archive_program($program);
        \tool_program\api::delete_program($program);

        $this->assertCount(0, $DB->get_records('tool_program'));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule_outcome'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);
        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        // We should have only one dynamic rule in the import.
        $dynamicrules = $importer->get_entities_in_workplace_export_file('tool_dynamicrule');
        $this->assertCount(1, $dynamicrules);

        $settings = [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            helper::get_setting_name_for_conflict_form('course', 'action') => 'create',
            helper::get_setting_name_for_conflict_form('course', 'catid') => $category1->id,
            helper::get_setting_name_for_conflict_form('customfield_field', 'action') => 'skip',
        ];

        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, $settings);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->wpgenerator->get_import_logs($importid);

        // Only one rule is on the exported file and was imported.
        $dynamicruleslog = array_filter($logs, function($log) {
            return (strpos($log['detail'], 'Created new rule') === 0);
        });
        $this->assertCount(1, $dynamicruleslog);

        $this->assertCount(1, $DB->get_records('tool_program'));
        // There are 6 program dynamic rules.
        $this->assertCount(6, $DB->get_records('tool_dynamicrule'));
        // All default conditions are created.
        $this->assertCount(6, $DB->get_records('tool_dynamicrule_condition'));
        // There is just one outcome imported from the original certifictaion.
        $this->assertCount(1, $DB->get_records('tool_dynamicrule_outcome'));
    }

    /**
     * Test importing program users with dynamic rule allocation type should be converted to manual
     */
    public function test_import_program_users_allocation_type(): void {
        global $DB;
        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        $program = $this->generator->generate_program((object)['tenantid' => $tenant->get('id')]);
        $programid = $program->get('id');

        \tool_program\api::allocate_user($program, (object) [
            'userid' => $users[1]->id,
            'certificationid' => 0,
            'allocationtype' => \tool_program\constants::ALLOCATION_DYNAMIC,
        ]);

        \tool_program\api::allocate_user($program, (object) [
            'userid' => $users[2]->id,
            'certificationid' => 0,
            'allocationtype' => \tool_program\constants::ALLOCATION_MANUAL,
        ]);

        $settings = [
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 1,
            programs_exporter::EXPORT_COURSE_BACKUPS => 0,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::EXPORT_SELECT_PROGRAMS => [$program->get('id')],
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(
            programs_exporter::class, $settings);

        \tool_program\api::archive_program($program);
        \tool_program\api::delete_program($program);

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_SELECTED,
            programs_importer::IMPORT_SELECTED_PROGRAMS => [$programid],
            programs_importer::IMPORT_USER_ALLOCATIONS => 1,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $this->assertCount(1, $DB->get_records('tool_program'));
        $programusers = \tool_program\persistent\program_user::get_records();
        $this->assertCount(2, $programusers);
        $this->assertEquals(\tool_program\constants::ALLOCATION_MANUAL, $programusers[0]->get('allocationtype'));
        $this->assertEquals(\tool_program\constants::ALLOCATION_MANUAL, $programusers[1]->get('allocationtype'));
    }

    /**
     * Create a program and add courses to it
     *
     * @param array $programdata
     * @param array $courses
     * @return \tool_program\persistent\program
     */
    protected function create_program_with_courses(array $programdata, array $courses): \tool_program\persistent\program {
        $program = $this->generator->generate_program((object)$programdata);
        foreach ($courses as $course) {
            \tool_program\api::add_course_to_base_set($program->get('id'), $course->id);
        }
        return $program;
    }

    /**
     * Test tenant with no category, program with shared courses and user don't have backup capability
     */
    public function test_tenant_with_no_category_and_no_capability(): void {
        // Create a tenant without a course category, assign a tenant admin.
        // Create another category where courses are visible, create a program inside this tenant that uses shared courses.
        // Login as this tenant admin with no backup capability and try to export/import courses.
        $misccat = core_course_category::get_default();
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(1, []);
        $courseshared = self::getDataGenerator()->create_course(['fullname' => 'Shared course', 'category' => $misccat->id]);
        $courseshared2 = self::getDataGenerator()->create_course(['fullname' => 'Shared course 2', 'category' => $misccat->id]);

        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        self::setUser($users[0]);

        $program = $this->create_program_with_courses(['tenantid' => $tenant->id], [$courseshared, $courseshared2]);

        $settings = [
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 0,
            programs_exporter::EXPORT_COURSE_BACKUPS => 1,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::EXPORT_SELECT_PROGRAMS => [$program->get('id')],
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(programs_exporter::class, $settings);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(1, $programs);
        // No courses have been exported.
        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(0, $courses);
    }

    /**
     * Test tenant with no category, program with shared courses and user has backup capability
     */
    public function test_tenant_with_no_category_and_capability(): void {
        global $DB;
        // Create a tenant without a course category, assign a tenant admin.
        // Create another category where courses are visible, create a program inside this tenant that uses shared courses.
        // Login as this tenant admin with backup capability on that category and try to export/import courses.
        $misccat = core_course_category::get_default();
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(1, []);
        $courseshared = self::getDataGenerator()->create_course(['fullname' => 'Shared course', 'category' => $misccat->id]);
        $courseshared2 = self::getDataGenerator()->create_course(['fullname' => 'Shared course 2', 'category' => $misccat->id]);

        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        self::setUser($users[0]);

        $program = $this->create_program_with_courses(['tenantid' => $tenant->id], [$courseshared, $courseshared2]);

        // Test same user has capability to backup those courses.
        $context = context_coursecat::instance($misccat->id);
        $role = $DB->get_record('role', ['shortname' => 'tool_tenant_admin']);
        assign_capability('moodle/backup:backupcourse', CAP_ALLOW, $role->id, $context->id);

        $settings = [
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 0,
            programs_exporter::EXPORT_COURSE_BACKUPS => 1,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::EXPORT_SELECT_PROGRAMS => [$program->get('id')],
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(programs_exporter::class, $settings);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(1, $programs);
        // Both shared courses are exported.
        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(2, $courses);
    }

    /**
     * Test exporting program with one shared course and one same tenant course and only exports tenant course
     */
    public function test_tenant_with_no_category(): void {
        $misccat = core_course_category::get_default();
        $coursecat = self::getDataGenerator()->create_category([]);
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(1, ['categoryid' => $coursecat->id]);
        $courseshared = self::getDataGenerator()->create_course(['fullname' => 'Shared course', 'category' => $misccat->id]);
        $coursetenant = self::getDataGenerator()->create_course(['fullname' => 'Tenant course', 'category' => $coursecat->id]);

        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        self::setUser($users[0]);

        $program = $this->create_program_with_courses(['tenantid' => $tenant->id], [$courseshared, $coursetenant]);

        // Login as this tenant admin and try to export/import courses, programs and certifications (with and without courses).
        $settings = [
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 0,
            programs_exporter::EXPORT_COURSE_BACKUPS => 1,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::EXPORT_SELECT_PROGRAMS => [$program->get('id')],
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(programs_exporter::class, $settings);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(1, $programs);
        // Only the tenant course has been exported.
        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(1, $courses);
        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($courses, false)[0];
        $this->assertEquals('Tenant course', $entity->get_raw_field('fullname'));
    }

    /**
     * Test importing a program with user allocations with allocation window closed and direct allocation disabled
     */
    public function test_import_program_with_allocation_window_and_direct_allocation_disabled(): void {
        global $DB;
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);

        $twomoredays = strtotime(' +2 day');
        $params = (object) [
            'tenantid' => $tenant->id,
            'allowdirectallocation' => 0,
            'allocationstartdatetype' => \tool_program\constants::DATE_ABSOLUTE,
            'allocationstartdateabsolute' => $twomoredays,
        ];

        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('tool/program:edit', CAP_ALLOW, $roleid, context_system::instance()->id);
        assign_capability('tool/program:allocateuser', CAP_ALLOW, $roleid, context_system::instance()->id);
        role_assign($roleid, $users[0]->id, context_system::instance()->id);

        self::setUser($users[0]);
        $program1 = $this->generator->generate_program_with_course($params);
        $this->generator->allocate_users_to_program($program1->get('id'), array_column($users, 'id'));
        // Create a new export containing only the first program.
        $exportid = $this->wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class, [
            programs_exporter::EXPORT_CONTENT => 1,
            programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
            programs_exporter::EXPORT_SELECT_PROGRAMS => [$program1->get('id')],
            programs_exporter::EXPORT_COURSE_BACKUPS => 0,
            programs_exporter::EXPORT_USER_ALLOCATIONS => 1,
            programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 0,
            programs_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        // We should have the first program in the import.
        $programs = $importer->get_entities_in_workplace_export_file(program::TABLE);
        $this->assertCount(1, $programs);

        $allocations = $importer->get_entities_in_workplace_export_file(\tool_program\persistent\program_user::TABLE);
        $this->assertCount(3, $allocations);

        \tool_program\api::archive_program($program1);
        \tool_program\api::delete_program($program1);

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_ALL,
            programs_importer::IMPORT_USER_ALLOCATIONS => 1,
            programs_importer::IMPORT_COURSE_BACKUPS => 0,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(4, $logs);

        $this->assertStringStartsWith('Created new program', $logs[0]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[1]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[2]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[3]['detail']);

        $programs = program::get_records();
        $this->assertEquals(0, $programs[0]->get('allowdirectallocation'));
        $this->assertEquals(\tool_program\constants::DATE_ABSOLUTE, $programs[0]->get('allocationstartdatetype'));
        $this->assertEquals($twomoredays, $programs[0]->get('allocationstartdateabsolute'));
        $this->assertCount(3, \tool_program\persistent\program_user::get_records());
    }
}

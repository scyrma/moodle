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
 * Class export_import_test
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_certification\certification;
use tool_certification\tool_wp\exporter\certifications as certifications_exporter;
use tool_certification\tool_wp\importer\certifications as certifications_importer;
use tool_tenant\tool_wp\exporter\tenants as tenants_exporter;
use tool_tenant\tool_wp\importer\tenants as tenants_importer;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;

/**
 * Class export_import_test
 *
 * @covers     \tool_certification\tool_wp\exporter\certifications
 * @covers     \tool_certification\tool_wp\importer\certifications
 * @package    tool_certification
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_export_import_testcase extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_wp_generator */
    protected $wpgenerator;
    /** @var string $importfixture */
    protected $importfixture = __DIR__ . '/fixtures/certifications-export.zip';

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->resetAfterTest();
        // Custom fields need to be reset after each test runs.
        \tool_certification\customfield\certification_handler::create()->delete_all();
    }

    /**
     * Test exporting single certification
     *
     * @return void
     */
    public function test_export_single_certification_manually(): void {
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $params = ['tenantid' => $defaulttenantid];
        $certification1 = $this->generator->generate_certification($params);

        $user = self::getDataGenerator()->create_user();
        \tool_certification\api::allocate_user($certification1, (object) [
            'userid' => $user->id,
            'status' => \tool_certification\constants::STATUS_OVERRIDE_DEFAULT,
        ]);

        // Create a new export containing only the first certification.
        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, [
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_SELECTED,
            certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification1->get('id')],
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 1,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_PROGRAMS => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(certifications_importer::class, $importer);

        // We should have the selected certification in the import.
        $certifications = $importer->get_entities_in_workplace_export_file(\tool_certification\certification::TABLE);
        $this->assertCount(1, $certifications);

        // We should have the selected certification user allocation in the import.
        $allocations = $importer->get_entities_in_workplace_export_file(\tool_certification\certification_user::TABLE);
        $this->assertCount(1, $allocations);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($certifications, false)[0];
        $this->assertEquals($certification1->get('fullname'), $entity->get_raw_field('fullname'));
    }

    /**
     * Test exporting all certifications
     *
     * @return void
     */
    public function test_export_all_certifications(): void {
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $params = ['tenantid' => $defaulttenantid];
        // Generate 2 active and 1 archived certifications.
        $certification1 = $this->generator->generate_certification($params);
        $certification2 = $this->generator->generate_certification($params);
        $certification3 = $this->generator->generate_certification($params + ['archived' => 1]);

        // Create a new export containing only the first certification.
        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, [
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_ALL,
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 0,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_PROGRAMS => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(certifications_importer::class, $importer);

        // We should have all certifications in the import.
        $certifications = $importer->get_entities_in_workplace_export_file(\tool_certification\certification::TABLE);
        $this->assertCount(3, $certifications);

        $certificationnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('fullname');
        }, iterator_to_array($certifications, false));
        $expected = [$certification1->get('fullname'), $certification2->get('fullname'), $certification3->get('fullname')];
        $this->assertEqualsCanonicalizing($expected, $certificationnames);
    }

    /**
     * Test exporting only active certifications
     *
     * @return void
     */
    public function test_export_active_certifications(): void {
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $params = ['tenantid' => $defaulttenantid];
        // Generate 2 active and 1 archived certifications.
        $certification1 = $this->generator->generate_certification($params);
        $certification2 = $this->generator->generate_certification($params);
        $certification3 = $this->generator->generate_certification($params + ['archived' => 1]);

        // Create a new export containing only the first certification.
        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, [
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_ACTIVE,
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 0,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_PROGRAMS => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(certifications_importer::class, $importer);

        // We should have only both active certifications in the import.
        $certifications = $importer->get_entities_in_workplace_export_file(\tool_certification\certification::TABLE);
        $this->assertCount(2, $certifications);

        $certificationnames = array_map(static function(\tool_wp\local\exportimport\wp_imported_entity $entity) {
            return $entity->get_raw_field('fullname');
        }, iterator_to_array($certifications, false));
        $expected = [$certification1->get('fullname'), $certification2->get('fullname')];
        $this->assertEqualsCanonicalizing($expected, $certificationnames);
    }

    /**
     * Test importing single certification with one user allocation
     *
     * @return void
     */
    public function test_import_single_certification_manually(): void {
        global $DB;
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        [$certification1, $exportid] = $this->create_and_export_certification([
            'tenantid' => $defaulttenantid,
            'idnumber' => 'ID1',
            'fullname' => 'My certification1',
        ], [], [$user1, $user2, $user3], [$user1]);
        $certificationid = $certification1->get('id');

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Sanity check.
        $this->assertCount(1, $DB->get_records(\tool_certification\certification_completion::TABLE));

        \tool_certification\api::archive_certification($certificationid);
        $certification1 = new \tool_certification\certification($certificationid);
        \tool_certification\api::delete_certification($certification1);
        $this->assertCount(0, $DB->get_records(tool_certification\certification::TABLE));
        $this->assertCount(0, $DB->get_records(\tool_certification\certification_user::TABLE));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        $this->assertCount(0, $DB->get_records(\tool_certification\certification_completion::TABLE));

        // Delete user2 and user3 and create a new user with same username as user3 but different firstname.
        $deleteduser0 = fullname($user2);
        delete_user($user2);
        $deleteduser = $user3;
        delete_user($user3);
        $newuser1 = self::getDataGenerator()->create_user(['username' => $deleteduser->username, 'firstname' => 'Laia']);
        $this->tenantgenerator->allocate_user($newuser1->id, $defaulttenantid);

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_SELECTED,
            certifications_importer::IMPORT_SELECT_CERTIFICATIONS => [$certificationid],
            certifications_importer::IMPORT_PROGRAMS => 0,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 1,
            certifications_importer::IMPORT_COURSE_BACKUPS => 0,
            certifications_importer::IMPORT_DYNAMICRULES => 0,
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $certifications = $DB->get_records(tool_certification\certification::TABLE);
        $this->assertCount(1, $certifications);
        $newcertificationid = (reset($certifications))->id;
        // Check user allocations.
        $certificationusers = $DB->get_records(\tool_certification\certification_user::TABLE);
        $this->assertCount(2, $certificationusers);
        $this->assertCount(2, $DB->get_records(\tool_program\persistent\program_user::TABLE));
        // Check that user allocation completion exist for user1 and it's mapped to the new certificationid.
        $completions = $DB->get_records(\tool_certification\certification_completion::TABLE);
        $this->assertCount(1, $completions);
        $completion = reset($completions);
        $this->assertEquals($user1->id, $completion->userid);
        $this->assertEquals($newcertificationid, $completion->certificationid);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(4, $logs);
        // There is 1 error for one user who could not be allocated to the certification.
        $allocations = array_filter($logs, function($log) {
            return (strpos($log['detail'], 'Allocated user') === 0);
        });
        $this->assertCount(2, $allocations);

        // Check log msg for user not allocated.
        $allocations = array_filter($logs, function($log) {
            return (strpos($log['detail'], 'Could not allocate user') === 0);
        });
        $obj = (object)['certification' => $certification1->get('fullname'), 'originaluserfullname' => $deleteduser0];
        $str = get_string('errorcouldnotallocate', 'tool_certification', $obj);
        $this->assertEquals($str, reset($allocations)['detail']);

        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(1, $errors);
        $usernames = [];
        foreach ($certificationusers as $certificationuser) {
            $usernames[] = $certificationuser->userid;
        }
        $this->assertEqualsCanonicalizing([$newuser1->id, $user1->id], $usernames);
        // One user was deleted and could not be imported.
        $this->assertCount(1, $this->wpgenerator->get_import_conflict_review($importid));
    }

    /**
     * Test importing all certifications
     *
     * @return void
     */
    public function test_import_all_certifications(): void {
        global $DB;

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        $params = ['tenantid' => $tenant->id];

        $this->generator->assign_edit_capability($users[0]->id, context_system::instance());
        self::setUser($users[0]);

        $cfgenerator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        // Define one customfield.
        $cfparams = [
            'component' => 'tool_certification',
            'area' => 'certification',
            'itemid' => 0,
            'contextid' => context_system::instance()->id
        ];
        $category = $cfgenerator->create_category($cfparams);
        $cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld1']);
        $cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld2']);

        $program1 = $this->programgenerator->generate_program_with_course((object)($params +
            ['fullname' => 'Program 1', 'idnumber' => 'ID1']));
        $certification1 = $this->generator->generate_certification($params +
            ['idnumber' => 'ID1', 'program' => $program1->get('id'), 'customfield_fld1' => 'Hello1', 'customfield_fld2' => 'Hi!']);

        $program2 = $this->programgenerator->generate_program_with_course((object)($params +
            ['fullname' => 'Program 2', 'idnumber' => 'ID2']));
        $certification2 = $this->generator->generate_certification($params +
            ['idnumber' => 'ID2', 'program' => $program2->get('id')]);

        $program3 = $this->programgenerator->generate_program_with_course((object)($params +
            ['fullname' => 'Program 3', 'idnumber' => 'ID3']));
        $certification3 = $this->generator->generate_certification($params +
            ['idnumber' => 'ID3', 'archived' => 1, 'program' => $program3->get('id')]);

        $this->assertCount(2, $DB->get_records('customfield_data'));

        // Create a new export containing only the first certification.
        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, [
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_ALL,
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 0,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_PROGRAMS => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));
        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        \tool_certification\api::archive_certification($certification1->get('id'));
        $certification1 = new \tool_certification\certification($certification1->get('id'));
        \tool_certification\api::delete_certification($certification1);

        \tool_certification\api::archive_certification($certification2->get('id'));
        $certification2 = new \tool_certification\certification($certification2->get('id'));
        \tool_certification\api::delete_certification($certification2);

        \tool_certification\api::delete_certification($certification3);
        $this->assertCount(0, $DB->get_records('tool_certification'));

        $this->assertCount(0, $DB->get_records('customfield_data'));

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_ALL,
            certifications_importer::IMPORT_PROGRAMS => 0,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 0,
            certifications_importer::IMPORT_COURSE_BACKUPS => 0,
            certifications_importer::IMPORT_DYNAMICRULES => 0,
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $this->assertCount(3, $DB->get_records('tool_certification'));

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(3, $logs);
        $this->assertCount(0, $logs[0]['errors']);
        $this->assertCount(0, $logs[0]['notices']);

        $this->assertCount(2, $DB->get_records('customfield_data'));
    }

    /**
     * Export and import shared certification allocations without the shared certification itself
     */
    public function test_export_import_shared_certification_allocations() {
        global $DB;

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();

        // Create a shared program.
        $program1 = $this->programgenerator->generate_program_with_course(
            (object)['fullname' => 'Program 1', 'tenantid' => $sharedtenantid]);
        $sharedcertification = $this->generator->generate_certification([
            'program' => $program1->get('id'),
            'idnumber' => 'C1',
            'tenantid' => $sharedtenantid,
        ]);
        $certificationid = $sharedcertification->get('id');

        // Make user $users[0] tenant administrator.
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);

        // Allocate users to certification from both tenants.
        $this->generator->allocate_users_to_certification($certificationid, array_column(array_merge($users, $users2), 'id'));

        // Export shared program as a tenant administrator.
        $this->setUser($users[0]);

        // Create a new export containing the shared certification.
        $exportparams = [
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_ALL,
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_PROGRAMS => 0,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 1,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, $exportparams);
        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Analyze the export file. Zero programs where exported but three user allocations.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importer = $importmanager->get_importer();

        $certifications = $importer->get_entities_in_workplace_export_file(certification::TABLE);
        $this->assertCount(0, $certifications);
        $allocations = $importer->get_entities_in_workplace_export_file('tool_certification_users');
        $this->assertCount(3, $allocations);

        // Remove all allocations, move one user to a different tenant, and perform import.
        array_walk($users, function($user) use ($certificationid) {
            \tool_certification\api::deallocate_user($certificationid, $user->id);
        });
        $this->assertCount(2, $DB->get_records('tool_certification_users'));
        (new \tool_tenant\manager())->allocate_user($users[2]->id, $tenant2->id, 'test', '');

        // Perform import.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_ALL,
            certifications_importer::IMPORT_PROGRAMS => 1,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 1,
            certifications_importer::IMPORT_COURSE_BACKUPS => 0,
            certifications_importer::IMPORT_DYNAMICRULES => 0,
            helper::get_importer_setting_name_for_conflict_form('tool_program', 'idnumberconflict', 'action') => 'increment',
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
        ]);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(3, $logs);
        core_collator::asort_array_of_arrays_by_key($logs, 'detail');
        $this->assertStringStartsWith('Allocated user', $logs[0]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[1]['detail']);
        $this->assertStringStartsWith('Could not allocate user', $logs[2]['detail']);
        $this->assertStringStartsWith('Could not find user', $logs[2]['errors'][0]);

        $this->assertCount(4, $DB->get_records('tool_certification_users'));

        // Perform the same import again, users should not be allocated.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_ALL,
            certifications_importer::IMPORT_PROGRAMS => 1,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 1,
            certifications_importer::IMPORT_COURSE_BACKUPS => 0,
            certifications_importer::IMPORT_DYNAMICRULES => 0,
            helper::get_importer_setting_name_for_conflict_form('tool_program', 'idnumberconflict', 'action') => 'increment',
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form('tool_certification_users', 'cannotallocate', 'action') => 'skip',
        ]);
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(3, $logs);
        $this->assertCount(4, $DB->get_records('tool_certification_users'));
        $this->assertStringStartsWith('Could not allocate user', $logs[0]['detail']);
        $this->assertStringStartsWith('Not possible to allocate user', $logs[0]['errors'][0]);
        $this->assertStringStartsWith('Could not allocate user', $logs[1]['detail']);
        $this->assertStringStartsWith('Not possible to allocate user', $logs[1]['errors'][0]);
        $this->assertStringStartsWith('Could not allocate user', $logs[2]['detail']);
        $this->assertStringStartsWith('Could not find user', $logs[2]['errors'][0]);

    }

    /**
     * Test exporting and importing a tenant with shared certification,
     * allocations are exported and imported without certifications.
     */
    public function test_export_shared_certifications_in_tenant_export() {
        global $DB;

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        // Create a shared program.
        $program1 = $this->programgenerator->generate_program_with_course(
            (object)['fullname' => 'Program 1', 'tenantid' => $sharedtenantid]);
        $sharedcertification = $this->generator->generate_certification([
            'program' => $program1->get('id'),
            'idnumber' => 'C1',
            'tenantid' => $sharedtenantid,
        ]);

        // Make user $users[0] tenant administrator.
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);

        // Allocate users to first certification from both tenants.
        $this->generator->allocate_users_to_certification($sharedcertification->get('id'),
            array_column(array_merge($users, $users2), 'id'));

        // Export  tenants as an admin.
        $this->setAdminUser();
        $exportparams = [
            tenants_exporter::EXPORT_USERS => 1,
            tenants_exporter::EXPORT_CERTIFICATIONS => 1,
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

        $certifications = $importer->get_entities_in_workplace_export_file(certification::TABLE);
        $this->assertCount(0, $certifications);
        $allocations = $importer->get_entities_in_workplace_export_file('tool_certification_users');
        $this->assertCount(5, $allocations);

        // Delete two users. Delete program allocations for all users (to make sure they are not created).
        delete_user($users[2]);
        delete_user($users2[1]);
        \tool_certification\api::deallocate_user($sharedcertification->get('id'), $users[0]->id);
        \tool_certification\api::deallocate_user($sharedcertification->get('id'), $users[1]->id);
        \tool_certification\api::deallocate_user($sharedcertification->get('id'), $users2[0]->id);
        $this->assertEmpty($DB->get_records('tool_certification_users'));

        // Import only one tenant into a new tenant.
        // What should happen: users that already exist will not be created. One user will be created ($users[2]).
        // This user will be allocated to the shared program. No other allocations will be created.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            tenants_importer::IMPORT_INSTANCES => tenants_importer::IMPORT_INSTANCES_MANUAL,
            tenants_importer::IMPORT_SELECT_MANUAL => [$tenant->id],
            tenants_importer::IMPORT_DESTINATION => tenants_importer::IMPORT_DESTINATION_NEW,
            tenants_importer::IMPORT_USERS => 1,
            tenants_importer::IMPORT_CERTIFICATIONS => 1,
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form('user', 'usernameconflict', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form('user', 'emailconflict', 'action') => 'skip',
        ]);
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(7, $logs);
        // Imported tenant, Couldn't create user x 2, Created user, Could not allocate user x 2, Allocated user.

        $this->assertCount(1, $DB->get_records('tool_certification_users'));
        $this->assertCount(1, $DB->get_records_sql(
            'SELECT 1 FROM {tool_certification_users} tu
                      JOIN {user} u ON u.id=tu.userid
                     WHERE u.username=?', [$users[2]->username]));
    }

    /**
     * Test for importing a certification with idnumber conflict.
     */
    public function test_certification_import_idnumberconflict() {
        global $DB;
        $this->resetAfterTest();

        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        [$certification1, $exportid] = $this->create_and_export_certification([
            'tenantid' => $tenant->get('id'),
            'idnumber' => 'ID1',
            'fullname' => 'My certification1',
        ]);

        $this->assertCount(1, $DB->get_records('tool_certification'));

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_ALL,
            certifications_importer::IMPORT_PROGRAMS => 1,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 0,
            certifications_importer::IMPORT_COURSE_BACKUPS => 0,
            certifications_importer::IMPORT_DYNAMICRULES => 0,
            helper::get_importer_setting_name_for_conflict_form('tool_certification', 'idnumberconflict', 'action') => 'increment',
            helper::get_importer_setting_name_for_conflict_form('tool_program', 'idnumberconflict', 'action') => 'increment',
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $this->assertCount(2, $DB->get_records('tool_certification'));

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(2, $logs);
        $this->assertCount(0, $logs[0]['errors']);
        $this->assertCount(1, $logs[0]['notices']);
        $this->assertCount(0, $logs[1]['errors']);
        $this->assertCount(1, $logs[1]['notices']);
        $this->assertEquals("ID number was changed from 'ID1' to 'ID2'", $logs[1]['notices'][0]);
    }

    /**
     * Test importing certification in a different tenant
     *
     * @return void
     */
    public function test_import_certification_different_tenant(): void {
        global $DB;
        self::setAdminUser();

        [$certification1, $exportid] = $this->create_and_export_certification([
            'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
            'idnumber' => 'TESTC',
            'fullname' => 'My certification1',
        ]);

        $certificationid = $certification1->get('id');
        $tenantid2 = $this->tenantgenerator->create_tenant()->id;

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            'tenantid' => $tenantid2,
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_SELECTED,
            certifications_importer::IMPORT_SELECT_CERTIFICATIONS => [$certificationid],
            certifications_importer::IMPORT_PROGRAMS => 1,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 0,
            certifications_importer::IMPORT_COURSE_BACKUPS => 0,
            certifications_importer::IMPORT_DYNAMICRULES => 0,
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $this->assertCount(1, $DB->get_records(tool_certification\certification::TABLE, ['tenantid' => $tenantid2]));

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(2, $logs);
    }

    /**
     * Create and export a certification
     *
     * @param array $certificationparams
     * @param array $exportsettings settings for export (by default export only this certification)
     * @param array $users array of users to be allocated to the certification
     * @param array $certifiedusers array of users that will be certified on this certification
     * @return array [$certification, $exportid]
     */
    protected function create_and_export_certification(array $certificationparams, array $exportsettings = [], array $users = [],
                                                       array $certifiedusers = []) {
        global $DB;
        $cfgenerator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $params = [
            'component' => 'tool_certification',
            'area' => 'certification',
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

        $certificationparams['customfield_fld1'] = 'Hello1';
        $certificationparams['customfield_fld2'] = 'Hello2';
        $certificationparams['customfield_fld3'] = 'Hello3';
        $certificationparams['customfield_fld4'] = '1';

        $certification = $this->generator->generate_certification($certificationparams);

        $sql = '
                SELECT drc.*
                FROM {tool_dynamicrule_condition} drc
                JOIN {tool_dynamicrule} dr
                ON dr.id = drc.ruleid
                WHERE dr.itemid = :itemid AND dr.component = :component AND dr.componentarea = :componentarea
                AND drc.classname = :classname
            ';
        $params0 = [
            'component' => 'tool_certification',
            'componentarea' => 'certification',
            'itemid' => $certification->get('id'),
            'classname' => 'tool_certification\tool_dynamicrule\condition\certification_certified',
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
            \tool_certification\api::allocate_user($certification, (object) [
                'userid' => $user->id,
                'status' => \tool_certification\constants::STATUS_OVERRIDE_DEFAULT,
            ]);
        }

        foreach ($certifiedusers as $user) {
            $expiry = time() + 1300;
            \tool_certification\api::set_user_as_certified($user->id, $certification->get('id'), $expiry);
        }

        // Export certification $certification.
        $settings = $exportsettings + [
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_SELECTED,
            certifications_exporter::EXPORT_PROGRAMS => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 1,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 1,
            certifications_exporter::EXPORT_DYNAMICRULES => 1,
            certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification->get('id')],
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(
            certifications_exporter::class, $settings);
        return [$certification, $exportid];
    }

    /**
     * Generates tenant admin with certification:edit and course_create capabilities and users
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
        assign_capability('tool/certification:edit', CAP_ALLOW, $managerrole->id, $context->id);
        assign_capability('tool/program:edit', CAP_ALLOW, $managerrole->id, $context->id);
        assign_capability('moodle/course:create', CAP_ALLOW, $managerrole->id, $context->id);
        return [$tenant, $users];
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

        $course1 = self::getDataGenerator()->create_course(['fullname' => 'Course 1', 'category' => $category1->id]);
        $program = $this->programgenerator->generate_program((object) ['tenantid' => $tenant->id]);
        $this->programgenerator->add_course_to_set($course1->id, $program->get_base_set()->get('id'));

        [$certification1, $exportid] = $this->create_and_export_certification([
            'tenantid' => $tenant->id,
            'idnumber' => 'TESTC',
            'fullname' => 'My certification',
            'program' => $program->get('id'),
        ]);

        // Submitting an import form without a required field.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_SELECTED,
            certifications_importer::IMPORT_SELECT_CERTIFICATIONS => [],
        ];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertFalse($form->is_validated());
        $this->assertEquals(
            [certifications_importer::IMPORT_SELECT_CERTIFICATIONS =>
                get_string('selectatleastonecertification', 'tool_certification')],
            $form->get_quick_form()->_errors);

        // Submitting an import form without errors, make sure all default values apply.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $settings = [];
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertEquals(certifications_importer::IMPORT_INSTANCES_ALL, $data->{certifications_importer::IMPORT_INSTANCES});
        $this->assertEquals(1, $data->{certifications_importer::IMPORT_CONTENT});
        $this->assertEquals(1, $data->{certifications_importer::IMPORT_PROGRAMS});
        $this->assertEquals(1, $data->{certifications_importer::IMPORT_COURSE_BACKUPS});
        $this->assertEquals(0, $data->{certifications_importer::IMPORT_USER_ALLOCATIONS});
        $this->assertEquals(1, $data->{certifications_importer::IMPORT_DYNAMICRULES});
    }

    /**
     * Test import certification with DRs and import just one rule with outcome and check if the others get created.
     */
    public function test_import_certification_with_dynamicrules() {
        global $DB;

        $category1 = self::getDataGenerator()->create_category(['name' => 'Category 1']);
        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        [$certification1, $exportid] = $this->create_and_export_certification([
            'tenantid' => $tenant->get('id'),
            'idnumber' => 'ID1',
            'fullname' => 'My certification1',
        ]);

        $certificationid = $certification1->get('id');
        $program = new \tool_program\persistent\program($certification1->get('program'));
        \tool_certification\api::archive_certification($certificationid);
        $certification1 = new \tool_certification\certification($certificationid);
        \tool_certification\api::delete_certification($certification1);
        \tool_program\api::archive_program($program);
        \tool_program\api::delete_program($program);

        $this->assertCount(0, $DB->get_records('tool_certification'));
        $this->assertCount(0, $DB->get_records('tool_program'));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule'));
        $this->assertCount(0, $DB->get_records('tool_dynamicrule_outcome'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($exportid);
        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);
        $importer = reset($importers);
        $this->assertInstanceOf(certifications_importer::class, $importer);

        // We should have only one dynamic rule in the import.
        $dynamicrules = $importer->get_entities_in_workplace_export_file('tool_dynamicrule');
        $this->assertCount(1, $dynamicrules);

        $settings = [
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_PROGRAMS => 1,
            certifications_importer::IMPORT_COURSE_BACKUPS => 0,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 0,
            certifications_importer::IMPORT_DYNAMICRULES => 1,
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_ALL,
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

        $this->assertCount(1, $DB->get_records('tool_certification'));
        $this->assertCount(1, $DB->get_records('tool_program'));
        // There are 8 certification dynamic rules and 6 from programs.
        $this->assertCount(14, $DB->get_records('tool_dynamicrule'));
        // All default conditions are created.
        $this->assertCount(14, $DB->get_records('tool_dynamicrule_condition'));
        // There is just one outcome imported from the original certifictaion.
        $this->assertCount(1, $DB->get_records('tool_dynamicrule_outcome'));
    }

    /**
     * Test import settings with no permissions.
     */
    public function test_import_settings_permissions() {
        global $DB;

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $context = context_system::instance();

        assign_capability('tool/wp:manageexportimport', CAP_ALLOW, $managerrole->id, $context->id);
        assign_capability('tool/wp:useexportimport', CAP_ALLOW, $managerrole->id, $context->id);

        unassign_capability('tool/dynamicrule:manage', $managerrole->id);
        unassign_capability('tool/certification:allocateuser', $managerrole->id);
        unassign_capability('moodle/course:create', $managerrole->id);
        self::getDataGenerator()->role_assign($managerrole->id, $users[0]->id, $context);
        self::setUser($users[0]);

        $settings = [
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 1,
            certifications_importer::IMPORT_DYNAMICRULES => 1,
            certifications_importer::IMPORT_COURSE_BACKUPS => 1,
        ];

        $importid = $this->wpgenerator->prepare_import_from_file($this->importfixture);
        $form = $this->wpgenerator->submit_import_form($importid, 3, $settings);

        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertEquals(1, $data->{certifications_importer::IMPORT_CONTENT});
        $this->assertEquals(0, $data->{certifications_importer::IMPORT_USER_ALLOCATIONS});
        $this->assertEquals(0, $data->{certifications_importer::IMPORT_DYNAMICRULES});
        $this->assertEquals(0, $data->{certifications_importer::IMPORT_COURSE_BACKUPS});
    }

    /**
     * Test importing certification users with dynamic rule allocation type should be converted to manual
     *
     * @return void
     */
    public function test_import_certification_users_allocation_type(): void {
        global $DB;
        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->get('id')]);
        $certificationid = $certification->get('id');

        \tool_certification\api::allocate_user($certification, (object) [
            'userid' => $users[1]->id,
            'status' => \tool_certification\constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => \tool_certification\constants::ALLOCATION_DYNAMIC,
        ]);

        \tool_certification\api::allocate_user($certification, (object) [
            'userid' => $users[2]->id,
            'status' => \tool_certification\constants::STATUS_OVERRIDE_DEFAULT,
            'allocationtype' => \tool_certification\constants::ALLOCATION_MANUAL,
        ]);

        // Export certification $certification.
        $settings = [
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_SELECTED,
            certifications_exporter::EXPORT_PROGRAMS => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 1,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification->get('id')],
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(
            certifications_exporter::class, $settings);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        \tool_certification\api::archive_certification($certificationid);
        $certification1 = new \tool_certification\certification($certificationid);
        \tool_certification\api::delete_certification($certification1);

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_SELECTED,
            certifications_importer::IMPORT_SELECT_CERTIFICATIONS => [$certificationid],
            certifications_importer::IMPORT_PROGRAMS => 0,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 1,
            certifications_importer::IMPORT_COURSE_BACKUPS => 0,
            certifications_importer::IMPORT_DYNAMICRULES => 0,
            helper::get_setting_name_for_conflict_form('user', 'action') => 'skip',
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $certifications = $DB->get_records(tool_certification\certification::TABLE);
        $this->assertCount(1, $certifications);
        // Check user allocations.
        $certificationusers = \tool_certification\certification_user::get_records();
        $this->assertCount(2, $certificationusers);
        // Assert than any dynamic rule allocation type has been converted to manual.
        $this->assertEquals(\tool_certification\constants::ALLOCATION_MANUAL, $certificationusers[0]->get('allocationtype'));
        $this->assertEquals(\tool_certification\constants::ALLOCATION_MANUAL, $certificationusers[1]->get('allocationtype'));
        $this->assertCount(2, $DB->get_records(\tool_program\persistent\program_user::TABLE));
    }

    /**
     * Create a program and add courses to it
     *
     * @param array $programdata
     * @param array $courses
     * @return \tool_program\persistent\program
     */
    protected function create_program_with_courses(array $programdata, array $courses): \tool_program\persistent\program {
        $program = $this->programgenerator->generate_program((object)$programdata);
        foreach ($courses as $course) {
            \tool_program\api::add_course_to_base_set($program->get('id'), $course->id);
        }
        return $program;
    }

    /**
     * Test tenant with no category, certification and program with shared courses and user don't have backup capability
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

        $certification = $this->generator->generate_certification([
            'tenantid' => $tenant->id,
            'program' => $program->get('id'),
        ]);

        $settings = [
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_SELECTED,
            certifications_exporter::EXPORT_PROGRAMS => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 1,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification->get('id')],
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(
            certifications_exporter::class, $settings);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(certifications_importer::class, $importer);

        $certifications = $importer->get_entities_in_workplace_export_file('tool_certification');
        $this->assertCount(1, $certifications);
        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(1, $programs);
        // No courses have been exported.
        $courses = $importer->get_entities_in_workplace_export_file('course');
        $this->assertCount(0, $courses);
    }

    /**
     * Test tenant with no category, certification and program with shared courses and user has backup capability
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
        $certification = $this->generator->generate_certification([
            'tenantid' => $tenant->id,
            'program' => $program->get('id'),
        ]);

        // Test same user has capability to backup those courses.
        $context = context_coursecat::instance($misccat->id);
        $role = $DB->get_record('role', ['shortname' => 'tool_tenant_admin']);
        assign_capability('moodle/backup:backupcourse', CAP_ALLOW, $role->id, $context->id);

        $settings = [
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_SELECTED,
            certifications_exporter::EXPORT_PROGRAMS => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 1,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification->get('id')],
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(
            certifications_exporter::class, $settings);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(certifications_importer::class, $importer);

        $certifications = $importer->get_entities_in_workplace_export_file('tool_certification');
        $this->assertCount(1, $certifications);
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
        $certification = $this->generator->generate_certification([
            'tenantid' => $tenant->id,
            'program' => $program->get('id'),
        ]);

        // Login as this tenant admin and try to export/import courses, programs and certifications (with and without courses).
        $settings = [
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_SELECTED,
            certifications_exporter::EXPORT_PROGRAMS => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 1,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification->get('id')],
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(
            certifications_exporter::class, $settings);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(certifications_importer::class, $importer);

        $certifications = $importer->get_entities_in_workplace_export_file('tool_certification');
        $this->assertCount(1, $certifications);
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
     * Test exporting and importing a shared certification
     *
     * @return void
     */
    public function test_export_import_shared_certification(): void {
        global $DB;
        self::setAdminUser();

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();

        $sharedprogram = $this->programgenerator->generate_program((object)['tenantid' => $sharedtenantid, 'idnumber' => 'P1']);
        $sharedcertification = $this->generator->generate_certification([
            'program' => $sharedprogram->get('id'),
            'idnumber' => 'C1',
        ]);

        $certificationid = $sharedcertification->get('id');
        $this->generator->allocate_user($user1->id, $sharedcertification->get('id'));
        $this->generator->allocate_user($user2->id, $sharedcertification->get('id'));

        // Switch to the tenant.
        \tool_tenant\tenancy::set_switched_tenant_id($tenant->id);

        // Create a new export containing the shared certification.
        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, [
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_SELECTED,
            certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$sharedcertification->get('id')],
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_PROGRAMS => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 1,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 1,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(certifications_importer::class, $importer);

        // We should have the selected certification in the import.
        $certifications = $importer->get_entities_in_workplace_export_file(\tool_certification\certification::TABLE);
        $this->assertCount(1, $certifications);

        // We should have the selected certification user allocation in the import.
        $allocations = $importer->get_entities_in_workplace_export_file(\tool_certification\certification_user::TABLE);
        $this->assertCount(2, $allocations);

        /** @var \tool_wp\local\exportimport\wp_imported_entity $entity */
        $entity = iterator_to_array($certifications, false)[0];
        $this->assertEquals($sharedcertification->get('fullname'), $entity->get_raw_field('fullname'));

        \tool_certification\api::archive_certification($certificationid);
        $sharedcertification = new \tool_certification\certification($certificationid);
        \tool_certification\api::delete_certification($sharedcertification);
        $this->assertCount(0, $DB->get_records(tool_certification\certification::TABLE));
        $this->assertCount(0, $DB->get_records(\tool_certification\certification_user::TABLE));
        $this->assertCount(0, $DB->get_records(\tool_program\persistent\program_user::TABLE));

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_ALL,
            certifications_importer::IMPORT_PROGRAMS => 1,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 1,
            certifications_importer::IMPORT_COURSE_BACKUPS => 0,
            certifications_importer::IMPORT_DYNAMICRULES => 0,
            helper::get_importer_setting_name_for_conflict_form('tool_program', 'idnumberconflict', 'action') => 'increment',
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $certifications = $DB->get_records(tool_certification\certification::TABLE);
        $this->assertCount(1, $certifications);
        $newcertificationid = (reset($certifications))->id;
        $newcertification = new \tool_certification\certification($newcertificationid);
        // Shared property should be set to 0.
        $this->assertEquals(0, $newcertification->get('shared'));
        // Check user allocations.
        $certificationusers = $DB->get_records(\tool_certification\certification_user::TABLE);
        $this->assertCount(2, $certificationusers);
        $this->assertCount(2, $DB->get_records(\tool_program\persistent\program_user::TABLE));

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(4, $logs);
        // There is 1 error for one user who could not be allocated to the certification.
        $allocations = array_filter($logs, function($log) {
            return (strpos($log['detail'], 'Allocated user') === 0);
        });
        $this->assertCount(2, $allocations);

        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(0, $errors);
    }

    /**
     * Tenant admin can export shared certification and only users from the current tenant will be exported.
     * Admin will be able to export the same certification with all users.
     */
    public function test_export_shared_certification() {
        global $DB;

        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(2);
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();

        $sharedprogram = $this->programgenerator->generate_program((object)[
            'tenantid' => $sharedtenantid,
            'fullname' => 'Program 1',
        ]);
        $sharedcertification = $this->generator->generate_certification([
            'program' => $sharedprogram->get('id'),
            'fullname' => 'Certification 1',
        ]);

        // Create a certification in a parent tenant but make it not shared.
        $sharedprogram2 = $this->programgenerator->generate_program_with_course( (object)[
            'fullname' => 'Program 2',
            'tenantid' => $sharedtenantid,
        ]);
        $DB->update_record('tool_program', ['id' => $sharedprogram2->get('id'), 'shared' => 0]); // Only possible manually.
        $sharedcertification2 = $this->generator->generate_certification([
            'program' => $sharedprogram2->get('id'),
            'fullname' => 'Certification 2',
        ]);
        // Only possible manually.
        $DB->update_record('tool_certification', ['id' => $sharedcertification2->get('id'), 'shared' => 0]);

        // Allocate users to first certification from both tenants.
        $this->generator->allocate_user($users[0]->id, $sharedcertification->get('id'));
        $this->generator->allocate_user($users[1]->id, $sharedcertification->get('id'));
        $this->generator->allocate_user($users[2]->id, $sharedcertification->get('id'));
        $this->generator->allocate_user($users2[0]->id, $sharedcertification->get('id'));
        $this->generator->allocate_user($users2[1]->id, $sharedcertification->get('id'));
        // Make user $users[0] tenant administrator.
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);

        // Export shared certification as a tenant admin.
        $this->setUser($users[0]);

        // Create a new export containing the shared certification.
        $exportparams = [
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_ALL,
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_PROGRAMS => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 1,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 1,
        ];
        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, $exportparams);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Analyze the export, there should be one certification (shared one) and only three users (from the current tenant).
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importer = $importmanager->get_importer();
        $certifications = $importer->get_entities_in_workplace_export_file('tool_certification');
        $this->assertCount(1, $certifications);
        $this->assertEquals($sharedcertification->get('id'), $certifications->current()->get_raw_field('id'));
        $certificationusers = $importer->get_entities_in_workplace_export_file('tool_certification_users');
        $this->assertCount(3, $certificationusers);

        // Do the same export as a global admin in the shared space. 2 certifications will be exported and 5 users.
        $this->setAdminUser();
        \tool_tenant\tenancy::set_switched_tenant_id($sharedtenantid);
        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, $exportparams);
        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importer = $importmanager->get_importer();
        $certifications = $importer->get_entities_in_workplace_export_file('tool_certification');
        $this->assertCount(2, $certifications);
        $certificationusers = $importer->get_entities_in_workplace_export_file('tool_certification_users');
        $this->assertCount(5, $certificationusers);
    }

    /**
     * Tenant admin can export a tenant certification that is linked to a shared program but only the certification
     * will be exported if INCLUDE_SHARED_ENTITIES setting is disabled.
     */
    public function test_export_tenant_certification_with_shared_program() {
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(1);
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();

        $sharedprogram = $this->programgenerator->generate_program((object)[
            'tenantid' => $sharedtenantid,
            'fullname' => 'Program 1',
        ]);
        $certification = $this->generator->generate_certification([
            'program' => $sharedprogram->get('id'),
            'fullname' => 'Certification 1',
            'tenantid' => $tenant->id,
        ]);

        // Make user $users[0] tenant administrator.
        (new tool_tenant\manager())->assign_tenant_admin_roles([$users[0]->id], $tenant->id);
        // Export shared certification as a tenant admin.
        $this->setUser($users[0]);

        // Create a new export containing the shared certification.
        $exportparams = [
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_ALL,
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_PROGRAMS => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 0,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ];
        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, $exportparams);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Analyze the export, there should be one certification and no programs.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importer = $importmanager->get_importer();
        $certifications = $importer->get_entities_in_workplace_export_file('tool_certification');
        $this->assertCount(1, $certifications);
        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(0, $programs);

        // Create a new export including shared entities. Now program should be exported.
        $exportparams = [
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_ALL,
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_PROGRAMS => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 0,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 1,
        ];
        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, $exportparams);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Analyze the export, there should be one certification and one program.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importer = $importmanager->get_importer();
        $certifications = $importer->get_entities_in_workplace_export_file('tool_certification');
        $this->assertCount(1, $certifications);
        $programs = $importer->get_entities_in_workplace_export_file('tool_program');
        $this->assertCount(1, $programs);
    }

    /**
     * Test importing allocations with certification that has allocation window closed
     *
     * @return void
     */
    public function test_import_certification_with_allocation_window_and_direct_allocation_disabled(): void {
        global $DB;
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);

        $twomoredays = strtotime(' +2 day');
        $params = [
            'tenantid' => $tenant->id,
            'allocationstartdatetype' => \tool_certification\constants::DATE_ABSOLUTE,
            'allocationstartdateabsolute' => $twomoredays,
        ];

        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('tool/certification:edit', CAP_ALLOW, $roleid, context_system::instance()->id);
        assign_capability('tool/certification:allocateuser', CAP_ALLOW, $roleid, context_system::instance()->id);
        role_assign($roleid, $users[0]->id, context_system::instance()->id);

        self::setUser($users[0]);

        $certification1 = $this->generator->generate_certification($params);
        $certificationid = $certification1->get('id');

        $this->generator->allocate_users_to_certification($certification1->get('id'), array_column($users, 'id'));

        $exportid = $this->wpgenerator->perform_export(certifications_exporter::class, [
            certifications_exporter::EXPORT_INSTANCES => certifications_exporter::EXPORT_INSTANCES_ALL,
            certifications_exporter::EXPORT_CONTENT => 1,
            certifications_exporter::EXPORT_USER_ALLOCATIONS => 1,
            certifications_exporter::EXPORT_DYNAMICRULES => 0,
            certifications_exporter::EXPORT_PROGRAMS => 1,
            certifications_exporter::EXPORT_COURSE_BACKUPS => 0,
            certifications_exporter::INCLUDE_SHARED_ENTITIES => 0,
        ]);

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(certifications_importer::class, $importer);

        // We should have the selected certification in the import.
        $certifications = $importer->get_entities_in_workplace_export_file(\tool_certification\certification::TABLE);
        $this->assertCount(1, $certifications);

        // We should have the selected certification user allocation in the import.
        $allocations = $importer->get_entities_in_workplace_export_file(\tool_certification\certification_user::TABLE);
        $this->assertCount(3, $allocations);

        \tool_certification\api::archive_certification($certificationid);
        $certification1 = new \tool_certification\certification($certificationid);
        \tool_certification\api::delete_certification($certification1);

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            certifications_importer::IMPORT_CONTENT => 1,
            certifications_importer::IMPORT_INSTANCES => certifications_importer::IMPORT_INSTANCES_ALL,
            certifications_importer::IMPORT_PROGRAMS => 1,
            certifications_importer::IMPORT_USER_ALLOCATIONS => 1,
            certifications_importer::IMPORT_COURSE_BACKUPS => 0,
            certifications_importer::IMPORT_DYNAMICRULES => 0,
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(4, $logs);

        $this->assertStringStartsWith('Created new certification', $logs[0]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[1]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[2]['detail']);
        $this->assertStringStartsWith('Allocated user', $logs[3]['detail']);

        $certifications = certification::get_records();
        $this->assertEquals(\tool_certification\constants::DATE_ABSOLUTE, $certifications[0]->get('allocationstartdatetype'));
        $this->assertEquals($twomoredays, $certifications[0]->get('allocationstartdateabsolute'));
        $this->assertCount(3, \tool_certification\certification_user::get_records());
    }
}

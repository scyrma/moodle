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
 * Class export_import_test
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_wp\local\exportimport\helper;
use tool_certification\tool_wp\exporter\certifications as certifications_exporter;
use tool_certification\tool_wp\importer\certifications as certifications_importer;

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
    public function setUp() {
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
                certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification->get('id')]
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
                certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification->get('id')]
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
            certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification->get('id')]
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
            certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification->get('id')]
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
            certifications_exporter::EXPORT_SELECT_CERTIFICATIONS => [$certification->get('id')]
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
}
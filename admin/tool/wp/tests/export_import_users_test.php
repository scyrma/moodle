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
use context_user;
use core_user;
use dml_multiple_records_exception;
use lang_string;
use stdClass;
use tool_wp_generator;
use tool_tenant_generator;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\wp_imported_entity;
use tool_wp\tool_wp\exporter\users as exporter;
use tool_wp\tool_wp\importer\userfields as fieldimporter;
use tool_wp\tool_wp\importer\users as importer;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\exporter\userfields
 * @covers      \tool_wp\tool_wp\exporter\users
 * @covers      \tool_wp\tool_wp\importer\userfields
 * @covers      \tool_wp\tool_wp\importer\users
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_users_test extends advanced_testcase {

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
    }

    /**
     * Data provider for exporting single user
     *
     * @see test_export_single_user
     *
     * @return array[]
     */
    public function export_single_user_provider(): array {
        return [
            [true],
            [false],
        ];
    }

    /**
     * Test exporting single user
     *
     * @param bool $exportpicture
     * @return void
     *
     * @dataProvider export_single_user_provider
     */
    public function test_export_single_user(bool $exportpicture): void {
        // Always create the user with a picture.
        $user = $this->create_user([], true);

        // Export them!
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => $exportpicture,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_plugin_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have our test user in the import.
        $users = $importer->get_entities_in_workplace_export_file(importer::ENTITY_NAME);
        $this->assertCount(1, $users);

        /** @var wp_imported_entity $entity */
        $entity = iterator_to_array($users, false)[0];
        $this->assertEquals($user->username, $entity->get_raw_field('username'));

        $entityusericonfiles = $entity->get_raw_files(['component' => 'user', 'filearea' => 'icon']);
        if ($exportpicture) {
            $this->assertCount(3, $entityusericonfiles);
        } else {
            $this->assertEmpty($entityusericonfiles);
        }
    }

    /**
     * Test exporting single user with user profile field
     */
    public function test_export_single_user_fields(): void {
        global $DB;

        $user = $this->create_user();
        $userfield = $this->create_user_profile_field('myfield', 'My field');

        $DB->insert_record('user_info_data', (object) [
            'userid' => $user->id,
            'fieldid' => $userfield->id,
            'data' => 'Fish & chips',
            'dataformat' => 0,
        ]);

        // Export them!
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => false,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_plugin_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have our test user in the import.
        $users = $importer->get_entities_in_workplace_export_file(importer::ENTITY_NAME);
        $this->assertCount(1, $users);

        /** @var wp_imported_entity $userentity */
        $userentity = iterator_to_array($users, false)[0];
        $this->assertEquals($user->username, $userentity->get_raw_field('username'));

        // We should also have our test user profile field in the import.
        $userfields = $importer->get_entities_in_workplace_export_file(fieldimporter::ENTITY_NAME);
        $this->assertCount(1, $userfields);

        /** @var wp_imported_entity $userfieldentity */
        $userfieldentity = iterator_to_array($userfields, false)[0];
        $this->assertEquals('Fish & chips', $userfieldentity->get_raw_field('data'));
    }

    /**
     * Test exporting single user from a different tenant, when exporting users from the site
     */
    public function test_export_single_user_different_tenant(): void {
        [$othertenant, [$othertenantuser]] = $this->get_tenant_generator()->create_tenant_and_users(1);

        // Export our user from another tenant.
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$othertenantuser->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_plugin_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have our test user in the import.
        $users = $importer->get_entities_in_workplace_export_file(importer::ENTITY_NAME);
        $this->assertCount(1, $users);

        /** @var wp_imported_entity $entity */
        $entity = iterator_to_array($users, false)[0];
        $this->assertEquals($othertenantuser->username, $entity->get_raw_field('username'));
    }

    /**
     * Test exporting all users from the site
     */
    public function test_export_all_users(): void {
        $user = $this->create_user(['username' => 'tenantuser']);
        $this->get_tenant_generator()->allocate_user($user->id, tenancy::get_default_tenant_id());

        // Create some users in another tenant.
        [, $tenanttwousers] = $this->get_tenant_generator()->create_tenant_and_users(2);

        // Export all users.
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => false,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_plugin_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have our test user in the import, plus the admin and the two users in the second tenant.
        $users = $importer->get_entities_in_workplace_export_file(importer::ENTITY_NAME);
        $this->assertCount(4, $users);

        $usernames = array_map(static function(wp_imported_entity $entity): string {
            return $entity->get_raw_field('username');
        }, iterator_to_array($users, false));

        $this->assertEqualsCanonicalizing([
            'admin',
            $user->username,
            $tenanttwousers[0]->username,
            $tenanttwousers[1]->username,
        ], $usernames);
    }

    /**
     * Test exporting all users from a tenant
     */
    public function test_export_tenant_users(): void {
        $user = $this->create_user(['username' => 'tenantuser']);
        $tenantid = tenancy::get_default_tenant_id();
        $this->get_tenant_generator()->allocate_user($user->id, $tenantid);

        // Create some users in another tenant.
        $this->get_tenant_generator()->create_tenant_and_users(2);

        // Export users from the current tenant.
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => false,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_TENANT,
            'exportertenant' => $tenantid,
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Prepare to import the export we previously created.
        $importid = $this->get_plugin_generator()->prepare_import_from_export_id($export->get('id'));

        $importers = (new import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(importer::class, $importer);

        // We should have our test user in the import, plus the admin.
        $users = $importer->get_entities_in_workplace_export_file(importer::ENTITY_NAME);
        $this->assertCount(2, $users);

        $usernames = array_map(static function(wp_imported_entity $entity): string {
            return $entity->get_raw_field('username');
        }, iterator_to_array($users, false));
        $this->assertEqualsCanonicalizing(['admin', 'tenantuser'], $usernames);
    }

    /**
     * Test importing single user
     */
    public function test_import_single_user(): void {
        $user = $this->create_user([
            'username' => 'user1',
            'email' => 'user@example.com',
        ], true);

        // Export them!
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => true,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Allow same e-mail, to avoid conflict.
        set_config('allowaccountssameemail', 1);

        $importid = $this->get_plugin_generator()->perform_import_from_export_id($export->get('id'), [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            importer::IMPORT_PICTURE => true,
            helper::get_importer_setting_name_for_conflict_form(importer::ENTITY_NAME, 'usernameconflict', 'action') => 'increment',
        ]);

        // Conflicts.
        $conflicts = $this->get_plugin_generator()->get_import_conflict_review($importid);
        $this->assertEquals([
            ['An instance with the same \'username\' already exists', 'Add a numeric suffix to the \'username\' field'],
        ], $conflicts);

        // Analyse logs.
        $logs = $this->get_plugin_generator()->get_import_logs($importid);
        $this->assertCount(1, $logs);

        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[0];
        $this->assertEquals('Created user \'' . fullname($user) . '\'', $detail);
        $this->assertEmpty($errors);
        $this->assertEquals([
            'Changed field \'username\' from \'user1\' to \'user2\'',
        ], $notices);

        // Get our imported user.
        $newuser = core_user::get_user_by_username('user2');
        $this->assertInstanceOf(stdClass::class, $newuser);

        // We should have f1, f2 & f3 user icons.
        $usericonfiles = get_file_storage()->get_area_files(context_user::instance($newuser->id)->id, 'user', 'icon',
            0, 'filename', false);
        $this->assertCount(3, $usericonfiles);

        $f1 = reset($usericonfiles);
        $this->assertEquals($newuser->picture, $f1->get_id());
    }

    /**
     * Test importing single user where "Allow accounts with same email" is set
     */
    public function test_import_single_user_sameemail(): void {
        set_config('allowaccountssameemail', true);

        $user = $this->create_user([
            'username' => 'user1',
            'email' => 'user@example.com',
        ], true);

        // Export them!
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => false,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        $importid = $this->get_plugin_generator()->perform_import_from_export_id($export->get('id'), [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            importer::IMPORT_PICTURE => true,
            helper::get_importer_setting_name_for_conflict_form(importer::ENTITY_NAME, 'usernameconflict', 'action') => 'increment',
        ]);

        // Conflicts.
        $conflicts = $this->get_plugin_generator()->get_import_conflict_review($importid);
        $this->assertEquals([
            ['An instance with the same \'username\' already exists', 'Add a numeric suffix to the \'username\' field'],
        ], $conflicts);

        // Analyse logs.
        $logs = $this->get_plugin_generator()->get_import_logs($importid);
        $this->assertCount(1, $logs);

        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[0];
        $this->assertEquals('Created user \'' . fullname($user) . '\'', $detail);
        $this->assertEmpty($errors);
        $this->assertEquals([
            'Changed field \'username\' from \'user1\' to \'user2\'',
        ], $notices);

        // Confirm we get a duplicate record exception when trying to retrieve user by email.
        $this->expectException(dml_multiple_records_exception::class);
        core_user::get_user_by_email($user->email, '*', null, MUST_EXIST);
    }

    /**
     * Test importing users whose 'mnethostid' field differs from that of the local site
     */
    public function test_import_single_user_different_mnethost_value(): void {
        $user = $this->create_user([
            'username' => 'user1',
            'email' => 'user@example.com',
        ]);

        // Export them!
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Now delete the test user so we don't have to deal with username/email conflicts.
        $username = $user->username;
        $userfullname = fullname($user);
        user_delete_user($user);

        // Change the site mnet_localhost_id to simulate importing a user whose own value doesn't match the site.
        set_config('mnet_localhost_id', 42);

        $conflictresolutionsetting = helper::get_importer_setting_name_for_conflict_form(importer::ENTITY_NAME,
            'mnethostconflict', 'action');
        $importid = $this->get_plugin_generator()->perform_import_from_export_id($export->get('id'), [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            $conflictresolutionsetting => 'matchlocal',
        ]);

        // Conflicts.
        $conflicts = $this->get_plugin_generator()->get_import_conflict_review($importid);
        $this->assertEquals([
            ['MNet host value differs', 'Update value to match site'],
        ], $conflicts);

        // Analyse logs.
        $logs = $this->get_plugin_generator()->get_import_logs($importid);
        $this->assertCount(1, $logs);

        ['detail' => $detail, 'errors' => $errors, 'notices' => $notices] = $logs[0];
        $this->assertEquals("Created user '{$userfullname}'", $detail);
        $this->assertEmpty($errors);
        $this->assertEquals([
            'Changed field \'mnethostid\' from \'1\' to \'42\'',
        ], $notices);

        // Confirm our imported user 'mnethostid' field was changed to match the site.
        $newuser = core_user::get_user_by_username($username);
        $this->assertEquals(42, $newuser->mnethostid);
    }

    /**
     * Test importing users whose 'lang' field contains language not available in installation.
     */
    public function test_import_single_user_different_lang_value(): void {
        global $DB;
        $user = $this->create_user([
            'username' => 'user1',
            'email' => 'user@example.com',
        ]);

        // Modify user lang.
        $DB->update_record('user', ['id' => $user->id, 'lang' => 'nonexisting']);

        // Export them!
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Now delete the test user so we don't have to deal with username/email conflicts.
        $username = $user->username;
        $userfullname = fullname($user);
        user_delete_user($user);

        $resolutionaction = helper::get_importer_setting_name_for_conflict_form(importer::ENTITY_NAME,
            'missinglangerror', 'action');
        $resolutionlang = helper::get_importer_setting_name_for_conflict_form(importer::ENTITY_NAME,
            'missinglangerror', 'lang');
        $importid = $this->get_plugin_generator()->perform_import_from_export_id($export->get('id'), [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            $resolutionaction => 'useselected',
            $resolutionlang => 'en',
        ]);

        // Conflicts.
        $conflicts = $this->get_plugin_generator()->get_import_conflict_review($importid);
        $this->assertEquals([
            ['User language is missing in the system', 'Use selected language'],
        ], $conflicts);

        // Analyse logs.
        $logs = $this->get_plugin_generator()->get_import_logs($importid);
        $this->assertCount(1, $logs);

        ['detail' => $detail, 'errors' => $errors, 'notices' => $notices] = $logs[0];
        $this->assertEquals("Created user '{$userfullname}'", $detail);
        $this->assertEmpty($errors);
        $this->assertEquals([
            'Changed field \'lang\' from \'nonexisting\' to \'en\'',
        ], $notices);

        // Confirm our imported user 'lang' field was changed to match the site.
        $newuser = core_user::get_user_by_username($username);
        $this->assertEquals('en', $newuser->lang);
    }

    /**
     * Test import user when site user limit is reached.
     */
    public function test_import_single_user_when_site_user_limit_enabled() {
        $user = $this->create_user([
            'username' => 'user1',
            'email' => 'user@example.com',
        ], true);

        // Export them!
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => false,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Set a limit site wide.
        set_config('userlimitenabled', 1);
        set_config('userlimit', 1);
        set_config('allowaccountssameemail', 1);

        $importid = $this->get_plugin_generator()->perform_import_from_export_id($export->get('id'), [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            importer::IMPORT_PICTURE => true,
            helper::get_importer_setting_name_for_conflict_form(importer::ENTITY_NAME, 'usernameconflict', 'action') => 'increment',
        ]);

        $logs = $this->get_plugin_generator()->get_import_logs($importid);
        $this->assertCount(1, $logs);
        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[0];
        $this->assertEmpty($errors);
        $this->assertStringContainsString("Exception: User accounts limit reached", $detail);
    }

    /**
     * Test import user when tenant user limit is reached.
     */
    public function test_import_single_user_when_tenant_user_limit_enabled() {
        $user = $this->create_user([
            'username' => 'user1',
            'email' => 'user@example.com',
        ], true);

        // Export them!
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => false,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Set a limit site wide.
        set_config('tool_tenant_userlimitenabled', 1);
        set_config('tool_tenant_userlimit', 1);
        set_config('allowaccountssameemail', 1);

        $importid = $this->get_plugin_generator()->perform_import_from_export_id($export->get('id'), [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            importer::IMPORT_PICTURE => true,
            helper::get_importer_setting_name_for_conflict_form(importer::ENTITY_NAME, 'usernameconflict', 'action') => 'increment',
        ]);

        $logs = $this->get_plugin_generator()->get_import_logs($importid);
        $this->assertCount(1, $logs);
        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[0];
        $this->assertEmpty($errors);
        $this->assertStringContainsString("Exception: User accounts limit reached", $detail);
    }
    /**
     * Test import form settings validation
     */
    public function test_import_settings_form(): void {
        $user = $this->create_user([]);

        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => false,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $importid = $this->get_plugin_generator()->prepare_import_from_export_id($exportid);

        // Submitting an import form without a required field.
        $form = $this->get_plugin_generator()->submit_import_form($importid, 3, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_MANUAL,
        ]);
        $this->assertFalse($form->is_validated());
        $this->assertEquals([
            importer::IMPORT_SELECT_MANUAL => new lang_string('required'),
        ], $form->get_quick_form()->_errors);

        $form = $this->get_plugin_generator()->submit_import_form($importid, 3, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_MANUAL,
            importer::IMPORT_SELECT_MANUAL => [$user->id],
        ]);
        $this->assertTrue($form->is_validated());

        $data = $form->get_data();
        $this->assertEquals(1, $data->{importer::IMPORT_PROFILE});
        $this->assertEquals(1, $data->{importer::IMPORT_PICTURE});
        $this->assertEquals(importer::IMPORT_INSTANCES_MANUAL, $data->{importer::IMPORT_INSTANCES});
        $this->assertEquals([$user->id], $data->{importer::IMPORT_SELECT_MANUAL});
    }

    /**
     * Test importing single user with user profile field
     */
    public function test_import_single_user_fields(): void {
        global $DB;

        $user = $this->create_user();
        $userfield = $this->create_user_profile_field('myfield', 'My field');

        $DB->insert_record('user_info_data', (object) [
            'userid' => $user->id,
            'fieldid' => $userfield->id,
            'data' => 'Fish & chips',
            'dataformat' => 0,
        ]);

        // Export them!
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => false,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $importid = $this->get_plugin_generator()->prepare_import_from_export_id($exportid);

        // Submitting an import form without a required field.
        $form = $this->get_plugin_generator()->submit_import_form($importid, 3, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_MANUAL,
        ]);
        $this->assertFalse($form->is_validated());
        $this->assertEquals([
            importer::IMPORT_SELECT_MANUAL => new lang_string('required'),
        ], $form->get_quick_form()->_errors);

        $form = $this->get_plugin_generator()->submit_import_form($importid, 3, [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_MANUAL,
            importer::IMPORT_SELECT_MANUAL => [$user->id],
        ]);
        $this->assertTrue($form->is_validated());

        $data = $form->get_data();
        $this->assertEquals(1, $data->{importer::IMPORT_PROFILE});
        $this->assertEquals(1, $data->{importer::IMPORT_PICTURE});
        $this->assertEquals(importer::IMPORT_INSTANCES_MANUAL, $data->{importer::IMPORT_INSTANCES});
        $this->assertEquals([$user->id], $data->{importer::IMPORT_SELECT_MANUAL});

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Now delete the test user so we don't have to deal with conflicts.
        $userid = $user->id;
        $username = $user->username;
        $userfullname = fullname($user);
        user_delete_user($user);

        $importid = $this->get_plugin_generator()->perform_import_from_export_id($export->get('id'), [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);
        $importmanager = new import_manager($importid);

        // Confirm mapping data was added.
        $usermappingdata = $importmanager->get_raw_mapping_from_workplace_export_file('user', $userid);
        $this->assertIsArray($usermappingdata);
        $this->assertEquals($userid, $usermappingdata['id']);

        $userfieldmappingdata = $importmanager->get_raw_mapping_from_workplace_export_file('userfield', $userfield->id);
        $this->assertIsArray($userfieldmappingdata);
        $this->assertEquals($userfield->id, $userfieldmappingdata['id']);

        // Conflicts.
        $conflicts = $this->get_plugin_generator()->get_import_conflict_review($importmanager->get_import_id());
        $this->assertEmpty($conflicts);

        // Analyse logs.
        $logs = $this->get_plugin_generator()->get_import_logs($importmanager->get_import_id());
        $this->assertCount(1, $logs);

        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[0];
        $this->assertEquals("Created user '{$userfullname}'", $detail);
        $this->assertEmpty($errors);
        $this->assertEmpty($notices);

        // Confirm our new user was created.
        $newuser = core_user::get_user_by_username($username);
        $this->assertInstanceOf(stdClass::class, $newuser);

        $newuserfielddata = $DB->get_field('user_info_data', 'data', ['fieldid' => $userfield->id, 'userid' => $newuser->id]);
        $this->assertEquals('Fish & chips', $newuserfielddata);
    }

    /**
     * Test importing single user with user profile field that doesn't exist
     */
    public function test_import_single_user_fields_missing(): void {
        global $DB;

        $user = $this->create_user();
        $userfield = $this->create_user_profile_field('myfield', 'My field');

        $DB->insert_record('user_info_data', (object) [
            'userid' => $user->id,
            'fieldid' => $userfield->id,
            'data' => 'Fish & chips',
            'dataformat' => 0,
        ]);

        // Export them!
        $exportid = $this->get_plugin_generator()->perform_export(exporter::class, [
            exporter::EXPORT_PICTURE => false,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_MANUAL,
            exporter::EXPORT_SELECT_MANUAL => [$user->id],
        ]);

        $export = new export_persistent($exportid);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Now delete the test user so we don't have to deal with user conflicts.
        $username = $user->username;
        $userfullname = fullname($user);
        user_delete_user($user);

        // Rename our userfield so it's no longer matched by the mapper.
        $DB->set_field('user_info_field', 'shortname', 'somethingelse', ['id' => $userfield->id]);

        $importid = $this->get_plugin_generator()->perform_import_from_export_id($export->get('id'), [
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
            helper::get_setting_name_for_conflict_form('userfield', 'action') => 'skip',
        ]);

        // Conflicts.
        $conflicts = $this->get_plugin_generator()->get_import_conflict_review($importid);
        $this->assertEquals([
            ['Some user profile fields do not exist', 'Do not import'],
        ], $conflicts);

        // Analyse logs.
        $logs = $this->get_plugin_generator()->get_import_logs($importid);
        $this->assertCount(2, $logs);

        // First log is the user creation.
        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[0];
        $this->assertEquals("Created user '{$userfullname}'", $detail);
        $this->assertEmpty($errors);
        $this->assertEmpty($notices);

        // Second log is missing user profile field.
        list('detail' => $detail, 'errors' => $errors, 'notices' => $notices) = $logs[1];
        $this->assertEquals('Couldn\'t import user profile field \'Fish &amp; chips\'', $detail);
        $this->assertEquals([
            'User profile field \'My field\' (\'myfield\') was not found',
        ], $errors);
        $this->assertEmpty($notices);

        // Confirm our new user was created.
        $newuser = core_user::get_user_by_username($username);
        $this->assertInstanceOf(stdClass::class, $newuser);
    }

    /**
     * Helper method for creating user, optionally setting their profile picture
     *
     * @param array $record
     * @param bool $userpicture
     * @return stdClass
     */
    private function create_user(array $record = [], bool $userpicture = false): stdClass {
        global $CFG, $USER;

        $user = $this->getDataGenerator()->create_user($record);

        // Set users profile picture using test fixture file.
        if ($userpicture) {
            $file = get_file_storage()->create_file_from_pathname([
                'contextid' => context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => file_get_unused_draft_itemid(),
                'filepath' => '/',
                'filename' => 'profile.png',
            ], "{$CFG->dirroot}/lib/tests/fixtures/gd-logo.png");

            $user->imagefile = $file->get_itemid();
            core_user::update_picture($user);
        }

        return core_user::get_user($user->id);
    }

    /**
     * Helper method to create a new user profile field
     *
     * @param string $shortname
     * @param string $name
     * @return stdClass
     */
    private function create_user_profile_field(string $shortname, string $name): stdClass {
        global $DB;

        // Create a category for our field.
        $categoryid = $DB->insert_record('user_info_category', (object) [
            'name' => 'My category',
            'sortorder' => 1,
        ]);

        $field = (object) [
            'categoryid' => $categoryid,
            'shortname' => $shortname,
            'name' => $name,
            'datatype' => 'text',
        ];

        $field->id = $DB->insert_record('user_info_field', $field);

        return $field;
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
}

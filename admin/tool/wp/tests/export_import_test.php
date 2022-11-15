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
 * File containing tests for export/import API.
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

use advanced_testcase;
use context_system;
use moodle_exception;
use moodle_url;
use tool_organisation_generator;
use tool_wp_generator;
use tool_tenant_generator;
use tool_organisation\tool_wp\exporter\orgstructure as exporter;
use tool_organisation\tool_wp\importer\orgstructure as importer;
use tool_wp\event\export_created;
use tool_wp\event\export_updated;
use tool_wp\event\import_created;
use tool_wp\event\import_updated;
use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\import_detail_persistent;
use tool_wp\local\exportimport\import_persistent;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the export/import API.
 *
 * @package    tool_wp
 * @covers     \tool_wp\local\exportimport\export
 * @covers     \tool_wp\local\exportimport\import
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_test extends advanced_testcase {
    /** @var tool_wp_generator */
    protected $wpgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
    }

    public function test_array_to_xml() {
        $data = [];
        $result = \tool_wp\local\exportimport\helper::array_to_xml('myentity', $data);
        $this->assertEquals('<myentity/>', $result);

        // Simple values.
        $data = ['property1' => 'valuestring', 'property2' => 123, 'property3' => true, 'property4' => false, 'property5' => 0];
        $result = \tool_wp\local\exportimport\helper::array_to_xml('myentity', $data);
        $expected = <<<EOL
<myentity>
  <property1>valuestring</property1>
  <property2>123</property2>
  <property3>1</property3>
  <property4>0</property4>
  <property5>0</property5>
</myentity>
EOL;
        $this->assertEquals($expected, $result);

        // Null/empty handling.
        $data = ['property4' => null, 'property5' => '', 'property6' => [], 'property7' => ' '];
        $result = \tool_wp\local\exportimport\helper::array_to_xml('myentity', $data);
        $expected = <<<EOL
<myentity>
  <property4>$@NULL@$</property4>
  <property5></property5>
  <property6/>
  <property7> </property7>
</myentity>
EOL;
        $this->assertEquals($expected, $result);

        // Nested arrays.
        $data = ['id' => 1, 'name' => 'Myname', '_files' => [
            ['id' => '2', 'name' => 'file1'],
            ['id' => '3', 'name' => 'file2']
        ]];
        $result = \tool_wp\local\exportimport\helper::array_to_xml('myentity', $data);
        $expected = <<<EOL
<myentity>
  <id>1</id>
  <name>Myname</name>
  <_files>
    <_file>
      <id>2</id>
      <name>file1</name>
    </_file>
    <_file>
      <id>3</id>
      <name>file2</name>
    </_file>
  </_files>
</myentity>
EOL;
        $this->assertEquals($expected, $result);
    }

    public function test_xml_to_array() {
        // Simple values.
        $xml = <<<EOL
<myentity>
  <property1>valuestring</property1>
  <property2>123</property2>
  <property3>1</property3>
  <property4>0</property4>
  <property5>0</property5>
</myentity>
EOL;
        $data = \tool_wp\local\exportimport\helper::xml_to_array($xml);
        $expected = ['property1' => 'valuestring', 'property2' => '123',
            'property3' => '1', 'property4' => '0', 'property5' => '0'];
        $this->assertEquals($expected, $data);

        // Empty/null handling.
        $xml = <<<EOL
<myentity>
  <property6>$@NULL@$</property6>
  <property7></property7>
  <property8/>
  <property9> </property9>
</myentity>
EOL;
        $data = \tool_wp\local\exportimport\helper::xml_to_array($xml);
        $expected = ['property6' => null, 'property7' => '', 'property8' => '', 'property9' => ' '];
        $this->assertEquals($expected, $data);

        // IMPORTANT! xml_to_array can not make distinction between empty string and empty arrays.
        // It assumes empty strings.

        // Nested arrays.
        $xml = <<<EOL
<myentity>
  <id>1</id>
  <name>Myname</name>
  <_files>
    <_file>
      <id>2</id>
      <name>file1</name>
    </_file>
    <_file>
      <id>3</id>
      <name>file2</name>
    </_file>
  </_files>
</myentity>
EOL;
        $data = \tool_wp\local\exportimport\helper::xml_to_array($xml);
        $expected = ['id' => '1', 'name' => 'Myname', '_files' => [
            ['id' => '2', 'name' => 'file1'],
            ['id' => '3', 'name' => 'file2']
        ]];
        $this->assertEquals($expected, $data);
    }

    /**
     * Test export manager schedule_export method
     *
     * @return void
     */
    public function test_export_manager_schedule_export() {
        if (!class_exists(exporter::class)) {
            $this->markTestSkipped('Exporter \'' . exporter::class . '\' doesn\'t exist, skipping test');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        // Catch all triggered events.
        $sink = $this->redirectEvents();
        $exportid = export_manager::schedule_export([
            'exporter' => exporter::class,
        ]);

        $events = $sink->get_events();
        $sink->close();

        // Ensure persistent was created.
        $export = export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(exporter::class, $export->get('exporter'));
        $this->assertEquals(helper::STATUS_SCHEDULED, $export->get('status'));

        // Ensure export created event was triggered.
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(export_created::class, $event);
        $this->assertEquals($export->get('id'), $event->objectid);
        $this->assertEquals($export->get('createdby'), $event->relateduserid);
        $this->assertEquals($export->get('exporter'), $event->other['exporter']);
        $this->assertEquals($export->get('entrypoint'), $event->other['entrypoint']);
        $this->assertEquals($export->get('entrypointid'), $event->other['entrypointid']);
        $this->assertEquals($export->get('status'), $event->other['status']);
    }

    /**
     * Test export manager do_export method
     *
     * @return void
     */
    public function test_export_manager_do_export() {
        if (!class_exists(exporter::class)) {
            $this->markTestSkipped('Exporter \'' . exporter::class . '\' doesn\'t exist, skipping test');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        $exportid = export_manager::schedule_export([
            'exporter' => exporter::class,
        ], false);

        // Catch all triggered events/sent messages.
        $eventsink = $this->redirectEvents();
        $messagesink = $this->redirectMessages();

        (new export_manager($exportid))->perform_export();

        $messages = $messagesink->get_messages();
        $messagesink->close();

        $events = $eventsink->get_events();
        $eventsink->close();

        // Ensure two import_updated events where triggered (there may be a notification_sent event under some DB's too).
        $this->assertGreaterThanOrEqual(2, $events);

        $this->assertInstanceOf(export_updated::class, $events[0]);
        $this->assertEquals(helper::STATUS_IN_PROGRESS, $events[0]->other['status']);

        $this->assertInstanceOf(export_updated::class, $events[1]);
        $this->assertEquals(helper::STATUS_DONE, $events[1]->other['status']);

        // Verify the content of the sent message.
        $this->assertCount(1, $messages);
        $message = reset($messages);

        $this->assertEquals(get_admin()->id, $message->useridto);
        $this->assertEquals('Export completed', $message->subject);
        $this->assertMatchesRegularExpression('/^Your export was completed on.*Status: Success/ms', $message->fullmessage);

        $importurl = new moodle_url('/admin/tool/wp/export.php', ['exportid' => $exportid]);
        $this->assertEquals((string)$importurl, $message->contexturl);
    }

    /**
     * Test import manager create_import_from_draftfile method
     *
     * @return void
     */
    public function test_import_manager_create_import_from_draftfile() {
        if (!class_exists(importer::class)) {
            $this->markTestSkipped('Importer \'' . importer::class . '\' doesn\'t exist, skipping test');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        // Catch all triggered events.
        $sink = $this->redirectEvents();
        $manager = import_manager::create_import_from_draftfile([]);

        $events = $sink->get_events();
        $sink->close();

        // Ensure persistent was created.
        $import = import_persistent::get_record(['id' => $manager->get_import_id()]);
        $this->assertEquals(helper::STATUS_CREATED, $import->get('status'));

        // Ensure import created event was triggered.
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(import_created::class, $event);
        $this->assertEquals($import->get('id'), $event->objectid);
        $this->assertEquals($import->get('createdby'), $event->relateduserid);
        $this->assertEquals($import->get('importer'), $event->other['importer']);
        $this->assertEquals($import->get('entrypoint'), $event->other['entrypoint']);
        $this->assertEquals($import->get('entrypointid'), $event->other['entrypointid']);
        $this->assertEquals($import->get('status'), $event->other['status']);
    }

    /**
     * Test import manager schedule_import method
     *
     * @return void
     */
    public function test_import_manager_schedule_import() {
        if (!class_exists(importer::class)) {
            $this->markTestSkipped('Importer \'' . importer::class . '\' doesn\'t exist, skipping test');
        }

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // Create an import.
        $import = (new import_persistent(0, (object)[
            'createdby' => $user->id,
            'importer' => importer::class,
        ]))->create();

        // Catch all triggered events.
        $sink = $this->redirectEvents();
        (new import_manager(0, $import))->schedule_import();

        $events = $sink->get_events();
        $sink->close();

        // Ensure import created event was triggered.
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(import_updated::class, $event);
        $this->assertEquals($import->get('id'), $event->objectid);
        $this->assertEquals($import->get('createdby'), $event->relateduserid);
        $this->assertEquals($import->get('importer'), $event->other['importer']);
        $this->assertEquals($import->get('entrypoint'), $event->other['entrypoint']);
        $this->assertEquals($import->get('entrypointid'), $event->other['entrypointid']);
        $this->assertEquals(helper::STATUS_SCHEDULED, $event->other['status']);
    }

    /**
     * Test import manager do_import method
     *
     * @return void
     */
    public function test_import_manager_do_import() {
        if (!class_exists(importer::class)) {
            $this->markTestSkipped('Importer \'' . importer::class . '\' doesn\'t exist, skipping test');
        }

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // Create an import.
        $import = (new import_persistent(0, (object)[
            'createdby' => $user->id,
            'importer' => importer::class,
        ]))->create();

        ($manager = new import_manager(0, $import))->schedule_import(false);

        // Catch all triggered events/sent messages.
        $eventsink = $this->redirectEvents();
        $messagesink = $this->redirectMessages();

        $manager->perform_import();

        $messages = $messagesink->get_messages();
        $messagesink->close();

        $events = $eventsink->get_events();
        $eventsink->close();

        // Ensure two import_updated events where triggered (there may be a notification_sent event under some DB's too).
        $this->assertGreaterThanOrEqual(2, $events);

        $this->assertInstanceOf(import_updated::class, $events[0]);
        $this->assertEquals(helper::STATUS_IN_PROGRESS, $events[0]->other['status']);

        $this->assertInstanceOf(import_updated::class, $events[1]);
        $this->assertEquals(helper::STATUS_DONE, $events[1]->other['status']);

        // Verify the content of the sent message.
        $this->assertCount(1, $messages);
        $message = reset($messages);

        $this->assertEquals($user->id, $message->useridto);
        $this->assertEquals('Import completed', $message->subject);
        $this->assertMatchesRegularExpression('/^Your import was completed on.*Status: Success/ms', $message->fullmessage);

        $importurl = new moodle_url('/admin/tool/wp/import.php', ['importid' => $import->get('id')]);
        $this->assertEquals((string)$importurl, $message->contexturl);
    }

    /**
     * Test format_logged_exception returns appropriate details according to current debugging level
     */
    public function test_import_manager_format_logged_exception(): void {
        if (!class_exists(importer::class)) {
            $this->markTestSkipped('Importer \'' . importer::class . '\' doesn\'t exist, skipping test');
        }

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // Create an import.
        $import = (new import_persistent(0, (object)[
            'createdby' => $user->id,
            'importer' => importer::class,
        ]))->create();

        $manager = new import_manager(0, $import);
        $manager->log_exception(new moodle_exception('ohno'));

        // The top of the stacktrace (the calling method), should be this one.
        $expectedtopofstack = sprintf('%s->%s()', __CLASS__, __FUNCTION__);

        // Lower debugging levels shouldn't get the full stacktrace.
        set_debugging(DEBUG_NONE);
        $logs = $manager->get_import_logs();
        $detail = reset($logs)['detail'];

        $this->assertStringStartsWith('Exception: error/ohno', $detail);
        $this->assertStringNotContainsString($expectedtopofstack, $detail);

        // Now we should get the stacktrace.
        set_debugging(DEBUG_DEVELOPER);
        $logs = $manager->get_import_logs();
        $detail = reset($logs)['detail'];

        $this->assertStringStartsWith('Exception: error/ohno', $detail);
        $this->assertStringContainsString($expectedtopofstack, $detail);
    }

    /**
     * Test switching tenant during export and import
     */
    public function test_tenant_switch() {
        global $DB;
        if (!class_exists('tool_organisation\tool_wp\exporter\orgstructure')) {
            $this->markTestSkipped('Skipping the test, tool_organisation is not available');
        }

        $this->resetAfterTest();
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant1 = $tenantgenerator->create_tenant();
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $df = $generator->create_department(['tenantid' => $tenant1->id]);
        $generator->create_department(['parentid' => $df->id]);

        // Set admin user who has access to all tenants but belongs to the default tenant.
        $this->setAdminUser();

        $settings = [
            \tool_organisation\tool_wp\exporter\orgstructure::EXPORT_INSTANCES =>
                \tool_organisation\tool_wp\exporter\orgstructure::EXPORT_INSTANCES_DEPARTMENTS
        ];
        $data = ['exporter' => \tool_organisation\tool_wp\exporter\orgstructure::class] + $settings;

        // Switch to another tenant when scheduling export and then switch back.
        \tool_tenant\tenancy::set_switched_tenant_id($tenant1->id);
        $exportid = \tool_wp\local\exportimport\export_manager::schedule_export($data);
        \tool_tenant\tenancy::set_switched_tenant_id(\tool_tenant\tenancy::get_default_tenant_id());

        // Run export.
        $this->runAdhocTasks(\tool_wp\task\export_adhoc_task::class);

        // Import into another tenant.
        $tenant2 = $tenantgenerator->create_tenant([]);

        // Now let's create an importer from the exported file.
        $importmanager = \tool_wp\local\exportimport\import_manager::create_import_from_exportfile([
            'importer' => \tool_organisation\tool_wp\importer\orgstructure::class
        ], $exportid);
        $settings = [\tool_organisation\tool_wp\importer\orgstructure::IMPORT_INSTANCES =>
            \tool_organisation\tool_wp\importer\orgstructure::IMPORT_INSTANCES_ALL];
        $importmanager->save_settings($settings);

        // Switch to tenant2 while scheduling import.
        \tool_tenant\tenancy::set_switched_tenant_id($tenant2->id);
        $importmanager->schedule_import();
        \tool_tenant\tenancy::set_switched_tenant_id(\tool_tenant\tenancy::get_default_tenant_id());

        // Run import.
        $this->runAdhocTasks(\tool_wp\task\import_adhoc_task::class);

        // Make sure the records were imported into the tenant2. This checks both that they were exported
        // and that they were imported in the correct tenant.
        $newdepts = $DB->get_records('tool_organisation_department', ['tenantid' => $tenant2->id]);
        $this->assertCount(2, $newdepts);
    }

    /**
     * Test that all exports and imports are deleted when tenant is deleted
     *
     * This test also tests the generator functions to perform exports and imports.
     */
    public function test_delete_on_tenant_delete() {
        global $DB, $CFG;
        if (!class_exists('tool_organisation\tool_wp\exporter\orgstructure')) {
            $this->markTestSkipped('Skipping the test, tool_organisation is not available');
        }

        $this->resetAfterTest();
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        list($tenant1, $users1) = $tenantgenerator->create_tenant_and_users(2);

        // Set user from the tenant, create one export and two imports.
        // This test also tests the generator functions to perform exports and imports.
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$users1[0]->id], $tenant1->id);
        $this->setUser($users1[0]);
        $exportid = $this->wpgenerator->perform_export(\tool_organisation\tool_wp\exporter\orgstructure::class);
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid);
        $importid2 = $this->wpgenerator->perform_import_from_file(
            $CFG->dirroot.'/admin/tool/organisation/tests/fixtures/fullexport.zip');

        // Now we have one export and two imports in the db.
        $this->assertEquals(1, count($DB->get_records('tool_wp_export', ['tenantid' => $tenant1->id])));
        $this->assertEquals(2, count($DB->get_records('tool_wp_import', ['tenantid' => $tenant1->id])));

        // Delete tenant.
        (new \tool_tenant\manager())->archive_tenant($tenant1->id);
        (new \tool_tenant\manager())->delete_tenant($tenant1->id);

        // Now we don't have any exports and imports in the deleted tenant.
        $this->assertEquals(0, count($DB->get_records('tool_wp_export', ['tenantid' => $tenant1->id])));
        $this->assertEquals(0, count($DB->get_records('tool_wp_import', ['tenantid' => $tenant1->id])));
    }

    /**
     * Test for context mapper
     */
    public function test_context_mapper() {
        $systemcontext = context_system::instance();

        // Check the mapper class.
        $mapper = helper::find_mapper_for_entity('context', helper::get_all_mappers());
        $this->assertInstanceOf(\tool_wp\tool_wp\mapper\context::class, $mapper);
        $data = $mapper->get_mapping_data_for_workplace_export($systemcontext->id);
        $this->assertEquals(CONTEXT_SYSTEM, $data['contextlevel']);
        unset($data['id']);

        // Different kinds of valid mappings.
        $result = $this->wpgenerator->locate_mapping('context', $data);
        $this->assertEquals([$systemcontext->id, [], [], true], $result);

        $result = $this->wpgenerator->locate_mapping('context', ['contextlevel' => CONTEXT_SYSTEM]);
        $this->assertEquals([$systemcontext->id, [], [], true], $result);

        // TODO more tests for conflicts, notices, errors etc.
    }

    /**
     * Test calling get_mapping method several times.
     *
     * We want to make sure that:
     * a) IGNORE_MISSING does not raise errors
     * b) calling MUST_EXIST after IGNORE_MISSING raises error
     * c) calling multiple times within one import process does not query the database
     *
     * @return void
     */
    public function test_get_mapping() {
        global $CFG;
        if (!class_exists(importer::class)) {
            $this->markTestSkipped('Importer \'' . importer::class . '\' doesn\'t exist, skipping test');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create an import. This import includes a mapping for user with id 134004 and tenant with id 510000.
        $importid = $this->wpgenerator->perform_import_from_file(
            $CFG->dirroot.'/admin/tool/organisation/tests/fixtures/fullexport.zip');
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importmanager->set_mapping('tool_tenant', 7, \tool_tenant\tenancy::get_default_tenant_id());

        // First call get_mapping() with IGNORE_MISSING. No errors should be raised, detail should be validated.
        $logpersistent = new \tool_wp\local\exportimport\import_detail_persistent();
        $importmanager->set_current_import_detail_persistent($logpersistent);
        $m = $importmanager->get_mapping('user', 83, IGNORE_MISSING);
        $this->assertNull($m);
        $this->assertTrue($logpersistent->is_validated());
        $errors = $logpersistent->get_formatted_errors(null);
        $this->assertEmpty($errors);

        // The string we expect to receive back from the user mapper.
        $usererrorstring = 'Could not find user \'user77\' (\'user77@example.com\') in current tenant';

        // Call the same get_mapping() but with MUST_EXIST. Error should be raised, detail should not be validated.
        $logpersistent = new \tool_wp\local\exportimport\import_detail_persistent();
        $importmanager->set_current_import_detail_persistent($logpersistent);
        $m = $importmanager->get_mapping('user', 83, MUST_EXIST);
        $this->assertNull($m);
        $this->assertFalse($logpersistent->is_validated());
        $errors = $logpersistent->get_formatted_errors(null);
        $this->assertCount(1, $errors);
        $this->assertEquals($usererrorstring, $errors[0]);

        // Call it again. The same error should be raised again, detail should not be validated.
        $logpersistent = new \tool_wp\local\exportimport\import_detail_persistent();
        $importmanager->set_current_import_detail_persistent($logpersistent);
        $m = $importmanager->get_mapping('user', 83, MUST_EXIST);
        $this->assertNull($m);
        $this->assertFalse($logpersistent->is_validated());
        $errors = $logpersistent->get_formatted_errors(null);
        $this->assertCount(1, $errors);
        $this->assertEquals($usererrorstring, $errors[0]);

        // Now create the user, make sure the get_mapping() does not try to retrieve again and still returns null.
        $user = $this->getDataGenerator()->create_user(['username' => 'user77']);
        $logpersistent = new \tool_wp\local\exportimport\import_detail_persistent();
        $importmanager->set_current_import_detail_persistent($logpersistent);
        $m = $importmanager->get_mapping('user', 83, MUST_EXIST);
        $this->assertNull($m);
        $this->assertFalse($logpersistent->is_validated());
        $errors = $logpersistent->get_formatted_errors(null);
        $this->assertCount(1, $errors);
        $this->assertEquals($usererrorstring, $errors[0]);

        // Now create a new instance of import_manager. The user will be retrieved, detail validated, no errors.
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importmanager->set_mapping('tool_tenant', 7, \tool_tenant\tenancy::get_default_tenant_id());
        $logpersistent = new \tool_wp\local\exportimport\import_detail_persistent();
        $importmanager->set_current_import_detail_persistent($logpersistent);
        $m = $importmanager->get_mapping('user', 83, MUST_EXIST);
        $this->assertEquals($user->id, $m);
        $this->assertTrue($logpersistent->is_validated());
        $errors = $logpersistent->get_formatted_errors(null);
        $this->assertEmpty($errors);
    }

    /**
     * Test get_mapping when there is no mapper available
     */
    public function test_get_mapping_missing(): void {
        global $CFG;

        if (!class_exists(importer::class)) {
            $this->markTestSkipped('Importer \'' . importer::class . '\' doesn\'t exist, skipping test');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        $importid = $this->wpgenerator->perform_import_from_file(
            "{$CFG->dirroot}/{$CFG->admin}/tool/organisation/tests/fixtures/fullexport.zip");
        $manager = new import_manager($importid);

        $importdetail = new import_detail_persistent();
        $manager->set_current_import_detail_persistent($importdetail);

        // Try to map entity that doesn't exist.
        $mapping = $manager->get_mapping('doesntexist', 42);
        $this->assertNull($mapping);

        $errors = $importdetail->get_formatted_errors(null);
        $this->assertCount(1, $errors);
        $this->assertEquals('Not possible to map doesntexist, no mapper available', reset($errors));
    }

    /**
     * Test the list of instances for the review script import_manager::get_instances() with importer errors
     */
    public function test_import_review_importer_error() {
        $this->resetAfterTest();
        self::setAdminUser();
        $course1 = self::getDataGenerator()->create_course(['shortname' => 'C1']);
        $course2 = self::getDataGenerator()->create_course(['shortname' => 'C2']);
        $course3 = self::getDataGenerator()->create_course(['shortname' => 'C3']);

        // Create a new export containing three courses and then delete one of them.
        $exportid = $this->wpgenerator->perform_export(\tool_wp\tool_wp\exporter\courses::class, [
            \tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => \tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_SELECTED,
            \tool_wp\tool_wp\exporter\courses::EXPORT_SELECT_COURSES => [$course1->id, $course2->id, $course3->id],
        ]);
        delete_course($course1->id, false);

        // Import courses from the export file.
        $shortnameconflict = \tool_wp\local\exportimport\helper::get_importer_setting_name_for_conflict_form(
            'course', 'shortnameconflict', '');
        $settings = [
            \tool_wp\tool_wp\importer\courses::IMPORT_INSTANCES => \tool_wp\tool_wp\importer\courses::IMPORT_INSTANCES_ALL,
        ];

        // Scenario 1: When course shortname exists - do not import.
        $importmanager = \tool_wp\local\exportimport\import_manager::create_import_from_exportfile([], $exportid);
        $importmanager->save_settings($settings);
        // There is 1 collected error for 'shortnameconflict' with two courses.
        $errors1 = $importmanager->get_collected_errors();
        $this->assertCount(1, $errors1);
        $this->assertEquals('shortnameconflict', $errors1[$shortnameconflict]['errorcode']);
        $this->assertEqualsCanonicalizing([$course2->fullname, $course3->fullname],
            array_column($errors1[$shortnameconflict]['details'], 'fullname'));
        // Get the list of instances to be imported.
        // Course 1 has id=-1 which means it will be imported, other two courses have id=null which means they will not be.
        $this->assertEqualsCanonicalizing([
            ['entityname' => 'course', 'instancename' => $course1->fullname, 'id' => -1],
            ['entityname' => 'course', 'instancename' => $course2->fullname, 'id' => null],
            ['entityname' => 'course', 'instancename' => $course3->fullname, 'id' => null],
        ], $importmanager->get_instances());

        // Scenario 2: When course shortname exists - import with an increment.
        $importmanager = \tool_wp\local\exportimport\import_manager::create_import_from_exportfile([], $exportid);
        $importmanager->save_settings($settings + [$shortnameconflict . 'action' => 'increment']);
        // Collected errors are exactly the same as before.
        $this->assertEquals($errors1, $importmanager->get_collected_errors());
        // All courses are marked as "will be imported".
        $this->assertEqualsCanonicalizing([
            ['entityname' => 'course', 'instancename' => $course1->fullname, 'id' => -1],
            ['entityname' => 'course', 'instancename' => $course2->fullname, 'id' => -1],
            ['entityname' => 'course', 'instancename' => $course3->fullname, 'id' => -1],
        ], $importmanager->get_instances());
    }

    /**
     * Helper method to prepare an export file for the next test
     *
     * @return int export id
     */
    protected function prepare_export_for_test_import_review_mapping_error(): int {
        global $DB;
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        /** @var tool_organisation_generator $orggenerator */
        $orggenerator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $tenant = $tenantgenerator->create_tenant();

        $pf = $orggenerator->create_position(['tenantid' => $tenant->id]);
        $p1 = $orggenerator->create_position(['parentid' => $pf->id, 'departmentmanager' => 1, 'idnumber' => 'p1', 'name' => 'P1']);
        $df = $orggenerator->create_department(['tenantid' => $tenant->id]);
        $d1 = $orggenerator->create_department(['parentid' => $df->id, 'idnumber' => 'd1', 'name' => 'D1']);
        $d2 = $orggenerator->create_department(['parentid' => $df->id, 'idnumber' => 'd2', 'name' => 'D2']);

        $user1 = $tenantgenerator->create_user(['tenantid' => $tenant->id, 'username' => 'user1']);
        $orggenerator->assign_job(['userid' => $user1->id, 'positionid' => $p1->id, 'departmentid' => $d1->id]);
        $user2 = $tenantgenerator->create_user(['tenantid' => $tenant->id, 'username' => 'user2']);
        $orggenerator->assign_job(['userid' => $user2->id, 'positionid' => $p1->id, 'departmentid' => $d2->id]);
        $user3 = $tenantgenerator->create_user(['tenantid' => $tenant->id, 'username' => 'user3']);
        $orggenerator->assign_job(['userid' => $user3->id, 'positionid' => $p1->id, 'departmentid' => $d2->id]);

        $this->setAdminUser();
        \tool_tenant\tenancy::set_switched_tenant_id($tenant->id);

        $settings = [
            \tool_organisation\tool_wp\exporter\jobs::EXPORT_TYPE => \tool_organisation\tool_wp\exporter\jobs::EXPORT_TYPE_ALL,
        ];
        $exportid = $this->wpgenerator->perform_export(
            \tool_organisation\tool_wp\exporter\jobs::class, $settings);

        $ex = new export_persistent($exportid);

        // Remove all jobs and departments from this tenant and import.
        (new \tool_organisation\department_manager())->delete_all_departments_for_tenant($tenant->id);
        (new \tool_organisation\position_manager())->delete_all_positions_for_tenant($tenant->id);
        delete_user($user1);
        delete_user($user2);
        delete_user($user3);

        return $exportid;
    }

    /**
     * Test the list of instances for the review script import_manager::get_instances() with mapping errors
     */
    public function test_import_review_mapping_error() {
        global $DB;
        $this->resetAfterTest();

        // Export file that contains three jobs in position p1: one in department d1 and two in department d2.
        // For users with usernames user1, user2 and user3 (all departments, positions and users are deleted).
        $exportid = $this->prepare_export_for_test_import_review_mapping_error();

        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        /** @var tool_organisation_generator $orggenerator */
        $orggenerator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $tenant = $tenantgenerator->create_tenant();

        $pf = $orggenerator->create_position(['tenantid' => $tenant->id]);
        $p1 = $orggenerator->create_position(['parentid' => $pf->id, 'departmentmanager' => 1, 'idnumber' => 'p1']);
        $df = $orggenerator->create_department(['tenantid' => $tenant->id]);
        $d1 = $orggenerator->create_department(['parentid' => $df->id, 'idnumber' => 'd1']);

        $user1 = $tenantgenerator->create_user(['tenantid' => $tenant->id, 'username' => 'user1', 'firstname' => 'A']);
        $user2 = $tenantgenerator->create_user(['tenantid' => $tenant->id, 'username' => 'user2', 'firstname' => 'B']);
        $user3 = $tenantgenerator->create_user(['tenantid' => $tenant->id, 'username' => 'user3', 'firstname' => 'C']);

        \tool_tenant\tenancy::set_switched_tenant_id($tenant->id);

        $settingname = helper::get_setting_name_for_conflict_form('tool_organisation_department', '');
        $settingname2 = helper::get_setting_name_for_conflict_form('tool_organisation_department_framework', '');

        // Scenario 1: When department does not exists - do not import.
        $importmanager = \tool_wp\local\exportimport\import_manager::create_import_from_exportfile([], $exportid);
        $importsettings = [
            \tool_organisation\tool_wp\importer\jobs::IMPORT_TYPE => \tool_organisation\tool_wp\importer\jobs::IMPORT_TYPE_ALL,
            $settingname . 'action' => 'skip',
            $settingname2 . 'action' => 'skip',
        ];
        $importmanager->save_settings($importsettings);
        $this->assertTrue($importmanager->has_conflicts());
        $this->assertTrue($importmanager->has_conflicts_settings());
        // There is 1 collected error for non-existing department.
        $errors1 = $importmanager->get_collected_errors();
        $this->assertCount(1, $errors1);
        $this->assertEquals(['tool_organisation_job'], array_values($errors1[$settingname]['importedentities']));
        $this->assertEquals('tool_organisation_department', $errors1[$settingname]['entityname']);
        $this->assertEqualsCanonicalizing(['d2'],
            array_column($errors1[$settingname]['identifiers'], 'idnumber'));
        // Get the list of instances to be imported.
        // Job for user 1 has id=-1 which means it will be imported, other two jobs have id=null which means they will not be.
        $this->assertEqualsCanonicalizing([
            ['entityname' => 'tool_organisation_job', 'instancename' => fullname($user1) . ' - P1 (D1)', 'id' => -1],
            ['entityname' => 'tool_organisation_job', 'instancename' => fullname($user2) . ' - P1 (D2)', 'id' => null],
            ['entityname' => 'tool_organisation_job', 'instancename' => fullname($user3) . ' - P1 (D2)', 'id' => null],
        ], $importmanager->get_instances());

        // Scenario 2: When department does not exists - create a new one.
        $importmanager = \tool_wp\local\exportimport\import_manager::create_import_from_exportfile([], $exportid);
        $importsettings = [
            \tool_organisation\tool_wp\importer\jobs::IMPORT_TYPE => \tool_organisation\tool_wp\importer\jobs::IMPORT_TYPE_ALL,
            $settingname . 'action' => 'create',
            $settingname . 'frmid' => $df->id,
        ];
        $importmanager->save_settings($importsettings);
        $this->assertTrue($importmanager->has_conflicts());
        $this->assertTrue($importmanager->has_conflicts_settings());
        // Collected errors are exactly the same as before.
        $this->assertEquals($errors1, $importmanager->get_collected_errors());
        // All jobs will be imported.
        $this->assertEqualsCanonicalizing([
            ['entityname' => 'tool_organisation_job', 'instancename' => fullname($user1) . ' - P1 (D1)', 'id' => -1],
            ['entityname' => 'tool_organisation_job', 'instancename' => fullname($user2) . ' - P1 (D2)', 'id' => -1],
            ['entityname' => 'tool_organisation_job', 'instancename' => fullname($user3) . ' - P1 (D2)', 'id' => -1],
        ], $importmanager->get_instances());
    }

    /**
     * Testing error during export
     */
    public function test_error_calling_exporter() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $exportid = export_manager::schedule_export([
            'exporter' => exporter::class,
        ], false);

        // Errors during export are hard to test, if we know there can be an error we'd fix it.
        // Let's just mess with the database.
        $DB->update_record('tool_wp_export', ['id' => $exportid, 'exporter' => 'nonexisting']);

        // Catch all notifications.
        $messagesink = $this->redirectMessages();

        (new export_manager($exportid))->perform_export();

        $messages = $messagesink->get_messages();
        $messagesink->close();

        // Make sure the notification message contains error status and the explanation.
        $this->assertCount(1, $messages);
        $this->assertStringContainsString("Status: Error\nException: Could not find exporter 'nonexisting'\n",
            $messages[0]->fullmessage);

        // Status in the database is also "error".
        $this->assertEquals(helper::STATUS_ERROR, $DB->get_field('tool_wp_export', 'status', ['id' => $exportid]));
    }

    /**
     * Testing error during export
     */
    public function test_error_in_export_process() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $c1 = $this->getDataGenerator()->create_course([]);

        $exportid = export_manager::schedule_export([
            'exporter' => tool_wp_testing_mock_export_error::class,
            \tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES => \tool_wp\tool_wp\exporter\courses::EXPORT_INSTANCES_SELECTED,
            \tool_wp\tool_wp\exporter\courses::EXPORT_SELECT_COURSES => [$c1->id],
        ], false);

        // Catch all notifications.
        $messagesink = $this->redirectMessages();

        (new export_manager($exportid))->perform_export();

        $messages = $messagesink->get_messages();
        $messagesink->close();

        // Make sure the notification message contains error status and the explanation.
        $this->assertCount(1, $messages);
        $this->assertStringContainsString("Status: Error\nException: Table \"nonexistingtable\" does not exist\n",
            $messages[0]->fullmessage);

        // Status in the database is also "error".
        $record = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(helper::STATUS_ERROR, $record->status);
        $reviewdata = json_decode($record->reviewdata, true);
        $this->assertCount(1, $reviewdata['errors']);
        $this->assertEquals(\dml_exception::class, $reviewdata['errors'][0]['class']);
        $this->assertEquals("Table \"nonexistingtable\" does not exist", $reviewdata['errors'][0]['message']);
        $this->assertStringContainsString('tool_wp_testing_mock_export_error->perform_export()',
            $reviewdata['errors'][0]['trace']);
    }
}

/**
 * Class tool_wp_testing_mock_export_error
 *
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_testing_mock_export_error extends \tool_wp\tool_wp\exporter\courses {
    /**
     * Perform export (throws exception)
     */
    public function perform_export(): void {
        global $DB;
        $DB->get_record('nonexistingtable', ['id' => 1]);
    }
}

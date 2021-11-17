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
 * File containing tests for functions in helper.
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_organisation\tool_wp\exporter\orgstructure as exporter;
use tool_organisation\tool_wp\importer\orgstructure as importer;
use tool_wp\event\export_deleted;
use tool_wp\event\import_deleted;
use tool_wp\local\exportimport\export_persistent;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_persistent;

/**
 * Tests for functions in helper
 *
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_export_import_helper_testcase extends advanced_testcase {

    public function test_cleanup_abandoned_imports() {
        global $DB;
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();

        $data = (object) [
            'createdby' => $user->id,
            'tenantid' => 1,
            'status' => \tool_wp\local\exportimport\import_manager::DETAILTYPE_SUCCESS
        ];
        $persistent = new \tool_wp\local\exportimport\import_persistent(0, $data);
        $persistent->create();

        helper::cleanup_abandoned_imports();
        $this->assertTrue($DB->record_exists('tool_wp_import', ['id' => $persistent->get('id')]));

        $persistent->set('status', helper::STATUS_CREATED);
        $persistent->update();
        helper::cleanup_abandoned_imports();
        $this->assertTrue($DB->record_exists('tool_wp_import', ['id' => $persistent->get('id')]));

        $DB->set_field('tool_wp_import', 'timecreated', strtotime('-2 day'));
        helper::cleanup_abandoned_imports();
        $this->assertFalse($DB->record_exists('tool_wp_import', ['id' => $persistent->get('id')]));
    }

    public function test_cleanup_expired_exports_imports() {
        global $DB, $CFG;
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();

        $data = (object) [
            'createdby' => $user->id,
            'tenantid' => 1,
            'status' => \tool_wp\local\exportimport\import_manager::DETAILTYPE_SUCCESS
        ];
        $importpersistent = new \tool_wp\local\exportimport\import_persistent(0, $data);
        $importpersistent->create();

        $data = (object) [
            'createdby' => $user->id,
            'tenantid' => 1,
            'exporter' => 'tool_program\tool_wp\exporter\programs'
        ];
        $exportpersistent = new \tool_wp\local\exportimport\export_persistent(0, $data);
        $exportpersistent->create();

        $CFG->wpexportimportexpiry = 20 * DAYSECS;
        helper::cleanup_expired_exports_imports();
        $this->assertTrue($DB->record_exists('tool_wp_import', ['id' => $importpersistent->get('id')]));
        $this->assertTrue($DB->record_exists('tool_wp_export', ['id' => $exportpersistent->get('id')]));

        $DB->set_field('tool_wp_import', 'timecreated', strtotime('-22 day'));
        $DB->set_field('tool_wp_export', 'timecreated', strtotime('-22 day'));
        helper::cleanup_expired_exports_imports();
        $this->assertFalse($DB->record_exists('tool_wp_import', ['id' => $importpersistent->get('id')]));
        $this->assertFalse($DB->record_exists('tool_wp_export', ['id' => $exportpersistent->get('id')]));
    }

    /**
     * Test helper delete_export method
     *
     * @return void
     */
    public function test_delete_export() {
        if (!class_exists(exporter::class)) {
            $this->markTestSkipped('Exporter \'' . exporter::class . '\' doesn\'t exist, skipping test');
        }

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $export = (new export_persistent(0, (object) [
            'createdby' => $user->id,
            'exporter' => exporter::class,
        ]))->create();

        // Catch all triggered events.
        $sink = $this->redirectEvents();
        helper::delete_export($export->get('id'));

        $events = $sink->get_events();
        $sink->close();

        // Verify export was deleted.
        $this->assertFalse(export_persistent::record_exists($export->get('id')));

        // Ensure export deleted event was triggered.
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(export_deleted::class, $event);
        $this->assertEquals($export->get('id'), $event->objectid);
    }

    /**
     * Test helper delete_import method
     *
     * @return void
     */
    public function test_delete_import() {
        if (!class_exists(importer::class)) {
            $this->markTestSkipped('Importer \'' . importer::class . '\' doesn\'t exist, skipping test');
        }

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $import = (new import_persistent(0, (object) [
            'createdby' => $user->id,
            'importer' => importer::class,
        ]))->create();

        // Catch all triggered events.
        $sink = $this->redirectEvents();
        helper::delete_import($import->get('id'));

        $events = $sink->get_events();
        $sink->close();

        // Verify import was deleted.
        $this->assertFalse(import_persistent::record_exists($import->get('id')));

        // Ensure import deleted event was triggered.
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(import_deleted::class, $event);
        $this->assertEquals($import->get('id'), $event->objectid);
    }

    /**
     * Test ensuring that conflict form settings names are formed and parsed correctly
     */
    public function test_settings_names() {
        $settings = [
            helper::get_setting_name_for_conflict_form('course', 'action') => 'create',
            helper::get_setting_name_for_conflict_form('course', 'catid') => 123,
            helper::get_setting_name_for_conflict_form('tool_program', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form('department', 'idnumberconflict', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form('position', 'idnumberconflict', 'action') => 'create',
            helper::get_importer_setting_name_for_conflict_form('position', 'idnumberconflict', 'frmid') => 15,
            'anothersetting' => 99,
            'yet:another:setting' => 100,
        ];

        $importersettings = helper::parse_importer_conflict_settings($settings);
        $this->assertEquals([
            [
                'importedentity' => 'department',
                'errorcode' => 'idnumberconflict',
                'settings' => ['action' => 'skip']
            ],
            [
                'importedentity' => 'position',
                'errorcode' => 'idnumberconflict',
                'settings' => ['action' => 'create', 'frmid' => 15]
            ],
        ], $importersettings);

        $mappersettings = helper::parse_mapping_conflict_settings($settings);
        $this->assertEquals([
            [
                'entityname' => 'course',
                'settings' => ['action' => 'create', 'catid' => 123]
            ],
            [
                'entityname' => 'tool_program',
                'settings' => ['action' => 'skip']
            ]
        ], $mappersettings);
    }
}

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
 * File containing tests for tool_wp_external class.
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_wp_external class methods.
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_external_testcase extends advanced_testcase {

    /**
     * Test for funciton potential_users_selector()
     */
    public function test_potential_users_selector() {
        global $USER;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => 'User', 'email' => 'user@user.com']);
        $this->setAdminUser();
        // Search without search string.
        $users = tool_wp_external::potential_users_selector('', 'tool_wp', '', 0);
        $this->assertEquals(2, count($users)); // Admin and user.
        // Search with one word search string.
        $users = tool_wp_external::potential_users_selector('Admin', 'tool_wp', '', 0);
        $this->assertEquals(1, count($users)); // Admin.
        // Search with two words search string.
        $users = tool_wp_external::potential_users_selector('admin user', 'tool_wp', '', 0);
        $this->assertEquals(1, count($users)); // Admin.
        // Search with two words search string.
        $users = tool_wp_external::potential_users_selector('user', 'tool_wp', '', 0);
        $this->assertEquals(2, count($users)); // Admin and user.
        $users = tool_wp_external::potential_users_selector('user', 'tool_wp', 'excludeuser', $user->id);
        $this->assertEquals(1, count($users)); // Admin.

        $users = external_api::clean_returnvalue(tool_wp_external::potential_users_selector_returns(), $users);
        $this->assertEquals($USER->id, $users[0]['id']);
    }

    /**
     * Test for funciton test_delete_import()
     *
     * @covers tool_wp\local\exportimport\helper::delete_import
     */
    public function test_delete_import() {
        global $DB;
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $this->setAdminUser();

        $data = (object) [
            'createdby' => $user->id,
            'tenantid' => 1
        ];
        $persistent = new \tool_wp\local\exportimport\import_persistent(0, $data);
        $persistent->create();
        $this->assertTrue($DB->record_exists('tool_wp_import', ['id' => $persistent->get('id')]));
        tool_wp_external::delete_import($persistent->get('id'));
        $this->assertFalse($DB->record_exists('tool_wp_import', ['id' => $persistent->get('id')]));
    }

    /**
     * Test for funciton test_delete_export()
     *
     * @covers tool_wp\local\exportimport\helper::delete_export
     */
    public function test_delete_export() {
        global $DB;
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $this->setAdminUser();

        $data = (object) [
            'createdby' => $user->id,
            'tenantid' => 1,
            'exporter' => 'tool_program\tool_wp\exporter\programs'
        ];
        $persistent = new \tool_wp\local\exportimport\export_persistent(0, $data);
        $persistent->create();
        $this->assertTrue($DB->record_exists('tool_wp_export', ['id' => $persistent->get('id')]));
        tool_wp_external::delete_export($persistent->get('id'));
        $this->assertFalse($DB->record_exists('tool_wp_export', ['id' => $persistent->get('id')]));
    }

    /**
     * Test for funciton get_export_progress()
     *
     * @covers tool_wp\local\exportimport\export_manager::get_export_progress
     */
    public function test_get_export_status() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Schedule export.
        $exportid = \tool_wp\local\exportimport\export_manager::schedule_export(
            ['exporter' => tool_program\tool_wp\exporter\programs::class]
        );
        $this->assertTrue($DB->record_exists('tool_wp_export', ['id' => $exportid]));

        $status = tool_wp_external::get_export_status($exportid);
        $status = external_api::clean_returnvalue(tool_wp_external::get_export_status_returns(), $status);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_SCHEDULED, $status['status']);
        $this->assertEquals('Scheduled', $status['statusstr']);
        $this->assertEquals(0, $status['progress']);

        // Run export.
        $this->runAdhocTasks(\tool_wp\task\export_adhoc_task::class);
        $status = tool_wp_external::get_export_status($exportid);
        $status = external_api::clean_returnvalue(tool_wp_external::get_export_status_returns(), $status);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $status['status']);
        $this->assertEquals('Success', $status['statusstr']);
        $this->assertEquals(100, $status['progress']);
    }

    /**
     * Test for funciton get_import_progress()
     *
     * @covers tool_wp\local\exportimport\import_manager::get_import_progress
     */
    public function test_get_import_status() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create export.
        $wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $exportid = $wpgenerator->perform_export(tool_program\tool_wp\exporter\programs::class);
        $this->assertTrue($DB->record_exists('tool_wp_export', ['id' => $exportid]));

        // Create and schedule import.
        $importmanager = \tool_wp\local\exportimport\import_manager::create_import_from_exportfile([
            'importer' => \tool_program\tool_wp\importer\programs::class
        ], $exportid);
        $importid = $importmanager->get_import_id();
        $this->assertTrue($DB->record_exists('tool_wp_import', ['id' => $importid]));
        $importmanager->schedule_import();
        $status = tool_wp_external::get_import_status($importid);
        $status = external_api::clean_returnvalue(tool_wp_external::get_import_status_returns(), $status);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_SCHEDULED, $status['status']);
        $this->assertEquals('Scheduled', $status['statusstr']);
        $this->assertEquals(0, $status['progress']);

        // Run import.
        $this->runAdhocTasks(\tool_wp\task\import_adhoc_task::class);
        $status = tool_wp_external::get_import_status($importid);
        $status = external_api::clean_returnvalue(tool_wp_external::get_import_status_returns(), $status);
        $this->assertEquals(\tool_wp\local\exportimport\helper::STATUS_DONE, $status['status']);
        $this->assertEquals('Success', $status['statusstr']);
        $this->assertEquals(100, $status['progress']);
    }
}

/**
 * Test callback implementation
 *
 * @param string $area
 * @param int $itemid
 * @return array
 */
function tool_wp_potential_users_selector(string $area, int $itemid) {
    $join = '';
    $where = 'u.deleted = 0';
    $params = [];
    if ($area === 'excludeuser') {
        // This is not a realistic example.
        $where .= ' AND u.id <> :excludeuser';
        $params['excludeuser'] = $itemid;
    }
    return [$join, $where, $params];
}
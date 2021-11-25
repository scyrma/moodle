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
 * File containing tests for tool_wp_external class.
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Tests for the tool_wp_external class methods.
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_external_testcase extends advanced_testcase {

    /**
     * Load required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->libdir}/externallib.php");
    }

    /**
     * Data provider for potential user selection
     *
     * @see test_potential_users_selector
     *
     * @return array
     */
    public function potential_users_selector_provider(): array {
        return [
            ['', ['Bob Smith', 'Lee Smith']],
            ['bob', ['Bob Smith']],
            ['smith', ['Bob Smith', 'Lee Smith']],
            ['jim', []],
        ];
    }

    /**
     * Test for function potential_users_selector()
     *
     * @param string $search
     * @param string[] $expectedfullnames
     *
     * @dataProvider potential_users_selector_provider
     */
    public function test_potential_users_selector(string $search, array $expectedfullnames): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant = $this->get_tenant_generator()->create_tenant();

        $user1 = $this->getDataGenerator()->create_user(
            ['firstname' => 'Bob', 'lastname' => 'Smith', 'email' => 'test@example.invalid']);
        // Add a space to this user's email (may accidentally happen when importing from some external sources).
        $DB->update_record('user', ['id' => $user1->id, 'email' => ' test@example.invalid']);
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant->id);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Lee', 'lastname' => 'Smith']);
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant->id);

        $matchedusers = external_api::clean_returnvalue(
            tool_wp_external::potential_users_selector_returns(),
            tool_wp_external::potential_users_selector($search, 'tool_wp', 'users', $tenant->id)
        );

        $this->assertEqualsCanonicalizing($expectedfullnames, array_column($matchedusers, 'fullname'));
    }

    /**
     * Test for potential user selector, selecting users across the site and tenants
     */
    public function test_potential_users_selector_tenant(): void {
        $this->resetAfterTest();

        [$tenantone, $tenantoneusers] = $this->get_tenant_generator()->create_tenant_and_users(2);
        [$tenanttwo, $tenanttwousers] = $this->get_tenant_generator()->create_tenant_and_users(2);

        // Admin can search users from any tenant.
        $this->setAdminUser();
        $this->get_tenant_generator()->allocate_user(get_admin()->id, $tenanttwo->id);

        $matchedusers = external_api::clean_returnvalue(
            tool_wp_external::potential_users_selector_returns(),
            tool_wp_external::potential_users_selector($tenantoneusers[0]->username, 'tool_wp', 'users', 0)
        );

        $this->assertCount(1, $matchedusers);
        $this->assertEquals(fullname($tenantoneusers[0]), reset($matchedusers)['fullname']);

        // Tenant admin can search users only from their own tenant.
        $tenantadmin = $this->getDataGenerator()->create_user();

        $manager = new \tool_tenant\manager();
        $manager->allocate_user($tenantadmin->id, $tenantone->id, 'tool_wp', 'test');
        $manager->assign_tenant_admin_roles([$tenantadmin->id], $tenantone->id);

        $this->setUser($tenantadmin);

        // Not allowed to search across site.
        $matchedsiteusers = external_api::clean_returnvalue(
            tool_wp_external::potential_users_selector_returns(),
            tool_wp_external::potential_users_selector($tenantoneusers[0]->username, 'tool_wp', 'users', 0)
        );
        $this->assertEmpty($matchedsiteusers);

        // Allowed to search own tenant.
        $matchedtenantusers = external_api::clean_returnvalue(
            tool_wp_external::potential_users_selector_returns(),
            tool_wp_external::potential_users_selector($tenantoneusers[0]->username, 'tool_wp', 'users', $tenantone->id)
        );
        $this->assertCount(1, $matchedtenantusers);
        $this->assertEquals(fullname($tenantoneusers[0]), reset($matchedusers)['fullname']);

        // Not allowed to search in other tenant.
        $matchednontenantusers = external_api::clean_returnvalue(
            tool_wp_external::potential_users_selector_returns(),
            tool_wp_external::potential_users_selector($tenanttwousers[0]->username, 'tool_wp', 'users', $tenanttwo->id)
        );
        $this->assertEmpty($matchednontenantusers);
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

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}

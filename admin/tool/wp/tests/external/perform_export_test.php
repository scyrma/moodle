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
 * File containing tests for perform_export external class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\external;

use tool_wp\local\exportimport\export_manager;
use tool_tenant_generator;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\external\perform_export
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class perform_export_test extends \advanced_testcase {

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test perform_export missing rights.
     */
    public function test_perform_export_no_rights() {
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->expectException(\required_capability_exception::class);
        perform_export::execute(\tool_wp\tool_wp\exporter\users::class,
            '{}', $tenant->id, true, false);
    }

    /**
     * Test perform_export not available exporter.
     */
    public function test_perform_export_no_exporter() {
        $this->expectException(\invalid_parameter_exception::class);
        perform_export::execute('\tool_wp\tool_wp\exporter\nonexisting',
            '{}', 0, true, false);
    }

    /**
     * Test perform_export nonexisting tenant.
     */
    public function test_perform_export_nonexisting_tenant() {
        $tenantid = 1000;
        $this->expectExceptionMessage(get_string('migrationcannotswitchtenant', 'tool_wp', $tenantid));
        perform_export::execute(\tool_wp\tool_wp\exporter\users::class,
            '{}', $tenantid, true, false);
    }

    /**
     * Test perform_export tenant and no-tenant supplied.
     */
    public function test_perform_export_tenant_and_notenant() {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->expectExceptionMessage(get_string('migrationnotenanterror', 'tool_wp'));
        perform_export::execute(\tool_wp\tool_wp\exporter\users::class,
            '{}', $tenant->id, true, true);
    }

    /**
     * Test perform_export tenant required (no-tenant is not permitted).
     */
    public function test_perform_export_tenant_required() {
        $exporterclass = \tool_organisation\tool_wp\exporter\jobs::class;
        $exporterinstance = \tool_wp\exporter_base::create($exporterclass);

        // Sanity check.
        $this->assertTrue($exporterinstance->is_tenant_required());

        // Called with no-tenant.
        $this->expectExceptionMessage(get_string('exporterrequirestenant', 'tool_wp', $exporterinstance->get_name()));
        perform_export::execute(get_class($exporterinstance), '{}', 0, true, true);
    }

    /**
     * Test perform_export can't switch tenant.
     */
    public function test_perform_export_not_allowed_switch_tenant() {
        global $DB;
        $tenant = $this->get_tenant_generator()->create_tenant();
        $user = self::getDataGenerator()->create_user();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $context = \context_system::instance();
        self::getDataGenerator()->role_assign($managerrole->id, $user->id, $context);
        assign_capability('tool/wp:useexportimport', CAP_ALLOW, $managerrole->id, $context->id);
        $this->setUser($user);

        $this->expectExceptionMessage(get_string('migrationcannotswitchtenant', 'tool_wp', $tenant->id));
        perform_export::execute(\tool_wp\tool_wp\exporter\users::class,
            '{}', $tenant->id, true, false);
    }

    /**
     * Test perform_export settings validation failed.
     */
    public function test_perform_export_settings_validation_failed() {
        $tenant = $this->get_tenant_generator()->create_tenant();

        // Invalid JSON.
        try {
            perform_export::execute(\tool_certification\tool_wp\exporter\certifications::class,
            'string', $tenant->id, true, false);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString(get_string('exportersettingsinvalid', 'tool_wp'), $e->getMessage());
        }

        try {
            perform_export::execute(\tool_certification\tool_wp\exporter\certifications::class,
            '{"key":}', $tenant->id, true, false);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString(get_string('exportersettingsinvalid', 'tool_wp'), $e->getMessage());
        }

        // Set required setting to null.
        $this->expectExceptionMessage(get_string('exportersettingsvalidationfailed', 'tool_wp',
            'select_certifications - Select at least one certification'));
        perform_export::execute(\tool_certification\tool_wp\exporter\certifications::class,
            json_encode(['export_instances' => 'selected']), $tenant->id, true, false);
    }

    /**
     * Test perform_export
     */
    public function test_perform_export() {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'user1']);

        // Dry-run of export.
        $result = \tool_wp\external\perform_export::execute(\tool_wp\tool_wp\exporter\users::class,
            '{}', $tenant->id, true, false);
        $result = \external_api::clean_returnvalue(perform_export::execute_returns(), $result);
        $this->assertEquals(0, $result['id']);

        // Create export.
        $result = perform_export::execute(\tool_wp\tool_wp\exporter\users::class,
            '{}', $tenant->id, false, false);
        $result = \external_api::clean_returnvalue(perform_export::execute_returns(), $result);
        $this->assertNotEquals(0, $result['id']);
    }

    /**
     * Test perform_export_with_settings
     */
    public function test_perform_export_with_settings() {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $user1 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'user1']);
        $user2 = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'user2']);

        // Create export.
        $result = perform_export::execute(\tool_wp\tool_wp\exporter\users::class,
            json_encode(['export_instances' => 'manual', 'select_users' => [$user1->id, $user2->id]]), $tenant->id, false, false);
        $result = \external_api::clean_returnvalue(perform_export::execute_returns(), $result);

        // Sanity check.
        $this->assertNotEquals(0, $result['id']);

        // Check settings in the result.
        $resultsettings = json_decode($result['settings'], true);
        $this->assertEquals('manual', $resultsettings['export_instances']);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $resultsettings['select_users']);

        // Perform export.
        $this->runAdhocTasks(\tool_wp\task\export_adhoc_task::class);

        // Get export.
        $exportmanager = new export_manager($result['id']);
        $settings = $exportmanager->get_export_settings();
        // Validate settings.
        $this->assertEquals('manual', $settings['export_instances']);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $settings['select_users']);
    }
}

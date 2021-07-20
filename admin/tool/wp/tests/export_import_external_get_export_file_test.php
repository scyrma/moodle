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
 * File containing tests for get_export_file external class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\external;

use tool_wp\external\get_export_file;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\external\get_export_file
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_external_get_export_file extends \advanced_testcase {

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
    protected function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Returns the plugin generator
     *
     * @return \tool_wp_generator
     */
    protected function get_plugin_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Prepare a user export
     *
     * @return int $exportid
     */
    protected function prepare_export(): int {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->getDataGenerator()->create_user(['tenantid' => $tenant->id, 'username' => 'user1']);

        // Create a new export containing one course.
        $exportid = $this->get_plugin_generator()->perform_export(\tool_wp\tool_wp\exporter\users::class, [
            'exportertenant' => $tenant->id,
        ]);

        return $exportid;
    }

    /**
     * Test get_export_file using invalid export id
     */
    public function test_get_export_file_invalid_exportid() {
        $this->expectExceptionMessage(get_string('exportnotfound', 'tool_wp'));
        get_export_file::execute(1000);
    }

    /**
     * Test get_export_file not ready.
     */
    public function test_get_export_file_not_ready() {
        // Create export.
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'username' => 'user1']);
        $result = perform_export::execute(\tool_wp\tool_wp\exporter\users::class,
            '{}', $tenant->id, false, false);
        $result = \external_api::clean_returnvalue(perform_export::execute_returns(), $result);

        // Sanity check.
        $this->assertNotEquals(0, $result['id']);

        // Get export file.
        $this->expectExceptionMessage(get_string('exportnotready', 'tool_wp'));
        get_export_file::execute($result['id']);
    }

    /**
     * Test get_export_file
     */
    public function test_get_export_file() {
        // Create export.
        $exportid = $this->prepare_export();

        // Get export file.
        $result = get_export_file::execute($exportid);
        $result = \external_api::clean_returnvalue(get_export_file::execute_returns(), $result);
        $this->assertNotEmpty($result[0]['fileurl']);
    }
}

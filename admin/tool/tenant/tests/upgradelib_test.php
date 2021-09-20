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

use tool_tenant\manager;
use tool_tenant\tenant;

/**
 * Class tool_tenant_upgradelib_testcase
 *
 * @package   tool_tenant
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 Odei Alba
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_tenant_upgradelib_testcase extends advanced_testcase {
    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Setup to ensure that tool_tenant_upgrade_remove_orphaned_files is loaded.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/tenant/db/upgradelib.php');
    }

    /**
     * Tests for function tool_tenant_upgrade_remove_orphaned_files()
     */
    public function test_upgrade_remove_orphaned_files(): void {
        global $DB;

        $tenantone = $this->generator->create_tenant();
        $tenanttwo = $this->generator->create_tenant();
        $twoid = $tenanttwo->id;

        $tenanttodelete = new tenant($twoid);
        $tenanttodelete->delete();

        $this->assertNotEquals(0, $DB->count_records('files', ['component' => 'tool_tenant', 'itemid' => $tenantone->id]));
        $this->assertNotEquals(0, $DB->count_records('files', ['component' => 'tool_tenant', 'itemid' => $twoid]));

        // Run upgrade script.
        tool_tenant_upgrade_remove_orphaned_files();

        // Make sure there is no file left for empty tenant.
        $this->assertNotEquals(0, $DB->count_records('files', ['component' => 'tool_tenant', 'itemid' => $tenantone->id]));
        $this->assertEquals(0, $DB->count_records('files', ['component' => 'tool_tenant', 'itemid' => $twoid]));
    }

}

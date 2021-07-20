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
 * File containing tests for observer class
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\test\mock_report;
use tool_tenant\manager;

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\observer
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_observer_testcase extends advanced_testcase {

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test reports list is empty for a normal user without any audience records configured
     *
     * @return void
     */
    public function test_tenant_deleted() {
        // Create report in default tenant.
        $this->get_plugin_generator()->create_report(['source' => mock_report::class]);

        // Create another report in new tenant.
        $tenant = $this->get_tenant_generator()->create_tenant();
        $this->get_plugin_generator()->create_report([
            'tenantid' => $tenant->id,
            'source' => mock_report::class,
        ]);

        // Sanity check.
        $this->assertEquals(2, reportbuilder::count_records());
        $this->assertEquals(1, reportbuilder::count_records(['tenantid' => $tenant->id]));

        // Archive & delete the new tenant.
        $manager = new manager();
        $manager->archive_tenant($tenant->id);
        $manager->delete_tenant($tenant->id);

        // The report in the new tenant should have been deleted.
        $this->assertEquals(1, reportbuilder::count_records());
        $this->assertEquals(0, reportbuilder::count_records(['tenantid' => $tenant->id]));
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_plugin_generator(): tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
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

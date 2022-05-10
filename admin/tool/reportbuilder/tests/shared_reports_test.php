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

namespace tool_reportbuilder;

use advanced_testcase;
use tool_reportbuilder_generator;
use tool_tenant_generator;

/**
 * Tests for Shared reports
 *
 * @package    tool_reportbuilder
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class shared_reports_test extends advanced_testcase {

    /** @var tool_reportbuilder_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_reportbuilder');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }


    /**
     * Report created in the tenant is always not shared, in the shared space is always shared
     */
    public function test_create_shared_report(): void {
        self::setAdminUser();
        $tenant = $this->tenantgenerator->create_tenant();
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();

        $user = $this->getDataGenerator()->create_user(['firstname' => 'John', 'lastname' => 'Smith'])->id;
        $this->tenantgenerator->allocate_user($user, $tenant->id);

        // Report created in a tenant is always not shared.
        $report = $this->generator->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
            'tenantid' => $tenant->id,
            'adddefault' => (int)true,
            'shared' => false,
        ]);
        $this->assertEquals(0, $report->get_persistent()->get('shared'));

        // Report created in a tenant is always not shared even if shared is set to true.
        $report = $this->generator->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
            'tenantid' => $tenant->id,
            'adddefault' => (int)true,
            'shared' => true,
        ]);
        $this->assertEquals(0, $report->get_persistent()->get('shared'));

        // Report created in shared space can be set as shared or not shared.
        $report = $this->generator->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
            'tenantid' => $sharedspaceid,
            'adddefault' => (int)true,
            'shared' => false,
        ]);
        $this->assertEquals(0, $report->get_persistent()->get('shared'));

        // Report created in shared space can be set as shared or not shared.
        $report = $this->generator->create_report([
            'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
            'tenantid' => $sharedspaceid,
            'adddefault' => (int)true,
            'shared' => true,
        ]);
        $this->assertEquals(1, $report->get_persistent()->get('shared'));
    }
}

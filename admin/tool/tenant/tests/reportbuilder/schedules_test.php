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

declare(strict_types=1);

namespace tool_tenant;

use advanced_testcase;
use core_reportbuilder_generator;
use tool_tenant_generator;
use core_reportbuilder\local\helpers\schedule;
use core_user\reportbuilder\datasource\users;

/**
 * Unit tests of tenancy related changes to reportbuilder schedule helper
 *
 * @package     tool_tenant
 * @covers      \core_reportbuilder\local\helpers\schedule
 * @author      2023 Paul Holden <paulh@moodle.com>
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class schedules_test extends advanced_testcase {

    /**
     * Test that getting schedule report users returns only those within the current tenant
     */
    public function test_get_schedule_report_users_current_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        [$tenantone, $tenantoneusers] = $generator->create_tenant_and_users(2);
        $tenantonereport = $generator->create_report(['name' => 'Tenant one', 'source' => users::class], $tenantone->id);

        [$tenanttwo, $tenanttwousers] = $generator->create_tenant_and_users(3);
        $tenanttworeport = $generator->create_report(['name' => 'Tenant two', 'source' => users::class], $tenantone->id);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');

        // Add all users audience to each report, create a schedule.
        $tenantoneaudience = $generator->create_audience(['reportid' => $tenantonereport->get('id'), 'configdata' => []]);
        $tenantoneschedule = $generator->create_schedule([
            'reportid' => $tenantonereport->get('id'),
            'name' => 'Tenant one',
            'audiences' => json_encode([
                $tenantoneaudience->get_persistent()->get('id'),
            ]),
        ]);

        $tenanttwoaudience = $generator->create_audience(['reportid' => $tenanttworeport->get('id'), 'configdata' => []]);
        $tenanttwoschedule = $generator->create_schedule([
            'reportid' => $tenanttworeport->get('id'),
            'name' => 'Tenant two',
            'audiences' => json_encode([
                $tenanttwoaudience->get_persistent()->get('id'),
            ]),
        ]);

        // Switch to tenant one.
        tenancy::set_switched_tenant_id($tenantone->id);

        $users = schedule::get_schedule_report_users($tenantoneschedule);
        $this->assertEqualsCanonicalizing(
            array_column($users, 'id'),
            array_column($tenantoneusers, 'id')
        );

        // Switch to tenant two.
        tenancy::set_switched_tenant_id($tenanttwo->id);

        $users = schedule::get_schedule_report_users($tenanttwoschedule);
        $this->assertEqualsCanonicalizing(
            array_column($users, 'id'),
            array_column($tenanttwousers, 'id')
        );
    }
}

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
use tool_tenant_generator;
use core_user\external\search_identity;

/**
 * Unit tests of tenancy related changes to external identity search class
 *
 * @package     tool_tenant
 * @covers      \core_user\external\search_identity
 * @author      2023 Paul Holden <paulh@moodle.com>
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class search_identity_test extends advanced_testcase {

    /**
     * Test that searching for users returns only those within the current tenant
     */
    public function test_search_identity_observes_current_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        [$tenantone, $tenantoneusers] = $generator->create_tenant_and_users(2);
        [$tenanttwo, $tenanttwousers] = $generator->create_tenant_and_users(3);

        // Switch to tenant one.
        tenancy::set_switched_tenant_id($tenantone->id);

        $users = search_identity::clean_returnvalue(search_identity::execute_returns(), search_identity::execute(''));
        $this->assertEqualsCanonicalizing(
            array_column($users['list'], 'id'),
            array_column($tenantoneusers, 'id')
        );

        // Switch to tenant two.
        tenancy::set_switched_tenant_id($tenanttwo->id);

        $users = search_identity::clean_returnvalue(search_identity::execute_returns(), search_identity::execute(''));
        $this->assertEqualsCanonicalizing(
            array_column($users['list'], 'id'),
            array_column($tenanttwousers, 'id')
        );
    }
}

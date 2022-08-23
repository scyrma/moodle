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
 * File containing tests for tool_tenant\external\potential_tenant_selector class.
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

use advanced_testcase;
use external_api;
use tool_tenant_generator;
use tool_tenant\external\potential_tenant_selector;

/**
 * Class potential_tenant_selector_test
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class potential_tenant_selector_test extends advanced_testcase {

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
     * Load required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->libdir}/externallib.php");
    }

    /**
     * Data provider for potential user selection
     *
     * @return array
     * @see test_potential_tenant_selector
     */
    public function potential_tenants_selector_provider(): array {
        return [
            ['', ['Tenant 4', 'Tenant 5', 'Tenant 6', 'Tenant 7', 'Tenant 8', 'Small Company 1',
                'Small Company 2', 'Small Company 3', 'Default tenant']],
            ['tenant', ['Tenant 4', 'Tenant 5', 'Tenant 6', 'Tenant 7', 'Tenant 8', 'Default tenant']],
            ['Small', ['Small Company 1', 'Small Company 2', 'Small Company 3']],
            ['Shared', []]
        ];
    }

    /**
     * Test method for potential tenant selector
     *
     * @dataProvider potential_tenants_selector_provider
     * @param string $search
     * @param array $expectedtenantnames
     */
    public function test_potential_tenant_selector(string $search, array $expectedtenantnames): void {
        $this->setAdminUser();
        for ($i = 1; $i <= 8; $i++) {
            if ($i > 3) {
                $this->generator->create_tenant(['name' => "Tenant " . $i]);
            } else {
                $this->generator->create_tenant(['name' => "Small Company " . $i]);
            }
        }
        $matchedtenants = external_api::clean_returnvalue(
            potential_tenant_selector::execute_returns(),
            potential_tenant_selector::execute($search)
        );
        $this->assertEqualsCanonicalizing($expectedtenantnames, array_column($matchedtenants, 'fullname'));
    }

    /**
     * Test that archived tenants are not returned
     */
    public function test_potential_tenant_selector_archived(): void {
        $this->setAdminUser();

        $tenant1 = $this->generator->create_tenant(['name' => 'Banana 1']);
        $tenant2 = $this->generator->create_tenant(['name' => 'Banana 2']);

        // Archive the second tenant.
        (new manager())->archive_tenant($tenant2->id);

        $matchedtenants = external_api::clean_returnvalue(
            potential_tenant_selector::execute_returns(),
            potential_tenant_selector::execute('Banana')
        );

        // Returned tenant should be the first one.
        $this->assertCount(1, $matchedtenants);
        $this->assertEquals([
            'id' => $tenant1->id,
            'fullname' => $tenant1->name,
        ], reset($matchedtenants));
    }

    /**
     * Test to ensure user with no permissions cannot see any results.
     */
    public function test_potential_tenant_selector_no_permissions() {
        $tenant = $this->generator->create_tenant(['name' => "Tenant 1"]);
        $user1 = $this->generator->create_user(['tenantid' => $tenant->id]);
        // Tenant user with no tenant admin permissions.
        $this->setUser($user1);
        $matchedtenants = external_api::clean_returnvalue(
            potential_tenant_selector::execute_returns(),
            potential_tenant_selector::execute('Tenant 1')
        );
        $this->assertEmpty($matchedtenants);
    }
}


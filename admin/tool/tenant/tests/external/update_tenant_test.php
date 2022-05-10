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

namespace tool_tenant\external;

use advanced_testcase;
use tool_tenant_generator;
use tool_tenant\manager;

/**
 * Tests for the update_tenant_test class.
 *
 * @package    tool_tenant
 * @covers     \update_tenant_test
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class update_tenant_test extends advanced_testcase {

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Returns the tenant generator
     * @return tool_tenant_generator
     */
    protected function get_generator(): tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test for update_tenant()
     */
    public function test_update_tenant() {
        $tenant1 = $this->get_generator()->create_tenant(['name' => 'Tenant to be updated']);
        $tenant2 = $this->get_generator()->create_tenant([
            'name' => 'Tenant to be updated 2',
            'idnumber' => 'TID2',
            'useloginurlidnumber' => false,
        ]);

        $manager = new manager();
        $tenantparams = [
            'name' => 'Tenant updated',
            'sitename' => 'Tenant updated site1',
        ];

        $this->assertNotEquals($tenant1->name, $tenantparams['name']);

        // Update tenant1 params.
        $tenantparams['id'] = $tenant1->id;

        $update = update_tenant::execute($tenantparams);
        $this->assertNull($update);

        $tenantupdated = $manager->get_tenant($tenant1->id);
        $this->assertEquals($tenantupdated->get('name'), $tenantparams['name']);
        $this->assertEquals($tenantupdated->get('sitename'), $tenantparams['sitename']);

        // Update tenant1 useloginurlidnumber = true, but not valid idnumber exists then useloginurlidnumber = false.
        $tenantparams['useloginurlidnumber'] = true;

        $update = update_tenant::execute($tenantparams);
        $this->assertNull($update);

        $tenantupdated = $manager->get_tenant($tenant1->id);
        $this->assertFalse((bool)$tenantupdated->get('useloginurlidnumber'));

        // Update tenant1 idnumber and useloginurlidnumber = true, idnumber is valid.
        $tenantparams['idnumber'] = 'TID1';
        $tenantparams['useloginurlidnumber'] = true;

        $update = update_tenant::execute($tenantparams);
        $this->assertNull($update);

        $tenantupdated = $manager->get_tenant($tenant1->id);
        $this->assertEquals($tenantupdated->get('idnumber'), $tenantparams['idnumber']);
        $this->assertTrue((bool)$tenantupdated->get('useloginurlidnumber'));

        // Update tenant2 useloginurlidnumber = true.
        $tenantparams2['id'] = $tenant2->id;
        $tenantparams2['useloginurlidnumber'] = true;

        $update2 = update_tenant::execute($tenantparams2);
        $this->assertNull($update2);

        $tenantupdated2 = $manager->get_tenant($tenant2->id);
        $this->assertTrue((bool)$tenantupdated2->get('useloginurlidnumber'));
    }
}

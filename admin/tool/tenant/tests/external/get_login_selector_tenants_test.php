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
 * File containing tests for tool_tenant\external\check_user_limit class.
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <hittesh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\external;

use advanced_testcase;
use external_api;
use tool_tenant_generator;

/**
 * Class external_get_login_selector_tenants_test
 *
 * @package     tool_tenant
 * @category    test
 * @covers      \tool_tenant\external\get_login_selector_tenants
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Mikel Martín <hittesh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_login_selector_tenants_test extends advanced_testcase {

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
     * Test method for test_get_login_selector_tenants() for enabled 'showtenantselector' global setting.
     *
     */
    public function test_get_login_selector_tenants() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Set showtenantselector to 1.
        set_config('showtenantselector', 1, 'tool_tenant');

        $t1 = $this->generator->create_tenant();
        $t2 = $this->generator->create_tenant(['showinloginselector' => 1, 'sitename' => 'Tenant 02']);
        $t3 = $this->generator->create_tenant(['showinloginselector' => 1, 'idnumber' => 'T03',
            'useloginurlid' => 0, 'useloginurlidnumber' => 1]);
        $t4 = $this->generator->create_tenant(['showinloginselector' => 0]);

        $return = external_api::clean_returnvalue(
            get_login_selector_tenants::execute_returns(),
            get_login_selector_tenants::execute()
        );

        $this->assertTrue($return['enabled']);
        // Check that WS returns 4 tenants (default tenants and three tenants created with showinloginselector enabled).
        $this->assertCount(4, $return['tenants']);
        // Check that default tenant data is correctly returned.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $defaulttenantdata = array_shift($return['tenants']);
        $this->assertEquals('PHPUnit test site', $defaulttenantdata['name']);
        $this->assertEquals($CFG->wwwroot.'/?tenantid='.$defaulttenantid, $defaulttenantdata['url']);
        $defaulttenantlogourl = $CFG->wwwroot.'/pluginfile.php/1/tool_tenant/tenantselectorlogo/'.$defaulttenantid.
            '/workplacelogo.png';
        $this->assertEquals($defaulttenantlogourl, $defaulttenantdata['logourl']);

        // Check that tentant1 data is correctly returned.
        $tenant1data = array_shift($return['tenants']);
        $this->assertEquals('PHPUnit test site', $tenant1data['name']);
        $this->assertEquals($CFG->wwwroot.'/?tenantid='.$t1->id, $tenant1data['url']);
        // Check that tenant2 use defined tenant sitename.
        $tenant2data = array_shift($return['tenants']);
        $this->assertEquals($t2->sitename, $tenant2data['name']);
        // Check that tenant3 use idnumber in the URL.
        $tenant3data = array_shift($return['tenants']);
        $this->assertEquals($CFG->wwwroot.'/?tenant='.$t3->idnumber, $tenant3data['url']);
    }

    /**
     * Test method for test_get_login_selector_tenants() for disabled 'showtenantselector' global setting.
     *
     */
    public function test_get_login_selector_tenants_disabled() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Set showtenantselector to 0.
        set_config('showtenantselector', 0, 'tool_tenant');

        $tenant1 = $this->generator->create_tenant();

        $return = external_api::clean_returnvalue(
            get_login_selector_tenants::execute_returns(),
            get_login_selector_tenants::execute()
        );

        $this->assertFalse($return['enabled']);
        $this->assertEmpty( $return['tenants']);

    }
}


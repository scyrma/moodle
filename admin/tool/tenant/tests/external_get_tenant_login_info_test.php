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
use external_api;
use invalid_parameter_exception;
use moodle_url;
use tool_tenant_generator;

/**
 * Class external_get_tenant_login_info_test
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external_get_tenant_login_info_test extends advanced_testcase {

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
     * Test method for get_tenant_login_info
     */
    public function test_get_tenant_login_info() {
        $t1 = $this->generator->create_tenant(['sitename' => 'Tenant 02', 'useloginurlid' => 1, 'useloginurlidnumber' => 0]);
        $t2 = $this->generator->create_tenant(['sitename' => 'Tenant 03', 'idnumber' => 'T03', 'useloginurlid' => 0,
            'useloginurlidnumber' => 1]);

        $manager = new \tool_tenant\manager();

        // Test with tenantid when tenantid is active in tenant URL configuration.
        $url = new moodle_url('/', ['tenantid' => $t1->id]);
        $result = get_tenant_login_info::execute($url);
        $return = external_api::clean_returnvalue(
            get_tenant_login_info::execute_returns(),
            $result
        );
        $logourl = $manager->get_tenant_file_url($t1->id, 'tenantselectorlogo');
        $this->assertEquals($t1->sitename, $return['name']);
        $this->assertEquals($logourl, $return['logourl']);

        // Test with tenant idnumber when idnumber is active in tenant URL configuration.
        $url = new moodle_url('/', ['tenant' => $t2->idnumber]);
        $result = get_tenant_login_info::execute($url);
        $return = external_api::clean_returnvalue(
            get_tenant_login_info::execute_returns(),
            $result
        );
        $logourl = $manager->get_tenant_file_url($t2->id, 'tenantselectorlogo');
        $this->assertEquals($t2->sitename, $return['name']);
        $this->assertEquals($logourl, $return['logourl']);
    }

    /**
     * Test when Login URL is disabled for tenantid
     */
    public function test_get_tenant_login_info_disabled_url_id() {
        $t1 = $this->generator->create_tenant(['sitename' => 'Tenant 04', 'idnumber' => 'T04', 'useloginurlid' => 0,
            'useloginurlidnumber' => 0]);

        // Test with tenant id when id is NOT active in tenant URL configuration.
        $url = new moodle_url('/', ['tenantid' => $t1->id]);

        $this->expectException(invalid_parameter_exception::class);
        $this->expectExceptionMessage('Invalid URL');
        get_tenant_login_info::execute($url);
    }

    /**
     * Test when Login URL is disabled for tenant idnumber
     */
    public function test_get_tenant_login_info_disabled_url_idnumber() {
        $t1 = $this->generator->create_tenant(['sitename' => 'Tenant 04', 'idnumber' => 'T04', 'useloginurlid' => 0,
            'useloginurlidnumber' => 0]);

        $url = new moodle_url('/', ['tenant' => $t1->idnumber]);

        $this->expectException(invalid_parameter_exception::class);
        $this->expectExceptionMessage('Invalid URL');
        get_tenant_login_info::execute($url);
    }

    /**
     * Test for non existing tenantid
     */
    public function test_get_tenant_login_info_invalid_tenantid() {
        $url = new moodle_url('/', ['tenantid' => 0]);

        $this->expectException(invalid_parameter_exception::class);
        $this->expectExceptionMessage('Invalid URL');
        get_tenant_login_info::execute($url);
    }

    /**
     * Test for non existing tenant idnumber
     */
    public function test_get_tenant_login_info_invalid_tenant_idnumber() {
        $url = new moodle_url('/', ['tenant' => 'nonexistingtenant']);

        $this->expectException(invalid_parameter_exception::class);
        $this->expectExceptionMessage('Invalid URL');
        get_tenant_login_info::execute($url);
    }

    /**
     * Test for invalid url param name (not tenantid or tenant)
     */
    public function test_get_tenant_login_info_invalid_url_param() {
        $t1 = $this->generator->create_tenant(['sitename' => 'Tenant 04', 'idnumber' => 'T04', 'useloginurlid' => 0,
            'useloginurlidnumber' => 0]);

        // Test with a param that is not tenantid or tenant.
        $url = new moodle_url('/', ['invalidparam' => $t1->id]);

        $this->expectException(invalid_parameter_exception::class);
        $this->expectExceptionMessage('Invalid URL');
        get_tenant_login_info::execute($url);
    }
}

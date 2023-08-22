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
use tool_tenant\manager;

/**
 * Tests for the create_tenant_test class.
 *
 * @package    tool_tenant
 * @covers     \tool_tenant\external\create_tenant
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class create_tenant_test extends advanced_testcase {

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test for create_tenant()
     */
    public function test_create_tenant() {
        $manager = new manager();

        // Create tenant just sending tenant name.
        $tenantname = ['name' => 'Tenant test1'];
        $result = create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantname)
        );
        $tenantcreated = $manager->get_tenant($result['tenantid']);
        $this->assertEquals($tenantcreated->get('name'), $tenantname['name']);

        $tenantparams = [
            'name' => 'Tenant test1',
            'sitename' => 'Tenant site1',
            'siteshortname' => 'T1',
            'idnumber' => 'T1',
            'useloginurlid' => true,
            'useloginurlidnumber' => false,
            'showinloginselector' => true
        ];

        // Create tenant sending all parameter.
        $resultall = create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantparams)
        );
        $tenantcreatedall = $manager->get_tenant($resultall['tenantid']);
        $this->assertEquals($tenantcreatedall->get('sitename'), $tenantparams['sitename']);
        $this->assertEquals($tenantcreatedall->get('siteshortname'), $tenantparams['siteshortname']);
        $this->assertEquals($tenantcreatedall->get('idnumber'), $tenantparams['idnumber']);
        $this->assertEquals($tenantcreatedall->get('useloginurlid'), $tenantparams['useloginurlid']);
        $this->assertEquals((bool)$tenantcreatedall->get('useloginurlidnumber'), $tenantparams['useloginurlidnumber']);

        // Create tenant with useloginurlidnumber true but without idnumber param.
        $tenantparams['idnumber'] = '';
        $resultidnumber = create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantparams)
        );
        $tenantidnumber = $manager->get_tenant($resultidnumber['tenantid']);

        // Because idnumber isn't provided the useloginurlidnumber is saved as false.
        $this->assertFalse((bool)$tenantidnumber->get('useloginurlidnumber'));

        // Enable tenant limit and set it in 3 max.
        set_config('tool_tenant_tenantlimitenabled', 1);
        set_config('tool_tenant_tenantlimit', 3);

        // As tenant limit was reached, sending a new creation of tenant should return exception.
        $this->expectException('required_capability_exception');
        create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantparams)
        );
    }

    /**
     * Test for create_tenant when autocreatecategory = true is provided
     */
    public function test_create_tenant_new_category() {
        $tenantparams = [
            'name' => 'Tenant new category',
            'sitename' => 'Tenant site1',
            'siteshortname' => 'T1',
            'idnumber' => 'T1',
            'useloginurlid' => true,
            'useloginurlidnumber' => false,
            'showinloginselector' => true
        ];

        // Create tenant sending autocreatecategory = true.
        $tenantparams['autocreatecategory'] = true;
        create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantparams)
        );

        // Create tenant sending same tenant params and autocreatecategory = true, should return exception.
        $this->expectException('moodle_exception');
        $this->expectExceptionMessage(get_string('categorynameexistws', 'tool_tenant', $tenantparams['name']));
        create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantparams)
        );
    }

    /**
     * Test for create_tenant when existing assigned categoryid is provided
     */
    public function test_create_tenant_assigned_category() {
        global $DB;
        $tenantparams = [
            'name' => 'Tenant assigned category',
            'sitename' => 'Tenant site1',
            'siteshortname' => 'T1',
            'idnumber' => 'T1',
            'useloginurlid' => true,
            'useloginurlidnumber' => false,
            'showinloginselector' => true
        ];

        // Create tenant sending autocreatecategory = true.
        $tenantparams['autocreatecategory'] = true;

        create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantparams)
        );
        $categorycreated = $DB->get_record('course_categories', ['name' => $tenantparams['name']]);

        // Create tenant sending autocreatecategory = false and categoryid assigned to another tenant,
        // should return exception.
        $tenantparams['autocreatecategory'] = false;
        $tenantparams['categoryid'] = $categorycreated->id;
        $this->expectException('moodle_exception');
        $this->expectExceptionMessage(get_string('categorytaken', 'tool_tenant'));
        create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantparams)
        );
    }

    /**
     * Test for create_tenant when existing categoryid is provided
     */
    public function test_create_tenant_existing_category() {
        global $DB;
        $manager = new manager();

        $tenantparams = [
            'name' => 'Tenant existing category',
            'sitename' => 'Tenant site1',
            'siteshortname' => 'T1',
            'idnumber' => 'T1',
            'useloginurlid' => true,
            'useloginurlidnumber' => false,
            'showinloginselector' => true
        ];

        // Create tenant sending categoryid.
        $newcategory = $this->getDataGenerator()->create_category(['name' => 'Tenant category']);
        $tenantparams['categoryid'] = $newcategory->id;
        $resultexisting = create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantparams)
        );

        $tenantcreated = $manager->get_tenant($resultexisting['tenantid']);
        $categoryexists = $DB->get_record('course_categories', ['id' => $newcategory->id]);
        $this->assertEquals($tenantcreated->get('categoryid'), $categoryexists->id);

        // Create tenant sending not valid categoryid, should return exception.
        $tenantparams['categoryid'] = -1;
        $this->expectException('moodle_exception');
        $this->expectExceptionMessage(get_string('categorynotfound', 'tool_tenant'));
        create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantparams)
        );
    }

    /**
     * Test for create_tenant when autocreatecategory = true and categoryid is provided
     */
    public function test_create_tenant_bad_category() {
        $tenantparams = [
            'name' => 'Tenant new category',
            'sitename' => 'Tenant site1',
            'siteshortname' => 'T1',
            'idnumber' => 'T1',
            'useloginurlid' => true,
            'useloginurlidnumber' => false,
            'showinloginselector' => true,
            'autocreatecategory' => true,
            'categoryid' => 1
        ];

        // Create tenant sending autocreatecategory = true and categoryid, should return exception.
        $this->expectException('moodle_exception');
        $this->expectExceptionMessage(get_string('errornewcategorytenant', 'tool_tenant'));
        create_tenant::clean_returnvalue(
            create_tenant::execute_returns(),
            create_tenant::execute($tenantparams)
        );
    }
}

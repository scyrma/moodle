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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_organisation\external;
use core_external\external_api;
use tool_organisation\department;
use tool_tenant\tenancy;

/**
 * Tests for update_departments WS
 *
 * @covers     \tool_organisation\external\update_departments
 *
 * @package    tool_organisation
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class update_departments_test extends \advanced_testcase {

    /**
     * Tenant generator
     *
     * @return \tool_tenant_generator
     */
    protected function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Organisation generator
     *
     * @return \tool_organisation_generator
     */
    protected function get_organisation_generator(): \tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Test for \tool_organisation\external\update_departments::execute()
     */
    public function test_update_departments(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->get_organisation_generator();
        $tenantgenerator = $this->get_tenant_generator();

        $tenant = $tenantgenerator->create_tenant();
        $dep1 = $generator->create_department(['idnumber' => 'dep1', 'tenantid' => $tenant->id]);
        $dep11 = $generator->create_department(['idnumber' => 'dep11', 'parentid' => $dep1->id, 'tenantid' => $tenant->id]);
        $dep111 = $generator->create_department(['idnumber' => 'dep111', 'parentid' => $dep11->id, 'tenantid' => $tenant->id]);
        $dep12 = $generator->create_department(['idnumber' => 'dep12', 'name' => 'Dep 12', 'parentid' => $dep1->id,
            'tenantid' => $tenant->id]);
        // Dep13 has same idnumber as Dep12.
        $dep13 = $generator->create_department(['idnumber' => 'dep12', 'name' => 'Dep 13', 'parentid' => $dep1->id,
            'tenantid' => $tenant->id]);
        $dep2 = $generator->create_department(['idnumber' => 'dep2', 'tenantid' => $tenant->id]);
        tenancy::set_switched_tenant_id($tenant->id);

        // Check updating a non existing idnumber returns a warning.
        $departments = \tool_organisation\external\update_departments::execute([['idnumber' => 'wrongidnumber']]);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_departments::execute_returns(),
            $departments);
        $this->assertEquals('departmentnotfound', $departments['warnings'][0]['warningcode']);

        // Check moving a department to a non existing parent.
        $departments = \tool_organisation\external\update_departments::execute([['idnumber' => 'dep11',
            'parent' => 'wrongidnumber']]);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_departments::execute_returns(),
            $departments);
        $this->assertEquals('errorparentnotfounddepartment', $departments['warnings'][0]['warningcode']);

        // Check moving department to its child returns a warning.
        $departments = \tool_organisation\external\update_departments::execute([['idnumber' => 'dep11', 'parent' => 'dep111']]);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_departments::execute_returns(),
            $departments);
        $this->assertEquals('dep11', $departments['warnings'][0]['item']);
        $this->assertEquals('errormovehierarchy', $departments['warnings'][0]['warningcode']);

        // Check updating a department with repeated idnumber.
        $departments = \tool_organisation\external\update_departments::execute([['idnumber' => 'dep12',
            'name' => 'Department 13']]);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_departments::execute_returns(),
            $departments);
        $this->assertDebuggingCalled('Error: mdb->get_record() found more than one record!');

        // Check user cannot update department without permissions.
        [$tenant2, [$user1]] = $this->get_tenant_generator()->create_tenant_and_users(1);
        $this->setUser($user1);
        $departments = \tool_organisation\external\update_departments::execute([['idnumber' => 'dep11',
            'name' => 'Department 11']]);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_departments::execute_returns(),
            $departments);
        $this->assertEquals('dep11', $departments['warnings'][0]['item']);
        $this->assertEquals('departmentnotfound', $departments['warnings'][0]['warningcode']);

        // Check user can not update department in archived tenant.
        (new \tool_tenant\manager())->update_tenant($tenant->id, (object)['archived' => 0]);
        $departments = \tool_organisation\external\update_departments::execute([['idnumber' => 'dep11']]);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_departments::execute_returns(),
            $departments);
        $this->assertEquals('dep11', $departments['warnings'][0]['item']);
        $this->assertEquals('departmentnotfound', $departments['warnings'][0]['warningcode']);
    }

    /**
     * Test for \tool_organisation\external\update_departments::execute()
     */
    public function test_update_departments_without_warnings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->get_organisation_generator();

        // Create 3 departments.
        $dep1 = $generator->create_department(['idnumber' => 'dep1', 'name' => 'Dep1']);
        $dep11 = $generator->create_department(['idnumber' => 'dep11', 'name' => 'Dep11', 'parentid' => $dep1->id]);
        $dep12 = $generator->create_department(['idnumber' => 'dep12', 'name' => 'Dep12', 'parentid' => $dep1->id]);

        // Update dep11 and dep12.
        $records = [
            ['idnumber' => 'dep11', 'name' => 'Department 11', 'description' => 'Department 11 description'],
            ['idnumber' => 'dep12', 'name' => 'Department 12', 'parent' => 'dep11'],
        ];
        $departments = \tool_organisation\external\update_departments::execute($records);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_departments::execute_returns(),
            $departments);

        // Check the WS return values.
        $this->assertEmpty($departments['warnings']);
        $this->assertCount(2, $departments['result']);
        $this->assertEquals(['id' => $dep11->id, 'idnumber' => $dep11->idnumber], $departments['result'][0]);

        // Check that departments were updated.
        $dep11 = department::get_record(['id' => $dep11->id]);
        $dep12 = department::get_record(['id' => $dep12->id]);
        $this->assertEquals('Department 11', $dep11->get('name'));
        $this->assertEquals('Department 11 description', $dep11->get('description'));
        $this->assertEquals('Department 12', $dep12->get('name'));
        $this->assertEquals($dep11->get('id'), $dep12->get('parentid'));
    }

    /**
     * Test for \tool_organisation\external\update_departments::execute()
     */
    public function test_update_departments_as_tenant_admin(): void {
        $this->resetAfterTest();

        $tenantgenerator = $this->get_tenant_generator();
        $tenant1 = $tenantgenerator->create_tenant();
        $tenant2 = $tenantgenerator->create_tenant();
        $tenantadmin = $this->getDataGenerator()->create_user();
        $tenantgenerator->allocate_user($tenantadmin->id, $tenant1->id);
        $manager = new \tool_tenant\manager();
        $manager->assign_tenant_admin_role($tenant1->id, [$tenantadmin->id]);

        $this->setUser($tenantadmin);

        // Create a department in each tenant.
        $data = (object)['idnumber' => 'dep1', 'name' => 'Dep1', 'tenantid' => $tenant1->id];
        $dep1 = (new \tool_organisation\department_manager())->create_department($data, false);
        $data = (object)['idnumber' => 'dep2', 'name' => 'Dep2', 'tenantid' => $tenant2->id];
        $dep2 = (new \tool_organisation\department_manager())->create_department($data, false);

        // Update dep1.
        $records = [
            ['idnumber' => 'dep1', 'name' => 'Department 1', 'description' => 'Department 1 description'],
        ];
        $departments = \tool_organisation\external\update_departments::execute($records);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_departments::execute_returns(),
            $departments);

        // Check the WS return values.
        $this->assertEmpty($departments['warnings']);
        $this->assertCount(1, $departments['result']);
        $this->assertEquals(['id' => $dep1->get('id'), 'idnumber' => $dep1->get('idnumber')], $departments['result'][0]);

        // Check that departments were updated.
        $dep1 = department::get_record(['id' => $dep1->get('id')]);
        $this->assertEquals('Department 1', $dep1->get('name'));
        $this->assertEquals('Department 1 description', $dep1->get('description'));

        // Check that tenant1 admin can not update dep2.
        $records = [
            ['idnumber' => 'dep2', 'name' => 'Department 2', 'description' => 'Department 2 description'],
        ];
        $departments = \tool_organisation\external\update_departments::execute($records);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_departments::execute_returns(),
            $departments);
        $this->assertEquals('dep2', $departments['warnings'][0]['item']);
        $this->assertEquals('departmentnotfound', $departments['warnings'][0]['warningcode']);
    }

    /**
     * Global admin can update departments in other tenants
     */
    public function test_update_departments_as_global_admin(): void {
        $this->resetAfterTest();

        $tenantgenerator = $this->get_tenant_generator();
        $tenant1 = $tenantgenerator->create_tenant(['idnumber' => 't1']);
        $tenant2 = $tenantgenerator->create_tenant(['idnumber' => 't2']);

        $this->setAdminUser();

        // Create a department in each tenant.
        $data = (object)['idnumber' => 'dep1', 'name' => 'Dep11', 'tenantid' => $tenant1->id];
        $dep1 = (new \tool_organisation\department_manager())->create_department($data, false);
        $data = (object)['idnumber' => 'dep1', 'name' => 'Dep21', 'tenantid' => $tenant2->id];
        $dep2 = (new \tool_organisation\department_manager())->create_department($data, false);

        // Update dep1 in both tenants.
        $records = [
            ['idnumber' => 'dep1', 'description' => 'Updated 1', 'tenant' => 't1'],
            ['idnumber' => 'dep1', 'description' => 'Updated 2', 'tenant' => 't2'],
        ];
        $departments = \tool_organisation\external\update_departments::execute($records);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_departments::execute_returns(),
            $departments);

        // Check the WS return values.
        $this->assertEmpty($departments['warnings']);
        $this->assertCount(2, $departments['result']);
        $this->assertEquals(['id' => $dep1->get('id'), 'idnumber' => $dep1->get('idnumber')], $departments['result'][0]);
        $this->assertEquals(['id' => $dep2->get('id'), 'idnumber' => $dep2->get('idnumber')], $departments['result'][1]);
    }
}

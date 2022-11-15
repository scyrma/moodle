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

namespace tool_organisation;

use advanced_testcase;
use context_system;
use external_api;
use tool_organisation_external;

/**
 * Tests for the tool_organisation external class.
 *
 * @package    tool_organisation
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external_test extends advanced_testcase {
    /**
     * Test for funciton create_departments()
     */
    public function test_create_departments_with_warnings() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $records = [
            ['name' => 'Department 01', 'idnumber' => 'dep1'],
            ['name' => 'Department 02', 'idnumber' => 'dep2'],
            ['name' => 'Department 12', 'parent' => 'dep1'],
            ['name' => 'Department 22', 'parent' => 'dep2'],
            ['name' => 'Department 31', 'parent' => 'dep3'],
        ];
        $departments = tool_organisation_external::create_departments($records);
        $departments = external_api::clean_returnvalue(tool_organisation_external::create_departments_returns(), $departments);
        $this->assertEquals('Department 01', $departments['result'][0]['name']);
        $this->assertEquals('Department 02', $departments['result'][1]['name']);
        $this->assertEquals('Department 12', $departments['result'][2]['name']);
        $this->assertEquals('Department 22', $departments['result'][3]['name']);
        $this->assertEquals('Department 31', $departments['warnings'][0]['item']);
    }
    /**
     * Test for funciton create_departments()
     */
    public function test_create_departments_without_warnings() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $records = [
            ['name' => 'Department 01', 'idnumber' => 'dep1'],
            ['name' => 'Department 02', 'idnumber' => 'dep2'],
            ['name' => 'Department 12', 'parent' => 'dep1'],
            ['name' => 'Department 22', 'parent' => 'dep2'],
        ];
        $departments = tool_organisation_external::create_departments($records);
        $departments = external_api::clean_returnvalue(tool_organisation_external::create_departments_returns(), $departments);
        $this->assertEquals('Department 01', $departments['result'][0]['name']);
        $this->assertEquals('Department 02', $departments['result'][1]['name']);
        $this->assertEquals('Department 12', $departments['result'][2]['name']);
        $this->assertEquals('Department 22', $departments['result'][3]['name']);
        $this->assertEquals([], $departments['warnings']);
    }

    /**
     * Test for funciton create_positions()
     */
    public function test_create_positions() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $records = [
            ['name' => 'Position 00', 'idnumber' => 'pos0', 'globalmanager' => true],

            ['name' => 'Position 01', 'idnumber' => 'pos1',
             'globalpermissions' => ['allocateprograms' => true, 'viewreports' => true, 'receivenotifications' => true]],

            ['name' => 'Position 02', 'idnumber' => 'pos2', 'departmentmanager' => true],

            ['name' => 'Position 12', 'parent' => 'pos1', 'idnumber' => 'pos12',
             'departmentpermissions' => ['allocateprograms' => true, 'viewreports' => false, 'receivenotifications' => true]],

            ['name' => 'Position 22', 'parent' => 'pos2', 'idnumber' => 'pos22',
             'departmentpermissions' => ['allocateprograms' => true, 'viewreports' => true, 'receivenotifications' => false]],

            ['name' => 'Position 31', 'parent' => 'pos3'],
        ];

        $positions = tool_organisation_external::create_positions($records);
        $positions = external_api::clean_returnvalue(tool_organisation_external::create_positions_returns(), $positions);

        $this->assertEquals('Position 00', $positions['result'][0]['name']);
        $this->assertEquals('Position 01', $positions['result'][1]['name']);
        $this->assertEquals('Position 02', $positions['result'][2]['name']);
        $this->assertEquals('Position 12', $positions['result'][3]['name']);
        $this->assertEquals('Position 22', $positions['result'][4]['name']);
        $this->assertEquals('Position 31', $positions['warnings'][0]['item']);

        $pos0 = position::get_record(['idnumber' => 'pos0']);
        $this->assertEquals(1, $pos0->get('globalmanager'));
        $this->assertEquals(0, $pos0->get('departmentmanager'));

        $pos2 = position::get_record(['idnumber' => 'pos2']);
        $this->assertEquals(0, $pos2->get('globalmanager'));
        $this->assertEquals(1, $pos2->get('departmentmanager'));

        $this->assertEquals(7, position::get_record(['idnumber' => 'pos1'])->get('globalpermissions'));

        $this->assertEquals(5, position::get_record(['idnumber' => 'pos12'])->get('departmentpermissions'));

        $this->assertEquals(3, position::get_record(['idnumber' => 'pos22'])->get('departmentpermissions'));
    }

    /**
     * Test for funciton create_positions()
     */
    public function test_create_positions_without_warnings() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $records = [
            ['name' => 'Position 00', 'idnumber' => 'pos0', 'globalmanager' => true],
        ];

        $positions = tool_organisation_external::create_positions($records);
        $positions = external_api::clean_returnvalue(tool_organisation_external::create_positions_returns(), $positions);

        $this->assertEquals('Position 00', $positions['result'][0]['name']);
        $this->assertEquals([], $positions['warnings']);
    }

    /**
     * Test for \tool_organisation\external\update_departmetns::execute()
     */
    public function test_update_departments() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $tenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();
        $dep1 = $generator->create_department(['idnumber' => 'dep1', 'tenantid' => $tenant->id]);
        $dep11 = $generator->create_department(['idnumber' => 'dep11', 'parentid' => $dep1->id, 'tenantid' => $tenant->id]);
        $dep111 = $generator->create_department(['idnumber' => 'dep111', 'parentid' => $dep11->id, 'tenantid' => $tenant->id]);
        $dep12 = $generator->create_department(['idnumber' => 'dep12', 'name' => 'Dep 12', 'parentid' => $dep1->id,
            'tenantid' => $tenant->id]);
        // Dep13 has same idnumber as Dep12.
        $dep13 = $generator->create_department(['idnumber' => 'dep12', 'name' => 'Dep 13', 'parentid' => $dep1->id,
            'tenantid' => $tenant->id]);
        $dep2 = $generator->create_department(['idnumber' => 'dep2', 'tenantid' => $tenant->id]);

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
        $this->assertEquals('parentdepartmentnotfound', $departments['warnings'][0]['warningcode']);

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
        $this->assertEquals('dep12', $departments['warnings'][0]['item']);
        $this->assertEquals('multipledepartmentsfound', $departments['warnings'][0]['warningcode']);

        // Check usar cannot update department without permissions.
        [$tenant2, [$user1]] = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant_and_users(1);
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
     * Test for \tool_organisation\external\update_departmetns::execute()
     */
    public function test_update_departments_without_warnings() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

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
    public function test_update_departments_as_tenant_admin() {
        $this->resetAfterTest();

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
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
     * Test for \tool_organisation\external\update_positions::execute()
     */
    public function test_update_positions() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $tenant = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant();
        $pos1 = $generator->create_position(['idnumber' => 'pos1', 'tenantid' => $tenant->id]);
        $pos11 = $generator->create_position(['idnumber' => 'pos11', 'parentid' => $pos1->id, 'tenantid' => $tenant->id]);
        $pos111 = $generator->create_position(['idnumber' => 'pos111', 'parentid' => $pos11->id, 'tenantid' => $tenant->id]);
        $pos12 = $generator->create_position(['idnumber' => 'pos12', 'name' => 'Pos 12', 'parentid' => $pos1->id,
            'tenantid' => $tenant->id]);
        // Pos13 has same idnumber as Pos12.
        $pos13 = $generator->create_position(['idnumber' => 'pos12', 'name' => 'Pos 13', 'parentid' => $pos1->id,
            'tenantid' => $tenant->id]);
        $pos2 = $generator->create_position(['idnumber' => 'pos2', 'tenantid' => $tenant->id]);

        // Check updating a non existing idnumber returns a warning.
        $positions = \tool_organisation\external\update_positions::execute([['idnumber' => 'wrongidnumber']]);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(), $positions);
        $this->assertEquals('positionnotfound', $positions['warnings'][0]['warningcode']);

        // Check moving a position to a non existing parent.
        $positions = \tool_organisation\external\update_positions::execute([['idnumber' => 'pos11', 'parent' => 'wrongidnumber']]);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(), $positions);
        $this->assertEquals('parentpositionnotfound', $positions['warnings'][0]['warningcode']);

        // Check moving position to its child returns a warning.
        $positions = \tool_organisation\external\update_positions::execute([['idnumber' => 'pos11', 'parent' => 'pos111']]);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(), $positions);
        $this->assertEquals('pos11', $positions['warnings'][0]['item']);
        $this->assertEquals('errormovehierarchy', $positions['warnings'][0]['warningcode']);

        // Check updating a position with repeated idnumber.
        $positions = \tool_organisation\external\update_positions::execute([['idnumber' => 'pos12', 'name' => 'Position 13']]);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(), $positions);
        $this->assertEquals('pos12', $positions['warnings'][0]['item']);
        $this->assertEquals('multiplepositionsfound', $positions['warnings'][0]['warningcode']);

        // Check usar cannot update position without permissions.
        [$tenant2, [$user1]] = $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant_and_users(1);
        $this->setUser($user1);
        $positions = \tool_organisation\external\update_positions::execute([['idnumber' => 'pos11', 'name' => 'Position 11']]);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(), $positions);
        $this->assertEquals('pos11', $positions['warnings'][0]['item']);
        $this->assertEquals('positionnotfound', $positions['warnings'][0]['warningcode']);

        // Check user can not update department in archived tenant.
        (new \tool_tenant\manager())->update_tenant($tenant->id, (object)['archived' => 0]);
        $departments = \tool_organisation\external\update_positions::execute([['idnumber' => 'pos11']]);
        $departments = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(),
            $departments);
        $this->assertEquals('pos11', $departments['warnings'][0]['item']);
        $this->assertEquals('positionnotfound', $departments['warnings'][0]['warningcode']);
    }

    /**
     * Test for \tool_organisation\external\update_positions::execute()
     */
    public function test_update_positions_without_warnings() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        // Create 3 positions.
        $pos1 = $generator->create_position(['idnumber' => 'pos1', 'name' => 'Pos1']);
        $pos11 = $generator->create_position([
            'idnumber' => 'pos11', 'name' => 'Pos11', 'globalmanager' => 0, 'parentid' => $pos1->id,
            'globalpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS |
                \tool_organisation\organisation::PERM_VIEW_REPORTS,
        ]);
        $pos12 = $generator->create_position([
            'idnumber' => 'pos12', 'name' => 'Pos12', 'departmentmanager' => 0, 'parentid' => $pos1->id,
            'departmentpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS |
                \tool_organisation\organisation::PERM_VIEW_REPORTS,
        ]);

        // Update pos11 and pos12.
        $records = [
            [
                'idnumber' => 'pos11',
                'name' => 'Position 11',
                'description' => 'Position 11 description',
                'globalmanager' => 1,
                'globalpermissions' => ['allocateprograms' => true, 'viewreports' => true, 'receivenotifications' => true]
            ],
            [
                'idnumber' => 'pos12',
                'name' => 'Position 12',
                'departmentmanager' => 1,
                'parent' => 'pos11'
            ],
        ];
        $positions = \tool_organisation\external\update_positions::execute($records);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(), $positions);

        // Check the WS return values.
        $this->assertEmpty($positions['warnings']);
        $this->assertCount(2, $positions['result']);
        $this->assertEquals(['id' => $pos11->id, 'idnumber' => $pos11->idnumber], $positions['result'][0]);

        // Check that positions were updated.
        $pos11 = position::get_record(['id' => $pos11->id]);
        $pos12 = position::get_record(['id' => $pos12->id]);
        $this->assertEquals('Position 11', $pos11->get('name'));
        $this->assertEquals('Position 11 description', $pos11->get('description'));
        $this->assertEquals(1, $pos11->get('globalmanager'));
        $this->assertEquals(7, $pos11->get('globalpermissions'));
        $this->assertEquals('Position 12', $pos12->get('name'));
        $this->assertEquals(1, $pos12->get('departmentmanager'));
        $this->assertEquals($pos11->get('id'), $pos12->get('parentid'));
    }

    /**
     * Test for \tool_organisation\external\update_positions::execute()
     */
    public function test_update_positions_as_tenant_admin() {
        $this->resetAfterTest();

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant1 = $tenantgenerator->create_tenant();
        $tenant2 = $tenantgenerator->create_tenant();
        $tenantadmin = $this->getDataGenerator()->create_user();
        $tenantgenerator->allocate_user($tenantadmin->id, $tenant1->id);
        $manager = new \tool_tenant\manager();
        $manager->assign_tenant_admin_role($tenant1->id, [$tenantadmin->id]);

        $this->setUser($tenantadmin);

        // Create a position in each tenant.
        $data = (object)['idnumber' => 'pos1', 'name' => 'Pos1', 'tenantid' => $tenant1->id];
        $pos1 = (new \tool_organisation\position_manager())->create_position($data, false);
        $data = (object)['idnumber' => 'pos2', 'name' => 'Pos2', 'tenantid' => $tenant2->id];
        $pos2 = (new \tool_organisation\position_manager())->create_position($data, false);

        // Update position.
        $records = [
            ['idnumber' => 'pos1', 'name' => 'Position 1', 'description' => 'Position 1 description'],
        ];
        $positions = \tool_organisation\external\update_positions::execute($records);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(),
            $positions);

        // Check the WS return values.
        $this->assertEmpty($positions['warnings']);
        $this->assertCount(1, $positions['result']);
        $this->assertEquals(['id' => $pos1->get('id'), 'idnumber' => $pos1->get('idnumber')], $positions['result'][0]);

        // Check that departments were updated.
        $pos1 = position::get_record(['id' => $pos1->get('id')]);
        $this->assertEquals('Position 1', $pos1->get('name'));
        $this->assertEquals('Position 1 description', $pos1->get('description'));

        // Check that tenant1 admin can not update pos2.
        $records = [
            ['idnumber' => 'pos2', 'name' => 'Position 2', 'description' => 'Position 2 description'],
        ];
        $positions = \tool_organisation\external\update_positions::execute($records);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(),
            $positions);
        $this->assertEquals('pos2', $positions['warnings'][0]['item']);
        $this->assertEquals('positionnotfound', $positions['warnings'][0]['warningcode']);
    }

    /**
     * Test for function get_teams_tab_filters()
     */
    public function test_get_teams_tab_filters() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $pos1 = $generator->create_position(['name' => 'Pos1']);
        $pos2 = $generator->create_position(['name' => 'Pos2']);
        $dep1 = $generator->create_department(['name' => 'Dep1']);
        $dep2 = $generator->create_department(['name' => 'Dep2']);

        $result = tool_organisation_external::get_teams_tab_filters();
        $cleanresult = external_api::clean_returnvalue(tool_organisation_external::get_teams_tab_filters_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertNotEmpty($cleanresult);
        $this->assertArrayHasKey('departments', $cleanresult);
        $this->assertArrayHasKey('positions', $cleanresult);
        $this->assertArrayHasKey('fullname_filters', $cleanresult);

        $this->assertEqualsCanonicalizing([$pos1->id, $pos2->id], array_column($cleanresult['positions'], 'id'));
        $this->assertEqualsCanonicalizing([$pos1->name, $pos2->name], array_column($cleanresult['positions'], 'name'));
        $this->assertEqualsCanonicalizing([$pos1->parentid, $pos2->parentid],
            array_column($cleanresult['positions'], 'parentid'));

        $this->assertEqualsCanonicalizing([$dep1->id, $dep2->id], array_column($cleanresult['departments'], 'id'));
        $this->assertEqualsCanonicalizing([$dep1->name, $dep2->name], array_column($cleanresult['departments'], 'name'));
        $this->assertEqualsCanonicalizing([$dep1->parentid, $dep2->parentid],
            array_column($cleanresult['departments'], 'parentid'));

        $this->assertCount(7, $cleanresult['fullname_filters']);
        $this->assertEquals(1, $cleanresult['fullname_filters'][0]['id']);
        $this->assertEquals('contains', $cleanresult['fullname_filters'][0]['identifier']);
    }

    /**
     * Test for function get_managed_users()
     */
    public function test_get_managed_users() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $tenantgenerator->create_tenant();

        $pos1 = $generator->create_position([
            'name' => 'Pos1',
            'tenantid' => $tenant->id,
            'globalmanager' => 1,
            'globalpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS |
                \tool_organisation\organisation::PERM_VIEW_REPORTS,
        ]);
        $pos2 = $generator->create_position(['name' => 'Pos2', 'tenantid' => $tenant->id, 'parentid' => $pos1->id]);
        $pos3 = $generator->create_position(['name' => 'Pos3', 'tenantid' => $tenant->id, 'parentid' => $pos2->id]);
        $dep1 = $generator->create_department(['name' => 'Dep1', 'tenantid' => $tenant->id]);
        $dep2 = $generator->create_department(['name' => 'Dep2', 'tenantid' => $tenant->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $tenantgenerator->allocate_user($user1->id, $tenant->id);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Lionel', 'lastname' => 'Richie']);
        $tenantgenerator->allocate_user($user2->id, $tenant->id);
        $user3 = $this->getDataGenerator()->create_user();
        $tenantgenerator->allocate_user($user3->id, $tenant->id);
        $user4 = $this->getDataGenerator()->create_user();
        $tenantgenerator->allocate_user($user4->id, $tenant->id);

        $data = [
            'tenantid' => $tenant->id,
            'positionid' => $pos1->id,
            'departmentid' => $dep1->id,
            'userid' => $user1->id,
        ];
        $job1 = $generator->assign_job((object) $data);

        $data = [
            'tenantid' => $tenant->id,
            'positionid' => $pos2->id,
            'departmentid' => $dep1->id,
            'userid' => $user2->id,
        ];
        $job2 = $generator->assign_job((object) $data);

        $data = [
            'tenantid' => $tenant->id,
            'positionid' => $pos2->id,
            'departmentid' => $dep1->id,
            'userid' => $user3->id,
        ];
        $job3 = $generator->assign_job((object) $data);

        $data = [
            'tenantid' => $tenant->id,
            'positionid' => $pos3->id,
            'departmentid' => $dep1->id,
            'userid' => $user4->id,
        ];
        $job4 = $generator->assign_job((object) $data);

        $generator->assign_capability('tool/organisation:assignjobs', $user1->id, context_system::instance());
        self::setUser($user1->id);

        $result = tool_organisation_external::get_managed_users();
        $cleanresult = external_api::clean_returnvalue(tool_organisation_external::get_managed_users_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertNotEmpty($cleanresult);
        $this->assertArrayHasKey('user', $cleanresult);
        $this->assertArrayHasKey('managedusers', $cleanresult);
        $this->assertArrayHasKey('totalcount', $cleanresult);

        $this->assertEquals(fullname($user1), $cleanresult['user']['fullname']);
        $this->assertEquals(1, $cleanresult['user']['ismanager']);
        $this->assertEquals($job1->id, $cleanresult['user']['jobs'][0]['jobid']);
        $this->assertEquals($pos1->name, $cleanresult['user']['jobs'][0]['position_name']);
        $this->assertEquals($dep1->name, $cleanresult['user']['jobs'][0]['department_name']);

        $this->assertEqualsCanonicalizing([fullname($user2), fullname($user3), fullname($user4)],
            array_column($cleanresult['managedusers'], 'fullname'));

        $jobidsfromws = [
            $cleanresult['managedusers'][0]['jobs'][0]['jobid'],
            $cleanresult['managedusers'][1]['jobs'][0]['jobid'],
            $cleanresult['managedusers'][2]['jobs'][0]['jobid'],
        ];
        $this->assertEqualsCanonicalizing([$job2->id, $job3->id, $job4->id], $jobidsfromws);

        $this->assertEquals(0, $cleanresult['managedusers'][0]['ismanager']);
        $this->assertEquals(0, $cleanresult['managedusers'][1]['ismanager']);
        $this->assertEquals(0, $cleanresult['managedusers'][2]['ismanager']);

        $this->assertEquals(3, $cleanresult['totalcount']);

        $result = tool_organisation_external::get_managed_users($dep2->id);
        $cleanresult = external_api::clean_returnvalue(tool_organisation_external::get_managed_users_returns(), $result);
        $this->assertEmpty($cleanresult['managedusers']);

        $result = tool_organisation_external::get_managed_users($dep1->id, 0, $pos2->id, 0);
        $cleanresult = external_api::clean_returnvalue(tool_organisation_external::get_managed_users_returns(), $result);
        $this->assertEquals(2, $cleanresult['totalcount']);

        $result = tool_organisation_external::get_managed_users($dep1->id, 0, $pos2->id, 1);
        $cleanresult = external_api::clean_returnvalue(tool_organisation_external::get_managed_users_returns(), $result);
        $this->assertEquals(3, $cleanresult['totalcount']);

        $result = tool_organisation_external::get_managed_users($dep1->id, 0, $pos2->id, 1,
            \tool_organisation_external::FULLNAME_EQUAL, fullname($user2));
        $cleanresult = external_api::clean_returnvalue(tool_organisation_external::get_managed_users_returns(), $result);
        $this->assertEquals(1, $cleanresult['totalcount']);

        $result = tool_organisation_external::get_managed_users(0, 0, 0, 0, 0, '', 1, 0);
        $cleanresult = external_api::clean_returnvalue(tool_organisation_external::get_managed_users_returns(), $result);
        $this->assertCount(2, $cleanresult['managedusers']);
        $this->assertEquals(3, $cleanresult['totalcount']);

        $result = tool_organisation_external::get_managed_users(0, 0, 0, 0, 0, '', 1, 1);
        $cleanresult = external_api::clean_returnvalue(tool_organisation_external::get_managed_users_returns(), $result);
        $this->assertCount(1, $cleanresult['managedusers']);
        $this->assertEquals(3, $cleanresult['totalcount']);
    }
}

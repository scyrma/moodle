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
use tool_organisation\position;
use tool_tenant\tenancy;

/**
 * Tests for update_positions WS
 *
 * @covers     \tool_organisation\external\update_positions
 *
 * @package    tool_organisation
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class update_positions_test extends \advanced_testcase {

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
     * Test for \tool_organisation\external\update_positions::execute()
     */
    public function test_update_positions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->get_organisation_generator();

        $tenant = $this->get_tenant_generator()->create_tenant();
        $pos1 = $generator->create_position(['idnumber' => 'pos1', 'tenantid' => $tenant->id]);
        $pos11 = $generator->create_position(['idnumber' => 'pos11', 'parentid' => $pos1->id, 'tenantid' => $tenant->id]);
        $pos111 = $generator->create_position(['idnumber' => 'pos111', 'parentid' => $pos11->id, 'tenantid' => $tenant->id]);
        $pos12 = $generator->create_position(['idnumber' => 'pos12', 'name' => 'Pos 12', 'parentid' => $pos1->id,
            'tenantid' => $tenant->id]);
        // Pos13 has same idnumber as Pos12.
        $pos13 = $generator->create_position(['idnumber' => 'pos12', 'name' => 'Pos 13', 'parentid' => $pos1->id,
            'tenantid' => $tenant->id]);
        $pos2 = $generator->create_position(['idnumber' => 'pos2', 'tenantid' => $tenant->id]);
        tenancy::set_switched_tenant_id($tenant->id);

        // Check updating a non existing idnumber returns a warning.
        $positions = \tool_organisation\external\update_positions::execute([['idnumber' => 'wrongidnumber']]);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(), $positions);
        $this->assertEquals('positionnotfound', $positions['warnings'][0]['warningcode']);

        // Check moving a position to a non existing parent.
        $positions = \tool_organisation\external\update_positions::execute([['idnumber' => 'pos11', 'parent' => 'wrongidnumber']]);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(), $positions);
        $this->assertEquals('errorparentnotfoundposition', $positions['warnings'][0]['warningcode']);

        // Check moving position to its child returns a warning.
        $positions = update_positions::execute([['idnumber' => 'pos11', 'parent' => 'pos111']]);
        $positions = external_api::clean_returnvalue(update_positions::execute_returns(), $positions);
        $this->assertEquals('pos11', $positions['warnings'][0]['item']);
        $this->assertEquals('errormovehierarchy', $positions['warnings'][0]['warningcode']);

        // Check updating a position with repeated idnumber.
        $positions = update_positions::execute([['idnumber' => 'pos12', 'name' => 'Position 13']]);
        $positions = external_api::clean_returnvalue(update_positions::execute_returns(), $positions);
        $this->assertDebuggingCalled('Error: mdb->get_record() found more than one record!');

        // Check usar cannot update position without permissions.
        [$tenant2, [$user1]] = $this->get_tenant_generator()->create_tenant_and_users(1);
        $this->setUser($user1);
        $positions = update_positions::execute([['idnumber' => 'pos11', 'name' => 'Position 11']]);
        $positions = external_api::clean_returnvalue(update_positions::execute_returns(), $positions);
        $this->assertEquals('pos11', $positions['warnings'][0]['item']);
        $this->assertEquals('positionnotfound', $positions['warnings'][0]['warningcode']);

        // Check user can not update department in archived tenant.
        (new \tool_tenant\manager())->update_tenant($tenant->id, (object)['archived' => 0]);
        $departments = update_positions::execute([['idnumber' => 'pos11']]);
        $departments = external_api::clean_returnvalue(update_positions::execute_returns(),
            $departments);
        $this->assertEquals('pos11', $departments['warnings'][0]['item']);
        $this->assertEquals('positionnotfound', $departments['warnings'][0]['warningcode']);
    }

    /**
     * Test for update_positions::execute()
     */
    public function test_update_positions_without_warnings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->get_organisation_generator();

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
        $positions = update_positions::execute($records);
        $positions = external_api::clean_returnvalue(update_positions::execute_returns(), $positions);

        // Check the WS return values.
        $this->assertEmpty($positions['warnings']);
        $this->assertCount(2, $positions['result']);
        $this->assertEquals(['id' => $pos11->id, 'idnumber' => $pos11->idnumber], $positions['result'][0]);

        // Check that positions were updated.
        $pos11 = position::get_record(['id' => $pos11->id]);
        $pos12 = position::get_record(['id' => $pos12->id]);
        $this->assertEquals('Position 11', $pos11->get('name'));
        $this->assertEquals('Position 11 description', $pos11->get('description'));
        $this->assertTrue($pos11->get('globalmanager'));
        $this->assertEquals(7, $pos11->get('globalpermissions'));
        $this->assertEquals('Position 12', $pos12->get('name'));
        $this->assertTrue($pos12->get('departmentmanager'));
        $this->assertEquals($pos11->get('id'), $pos12->get('parentid'));
    }

    /**
     * Test for update_positions::execute()
     */
    public function test_update_positions_as_tenant_admin(): void {
        $this->resetAfterTest();

        $tenantgenerator = $this->get_tenant_generator();
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
        $positions = update_positions::execute($records);
        $positions = external_api::clean_returnvalue(update_positions::execute_returns(),
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
        $positions = update_positions::execute($records);
        $positions = external_api::clean_returnvalue(update_positions::execute_returns(),
            $positions);
        $this->assertEquals('pos2', $positions['warnings'][0]['item']);
        $this->assertEquals('positionnotfound', $positions['warnings'][0]['warningcode']);
    }

    /**
     * Global admin can update positions in other tenants
     */
    public function test_update_positions_as_global_admin(): void {
        $this->resetAfterTest();

        $tenantgenerator = $this->get_tenant_generator();
        $tenant1 = $tenantgenerator->create_tenant(['idnumber' => 't1']);
        $tenant2 = $tenantgenerator->create_tenant(['idnumber' => 't2']);

        $this->setAdminUser();

        // Create a position in each tenant.
        $data = (object)['idnumber' => 'pos1', 'name' => 'Pos11', 'tenantid' => $tenant1->id];
        $pos1 = (new \tool_organisation\position_manager())->create_position($data, false);
        $data = (object)['idnumber' => 'pos1', 'name' => 'Pos21', 'tenantid' => $tenant2->id];
        $pos2 = (new \tool_organisation\position_manager())->create_position($data, false);

        // Update pos1 in both tenants.
        $records = [
            ['idnumber' => 'pos1', 'description' => 'Updated 1', 'tenant' => 't1'],
            ['idnumber' => 'pos1', 'description' => 'Updated 2', 'tenant' => 't2'],
        ];
        $positions = \tool_organisation\external\update_positions::execute($records);
        $positions = external_api::clean_returnvalue(\tool_organisation\external\update_positions::execute_returns(),
            $positions);

        // Check the WS return values.
        $this->assertEmpty($positions['warnings']);
        $this->assertCount(2, $positions['result']);
        $this->assertEquals(['id' => $pos1->get('id'), 'idnumber' => $pos1->get('idnumber')], $positions['result'][0]);
        $this->assertEquals(['id' => $pos2->get('id'), 'idnumber' => $pos2->get('idnumber')], $positions['result'][1]);
    }
}
